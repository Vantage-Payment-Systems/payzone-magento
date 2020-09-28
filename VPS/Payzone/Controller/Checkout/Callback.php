<?php
/**
 * Attribution Notice: Based on the Paypal payment module included with Magento 2.
 *
 * @copyright  Copyright (c) 2015 Magento
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VPS\Payzone\Controller\Checkout;

use Magento\Framework\App\Action\Action as AppAction;
use Magento\Sales\Model\Order;

class Callback extends AppAction
{
	

	/**
	* @var \VPS\Payzone\Model\Payzone
	*/
	protected $_paymentMethod;

	/**
	* @var \Magento\Sales\Model\Order
	*/
	protected $_order;

	/**
	* @var \Magento\Sales\Model\OrderFactory
	*/
	protected $_orderFactory;

	/**
	* @var Magento\Sales\Model\Order\Email\Sender\OrderSender
	*/
	protected $_orderSender;

	/**
	* @var \Psr\Log\LoggerInterface
	*/
	protected $_logger;


	/**
	* @param \Magento\Framework\App\Action\Context $context
	* @param \Magento\Sales\Model\OrderFactory $orderFactory
	* @param \VPS\Payzone\Model\Payzone $paymentMethod
	* @param Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
	* @param  \Psr\Log\LoggerInterface $logger
	*/
	public function __construct(
	\Magento\Framework\App\Action\Context $context,
	\Magento\Sales\Model\OrderFactory $orderFactory,
	\VPS\Payzone\Model\Payzone $paymentMethod,
	\Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
	\Psr\Log\LoggerInterface $logger
	) {
		$this->_paymentMethod = $paymentMethod;
		$this->_orderFactory = $orderFactory;
		$this->_orderSender = $orderSender;
		$this->_logger = $logger;
		parent::__construct($context);
	}

	/**
	* Handle callback request
	*/
	public function execute()
	{
	
		$api = $this->_paymentMethod->_initApi();
        if ($api->handleCallbackStatus()) {
			$status = $api->getStatus();
			$order_id = $status->getOrderID();
            $this->_order = $this->_loadOrder($order_id);
			if($this->_paymentMethod->generateHashKey($order_id) == $status->getCtrlCustomData()){
				if ($status->getErrorCode() == 0) {
					$orderState = Order::STATE_COMPLETE ;
					$msg = 'Transaction succeffuly completed. Payzone Transaction ID :'.$status->getTransactionId();
					$this->_changeOrderState($orderState, $msg);
				} else {
					$orderState = Order::STATE_CANCELED  ;
					$msg = 'Transaction aborted !. Payzone Transaction ID :'.$status->getTransactionId();
					$this->_changeOrderState($orderState, $msg);
				}
			}
		}
	
	}

	protected function _changeOrderState($state, $message) {
		$this->_order->setState($state, true, $message, 1)->save();
		$this->_order->setStatus($state);
		$hist = $this->_order->addStatusHistoryComment($message);
		$hist->setIsCustomerNotified(true);
		$this->_order->save();

		$this->_orderSender->send($this->_order);
	}

	protected function _loadOrder($ref)
	{
		$order = $this->_orderFactory->create()->loadByIncrementId($ref);

		if (!($order && $order->getId())) {
			throw new \Exception('Could not find Magento order with id $order_id');
		}

		return $order;

	}
}

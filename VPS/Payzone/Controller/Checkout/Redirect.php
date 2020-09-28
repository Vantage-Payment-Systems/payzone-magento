<?php
/**
 * Attribution Notice: Based on the Paypal payment module included with Magento 2.
 *
 * @copyright  Copyright (c) 2015 Magento
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VPS\Payzone\Controller\Checkout;

class Redirect extends \Magento\Framework\App\Action\Action
{
	/**
	* @var \Magento\Checkout\Model\Session
	*/
	protected $_checkoutSession;

	/**
	* @var \VPS\Payzone\Model\Payzone
	*/
	protected $_paymentMethod;

	/**
	* @param \Magento\Framework\App\Action\Context $context
	* @param \Magento\Checkout\Model\Session $checkoutSession
	* @param \VPS\Payzone\Model\Payzone $paymentMethod
	*/
	public function __construct(
	\Magento\Framework\App\Action\Context $context,
	\Magento\Checkout\Model\Session $checkoutSession,
	\VPS\Payzone\Model\Payzone $paymentMethod
	) {
		$this->_paymentMethod = $paymentMethod;
		$this->_checkoutSession = $checkoutSession;
		$this->_resultRedirectFactory = $context->getResultRedirectFactory();
		parent::__construct($context);
	}

	/**
	* Start checkout by requesting checkout code and dispatching customer to PAYSBUY.
	*/
	public function execute()
	{
		$order = $this->_getOrder();
		$payURL = $this->_paymentMethod->getPaymentUrl($order);
		return $this->_resultRedirectFactory->create()->setUrl($payURL);
	}

	/**
	* Get order object.
	*
	* @return \Magento\Sales\Model\Order
	*/
	protected function _getOrder()
	{
		return $this->_checkoutSession->getLastRealOrder();
	}
}

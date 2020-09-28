<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace VPS\Payzone\Model;

use VPS\Payzone\Api\Connect2PayClient;

/**
 * Pay In Store payment method model
 */
class Payzone extends \Magento\Payment\Model\Method\AbstractMethod
{
	/**
	* Default Currency Is Morrocan Dirham
	*/
	const DEFAULT_CURRENCY_TYPE = 'MAD';
	
	/**
	* Return URL after payment is made
	*/
	const URL_RETURN = 'payzone/checkout/success';
	
	/**
	* Callback URL (IPN)
	*/
	const URL_CALLBACK = 'payzone/checkout/callback';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'payzone';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

	/**
	* @var \Magento\Framework\UrlInterface
	*/
	protected $_urlBuilder;

	/**
	* @var \Magento\Store\Model\StoreManagerInterface
	*/
	protected $_storeManager;
	
	/**
     * API ID
     *
     * @var string
     */
    protected $_api_id = ''; 
	
	protected $_currency_used = '';
	
	/**
     * API KEY
     *
     * @var string
     */
    protected $_api_key = '';
	
	/**
     * API URL
     *
     * @var string
     */
    protected $_api_url = '';

	public function __construct(
		\Magento\Framework\UrlInterface $urlBuilder,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct( $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data );
		
		$this->_urlBuilder = $urlBuilder;
		$this->_storeManager = $storeManager;
		$this->_api_id = $this->getConfigData('api_id');
		$this->_api_key = $this->getConfigData('api_key');
		$this->_api_url = $this->getConfigData('api_url');
		$this->_currency_used = $this->getConfigData('currency_used');
		
		
    }

	/**
	 * Instantiate state and set it to state object.
	 *
	 * @param string                        $paymentAction
	 * @param \Magento\Framework\DataObject $stateObject
	 */
	public function initialize($paymentAction, $stateObject)
	{
		$payment = $this->getInfoInstance();
		$order = $payment->getOrder();
		$order->setCanSendNewEmailFlag(false);

		$stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
		$stateObject->setStatus('pending_payment');
		$stateObject->setIsNotified(false);
	}

	/**
	 * Get full URL for payment
	 *
	 * @param order		$order
	 *
	 * @return string
	 */
	public function getPaymentUrl($order){
		$shopper = $order->getBillingAddress();
        $shipper = $order->getShippingAddress();
        $api = $this->_initApi();

        ///// shopper informations
		if($shopper){
			$api->setShopperID($shopper->getId());
			$api->setShopperFirstName($shopper->getFirstname());
			$api->setShopperLastName($shopper->getLastname());
			$api->setShopperAddress($shopper->getStreetFull());
			$api->setShopperCity($shopper->getCity());
			$api->setShopperState($shopper->getRegionCode());
			$api->setShopperZipcode($shopper->getPostcode());
			$api->setShopperCountryCode($shopper->getCountry());
			$api->setShopperPhone($shopper->getTelephone());
			$api->setShopperEmail($shopper->getEmail());
		}else{
			$api->setShopperFirstName($order->getCustomerFirstname());
			$api->setShopperLastName($order->getCustomerLastname());
		}
        
		
        ///// shipper informations
		if($shipper){
			$api->setShipToFirstName($shipper->getFirstname());
			$api->setShipToLastName($shipper->getLastname());
			$api->setShipToAddress($shipper->getStreetFull());
			$api->setShipToCity($shipper->getCity());
			$api->setShipToState($shipper->getRegionCode());
			$api->setShipToZipcode($shipper->getPostcode());
			$api->setShipToCountryCode($shipper->getCountry());
			$api->setShipToPhone($shipper->getTelephone());
		}
        $order_id = $order->getIncrementId();
        $api->setOrderID($order_id);
		
		$currency_code = $order->getOrderCurrency()->getCurrencyCode();
		$total =$order->getGrandTotal();
		
		$description = '';
		
		
		if ($currency_code !== 'MAD') {
		
			$currencyHelper = $api->getCurrencyHelper();
			  
			// $taux = $currencyHelper::getRate($currency_code, self::DEFAULT_CURRENCY_TYPE);
			   $taux = $currencyHelper::getRate($currency_code, 'MAD', $api->getMerchant(), $api->getPassword());
			
			if(empty($taux) OR is_null($taux)){
				$message = "Payzone : can't change the amount of this order";
				echo $message;exit;
			}
			
			 // print_r($this->_currency_used);exit;
		
		
			if($this->_currency_used == 0 ){
				
				$description = 'le montant de '. number_format($total, 2, ',', ' ') . ' '. $currency_code.'	a ete converti en Dirham marocain avec un taux de change de '.$taux;
				$total = $total * $taux;
							 
			}
			if($this->_currency_used == 1){
				
			 $description = number_format($total, 2, ',', ' ') . ' '. $currency_code;   
				$total = $total * $taux;
			    
			}
			
			
		
		}
		
        $api->setAmount($total * 100);
		$api->setOrderDescription( $description );
		$api->setCtrlCustomData($this->generateHashKey($order_id));
        if ($api->prepareTransaction()) {
            
            return $api->getCustomerRedirectURL();
        } else {
            echo $api->getClientErrorMessage();
			exit;
        }
	}
	
	/**
	 * Get return URL.
	 *
	 * @param int|null $storeId
	 *
	 * @return string
	 */
	public function getReturnUrl($storeId = null)
	{
		return $this->_getUrl(self::URL_RETURN, $storeId);
	}

	/**
	 * Get notify (IPN) URL.
	 *
	 * @param int|null $storeId
	 *
	 * @return string
	 */
	public function getCallbackUrl($storeId = null)
	{
		return $this->_getUrl(self::URL_CALLBACK, $storeId, false);
	}
	
	/**
	 * Build URL for store.
	 *
	 * @param string    $path
	 * @param int       $storeId
	 * @param bool|null $secure
	 *
	 * @return string
	 */
	protected function _getUrl($path, $storeId, $secure = null)
	{
		$store = $this->_storeManager->getStore($storeId);

		return $this->_urlBuilder->getUrl(
			$path,
			['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
		);
	}
	
	/**
	 * Init Connect2PayClient Object
	 *
	 * @return VPS\Payzone\Api\Connect2PayClient
	 */
	public function _initApi()
	{
		$c2pClient = new Connect2PayClient( $this->_api_url, $this->_api_id, $this->_api_key);
		
		$c2pClient->setPaymentType(Connect2PayClient::_PAYMENT_TYPE_CREDITCARD);
		$c2pClient->setPaymentMode(Connect2PayClient::_PAYMENT_MODE_SINGLE);
		$c2pClient->setShippingType(Connect2PayClient::_SHIPPING_TYPE_VIRTUAL);

		$c2pClient->setCurrency(self::DEFAULT_CURRENCY_TYPE);
		$c2pClient->setCtrlRedirectURL($this->getReturnUrl());
		$c2pClient->setCtrlCallbackURL($this->getCallbackUrl());
		
		return $c2pClient;
	}
	
	/**
	*
	* Generate hash to be virefied
	* @return string
	*/
	public function generateHashKey($order_id){
		return sha1($this->_api_key.$this->_api_id.$order_id);
	}
  

}

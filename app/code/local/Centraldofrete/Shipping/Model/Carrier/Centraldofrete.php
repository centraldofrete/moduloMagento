<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Carrier Model
 *
 * Model containing main methods to integrate to Central do Frete's API.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Carrier_Centraldofrete extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * Method unique code
     * @access protected
     */
    protected $_code = 'Centraldofrete_Shipping';

    /**
     * Quote result
     * @access protected
     */
    protected $_result = null;

    /**
     * From Zip
     * @access protected
     */
    protected $fromZip;

    /**
     * To Zip
     * @access protected
     */
    protected $toZip;

    /**
     * Package Weight
     * @access protected
     */
    protected $packageWeight;

    /**
     * Package Value
     * @access protected
     */
    protected $packageValue;

    /**
     * Get Allowed Methods
     */
    public function getAllowedMethods()
    {
        return array($this->_code => Mage::helper('Centraldofrete_Shipping')->getConfigValue('title'));
    }

    /**
     * Collect Rates
     *
     * Receives shipping request and process it. If there are quotes, return them. Else, return false.
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return array|bool
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // checking again if this method is active
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        // prepare items object according to environment
        if (!Mage::app()->getStore()->isAdmin()) {
            // quote on frontend
            if (Mage::helper('Centraldofrete_Shipping')->getConfigValue('active_frontend') == 1) {
                $items = Mage::getModel('checkout/cart')->getQuote()->getAllVisibleItems();
            } else {
                return false;
            }
        } else {
            // quote on backend
            $items = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getAllVisibleItems();
        }

        // perform initial validation
        $initialValidation = $this->performInitialValidation($request);
        if (!$initialValidation) {
            return false;
        }

        // perform validate dimensions
        $packages = $this->validateDimensions($items, Mage::helper('Centraldofrete_Shipping')->getConfigValue('validate_dimensions'));

        // initialize quote result object
        $this->_result = Mage::getModel('shipping/rate_result');

        // get allowed methods, passing free shipping if allowed
        $this->getQuotes($packages, $request->getFreeShipping());

        return $this->_result;
    }

    /**
     * Initial validation
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return bool
     */
    protected function performInitialValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        // verify sender and receiver countries as BR
        $senderCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
        if (!Mage::app()->getStore()->isAdmin()) {
            // quote on frontend
            $receiverCountry = Mage::getSingleton('checkout/cart')->getQuote()->getShippingAddress()->getCountryId();
        } else {
            // quote on backend
            $receiverCountry = $request->getCountryId();
        }

        if ($senderCountry != 'BR') {
            Mage::log('Centraldofrete_Shipping: This method is active but default store country is not set to Brazil');
        }
        if ($receiverCountry != 'BR') {
            return false;
        }

        // prepare postcodes and verify them
        $this->fromZip= Mage::helper('Centraldofrete_Shipping')->sanitizePostcode(Mage::getStoreConfig('shipping/origin/postcode', $this->getStore()));
        $this->toZip = Mage::helper('Centraldofrete_Shipping')->sanitizePostcode($request->getDestPostcode());
        if (!preg_match("/^([0-9]{8})$/", $this->fromZip) || !preg_match("/^([0-9]{8})$/", $this->toZip)) {
            return false;
        }

        // prepare package weight and verify it; this module works with kilos as standard weight unit
        $this->packageWeight = $request->getPackageWeight();
        if (Mage::helper('Centraldofrete_Shipping')->getConfigValue('weight_unit') != 'kg') {
            $this->packageWeight = Mage::helper('Centraldofrete_Shipping')->changeWeightToKilos($this->packageWeight);
        }
        $minWeight = Mage::helper('Centraldofrete_Shipping')->getConfigValue('min_order_weight') ? Mage::helper('Centraldofrete_Shipping')->getConfigValue('min_order_weight') : Mage::helper('Centraldofrete_Shipping')->getConfigValue('standard_min_order_weight');
        $maxWeight = Mage::helper('Centraldofrete_Shipping')->getConfigValue('max_order_weight') ? Mage::helper('Centraldofrete_Shipping')->getConfigValue('max_order_weight') : Mage::helper('Centraldofrete_Shipping')->getConfigValue('standard_max_order_weight');

        if ($this->packageWeight < $minWeight || $this->packageWeight > $maxWeight) {
            return false;
        }

        // prepare package value and verify it
        $this->packageValue = $request->getBaseCurrency()->convert($request->getPackageValue(),$request->getPackageCurrency());
        $minValue = Mage::helper('Centraldofrete_Shipping')->getConfigValue('min_order_value') ? Mage::helper('Centraldofrete_Shipping')->getConfigValue('min_order_value') : Mage::helper('Centraldofrete_Shipping')->getConfigValue('standard_min_order_value');
        $maxValue = Mage::helper('Centraldofrete_Shipping')->getConfigValue('max_order_value') ? Mage::helper('Centraldofrete_Shipping')->getConfigValue('max_order_value') : Mage::helper('Centraldofrete_Shipping')->getConfigValue('standard_max_order_value');

        if ($this->packageValue < $minValue || $this->packageValue > $maxValue) {
            return false;
        }
        return true;
    }

    /**
     * Validate Dimensions
     *
     * Used to create packages, according to dimensions rules or not.
     * @param Mage_Checkout_Model_Cart $items
     * @param bool $validate Validate Dimensions
     * @return bool
     */
    protected function validateDimensions($items, $validate = 0)
    {
        $helper = Mage::helper('Centraldofrete_Shipping');
        // get attribute codes
        $lengthCode = $helper->getConfigValue('attribute_length') != 'none' ? $helper->getConfigValue('attribute_length') : null;
        $widthCode = $helper->getConfigValue('attribute_width') != 'none' ? $helper->getConfigValue('attribute_width') : null;
        $heightCode = $helper->getConfigValue('attribute_height') != 'none' ? $helper->getConfigValue('attribute_height') : null;

        // if validate dimensions, use params; else set fictional params
        if ($validate == 1) {
            // define package min and max dimensions
            $minLength = $helper->getConfigValue('standard_min_length');
            $minWidth = $helper->getConfigValue('standard_min_width');
            $minHeight = $helper->getConfigValue('standard_min_height');
            $maxLength = $helper->getConfigValue('standard_max_length');
            $maxWidth = $helper->getConfigValue('standard_max_width');
            $maxHeight = $helper->getConfigValue('standard_max_height');

            // define package min and max weight and value, comparing custom and standard values, always in centimeters
            $minWeight = ($helper->getConfigValue('min_order_weight') >= $helper->getConfigValue('standard_min_order_weight')) ? $helper->getConfigValue('min_order_weight') : $helper->getConfigValue('standard_min_order_weight');
            $maxWeight = ($helper->getConfigValue('max_order_weight') <= $helper->getConfigValue('standard_max_order_weight')) ? $helper->getConfigValue('max_order_weight') : $helper->getConfigValue('standard_max_order_weight');
            $minValue = ($helper->getConfigValue('min_order_value') >= $helper->getConfigValue('standard_min_order_value')) ? $helper->getConfigValue('min_order_value') : $helper->getConfigValue('standard_min_order_value');
            $maxValue = ($helper->getConfigValue('max_order_value') <= $helper->getConfigValue('standard_max_order_value')) ? $helper->getConfigValue('max_order_value') : $helper->getConfigValue('standard_max_order_value');
        } else {
            // hardcoded values to avoid undefined variable errors
            $minLenght = 0;
            $minWidth = 0;
            $minHeight = 0;
            $maxLength = 10000; // 10.000 cm
            $maxWidth = 10000;
            $maxHeight = 10000;
            $minWeight = 0;
            $maxWeight = 1000; // 1000 kg
            $minValue = 0;
            $maxValue = 100000; // $ 100.000
        }

        // define packages array and first package
        $packages = array();
        $firstPackage = new Centraldofrete_Shipping_Model_Package();
        array_push($packages, $firstPackage);

        // loop through items to validate dimensions and define packages
        foreach ($items as $item) {
            // get product data
            $_product = $item->getProduct();
            $qty = $item->getQty();
            if ($qty == 0) {
                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                // set item dimensions; if the custom dimension is less than minimum, use standard; if greater than maximum, log and return false for the whole quote
                // set item length
                if ($lengthCode) {
                    $itemLength = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $lengthCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $lengthCode, $this->getStore()) : $helper->getConfigValue('standard_length');

                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemLength = $helper->changeDimensionToCentimeter($itemLength);
                    }

                    if ($itemLength < $minLength) {
                        $itemLength = $minLength;
                    }
                    if ($itemLength > $maxLength) {
                        Mage::log('Centraldofrete_Shipping: The product with SKU ' . $_product->getSku() . ' has as incorrect length set: ' . $itemLength);
                        return false;
                    }
                } else {
                    $itemLength = $helper->getConfigValue('standard_length');
                }

                // set item width
                if ($widthCode) {
                    $itemWidth = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $widthCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $widthCode, $this->getStore()) : $helper->getConfigValue('standard_width');

                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemWidth = $helper->changeDimensionToCentimeter($itemWidth);
                    }

                    if ($itemWidth < $minWidth) {
                        $itemWidth = $minWidth;
                    }
                    if ($itemWidth > $maxWidth) {
                        Mage::log('Centraldofrete_Shipping: The product with SKU ' . $_product->getSku() . ' has as incorrect width set: ' . $itemWidth);
                        return false;
                    }
                } else {
                    $itemWidth = $helper->getConfigValue('standard_width');
                }

                // set item height
                if ($heightCode) {
                    $itemHeight = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $heightCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $heightCode, $this->getStore()) : $helper->getConfigValue('standard_height');

                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemHeight = $helper->changeDimensionToCentimeter($itemHeight);
                    }

                    if ($itemHeight < $minHeight) {
                        $itemHeight = $minHeight;
                    }
                    if ($itemHeight > $maxHeight) {
                        Mage::log('Centraldofrete_Shipping: The product with SKU ' . $_product->getSku() . ' has as incorrect height set: ' . $itemHeight);
                        return false;
                    }
                } else {
                    $itemHeight = $helper->getConfigValue('standard_height');
                }

                // get product weight
                $itemWeight = $_product->getWeight();
                if ($itemWeight < $minWeight) {
                    $itemWeight = $minWeight;
                }
                if ($itemWeight > $maxWeight) {
                    Mage::log('Centraldofrete_Shipping: The product with SKU ' . $_product->getSku() . ' has as incorrect weight set: ' . $itemWeight);
                    return false;
                }

                // get product value
                $itemValue = $_product->getFinalPrice();
                if ($itemValue < $minValue || $itemValue > $maxValue) {
                    Mage::log('Centraldofrete_Shipping: The product with SKU ' . $_product->getSku() . ' has as incorrect value set: ' . $itemValue);
                    return false;
                }

                // loop through created packages
                $packagesCount = count($packages);
                $loop = 1;

                foreach ($packages as $pa) {
                    // verify if there is enough space to this item within a given package
                    if (($pa->getLength() + $itemLength) <= $maxLength && ($pa->getWidth() + $itemWidth) <= $maxWidth && ($pa->getHeight() + $itemHeight) <= $maxHeight && (($pa->getValue() + $itemValue) <= $maxValue) && ($pa->getWeight() + $itemWeight) <= $maxWeight) {
                        $pa->addLength($itemLength);
                        $pa->addWidth($itemWidth);
                        $pa->addHeight($itemHeight);
                        $pa->addWeight($itemWeight);
                        $pa->addValue($itemValue);
                        $pa->addItem($_product);
                        break;
                    } else {
                        // verify if there are more packages to test before creating a new one
                        if ($loop < $packagesCount) {
                            // if there are more packages, continue to loop
                            $loop++;
                            continue;
                        } else {
                            // create a new package and insert item
                            $pb = new Centraldofrete_Shipping_Model_Package();
                            $pb->addLength($itemLength);
                            $pb->addWidth($itemWidth);
                            $pb->addHeight($itemHeight);
                            $pb->addWeight($itemWeight);
                            $pb->addValue($itemValue);
                            $pb->addItem($_product);

                            array_push($packages, $pb);
                            $packagesCount++;
                            break;
                        }
                    }
                }
            }
        }
        return $packages;
    }

    /**
     * Get Quotes
     *
     * @param array $packages Packages list
     * @param bool $freeShipping Determines if free shipping is available
     * @return array
     */
    protected function getQuotes($packages, $freeShipping = false)
    {
        // prepare data
        $data = array(
            'origem_cep' => $this->fromZip,
            'destino_cep' => $this->toZip,
            'valor_carga' => $this->packageValue,
            'volumes' => array(),
        );

        // prepare packages
        foreach ($packages as $package) {
            $quoteData = array(
                'quantidade' => $package->getItemsQty(),
                'comprimento' => $package->getLength(),
                'largura' => $package->getWidth(),
                'altura' => $package->getHeight(),
                'peso' => $package->getWeight(),
                //'value' => $package->getValue(),
            );
            array_push($data['packages'], $quoteData);
        }

        // send request to API
        $quoteRequest = $this->processServerRequest($data, 'cotacao/calcular-frete');

        // process results
        if (!$quoteRequest || !array_key_exists(['cotacoes'])) {
            return false;
        }

        // sort by value, from cheapest to more expensive
        usort($quoteRequest, function($a, $b) {
            return $a->value - $b->value;
        });

        // apply free shipping only for the cheapest method
        $i = 0;
        foreach ($quoteRequest['cotacoes'] as $quoteResult) {
            if ($freeShipping == 1 && $i == 0) {
                $quoteCost = 0;
            } else {
                $quoteCost = $quoteResult['preco'];
            }
            $shippingMethod = $quoteResult['cotacao_codigo'];
            $shippingTitle = $quoteResult['transportadora']['nome_fantasia'];
            $shippingCost = $quoteCost;
            $shippingDelivery = $quoteResult['prazo_maximo'];
            $this->appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost, $shippingDelivery, $freeShipping);
            $i++;
        }
        return $this->_result;
    }

    /**
     * Append shipping return
     *
     * Used to process shipping return and append it to main object
     * @param string $shippingMethod Shipping method code
     * @param string $shippingTitle Shipping method title
     * @param float $shippingCost Cost of this method
     * @param int $shippingDelivery Estimate time to delivery
     * @param boolean $freeShipping Is there free shipping?
     * @return bool
     */
    protected function appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost = 0, $shippingDelivery = 0, $freeShipping = false)
    {
        $helper = Mage::helper('Centraldofrete_Shipping');

        // preparing and populating the shipping method
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($helper->getConfigValue('title'));
        $method->setMethod($shippingMethod);
        $method->setCost($shippingCost);

        // including estimate time of delivery
        if ($helper->getConfigValue('show_delivery_days')) {
            $shippingDelivery += $helper->getConfigValue('add_delivery_days');
            if ($shippingDelivery > 0) {
                $deliveryText = sprintf($helper->getConfigValue('delivery_message'), $shippingDelivery);
                $shippingTitle .= ' - ' . $deliveryText;
            }
        }
        $method->setMethodTitle($shippingTitle);

        // applying extra fee if required
        if ($freeShipping) {
            $shippingPrice = $shippingCost;
        } else {
            $shippingPrice = $shippingCost + $helper->getConfigValue('add_extra_fee');
        }

        $method->setPrice($shippingPrice);
        $this->_result->append($method);
    }

    /**
     * Is Tracking Available
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get Tracking Info
     *
     * Method to be triggered when a tracking info is requested.
     * @param array $trackings Trackings
     * @return Mage_Shipping_Model_Tracking_Result
     */
    public function getTrackingInfo($trackings)
    {
        // instatiate the object and get tracking results
        $this->_result = Mage::getModel('shipping/tracking_result');
        foreach ((array) $trackings as $trackingCode) {
            $this->requestTrackingInfo($trackingCode);
        }

        // check results
        if ($this->_result instanceof Mage_Shipping_Model_Tracking_Result){
            if ($trackings = $this->_result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($this->_result) && !empty($this->_result)) {
            return $this->_result;
        } else {
            return false;
        }
    }

    /**
     * Request Tracking Info
     *
     * Get data from the API regarding tracking code
     * @param string $trackingCode Tracking code
     * @return bool
     *
     * @todo this method must be adapted to the final API version
     */
    protected function requestTrackingInfo($trackingCode)
    {
        // prepare data to connect to API
        $data = array(
            'trackingCode' => $trackingCode,
        );
        $trackingRequest = $this->processServerRequest($data, 'rastreamento/consultar-status/' . $trackingCode);

        if (!$trackingRequest) {
            return false;
        }

        $progress = array();
        foreach ($trackingRequest as $code) {
            foreach($code->steps as $step) {
                $description = '';
                $datetime = explode(' ', $step->date);
                $locale = new Zend_Locale('pt_BR');
                $date = '';
                $date = new Zend_Date($datetime[0], 'dd/MM/YYYY', $locale);

                $track = array(
                    'deliverydate' => $date->toString('YYYY-MM-dd'),
                    'deliverytime' => $datetime[1],
                    'deliverylocation' => htmlentities($step->location),
                    'status' => htmlentities($step->status),
                    'activity' => htmlentities($step->activity),
                );
                $progress[] = $track;
            }
        }

        if (!empty($progress)) {
            $track = $progress[0];
            $track['progressdetail'] = $progress;

            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setTracking($trackingCode);
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->addData($track);

            $this->_result->append($tracking);
            return true;
        }
        return false;
    }

    /**
     * Process server request
     *
     * @param array $data Data to be sent to the server
     * @param string $action Action to perform
     * @return array
     */
    public function processServerRequest($data, $action)
    {
        $helper = Mage::helper('Centraldofrete_Shipping');
        $url = $helper->getAccessUrl() . $action;

        $data['access_token'] = $helper->getAccessToken();
        $data = json_encode($data);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data)) );
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $returnString = curl_exec($ch);
        curl_close($ch);

        return json_decode($returnString);
    }
}

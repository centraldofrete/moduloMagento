<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Estimate Model
 * 
 * Estimate method for Product Page Shipping Quotes.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Estimate extends Mage_Core_Model_Abstract
{
    /**
     * Estimate Quote
     * 
     * @param int $postcode Receiver postcode
     * @param int $productId The product ID
     * @param int $productQty The product quantity
     * @param boolean $params The way to create a Varien_Object
     * @return array
     */
    public function getEstimate($postcode, $productId, $productQty, $params = false)
    {
        // set country code to Brazil, as this method will work only there
        $countryCode = 'BR';
        
        // get product object
        $_product = Mage::getModel('catalog/product')->load($productId);
        $_product->getStockItem()->setUseConfigManageStock(false);
        $_product->getStockItem()->setManageStock(false);
        
        $requestObj = new Varien_Object($params);
        
        // prepare quote
        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore('default')->getId());
        $quote->addProduct($_product, $requestObj);
        $quote->getShippingAddress()->setCountryId($countryCode)->setPostcode($postcode);
        $quote->getShippingAddress()->collectTotals();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates(true);
        
        $groups = $quote->getShippingAddress()->getGroupedAllShippingRates();
        
        // handle result and return as array
        $shippingBlock = new Mage_Checkout_Block_Cart_Shipping();
        foreach($groups as $code => $_rates){
            $shippingEstimate[$code] = array(
                'name' => $shippingBlock->getCarrierName($code),
            );
            $shippingEstimate[$code]['methods'] = array();
            $i = 1;
            foreach ($_rates as $_rate) {
                $array = array(
                    'id' => $code . '-' . $i,
                    'title' => $_rate->getMethodTitle(),
                    'price' => Mage::helper('core')->currency($_rate->getPrice(), true, false),
                );
                array_push($shippingEstimate[$code]['methods'], $array);
                $i++;
            }
        }
        
        if ($shippingEstimate) {
            return $shippingEstimate;
        } else {
            return false;
        }
    }
}

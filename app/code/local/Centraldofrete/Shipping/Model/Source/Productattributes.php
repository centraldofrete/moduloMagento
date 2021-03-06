<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Product Attributes List
 * 
 * Get products attributes and return them as list, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Source_Productattributes
{
    /**
     * toOptionArray
     * 
     * Returns a list with user defined attributes, which means, custom attributes.
     * @return Array
     */
    public function toOptionArray()
    {
        $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection');
        $array = array(
            array(
                'value' => 'none',
                'label' => Mage::helper('Centraldofrete_Shipping')->__('No attribute selected'),
            )
        );
        foreach ($productAttributes as $pa) {
            if ($pa->getIsUserDefined() == 1) {
                $option = array(
                    'value' => $pa->getAttributeCode(),
                    'label' => $pa->getFrontendLabel(),
                );
                array_push($array, $option);
                unset($option);
            }
        }
        return $array;
    }
}

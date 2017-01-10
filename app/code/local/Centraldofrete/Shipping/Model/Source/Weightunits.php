<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Weight Units List
 * 
 * Lists weight units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Source_Weightunits
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'kg', 'label' => Mage::helper('adminhtml')->__('Kilos')),
            array('value' => 'g', 'label' => Mage::helper('adminhtml')->__('Grams')),
		);
    }
}

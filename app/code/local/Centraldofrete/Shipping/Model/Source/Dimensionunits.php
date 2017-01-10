<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Dimension Units List
 * 
 * Lists dimension units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Source_Dimensionunits
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'cm', 'label' => Mage::helper('adminhtml')->__('centimeters')),
            array('value' => 'mm', 'label' => Mage::helper('adminhtml')->__('milimeters')),
            array('value' => 'm', 'label' => Mage::helper('adminhtml')->__('meters')),
		);
    }
}

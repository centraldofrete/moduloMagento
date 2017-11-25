<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Freight types List
 *
 * Lists freight types, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.1
 * @package Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Source_Freighttype
{
    /**
     * toOptionArray
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => '1', 'label' => Mage::helper('Centraldofrete_Shipping')->__('Type 1')),
            array('value' => '2', 'label' => Mage::helper('Centraldofrete_Shipping')->__('Type 2')),
        );
    }
}

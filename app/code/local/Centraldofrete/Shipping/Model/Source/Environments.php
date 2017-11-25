<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Enviroments List
 *
 * Lists environments, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Model_Source_Environments
{
    /**
     * toOptionArray
     *
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'test', 'label' => Mage::helper('adminhtml')->__('Test')),
            array('value' => 'production', 'label' => Mage::helper('adminhtml')->__('Production')),
        );
    }
}

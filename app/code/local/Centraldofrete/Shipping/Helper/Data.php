<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Data Helper
 * 
 * Default module helper.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get Config Value
     * 
     * @param string $config Config key
     * @return string
     */
    public function getConfigValue($config)
    {
        return Mage::getStoreConfig('carriers/Centraldofrete_Shipping/' . $config);
    }
    
    /**
     * Sanitize postcodes
     * 
     * @param string $postcode Postcode
     * @return int
     */
    public function sanitizePostcode($postcode)
    {
        return str_replace(' ', '', str_replace(',', '', str_replace('/', '', str_replace('.', '', str_replace('-', '', $postcode)))));
    }
    
    /**
     * Change weight to kilos
     * 
     * @param float $weight Package weight
     * @return float
     */
    public function changeWeightToKilos($weight)
    {
        if ($this->getConfigValue('weight_unit') == 'kg') {
            return $weight;
        } elseif ($this->getConfigValue('weight_unit') == 'g') {
            return ($weight / 1000);
        } else {
            return null;
        }
    }
    
    /**
     * Change dimension to centimeters
     * 
     * @param float $dimension Package dimension
     * @return float
     */
    public function changeDimensionToCentimeters($dimension)
    {
        if ($this->getConfigValue('dimension_unit') == 'cm') {
            return $dimension;
        } elseif ($this->getConfigValue('dimension_unit') == 'm') {
            return ($dimension * 100);
        } elseif ($this->getConfigValue('dimension_unit') == 'mm') {
            return $dimension / 10;
        } else {
            return null;
        }
    }
}

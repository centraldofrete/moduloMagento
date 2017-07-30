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

    /**
     * getAccessUrl
     *
     * Provides the URL to access the API server.
     * @return string
     */
    public function getAccessUrl()
    {
        if ($this->getConfigValue('environment') == 'production') {
            return $this->getConfigValue('production_server_url');
        } else {
            return $this->getConfigValue('test_server_url');
        }
    }

    /**
     * getAccessToken
     *
     * Get access token from the API server.
     * @return bool|string
     */
    public function getAccessToken()
    {
        // prepare basic data
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->getConfigValue('carrier_username'),
            'client_secret' => $this->getConfigValue('carrier_password'),
        );

        // process connection to API
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $this->getAccessUrl());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $returnString = curl_exec($ch);
        curl_close($ch);

        if (!$returnString) {
            return false;
        }

        // verify and return access token
        $response = json_decode($returnString);
        if ($response['access_token']) {
            return $response['access_token'];
        }
        return false;
    }
}

<?php
/**
 * Centraldofrete_Shipping Module
 */

/**
 * Quote Controller
 * 
 * Quote controller.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Centraldofrete_Shipping
 * @license GNU General Public License, version 3
 */
class Centraldofrete_Shipping_QuoteController extends Mage_Core_Controller_Front_Action
{
    /**
     * Index action
     */
    public function indexAction()
    {
        // verify if a product has been sent
        if (!$this->getRequest()->getParam('currentProduct')) {
            die;
        }
        
        // prepare basic data
        $postcode = (int) str_replace('-', '', str_replace('.', '', $this->getRequest()->getParam('postcode')));
        $productId = (int) $this->getRequest()->getParam('currentProduct');
        $productQty = (int) $this->getRequest()->getParam('qty');
        if ($productQty == 0 || $productQty == null) {
            $productQty = 1;
        }
        $params = $this->getRequest()->getParams();
        
        // get estimate quote        
        $shippingHtml = Mage::getModel('Centraldofrete_Shipping/estimate')->getEstimate($postcode, $productId, $productQty, $params);
        if ($shippingHtml) {
            echo json_encode($shippingHtml);
        }
    }
}

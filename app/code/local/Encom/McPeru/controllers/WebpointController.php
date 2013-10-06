<?php

/**
 * SafetyPay Controller
 */
//date_default_timezone_set('America/Lima');
class Strobe_McPeru_WebpointController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get singleton with payment model
     *
     * @return Strobe_VisaNet_Model
     */
    public function getPayment()
    {
        return Mage::getSingleton('mcperu/webpoint');
    }
    /**
       Generates HmacSha1
     **/
    function hmacsha1($key,$data, $hex = false){
      $blocksize=64;
      $hashfunc='sha1';
      if (strlen($key)>$blocksize)
      $key=pack('H*', $hashfunc($key));
      $key=str_pad($key,$blocksize,chr(0x00));
      $ipad=str_repeat(chr(0x36),$blocksize);
      $opad=str_repeat(chr(0x5c),$blocksize);
      $hmac = pack('H*',$hashfunc(($key^$opad).pack('H*',$hashfunc(($key^$ipad).$data))));
      if ($hex == false) {
        return $hmac;
      }else{
        return bin2hex($hmac);
      }
    }

    public function getRedirectUrl($shop_code,$order_number,$mount,$coin_type,$txn_date,
                                   $txn_hour,$random,$cod_client,$cod_country,$key){
        $mount = number_format($mount,2);
      $target_url = "http://server.punto-web.com/gateway/PagoWebHd.asp";
      $order_number="0000".$order_number;
      $data = array();
      $data[] = $shop_code;
      $data[] =$order_number;
      $data[]=$mount;
      $data[]=$coin_type;
      $data[]=$txn_date;
      $data[]=$txn_hour;
      $data[]=$random;
      $data[]=$cod_client;
      $data[]=$cod_country;
      $data[]=$key;
      $final = implode('',$data);

      $hash = urlencode(base64_encode($this->hmacsha1($key,$final)));

      $final_html = '<html>
    <body onload="document.frm.submit()">
       <form name="frm" action="'.$target_url.'" method="POST">
        <input type="hidden" name="I1" value="'.$data[0].'">
        <input type="hidden" name="I2" value="'.$data[1].'">
        <input type="hidden" name="I3" value="'.$data[2].'">
        <input type="hidden" name="I4" value="'.$data[3].'">
        <input type="hidden" name="I5" value="'.$data[4].'">
        <input type="hidden" name="I6" value="'.$data[5].'">
        <input type="hidden" name="I7" value="'.$data[6].'">
        <input type="hidden" name="I8" value="'.$data[7].'">
        <input type="hidden" name="I9" value="'.$data[8].'">
        <input type="hidden" name="I10" value="'.$hash.'">
        <input type="submit">
       </form>
      </body>
      </html>';
      return $final_html;
    }
    /**
     * Get singleton with model checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    public function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    public function checkOrder($_POST,$order){
      if($order->getGrandTotal()!=$_POST['O9']){
        //Error in totals
        throw new Exception('La transacci&oacute;n de tu order N: '.$order->getId().'ha sido denegada');
      }
    }
    /**
     * Notification Endpoint
     *
     * @return Nothing
     */
    public function endpointAction()
    {
      //error_reporting(E_ALL);
      $shop_code = 4000285;
      $moneda = "PEN";
      $merchant_key = "drewuq7caduprastenatranaspudrUwr";
      $return_url = "http://bembos.neo.com.pe/mcperu/webpoint/endpoint/";
      $cod_country="PER";
      $txn_date=date('Ymd');
      $txn_hour=date('His');
      $random=substr(number_format(time() * rand(),0,'',''),0,10);
      //Si es nulo, mostramos error y nos vamos

      //Saving the data
      $write = Mage::getSingleton('core/resource')->getConnection('core_write');
      $query = "INSERT INTO `bmbdev_mcperu` (`O1`, `O2`, `O3`, `O4`, `O5`, `O6`, `O7`, `O8`, `O9`, `O10`, `id`) VALUES ('{$_POST['O1']}', '{$_POST['O2']}', '{$_POST['O3']}', '{$_POST['O4']}', '{$_POST['O5']}', '{$_POST['O6']}', '{$_POST['O7']}', '{$_POST['O8']}', '{$_POST['O9']}', '{$_POST['O10']}', NULL);";
      $write->query($query);
      //End
      if(!isset($_POST['O1'])){
        $this->_getCheckout()->addError("No pudimos procesar tu pedido en estos momentos");
        $this->_redirect('checkout/cart');
        return;
      }
      if(!isset($_POST['O10'])){
        $this->_getCheckout()->addError("No pudimos procesar tu pedido en estos momentos");
        $this->_redirect('checkout/cart');
        return;
      }
      $O1 = $_POST['O1'];
      $O10 = $_POST['O10'];
      $O9 = $_POST['O9'];
      $O15 = $_POST['O15'];
      $O17 = $_POST['O17'];
      $O11 = $_POST['O11'];
      //$fecha
      $fecha = substr($O11,0,4)."/".substr($O11,4,2)."/".substr($O11,6,2);
      $O12 = $_POST['O12'];
      $hora = substr($O12,0,2).":".substr($O12,2,2).":".substr($O12,4,2);

      if($O1!="A"){
        $this->_getCheckout()->addError("No pudimos procesar tu pedido en estos momentos");
        $this->_getCheckout()->addError("N&uacute;mero de referencia:".$O10);
        $this->_getCheckout()->addError("Fecha de Transacci&oacute;n:".$fecha);
        $this->_getCheckout()->addError("Hora de Transacci&oacute;n:".$hora);
        $this->_getCheckout()->addError("Monto de Transacci&oacute;n:".$O9);
        $this->_getCheckout()->addError("Numero de Tarjeta:".$O15);
        $this->_getCheckout()->addError("Mensaje de Respuesta:".$O17);
        $this->_redirect('checkout/cart');
        return;
      }

      $order_id = str_replace("0000","",$O10);

       //Load the order
      $order = Mage::getModel('sales/order');
      $order->load($order_id);
      try{
        $cod = $_POST['O2'];
        $this->checkOrder($_POST,$order);
        $order->setStatus('payment_confirmed_mcperu');
        #$order->setData('state','complete');
        $history = $order->addStatusHistoryComment(
                         __('Orden pagada con MCPeru')
            );
        $history->setIsCustomerNotified(true);
        $order->save();
        
        // Enviar email de cambio de estado.
        $order->sendOrderUpdateEmail(true, 'Pago confirmado por MCPeru');
        
        //$event = Mage::getModel('moneybookers/event')->setEventData($this->getRequest()->getParams());
        $quoteId = "Pago Completo";
        $this->_getCheckout()->setLastSuccessQuoteId($quoteId);
        //Success
        $this->_getCheckout()->addSuccess("N&uacute;mero de referencia:".$O10);
        $this->_getCheckout()->addSuccess("Fecha de Transacci&oacute;n:".$fecha);
        $this->_getCheckout()->addSuccess("Hora de Transacci&oacute;n:".$hora);
        $this->_getCheckout()->addSuccess("Monto de Transacci&oacute;n:".$O9);
        $this->_getCheckout()->addSuccess("Codigo de Autorizaci&oacute;n:".$cod);
        $this->_getCheckout()->addSuccess("Numero de Tarjeta:".$O15);
        $this->_getCheckout()->addSuccess("Mensaje de Respuesta:".$O17);
        $this->_redirect('checkout/onepage/success');
        return;
      }catch(Exception $e){
      /*$order->setState(Mage_Sales_Model_Order::STATE_CANCELED,
                Mage_Sales_Model_Order::STATE_CANCELED,
                $e->getMessage(),
                false
            );
            $order->save();*/
            $order->cancel();
            $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $e->getMessage());
            $order->save();
            
            $order->sendOrderUpdateEmail(true, $e->getMessage());
            
            Mage::getSingleton('checkout/session')->addException($e,
                                                                 Mage::helper('visanet')->__('La transacci&oacute;n de tu orden  ha sido denegada.')
            );
        $this->_getCheckout()->addError("N&uacute;mero de referencia:".$O10);
        $this->_getCheckout()->addError("Fecha de Transacci&oacute;n:".$fecha);
        $this->_getCheckout()->addError("Hora de Transacci&oacute;n:".$hora);
        $this->_getCheckout()->addError("Monto de Transacci&oacute;n:".$O9);
        $this->_getCheckout()->addError("Numero de Tarjeta:".$O15);
        $this->_getCheckout()->addError("Mensaje de Respuesta:".$O17);
            parent::_redirect('checkout/cart');
            return;
      }
        /*$this->_getCheckout()->clear();
        $this->getPayment()->processNotification($this->getRequest()->getParams());
        exit(0);*/
    }

    /**
     * Order Place and redirect to SafetyPay Express service
     */
    public function paymentAction()
    {
      $shop_code = 4000285;
      $moneda = "PEN";
      $merchant_key = "drewuq7caduprastenatranaspudrUwr";
      $return_url = "http://bembos.neo.com.pe/mcperu/webpoint/endpoint/";
      $cod_country="PER";
      $txn_date=date('Ymd');
      $txn_hour=date('His');
      $random=substr(number_format(time() * rand(),0,'',''),0,10)."1234";;

        try
        {
             //$this->loadLayout();
             //$this->renderLayout();
        }
        catch (Exception $e)
        {
            //Who cares?
        }
        try {

            $session = $this->_getCheckout();
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            $order->save();
            $cod_client= $order->getId();
            $cod_client = "CLI".$cod_client;
            $session->getQuote()->setIsActive(false)->save();
            $redirect = $this->getRedirectUrl($shop_code,$order->getId(),$order->getGrandTotal(),$moneda,$txn_date,
                           $txn_hour,$random,$cod_client,$cod_country,$merchant_key);
            $session->setSafetypayQuoteId($session->getQuoteId());
            $session->setSafetypayRealOrderId($session->getLastRealOrderId());
            $session->clear();

            // $this->loadLayout();
            //$this->renderLayout();

            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('mcperu')->__('The shopper has been redirected to Mastercard service using the Token URL.'),
                true
            );
            $order->sendNewOrderEmail();
            $order->setEmailSent(true);
            $order->save();
            echo $redirect;
        } catch (Exception $e){
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED,
                Mage_Sales_Model_Order::STATE_CANCELED,
                $e->getMessage(),
                false
            );
            $order->save();
            Mage::getSingleton('checkout/session')->addException($e,
                Mage::helper('mcperu')->__('Gateway returned an error message:<br>%s', $e->getMessage())
            );
            parent::_redirect('checkout/cart');
        }
    }

    /**
     * Action to which the customer will be returned when the payment is made.
     *
     * @return Nothing
     */
    /*public function successAction()
    {
        $event = Mage::getModel('safetypay/event')
                 ->setEventData($this->getRequest()->getParams());
        try {
            $quoteId = $event->successEvent();

            $message = $event->confirmationEvent();
            $this->getResponse()->setBody($message);

            $this->_getCheckout()->setLastSuccessQuoteId($quoteId);
            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }*/

    /**
     * Action to which the customer will be returned if the payment process is
     * cancelled.
     * Cancel order and redirect user to the shopping cart.
     */
    /*public function cancelAction()
    {
        $event = Mage::getModel('safetypay/event')
                 ->setEventData($this->getRequest()->getParams());
        $message = $event->cancelEvent();
        $this->_getCheckout()->setQuoteId($this->_getCheckout()->getSafetypayQuoteId());
        $this->_getCheckout()->addError($message);
        $this->_redirect('checkout/cart');
    }*/
}

<?php
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPayme extends vmPSPlugin
{	
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $jlang = JFactory::getLanguage();
        $jlang->load('vmpayment_payme', JPATH_ADMINISTRATOR, NULL, TRUE);
        $varsToPush        = array(
            'secret_key' => array('','string'),
            'public_key' => array('','string'),
        );
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {  //настройки
            return null; // Another method was selected, do nothing
        }
        $merchant_id = $method->public_key;
        $sum = number_format((float)$order['details']['BT']->order_total, 0, '.', '');
      	$sum=$sum*100;
        $account = $order['details']['BT']->virtuemart_order_id;
        $desc = 'Оплата по заказу №' . $order['details']['BT']->order_number;
        $html = '<form name="payme" action="http://checkout.paycom.uz" method="POST">';
        $html .= '<input type="text" name="merchant" value="' . $merchant_id . '">';
        $html .= '<input type="text" name="amount" value="' . $sum . '">';
        $html .= '<input type="text" name="account[order]" value="' . $account . '">';
      	$html .= '<input type="text" name="lang" value="ru">';
        $html .= '</form>';
        $html .= '<script type="text/javascript">';
        $html .= 'document.forms.payme.submit();';
        $html .= '</script>';
        return $this->processConfirmedOrderPaymentResponse(true, $cart, $order, $html, $this->renderPluginName($method, $order), 'P');
    }
    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
    function plgVmDeclarePluginParamsPaymentVM3( &$data) 
	{
        return $this->declarePluginParams('payment', $data);
    }
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }
    protected function displayLogos($logo_list)
    {
        $img = "";
        if (!(empty($logo_list))) {
            $url = JURI::root() . str_replace('\\', '/', str_replace(JPATH_ROOT, '', dirname(__FILE__))) . '/';
            if (!is_array($logo_list))
                $logo_list = (array) $logo_list;
            foreach ($logo_list as $logo) {
                $alt_text = substr($logo, 0, strpos($logo, '.'));
                $img .= '<img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /> ';
            }
        }
        return $img;
    }
    public function plgVmOnPaymentNotification()
    {
		$payload = json_decode(file_get_contents('php://input'), true);
		$method = '';
        $params = [];
		if (json_last_error() !== JSON_ERROR_NONE) {
            $this->respond($this->error_invalid_json());
        }
		if (!function_exists('getallheaders')) {
			function getallheaders(){
				$headers = '';
				foreach ($_SERVER as $name => $value){
					if (substr($name, 0, 5) == 'HTTP_'){
						$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
					}
				}
				return $headers;
			}
		}
		$headers = getallheaders();
      	$method = $this->getVmPluginMethod("7");
      	$secret=$method->secret_key;
		$code=base64_encode("Paycom:". $secret);
      	if (!$headers || !isset($headers['Authorization']) ||  !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) || $matches[1] != $code) {
			$this->respond($this->error_authorization($payload));
        }else{
			$orderModel = VmModel::getModel('orders');
			$order = $orderModel->getOrder($payload['params']['account']['order']);
			$response = method_exists($this, $payload['method'])
            ? $this->{$payload['method']}($payload)
            : $this->error_unknown_method($payload);
			$this->respond($response);
        }
    }
 	function respond($response)
    {
       	header('Content-Type: application/json');
        echo json_encode($response);
        die();
    }
	function get_order($payload)
    {
		$order_id = $payload['params']['account']['order'];
		$orderModel     = VmModel::getModel('orders');
		$order          = $orderModel->getOrder($order_id);
		if (empty($order['details'])){
			$this->respond($this->error_order_id($payload));
        }else{
			return $order;
        }
    }
	function get_order_by_id($payload)
	{
		$transaction =$payload['params']['id'];
		$db = JFactory::getDbo();
		$order_id = $db->setQuery("SELECT virtuemart_order_id FROM #__virtuemart_orders WHERE order_pass = '$transaction'")->loadResult();
		return $order_id;
	}
  	function ChangePassword( $payload )
    {
      $method = $this->getVmPluginMethod("7");
      $secret=$method->secret_key;
      if ($payload['params']['password'] != $secret){
        $replase =str_replace($secret,$payload['params']['password'],$method->payment_params);
        $db = JFactory::getDbo();
        $db->setQuery("update #__virtuemart_paymentmethods set payment_params = '$replase' where virtuemart_paymentmethod_id = $method->virtuemart_paymentmethod_id");
		$db->execute();
      	$response = ["id" => $payload['id'],"result" => ["success" => true]];
			return $response;
      }else{
        $this->respond($this->error_authorization($payload));
      }
    }
    function CheckPerformTransaction( $payload )
    {
        $order = $this->get_order($payload);
        $order_details  = $order['details']['BT'];
		$sum=($order_details->order_total)*100;
		if ($sum !=$payload['params']['amount']){
			$this->respond($this->error_amount($payload));
		}else{
			$response = ['id' => $payload['id'],
						 'result' => [
										'allow' => true],
						 'error' => null];
			return $response;
		}
    }
    function CreateTransaction( $payload )
    {
		$order = $this->get_order($payload);
		$order_details  = $order['details']['BT'];
		$transaction=$payload['params']['id'];
      	$sum=($order_details->order_total)*100;
		if ($sum !=$payload['params']['amount']){
			$this->respond($this->error_amount($payload));
		}elseif ($sum == $payload['params']['amount']){
			if ($order_details->order_status == "P" ){
				$create_time=round(microtime(true) * 1000);
				$db = JFactory::getDbo();
				$db->setQuery("update #__virtuemart_orders set  create_time = $create_time  where virtuemart_order_id = $order_details->virtuemart_order_id");
				$db->execute();
				$response = ["id" => $payload['id'],
											"result" => [
											"create_time" =>$create_time,
											"transaction"=>$order_details->order_number,
											"state"=>1]];
				$orderModel     = VmModel::getModel('orders');
				$orderStatus['order_status']        = 'U';  //подтвержден
				$orderStatus['order_pass']        = $payload['params']['id'];
				$orderModel->updateStatusForOneOrder($payload['params']['account']['order'], $orderStatus, true);
			}elseif ($order_details->order_status == "U" && $order_details->order_pass==$transaction){
				$response = ["id" => $payload['id'],
											"result" => [
											"create_time" =>(int)$order_details->create_time,
											"transaction"=>$order_details->order_number,
											"state"=>1]];
			}elseif ($order_details->order_status == "U" && $order_details->order_pass != $transaction){
				$this->respond($this->error_method($payload));
			}
		}else{
			$this->respond($this->unknown_error($payload));
		}
		return $response;
    }
  	function PerformTransaction( $payload )
    {
		$order_id = $this->get_order_by_id($payload);
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($order_id);
		$order_details = $order['details']['BT'];
		if ($order_details->order_status == "U" ){
			$perform_time=round(microtime(true) * 1000);
			$db = JFactory::getDbo();
			$db->setQuery("update #__virtuemart_orders set  perform_time =  $perform_time  where virtuemart_order_id = $order_id");
			$db->execute();
			$response = ["id" => $payload['id'],
											"result" => [
											"transaction"=>$order_details->order_number,
											"perform_time"=>$perform_time,
											"state"=>2]];
			$orderModel     = VmModel::getModel('orders');
			$orderStatus['order_status']        = 'C';
			$orderModel->updateStatusForOneOrder($order_id, $orderStatus, true);
		}elseif ($order_details->order_status == "C"){
			$response = ["id" => $payload['id'],
											"result" => [
											"transaction"=>$order_details->order_number,
											"perform_time"=>(int)$order_details->perform_time,
											"state"=>2]];
		}elseif ($order_details->order_status == "R" || $order_details->order_status == "X"){
			$response = ["error" => ["code"=>-31008,"message"=>[
																"ru"=>"Транзакция отменена или возвращена",
																"uz"=>"Tranzaksiya bekor qilingan yoki qaytarilgan",
																"en"=>"Transaction was cancelled or refunded"],
																"data"=>"order"],
												"result"=>null,	"id" => $payload['id']];
		}elseif ($order_details->order_status != "U"){
			$this->respond($this->error_transaction($payload));
		}else{
			$this->respond($this->unknown_error($payload));
		}
        return $response;
    }
	function CheckTransaction( $payload )
    {
		$order_id = $this->get_order_by_id($payload);
		$orderModel     = VmModel::getModel('orders');
		$order = $orderModel->getOrder($order_id);
		$order_details = $order['details']['BT'];
		if ($order_details->order_status == "U" && $order_details->order_pass == $payload['params']['id']){
			$response = ["id" => $payload['id'],
											"result" => [
											"create_time"=>(int)$order_details->create_time,
											"perform_time"=>0,
											"cancel_time"=>0,
											"transaction"=>$order_details->order_number,
											"state"=>1, 
											"reason"=>null]];
		}elseif ($order_details->order_status == "C" && $order_details->order_pass == $payload['params']['id'] ){
			$response = ["id" => $payload['id'],
											"result" => [
											"create_time"=>(int)$order_details->create_time,
											"perform_time"=>(int)$order_details->perform_time,
											"cancel_time"=>0,
											"transaction"=>$order_details->order_number,
											"state"=>2, 
											"reason"=>null]];
		}elseif ($order_details->order_status == "X" && $order_details->order_pass == $payload['params']['id']){
			$response = ["id" => $payload['id'],
											"result" => [
											"create_time"=>(int)$order_details->create_time,
											"perform_time"=>0,
											"cancel_time"=>(int)$order_details->cancel_time,
											"transaction"=>$order_details->order_number,
											"state"=>-1, 
											"reason"=>2]];
		}elseif ($order_details->order_status == "R" && $order_details->order_pass == $payload['params']['id']){
			$response = ["id" => $payload['id'],
											"result" => [
											"create_time"=>(int)$order_details->create_time,
											"perform_time"=>(int)$order_details->perform_time,
											"cancel_time"=>(int)$order_details->cancel_time,
											"transaction"=>$order_details->order_number,
											"state"=>-2, 
											"reason"=>5]];
		}else{
			$this->respond($this->error_transaction($payload));
		}
        return $response;
    }
	function CancelTransaction( $payload )
    {
		$order_id = $this->get_order_by_id($payload);
		$orderModel     = VmModel::getModel('orders');
		$order = $orderModel->getOrder($order_id);
		$order_details = $order['details']['BT'];
		$cancel_time=round(microtime(true) * 1000);
		if ($order_details->order_status == "U"){
			$db = JFactory::getDbo();
			$db->setQuery("update #__virtuemart_orders set  cancel_time =  $cancel_time  where virtuemart_order_id = $order_id");
			$db->execute();
			$response = ["id" => $payload['id'],
											"result" => [
										  	"transaction"=>$order_details->order_number,
										  	"cancel_time"=>$cancel_time,
										  	"state"=>-1]];
			$orderModel     = VmModel::getModel('orders');
			$orderStatus['order_status']        = 'X';
			$orderModel->updateStatusForOneOrder($order_id, $orderStatus, true);
		}elseif ($order_details->order_status == "C"){
			$db = JFactory::getDbo();
			$db->setQuery("update #__virtuemart_orders set  cancel_time =  $cancel_time  where virtuemart_order_id = $order_id");
			$db->execute();
			$response = ["id" => $payload['id'], 
											"result" => [
										  	"transaction"=>$order_details->order_number,
										  	"cancel_time"=>$cancel_time,
										  	"state"=>-2]];
			$orderModel     = VmModel::getModel('orders');
			$orderStatus['order_status']        = 'R';
			$orderModel->updateStatusForOneOrder($order_id, $orderStatus, true);
		}elseif ($order_details->order_status == "X"){
			$response = ["id" => $payload['id'], 
											"result" => [
										  	"transaction"=>$order_details->order_number,
										  	"cancel_time"=>(int)$order_details->cancel_time,
										  	"state"=>-1]];
		}elseif ($order_details->order_status == "R"){
			$response = ["id" => $payload['id'],
											"result" => [
										  	"transaction"=>$order_details->order_number,
										  	"cancel_time"=>(int)$order_details->cancel_time,
										  	"state"=>-2]];
		}else {
			$response = ["error" => ["code"=>-31007,"message"=>[
																"ru"=>"Невозможно отменить. Заказ выполнен.",
																"uz"=>"Buyurtma bajarilgan - uni bekor qilib bo`lmaydi",
																"en"=>"It is impossible to cancel. The order is completed"],
												"data"=>"order"],"result"=>null,"id" => $payload['id']];
		}
        return $response;
    }
  	function error_authorization($payload)
	{
		$response = ["error" => ["code"=>-32504,"message"=>[
												"ru"=>"Ошибка при авторизации",
												"uz"=>"Avtorizatsiyada xatolik",
												"en"=>"Error during authorization"],
								"data"=>null],"result"=>null,"id" => $payload['id']];
		return $response;
	}
    function error_amount($payload)
    {
		$response = ["error" => [
                    		"code" => -31001,
                    		"message" => ["ru"=>"Неверная сумма заказа",
										  "uz"=>"Buyurtma summasi xato",
										  "en"=>"Order ammount incorrect"],
                    		"data" => "amount"],
                		 "result" => null,
                		 "id" => $payload['id']];
        return $response;
    }
	function error_unknown_method($payload)
    {
        $response = ["error" => [
                    		"code" => -32601,
                   			"message" => ["ru" => "'Unknown method_ru",
                        				  "uz" => "'Unknown method_uz",
                        				  "en" => "'Unknown method_en"],
                    		"data" => $payload['method']],
                		"result" => null,
               			"id" => $payload['id']];
        return $response;
    }
   	function error_order_id($payload)
    {
        $response = ["error" => [
                    		"code" => -31099,
                    		"message" => ["ru"=>"Номер заказа не найден",
										  "uz"=>"Buyurtma raqami topilmadi",
										  "en"=>"Order number not found"],
                    		"data" => "order"],
                		"result" => null,
                		"id" => $payload['id']];
        return $response;
    }
  	function unknown_error($payload)
	{
		$response = ["error" => ["code"=>-31008,"message"=>[
													"ru"=>"Неизвестная ошибка",
													"uz"=>"Noma`lum xatolik",
													"en"=>"Unknown error"],
													"data"=>null],"result"=>null,"id" => $payload['id']];
		return $response;
	}
  	function error_transaction($payload)
	{
		$response = ["error" => ["code"=>-31003,"message"=>[
													"ru"=>"Номер транзакции не верен",
													"uz"=>"Tranzaksiya raqami xato",
													"en"=>"Transaction number is wrong"],
									"data"=>null],"result"=>null,"id" => $payload['id']];
		return $response;
	}
	function error_method($payload)
	{
		$response = ["error" => [	"code"=>-31099,	"message"=>[
														"ru"=>"Ошибка метода ".$payload['method'],
														"uz"=>"Xato ".$payload['method'],
														"en"=>"Error during ".$payload['method']],
										"data"=>"order"],"result"=>null,"id" => $payload['id']];
		return $response;
	}
}
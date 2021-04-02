<?php
class ControllerExtensionModuleAmocrm extends Controller {
	public function index() {
		$this->load->model('setting/amocrm');
		if (($this->request->server['REQUEST_METHOD'] == 'GET') AND isset($this->session->data['user_token'])) {
			unset($this->request->get['route']);
			sleep(1);
				if(isset($this->request->get['code']) AND isset($this->request->get['client_id']) AND isset($this->request->get['referer'])){
					$this->request->get['name'] = "amocrm";
					$this->request->get['status'] = 1;
					$this->model_setting_amocrm->addModule('amocrm', $this->request->get);
					$url = new Url(HTTP_SERVER."/admin/", $this->config->get('config_secure') ? HTTP_SERVER."/admin/" : HTTPS_SERVER."/admin/");
					$module_id = $this->model_setting_amocrm->access_token();
					$this->response->redirect($url->link('extension/module/amocrm', 'user_token=' . $this->session->data['user_token'] . '&type=module&module_id='.$module_id, true));
				}
			}else{
				echo "Error auth";
			}
		}
		public function hook(){
			$this->load->model('setting/amocrm');
			$this->load->model('checkout/order');
			$data_r = $this->model_setting_amocrm->getModule('amocrm');
			foreach ($data_r['leads'] as $key => $value) {
				if($value['shop_columns'] == "order_id"){
					$order_id = $value['amo_columns'];
				}
			}
			$custom_fields = $this->request->post['leads']['update'][0]['custom_fields'];
			$data_fores = [];
			foreach ($custom_fields as $key => $value) {
				if(isset($value['values'][0]['enum'])  && $value['values'][0]['enum'] !=""){
					$data_fores[] = "{$value['id']}|{$value['values'][0]['enum']}";
				}elseif($order_id == $value['id']){
					$order_id  = $value['values'][0]['value'];
				}
			}
			if(count($data_fores) <= 1){
				$data_fores = $data_fores[0];
			}
			$order_status = false;
			if(isset($data_r['order_status'])){
				foreach ($data_r['order_status'] as $key => $value) {
					if($value == $data_fores){
						$order_status = $key;
					}
				}
			}
			if($order_status && $order_id){
				$this->model_checkout_order->addOrderHistory($order_id, $order_status,'Изменено из AmoCrm #'.$this->request->post['leads']['update'][0]['responsible_user_id']);
			}
		}
		public function webhook(){
			$this->load->model('setting/amocrm');
			$postData = json_decode(file_get_contents('php://input'),true);

			if(isset($postData['client_secret']) && isset($postData['client_id'])){
				$postData['name'] = "amocrm";
				$this->model_setting_amocrm->addModule('amocrm', $postData);
			}
		}
		public function addOrder(){
					$this->load->model('checkout/order');
					$this->load->model('setting/amocrm');
					if(isset($this->session->data['order_id'])){
							$setting = $this->model_setting_amocrm->getModule('amocrm');
					$order = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);
					$leads = [
						"name" => "Заказ №".$order['order_id'],
						"price"=>(int)$order['total'],
						'_embedded'=>[
							'contacts'=>[
							]
						]
					];
					$leads['_embedded']['contacts'][0]['first_name'] = $order['firstname'];
					$leads['_embedded']['contacts'][0]['last_name'] = $order['lastname'];
					foreach ($setting['contacts'] as $key => $value) {
						$leads['_embedded']['contacts'][0]['custom_fields_values'][] = 	[
												'field_id'=>(int)$value['amo_columns'],
							         	 		'values'=>[
							         	 				["value"=>$order[$value['shop_columns']]]
							         	 			]
							         	 		];
					}
					foreach ($setting['leads'] as $key => $value) {
						$leads['custom_fields_values'][] = 	[
												'field_id'=>(int)$value['amo_columns'],
							         	 		'values'=>[
							         	 				["value"=>$order[$value['shop_columns']]]
							         	 			]
							         	 		];
					}
					$leads['_embedded']['contacts'][0]['custom_fields_values'][]=["field_code"=>"EMAIL",
							         	 		'values'=>[["enum_code"=>"WORK","value"=>$order['email']]]
							         	 	];
					$leads['_embedded']['contacts'][0]['custom_fields_values'][]=["field_code"=>"PHONE",
							         	 		'values'=>[["enum_code"=>"WORK","value"=>$order['telephone']]]
							         	 	];
					if(isset($setting['order_status'][$order['order_status_id']]) && $setting['order_status'][$order['order_status_id']] !=""){
						$sert = explode("|", $setting['order_status'][$order['order_status_id']]);
						$leads['custom_fields_values'][] = 	[
												'field_id'=>(int)$sert[0],
							         	 		'values'=>[
							         	 				["enum_id"=>(int)$sert[1]]
							         	 			]
							         	 		];
					}
					$leads_add = $this->model_setting_amocrm->addLeadsComplex($leads);
					if(isset($leads_add[0]['id'])){
						// Товар к Сделки
						$product_amo = $this->model_setting_amocrm->getProductsSettings();
						$link_add = [];
						if(isset($product_amo['is_enabled']) && $product_amo['is_enabled']){
							$getOrderProducts = $this->model_checkout_order->getOrderProducts((int)$this->session->data['order_id']);
							$catalog_id = $product_amo['catalog_id'];
							$catalog_elements['add'] = [];
							foreach ($getOrderProducts as $key => $value) {
								if(isset($setting['other']['status']) && $setting['other']['status'] == 1){
									$options = [];
									foreach ($this->model_checkout_order->getOrderOptions($value['order_id'],$value['order_product_id']) as $key_option => $value_option) {
										$options[] = "{$value_option['name']}: {$value_option['value']}";
									}
									if(count($options) >= 1){
										$value['name'] = "{$value['name']}(".implode(" | ", $options).")";
									}
								}
								$catalog_elements['add'][] = [
										'catalog_id'=>$catalog_id,
										'name'=>$value['name'],
										'request_id'=>$value['quantity'],
										'custom_fields'=>[
											[
												'code'=>'SKU',
												'values'=>[
														['value'=>$value['model']],
												]
											],
											[
												'code'=>'PRICE',
												'values'=>[
														['value'=>(string)number_format($value['price'],0)],
												]
											],
											[
												'code'=>'DESCRIPTION',
												'values'=>[
														['value'=>''],
												]
											],
											[
												'code'=>'EXTERNAL_ID',
												'values'=>[
														['value'=>$value['product_id']],
												]
											]
										]
								];
							}
							$product_add = $this->model_setting_amocrm->catalog_elements($catalog_elements);
							foreach ($product_add['_embedded']['items'] as $key => $value) {
								$link_add[] = [
										'to_entity_id' => $value['id'],
										"to_entity_type" => "catalog_elements",
										'metadata'=>[
												// 'quantity'=> (int)$q[$value['custom_fields'][3]['values'][0]['value']],
												'quantity'=>$value['request_id'],
												'catalog_id'=>$catalog_id,
										]
								];
							}
						}
						if(isset($setting['task']['status']) && $setting['task']['status'] == 1){
							$task = [];
							$task[0]['text'] = $setting['task']['text'];
							$task[0]['entity_type'] = 'leads';
							if($setting['task']['responsible_user_id']!=""){
								$task[0]['responsible_user_id'] = $setting['task']['responsible_user_id'];
							}
							$task[0]['entity_id'] = $leads_add[0]['id'];
							$task[0]['complete_till'] = time()+(60*(int)$setting['task']['time']);
							$task_amo = $this->curl_post('/api/v4/tasks',$task);
						}
						if(count($link_add) >= 1){
								$this->model_setting_amocrm->LinkAdd($leads_add[0]['id'],$link_add);
						}
					}


					}
		}
}


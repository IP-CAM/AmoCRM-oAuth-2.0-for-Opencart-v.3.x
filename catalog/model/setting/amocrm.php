<?php
class ModelSettingAmocrm extends Model {
	public function addModule($code, $data) {
		if($data_update = $this->getModule($code)){
			foreach ($data as $key => $value) {
					$data_update[$key] = $value;
			}
			$this->db->query("DELETE FROM oc_module WHERE code='".$this->db->escape($code)."'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "module` SET `name` = '" . $this->db->escape($data['name']) . "', `code` = '" . $this->db->escape($code) . "', `setting` = '" . $this->db->escape(json_encode($data_update)) . "'");

		}else{
				$this->db->query("INSERT INTO `" . DB_PREFIX . "module` SET `name` = '" . $this->db->escape($data['name']) . "', `code` = '" . $this->db->escape($code) . "', `setting` = '" . $this->db->escape(json_encode($data)) . "'");
		}
		return $this->db->getLastId();
	}
	public function getModule($code) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "module WHERE code = '" . $this->db->escape($code) . "'");
		if ($query->row) {
			return json_decode($query->row['setting'], true);
		} else {
			return false;
		}
	}
	public function access_token(){
		    $data = $this->getModule('amocrm');
			/** Соберем данные для запроса */
			$url = new Url(HTTP_SERVER, $this->config->get('config_secure') ? HTTP_SERVER : HTTPS_SERVER);

			if(!isset($data['client_secret'])){
				return false;
			}
			$data = [
				'client_id' => trim($data['client_id']),
				'client_secret' => trim($data['client_secret']),
				'grant_type' => 'authorization_code',
				'code' => trim($data['code']),
				'redirect_uri' => $url->link('extension/module/amocrm'),
			];
			$response = $this->curl_post('/oauth2/access_token',$data,false);
			$response['name'] = 'amocrm';
			return $this->addModule('amocrm',$response);
	}
	protected function refresh_token(){
			$data = $this->getModule('amocrm');
			$url = new Url(HTTP_SERVER, $this->config->get('config_secure') ? HTTP_SERVER : HTTPS_SERVER);
			$data = [
				'client_id' => trim($data['client_id']),
				'client_secret' => trim($data['client_secret']),
				'grant_type' => 'refresh_token',
				'refresh_token' => trim($data['refresh_token']),
				'redirect_uri' => $url->link('extension/module/amocrm'),
			];
			$response = $this->curl_post('/oauth2/access_token',$data,false);
			$response['name'] = 'amocrm';
			$this->addModule('amocrm',$response);
			return $response;
	}
	protected function curl_post($method,$post = false,$token = true){
		$data = $this->getModule('amocrm');
		$link = 'https://' . $data['referer'] . $method;
		$curl = curl_init(); //Сохраняем дескриптор сеанса cURL
		$headers = [
			'Content-Type:application/json'
		];
		if($token){
			$headers[] = 'Authorization: Bearer ' . $data['access_token'];
		}
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
		curl_setopt($curl,CURLOPT_URL, $link);
		curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl,CURLOPT_HEADER, false);
		if($post){
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($post));
		}
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
		$out = json_decode(curl_exec($curl),true); 
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$code = (int)$code;
		if(isset($out['response']['error_code'])){
				if($out['response']['error_code'] == '110'){
					$data_refresh = $this->refresh_token();
					if(isset($data_refresh['expires_in'])){
						return $this->curl_post($method,$post);
					}
				}
		}
		try{
			if ($code < 200 || $code > 204) {
				throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
			}
		}catch(\Exception $e){
			return false;
		}
		return $out;
	}
	public function addOrder($orders){
		$this->load->model('checkout/order');
		$setting = $this->getModule('amocrm');
		$order = $this->model_checkout_order->getOrder((int)$orders);
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
		$leads_add = $this->curl_post('/api/v4/leads/complex',[$leads]);
		if(!isset($leads_add[0]['id'])){
				return false;
		}
		// Товар к Сделки
		$product_amo = $this->curl_post('/api/v2/products_settings');
		$link_add = [];
		if(isset($product_amo['is_enabled']) && $product_amo['is_enabled']){
			$getOrderProducts = $this->model_checkout_order->getOrderProducts((int)$orders);
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
			$product_add = $this->curl_post('/api/v2/catalog_elements',$catalog_elements);
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
				$this->curl_post("/api/v4/leads/{$leads_add[0]['id']}/link",$link_add);
		}
		return true;	
	}
	public function catalog_elements($catalog_elements){
		return $this->curl_post('/api/v2/catalog_elements',$catalog_elements);
	}
	public function LinkAdd($leads_id,$link_add){
		return $this->curl_post("/api/v4/leads/$leads_id/link",$link_add);
	}
	public function getProductsSettings(){
		return $this->curl_post('/api/v2/products_settings');
	}
	public function addLeadsComplex($leads){
		return $this->curl_post('/api/v4/leads/complex',[$leads]);
	}
}
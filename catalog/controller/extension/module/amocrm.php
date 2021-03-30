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
			}
		}

		public function test(){
			$this->load->model('setting/amocrm');
			$this->model_setting_amocrm->addOrder(15);

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
}


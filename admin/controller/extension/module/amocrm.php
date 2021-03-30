<?php

class ControllerExtensionModuleAmocrm extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/Amocrm');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');
		$this->load->model('extension/module/amocrm');

		$this->load->model('setting/module');

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}else{
			$module_info = false;
		}


		$data['order_shop_status'] = $module_info['order_status'];
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('amocrm', $this->request->post);
			} else {
				$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
				$module_info['leads'] = [];
				$module_info['contacts'] = []; 
				$module_info['other'] = []; 
				$module_info['task'] = []; 
				$module_info['order_status'] = []; 
				foreach ($this->request->post as $key => $value) {
					$module_info[$key] = $value;
				}
				$this->model_setting_module->editModule($this->request->get['module_id'], $module_info);
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/amocrm', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/amocrm', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}
		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/amocrm', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/amocrm', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info)) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}
		if (isset($this->request->post['code'])) {
			$data['code'] = $this->request->post['code'];
		} elseif (!empty($module_info)) {
			$data['code'] = $module_info['code'];
		} else {
			$data['code'] = '';
		}
		if (isset($this->request->post['client_id'])) {
			$data['client_id'] = $this->request->post['client_id'];
		} elseif (!empty($module_info)) {
			$data['client_id'] = $module_info['client_id'];
		} else {
			$data['client_id'] = '';
		}
		if (isset($this->request->post['referer'])) {
			$data['referer'] = $this->request->post['referer'];
		} elseif (!empty($module_info)) {
			$data['referer'] = $module_info['referer'];
		} else {
			$data['referer'] = '';
		}
		if (isset($this->request->post['client_secret'])) {
			$data['client_secret'] = $this->request->post['client_secret'];
		} elseif (!empty($module_info)) {
			$data['client_secret'] = $module_info['client_secret'];
		} else {
			$data['client_secret'] = '';
		}
		$data['leads_rows_shops'] = [];
		foreach ($this->model_extension_module_amocrm->getColumsOrder() as $key => $value) {
			$data['leads_rows_shops'][$value['COLUMN_NAME']] = $value['COLUMN_NAME'];
		}
		$data['leads_count'] = count($module_info['leads']);
		if(isset($module_info['contacts'])){
			$data['contacts'] = $module_info['contacts'];
		}else{
			$data['contacts'] = [];
		}
		if(isset($module_info['leads'])){
			$data['leads'] = $module_info['leads'];
		}else{
			$data['leads'] = [];
		}
		if(isset($module_info['other'])){
			$data['other'] = $module_info['other'];
		}else{
			$data['other'] = [];
		}
		if(isset($module_info['task'])){
			$data['task'] = $module_info['task'];
		}else{
			$data['task'] = [];
		}

		$data['contacts_count'] = count($module_info['contacts']);
		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info)) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = '';
		}
		$amo_fileds = $this->model_extension_module_amocrm->leads_custom_fields();
		$data['order_status'] = $this->model_extension_module_amocrm->getOrderStatus();
		$data['amo_fileds'] = [];
		$data['amo_fileds_selects'] = [];
		if(isset($amo_fileds['_embedded']['custom_fields'])){
			foreach ($amo_fileds['_embedded']['custom_fields'] as $key => $value) {
				if($value['type'] == "text"){
					$data['amo_fileds'][] = [
						'id'=>$value['id'],	
						'name'=>$value['name']
					];
				}elseif($value['type'] == "select"){

						$enums = [];
						foreach ($value['enums'] as $enum) {
							$enums[]=[
									'id'=>$enum['id'],
									'value'=>$enum['value'],
									'selected'=>$value['id']."|".$enum['id']
							];
						}
						$data['amo_fileds_selects'][]=[
								'id'=>$value['id'],	
								'name'=>$value['name'],
								'values'=>$enums
						];
				}else if($value['type'] == 'numeric'){
						$data['amo_fileds'][] = [
						'id'=>$value['id'],	
						'name'=>$value['name']
					];
				}
			}
		}

	
		$data['amo_fileds'] = array_reverse($data['amo_fileds']);

		$amo_fileds_contacts = $this->model_extension_module_amocrm->contacts_custom_fields();
		$data['amo_fileds_contacts'] = [];
		if(isset($amo_fileds_contacts['_embedded']['custom_fields'])){

			foreach ($amo_fileds_contacts['_embedded']['custom_fields'] as $key => $value) {
				if($value['type'] == "text"){
					$data['amo_fileds_contacts'][] = [
						'id'=>$value['id'],	
						'name'=>$value['name']
					];
				}
			}
			
		}

		$amo_fileds_users = $this->model_extension_module_amocrm->getUsers();
		$data['amo_users'] = [];
		if(isset($amo_fileds_users['_embedded']['users'])){
			foreach ($amo_fileds_users['_embedded']['users'] as $key => $value) {
				$data['amo_users'][] = [
					'id'=>$value['id'],	
					'name'=>$value['name']
				];
			}
		}


		$data['amo_fileds_contacts'] = array_reverse($data['amo_fileds_contacts']);
		$url = new Url(HTTP_CATALOG, $this->config->get('config_secure') ? HTTP_CATALOG : HTTPS_CATALOG);
		$data['secrets_url'] = $url->link('extension/module/amocrm/webhook');
		$data['redirect_url'] = $url->link('extension/module/amocrm');
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/module/amocrm_settings', $data));
	}


	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/amocrm')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}
		return !$this->error;
	}
}
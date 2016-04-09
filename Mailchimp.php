<?php
	// http://developer.mailchimp.com/documentation/mailchimp/reference/overview/
	
	include_once('Requests/Requests.php');
	Requests::register_autoloader();
	
	define('MAILCHIMP_MEMBERS_URL', '/lists/<list_id>/members');
	define('MAILCHIMP_MEMBER_URL', '/lists/<list_id>/members/<member_hash>');
	
	class Mailchimp_Member_Status {
		const Unsubscribed = 0;
		const Subscribed = 1;
		const Already_Subscribed = 2;
		const Not_Subscribed = 3;
	}
	
	class Mailchimp {
		private $base_url = 'https://<dc>.api.mailchimp.com/3.0';
		private $api_key;
		private $request_hooks;
		
		public function __construct($dc, $api_key) {
			$this->base_url = str_replace('<dc>', $dc, $this->base_url);
			$this->api_key = $api_key;
			
			$this->request_hooks = new Requests_Hooks();
			$this->request_hooks->register('requests.before_request', array($this,'_auth_callback'));
			$this->request_hooks->register('requests.before_request', array($this, '_content_type_callback'));
		}
		
		private static function autoloader($class) {
			if (strpos($class, 'Mailchimp') !== 0) {
				return ;
			}
			
			$file = str_replace('_', '/', $class);
			if (file_exists(dirname(__FILE__) . '/' . $file . '.php')) {
				require_once(dirname(__FILE__) . '.' . $file . '.php');
			}
		}
		
		public static function register_autoloader() {
			spl_autoload_register(array('Mailchimp', 'autoloader'));
		}
		
		public function _auth_callback($url, &$headers, &$data, &$type, &$options) {
			$headers['Authorization'] = 'Basic ' . base64_encode("user:$this->api_key");
		}
		
		public function _content_type_callback($url, &$headers, &$data, &$type, &$options) {
			if ($type === 'POST' || $type === 'PATCH') {
				$headers['Content-Type'] = 'application/json';
			}
		} 
		
		private function get_url($url) {
			return $this->base_url . $url;
		}
		
		private function members_url($list_id) {
			$url = str_replace(
				'<list_id>',
				$list_id,
				MAILCHIMP_MEMBERS_URL
			);
			
			return $this->get_url($url);
		}
		
		private function member_hash($email) {
			return md5(strtolower($email));
		}
		
		private function member_url($list_id, $email) {
			$url = str_replace(
				array('<list_id>', '<member_hash>'),
				array($list_id, $this->member_hash($email)),
				MAILCHIMP_MEMBER_URL);
			
			return $this->get_url($url);
		}
		
		private function prepare_member_data($email, $status, $merge_fields = array()) {
			return  json_encode(array(
				'email_address' => $email,
				'status' => $status,
				'merge_fields' => $merge_fields
			));
		}
		
		private function member_status($member) {
			return $member->status === 'subscribed' ? 
				Mailchimp_Member_Status::Subscribed : Mailchimp_Member_Status::Unsubscribed;
		}
		
		private function member($list_id, $email) {
			$response = Requests::get(
				$this->member_url($list_id, $email),
				array(),
				array('hooks' => $this->request_hooks));
			
			if ($response->status_code === 500 || $response->status_code === 503) {
				throw new Exception('Mailchimp_Error');
			}
			
			if ($response->status_code === 404) {
				return NULL;
			}
			
			return json_decode($response->body);
		}
		
		private function create_member($list_id, $email, $data) {
			$response = Requests::post(
				$this->members_url($list_id),
				array(),
				$data,
				array('hooks' => $this->request_hooks));
		}
		
		private function update_member($list_id, $email, $data) {
			$response = Requests::patch(
				$this->member_url($list_id, $email),
				array(),
				$data,
				array('hooks' => $this->request_hooks));
		}
		
		public function subscribe($list_id, $email, $first_name = '', $last_name = '') {
			$member = $this->member($list_id, $email);
			
			if ($member === NULL) {
				$member_data = 
					$this->prepare_member_data($email, 'subscribed', array(
						'FNAME' => $first_name,
						'LNAME' => $last_name
					));
				$this->create_member($list_id, $email, $member_data);
				
				return Mailchimp_Member_Status::Subscribed;
			}
			else if ($this->member_status($member) === Mailchimp_Member_Status::Unsubscribed) {
				$member_data = 
					$this->prepare_member_data($email, 'subscribed');
					
				$this->update_member($list_id, $email, $member_data);
				return Mailchimp_Member_Status::Subscribed;
			}
			else {				
				return Mailchimp_Member_Status::Already_Subscribed;
			} 
		}
		
		public function unsubscribe($list_id, $email) {
			$member = $this->member($list_id, $email);
			
			if ($member === NULL) {
				return Mailchimp_Member_Status::Not_Subscribed;
			}
			else if ($this->member_status($member) === Mailchimp_Member_Status::Subscribed) {
				$member_data = 
					$this->prepare_member_data($email, 'unsubscribed');
				
				$this->update_member($list_id, $email, $member_data);
			}
			
			return Mailchimp_Member_Status::Unsubscribed;
		}
	}
?>

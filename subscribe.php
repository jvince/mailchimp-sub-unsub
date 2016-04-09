<?php
	require_once('config.inc');
	require_once 'Mailchimp.php';
	
	$first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
	$last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
	$email = isset($_POST['email']) ? $_POST['email'] : '';
	
	$first_name = filter_var($first_name, FILTER_SANITIZE_STRING);
	$last_name = filter_var($last_name, FILTER_SANITIZE_STRING);
	$email = filter_var($email, FILTER_SANITIZE_EMAIL);
	$email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);
	
	$response = new stdClass();
	
	if ($email_valid === FALSE) {
		$response->status = 'Invalid Email';
		echo json_encode($response);
		return ;
	}
	
	$list_id = $mailchimp_config->list_id;
	$mailchimp = new Mailchimp($mailchimp_config->dc, $mailchimp_config->api_key);
	
	try {
		$status = $mailchimp->subscribe($list_id, $email, $first_name, $last_name);
		if ($status === Mailchimp_Member_Status::Subscribed) {
			$response->status = 'Subscribed';
		}
		else if ($status === Mailchimp_Member_Status::Already_Subscribed) {
			$response->status = 'Already Subscribed';	
		}
	}
	catch(Exception $e) {
		$response->status = $e;
	}
	finally {
		echo json_encode($response);
	}
?>

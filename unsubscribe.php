<?php
	require_once('config.inc');
	require_once 'Mailchimp.php';

	$email = isset($_POST['email']) ? $_POST['email'] : NULL;
	$email = filter_var($email, FILTER_SANITIZE_EMAIL);
	$email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);
	
	$response = new stdClass();
	$list_id = $mailchimp_config->list_id;
	$mailchimp = new Mailchimp($mailchimp_config->dc, $mailchimp_config->api_key);
	
	try {
		$status = $mailchimp->unsubscribe($list_id, $email);
		if ($status === Mailchimp_Member_Status::Not_Subscribed) {
			$response->status = 'Not Subscribed';
		}
		else if ($status === Mailchimp_Member_Status::Unsubscribed) {
			$response->status = 'Unsubscribed';	
		}
	}
	catch(Exception $e) {
		$response->status = $e;
	}
	finally {
		echo json_encode($response);
	}
?>
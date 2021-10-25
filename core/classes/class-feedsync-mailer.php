<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require CORE_PATH.'Exception.php';
require CORE_PATH.'PHPMailer.php';
require CORE_PATH.'SMTP.php';

class feedsync_mailer {

	private static $instance;

	public $mailer;

	function __construct() {

	}

	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof feedsync_mailer ) ) {
			self::$instance = new feedsync_mailer;
			self::$instance->init();
		}
		return self::$instance;
	}

	function init() {

		$this->mailer = new PHPMailer;
		$this->mailer->isHTML(true);

		$this->mailer->From 				= get_option('feedsync_debug_receiver');
		$this->mailer->FromName 			= "Feedsync Report";


		if( $this->is_smtp() ) {
			$this->setup_smtp();
		}

	}

	function setup_smtp() {

		$this->mailer->isSMTP();            
		$this->mailer->Host 				= get_option('feedsync_mailer_host');
		$this->mailer->SMTPSecure 			= 'tls';
		$this->mailer->SMTPAuth 			= true;                     
		$this->mailer->Username 			= get_option('feedsync_mailer_username');                 
		$this->mailer->Password 			= get_option('feedsync_mailer_password');                        
		$this->mailer->Port 				= get_option('feedsync_mailer_port');

	}

	function set($key,$value) {
		$this->mailer->{$key} = $value;
	}

	function is_smtp() {
		return get_option('feedsync_mail_mode') == 'smtp' ? true : false;
	}

	function send($to,$subject='',$body='') {

		$this->mailer->addAddress($to);
		$this->mailer->Subject 		= $subject;
		$this->mailer->Body 		= $body;
		return $this->mailer->send();
	}

	function enable_smtp_debug() {
		$this->mailer->SMTPDebug = 3;
	}

}

function init_feedsync_mailer() {
	global $feedsync_mailer;
	$feedsync_mailer = feedsync_mailer::instance();
	return $feedsync_mailer;
}

init_feedsync_mailer();

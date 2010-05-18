<?php defined('SYSPATH') OR die('No direct access allowed.');

// originally grabbed from: http://code.google.com/p/s7ncms/

class message_Core {
	public static function info($message = NULL, $uri = NULL) {
		if ($message !== NULL AND $uri !== NULL) {
			Session::instance()->set_flash('info_message', $message);
			url::redirect($uri);
		}
		
		return Session::instance()->get('info_message', FALSE);
	}
	
	public static function error($message = NULL, $uri = NULL) {
		if ($message !== NULL AND $uri !== NULL) {
			Session::instance()->set_flash('error_message', $message);
			url::redirect($uri);
		}
		
		return Session::instance()->get('error_message', FALSE);
	}
	
	public static function generate() {
		if($message = message::info()) {
			return "<div id=\"info_message\"><p>".$message."</p></div>";
		} else if($message = message::error()) {
			return "<div id=\"error_message\"><p>".$message."</p></div>";
		}
		
		return '';
	}
	
}
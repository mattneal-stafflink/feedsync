<?php
	
class feedsync_error_handler {

	private static $instance;

	function __construct() {
	}

	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof feedsync_error_handler ) ) {
			self::$instance = new feedsync_error_handler;
			self::$instance->init();
		}
		return self::$instance;
	}

	function init() {

		if( $this->is_debug_report_enabled() ) {
			register_shutdown_function( array($this, 'error_handler') );
		}
	}

	function is_debug_report_enabled() {
		return get_option('feedsync_enable_debug_report') == 'on' ? true : false;
	}

	function time_since_report() {
		return get_option('debug_report_sent');
	}

	function update_report_time() {
		update_option('debug_report_sent',time() );
	}

	function is_time_to_send() {
		return time() > $this->time_since_report() + 86400; // 86400 seconds in 24 hrs
	}

	function error_handler() {

	    $errfile = "unknown file";
	    $errstr  = "shutdown";
	    $errno   = E_CORE_ERROR;
	    $errline = 0;

	    $error = error_get_last();

	    if( $error !== NULL) {

	        $errno   = $error["type"];
	        $errfile = $error["file"];
	        $errline = $error["line"];
	        $errstr  = $error["message"];

	        $file           = ini_get('error_log');

	        $error_string   = "[" . date("d-M-Y H:i:s", $_SERVER['REQUEST_TIME']) . '] PHP ' . $errno . '::' . $errstr . " in " . $errfile . " on line " . $errline . "\r\n";

   			error_log($error_string, 3, $file);

	        if( !$this->is_time_to_send() )
			return;

	        $body = $this->error_details( $errno, $errstr, $errfile, $errline);

	        $this->send_debug_report($body);

	        $this->update_report_time();
	    }
	}

	function error_details( $errno, $errstr, $errfile, $errline ) {

	    $trace = print_r( debug_backtrace( false ), true );

	    $content = "
		    <table>
		        <tbody>
		            <tr>
		                <th>Error</th>
		                <td><pre>$errstr</pre></td>
		            </tr>
		            <tr>
		                <th>Errno</th>
		                <td><pre>$errno</pre></td>
		            </tr>
		            <tr>
		                <th>File</th>
		                <td>$errfile</td>
		            </tr>
		            <tr>
		                <th>Line</th>
		                <td>$errline</td>
		            </tr>
		            <tr>
		                <th>Trace</th>
		                <td><pre>$trace</pre></td>
		            </tr>
		        </tbody>
		    </table>";
	    return $content;
	}

	function send_debug_report($body) {

		global $feedsync_mailer;
		$to 	= get_option('feedsync_debug_receiver');
		$feedsync_mailer->send($to,'FeedSync Debug Report',$body);
	}
}


function init_feedsync_error_handler() {
	return feedsync_error_handler::instance();
}

init_feedsync_error_handler();
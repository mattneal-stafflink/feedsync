<?php
require_once('../config.php');
require_once('functions.php');
$page_now = 'settings';
$feedsync_hook->do_action('init');

get_header('settings');
	
	include(CORE_PATH.'classes/class-settings.php');
	
get_footer(); ?>
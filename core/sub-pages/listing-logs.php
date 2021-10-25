<?php
require_once('../../config.php');
require_once('../functions.php');
do_action('init');


$page = 'logs'; 
$results = feedsync_get_import_logs();
echo feedsync_render_log_table($results,$page);



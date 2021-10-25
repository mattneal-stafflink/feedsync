<?php
require_once('../../config.php');
require_once('../functions.php');
do_action('init');


$type = '';
$status = 'deleted';

$results = feedsync_list_listing_type( $type , $status );
$page = 'deleted';

echo display_export_data($results , $page );


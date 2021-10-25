<?php
require_once('../../config.php');
require_once('../functions.php');
do_action('init');


$type = '';
$status = 'leased';

$results = feedsync_list_listing_type( $type , $status );
$page = 'leased';

echo display_export_data($results , $page );


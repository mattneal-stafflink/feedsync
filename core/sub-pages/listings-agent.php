<?php
require_once('../../config.php');
require_once('../functions.php');
do_action('init');


$type = '';
$status = '';

$results = feedsync_list_listing_agent();
echo display_agents($results);


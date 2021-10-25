<!DOCTYPE html>
<html lang="en">
  <head>
	<title>FeedSync by Real Estate Connected</title>
	<?php
		enqueue_css( array('bootstrap.min.css','jumbotron-narrow.css') );
		enqueue_css( array('feedsync.css','prettyPhoto.css') );

		$googleapiurl = 'https://maps.googleapis.com/maps/api/js?v=3.exp';

		if( get_option('feedsync_google_api_key') != '' ) {
			$googleapiurl = $googleapiurl.'&key='.get_option('feedsync_google_api_key');
		}

		enqueue_js( array('jquery.min.js','jquery.prettyPhoto.js','tether.min.js','bootstrap.min.js','main.js') );

	?>
	<link rel="apple-touch-icon" sizes="57x57" href="<?php echo SITE_URL ?>/core/images/apple-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="<?php echo SITE_URL ?>/core/images/apple-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="<?php echo SITE_URL ?>/core/images/apple-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="<?php echo SITE_URL ?>/core/images/apple-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="<?php echo SITE_URL ?>/core/images/apple-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="<?php echo SITE_URL ?>/core/images/apple-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="<?php echo SITE_URL ?>/core/images/apple-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="<?php echo SITE_URL ?>/core/images/apple-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL ?>/core/images/apple-icon-180x180.png">
	<link rel="icon" type="image/png" sizes="192x192"  href="<?php echo SITE_URL ?>/core/images/android-icon-192x192.png">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL ?>/core/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="96x96" href="<?php echo SITE_URL ?>/core/images/favicon-96x96.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL ?>/core/images/favicon-16x16.png">
	<link rel="manifest" href="<?php echo SITE_URL ?>/core/images/manifest.json">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="<?php echo SITE_URL ?>/core/images/ms-icon-144x144.png">
	<meta name="theme-color" content="#ffffff">
	<?php do_action('feedsync_head'); ?>
  </head>

  <body>

   <div class="container">
   <div class="header">
		<?php feedsync_navigation() ?>
		<h3 class="text-muted"><a title="FeedSync" href="https://easypropertylistings.com.au/extensions/feedsync/">FeedSync</a></h3>
	</div>

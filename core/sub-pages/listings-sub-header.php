<!-- Listings Sub Navigation -->
	<ul class="nav nav-tabs" style="margin-bottom: 1em;">
		<li class="<?php echo $page_now == 'all' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'listings.php' ?>">All</a>
		</li>
		<li class="<?php echo $page_now == 'current' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-current.php' ?>">Current</a>
		</li>
		<li class="<?php echo $page_now == 'sold' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-sold.php' ?>">Sold</a>
		</li>
		<li class="<?php echo $page_now == 'leased' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-leased.php' ?>">Leased</a>
		</li>
		<li class="<?php echo $page_now == 'withdrawn' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-withdrawn.php' ?>">Withdrawn</a>
		</li>
		<li class="<?php echo $page_now == 'offmarket' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-offmarket.php' ?>">Off Market</a>
		</li>
		<li class="<?php echo $page_now == 'imported' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-imported.php' ?>">Imported</a>
		</li>
		<li class="<?php echo $page_now == 'agents' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-agent.php' ?>">Agents</a>
		</li>
		<?php
				$images = get_files_list(get_path('images'),"png|jpeg|gif|jpg|PNG|JPEG|GIF|JPG");
				if( count($images) > 0 ) {
		?>
		<li class="<?php echo $page_now == 'images' ? 'active':''; ?>">
			<a href="<?php echo CORE_URL.'sub-pages/listings-images.php' ?>">Images</a>
		</li>
		
		<?php } ?>
		<?php if( get_option('feedsync_enable_logging') == 'on' ) : ?>
			<li class="<?php echo $page_now == 'logs' ? 'active':''; ?>">
				<a href="<?php echo CORE_URL.'sub-pages/listing-logs.php' ?>">Logs</a>
			</li>
		<?php endif; ?>
	</ul>

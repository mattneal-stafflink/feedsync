<?php
	global $current_version, $build_number;
?>
		<div class="row feedsync-errors">
			<div class="col-md-12">
					<?php sitewide_notices(); ?>
			</div>
		</div>
		<div class="footer">
			<div class="feedsync-footer">
				<p><a href="https://easypropertylistings.com.au/extensions/feedsync/">FeedSync</a> v<?php echo $current_version;?> <span style="font-size: 0.7em"><?php echo $build_number; ?></span> | Developed by <a title="Real Estate Connected" href="http://www.realestateconnected.com.au/">Real Estate Connected</a> &copy; <?php echo date( 'Y' ); ?></p>
			</div>
		</div>
	</div>
  </body>
</html>

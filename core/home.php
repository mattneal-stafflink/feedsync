<?php
/**
 * Home Page template
 *
 * @package     FEEDSYNC
 * @subpackage  Templates/Home
 * @copyright   Copyright (c) 2019, Merv Barrett
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

// Exit if not active.
if ( ! defined('SITE_URL') ) {
	die;
}

get_header('home');
$xmls = get_input_xml();
?>

<div class="jumbotron">
	<?php echo feedsync_description_jumbotron(); ?>
</div>
<?php if(!empty($xmls)) { ?>
<div class="panel panel-default">
	<div class="panel-heading"><?php echo __( 'Files Ready For Processing', 'feedsync' ); ?></div>
	<table class="table">
		<?php
			foreach ($xmls as $my_glob) {
				$file_details = pathinfo($my_glob); ?>
				<tr>
					<td><a href="<?php echo INPUT_URL.$file_details['basename'] ?>"><?php echo $file_details['basename'] ?></a></td>
				</tr> <?php
			}
		?>
	</table>
</div>

<?php
}
echo get_footer();

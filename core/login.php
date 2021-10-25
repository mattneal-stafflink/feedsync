<?php
require_once('../config.php');
require_once('functions.php');
if( is_user_logged_in() ) {
    header('Location: '.SITE_URL);
    die;
}


/*
 * Login Page template
 *
*/

get_header('Login');
?>

<div class="jumbotron">
    <?php echo feedsync_login_jumbotron() ?>
</div>
<?php
echo get_footer(); 
?>

<?php

/************************************
* this illustrates how to activate
* a license key
*************************************/

function tba_feedsync_activate_license() {

    // listen for our activate button to be clicked
    if( isset( $_POST['edd_license_activate'] ) ) {

        // run a quick security check
        if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
            return; // get out if we didn't click the Activate button

        // retrieve the license from the database
        $license = trim( get_option( 'edd_sample_license_key' ) );


        // data to send in our API request
        $api_params = array(
            'edd_action'=> 'activate_license',
            'license'   => $license,
            'item_name' => urlencode( EDD_SAMPLE_ITEM_NAME ), // the name of our product in EDD
            'url'       => home_url()
        );

        // Call the custom API.
        $response = wp_remote_post( EDD_SAMPLE_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

        // make sure the response came back okay
        if ( is_fs_error( $response ) )
            return false;

        // decode the license data
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );

        // $license_data->license will be either "valid" or "invalid"

        update_option( 'edd_sample_license_status', $license_data->license );

    }
}
//add_action('admin_init', 'edd_sample_activate_license');


/**
 * Request the licence
 *
 * @since 2.2
 */
function feedsync_request_license( $edd_request = 'check_license' ) {

    $url = "https://easypropertylistings.com.au/?edd_action=$edd_request&item_name=FeedSync&license=".get_option('feedsync_license_key')."&url=".get_option('feedsync_license_url');
    $response = feedsync_remote_get( $url );
    $license = feedsync_remote_retrive_body( $response );
    $output = json_decode($license);
    $result = (array) json_decode($license);

    return $result;

    /**
        There are four request types:


        activate_license - Used to remotely activate a license key

        deactivate_license - Used to remotely deactivate a license key

        check_license - Used to remotely check if a license key is activated, valid, and not expired

        get_version - Used to remotely retrieve the latest version information for a product

    **/
}

/**
 * License Check
 *
 * @since 2.2
 */
function feedsync_license_check() {

    $result = feedsync_request_license( 'check_license' );

    if( $result['license'] == 'valid' ) {
        echo 'valid';
        // this license is still valid
    } else {
        echo 'invalid';
        // this license is no longer valid
    }
}

/**
 * License Get Version
 *
 * @since 2.2
 */
function feedsync_license_get_version() {

    $url = "https://easypropertylistings.com.au/?edd_action=get_version&item_name=FeedSync";
    $response = feedsync_remote_get( $url );
    $license = feedsync_remote_retrive_body( $response );
    $output = json_decode($license);
    $result = (array) json_decode($license);

    return $result;

}

/**
 * Return Formatted License Version
 *
 * @since 2.2
 */
function feedsync_license_the_version() {

    global $current_version;

    $result = feedsync_license_get_version();

    // New Version You Should Update
    if ( $result['new_version'] > $current_version ) { ?>
        <div class="alert alert-info">
            <strong>New Version Available!</strong> <?php echo $result['name'] , ' v', $result['new_version'] ?>, <a href="https://easypropertylistings.com.au/your-account/">Download it here</a>
        </div>

        <?php feedsync_license_the_changelog( $result ); ?>

    <?php
    }

    // You have the latest version
    else { ?>
        <div class="alert alert-success">
            <strong>You're Up To Date!</strong> You are running the latest version of FeedSync <?php echo $current_version ?>.
        </div>
    <?php
    }
}

/**
 * Pluralize words
 *
 * @since 2.2
 */
function feedsync_pluralize( $num, $singleWord, $pluralWord ) {
    return printf( ngettext( $singleWord, $pluralWord, $num ), $num );
}


//echo '<pre>';
//print_r( $result );
//echo '</pre>';


/**
 * Return Formatted Change Log
 *
 * @since 2.2
 */
function feedsync_license_the_changelog( $result = '' ) {

    if (  $result == '' )
        return;
    ?>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Changelog</h3>
        </div>
        <div class="panel-body">
            <?php
            $data = unserialize($result['sections']);
            echo $data['changelog'];
            ?>
        </div>
    </div>
<?php
}



function feedsync_license_activate() {

    $result = feedsync_request_license( 'activate_license' );





    // Activate License  'activate_license'
    if ( $result['license'] == 'valid' ) { ?>

        <div class="alert alert-success">
            <p><strong>Excellent your license is activated for this domain!</strong></p>
            <p>You have activated <?php echo $result['site_count']; ?> <?php feedsync_pluralize( $result['site_count'], 'site', 'sites' ); ?> and have <?php echo $result['activations_left'] ?> activations left for a total of <?php echo $result['license_limit'] ?> licenses. Your License is valid till <?php echo date('F j, Y, g:i a',strtotime($result['expires'])) ?></p>
        </div>
    <?php
    }
    else { ?>
        <div class="alert alert-danger">
            </strong>License Invalid or Missing</strong> Try checking your <a href="<?php echo CORE_URL.'settings.php#license'; ?>">settings</a> and try again.
        </div>
    <?php
    }

}


/**
 * License Validator
 *
 * @since 2.0
 */
function feedsync_license_validator( $edd_request = 'get_version') {
    global $current_version;

    $result = feedsync_request_license();

    // Activate License  'activate_license'
    if ( $edd_request == 'activate_license' ) {

        if ( $result['license'] == 'valid' ) { ?>

            <div class="alert alert-success">
                <strong>Excellent your license is activated for this domain!</strong> You have activated <?php echo $result['site_count'] ?> site and have <?php echo $result['activations_left'] ?> activations left for a total of <?php echo $result['license_limit'] ?> licenses.
            Your License is valid till <?php echo date('F j, Y, g:i a',strtotime($result['expires'])) ?>
            </div>

        <?php
        }
        else { ?>
            <div class="alert alert-danger">
                </strong>License Invalid or Missing</strong> Try checking your <a href="<?php echo CORE_URL.'settings.php#license'; ?>">settings</a> and try again.
            </div>
        <?php
        }
    } // END Activate






    // Check License  'activate_license'
    elseif ( $edd_request == 'check_license' ) {



        if ( $result['license'] == 'valid' ) { ?>

            <div class="alert alert-success">
            <strong>Excellent your license is activated for this website!</strong> You have activated <?php echo $result['site_count'] ?> site and have <?php echo $result['activations_left'] ?> activations left for a total of <?php echo $result['license_limit'] ?> licenses.
            Your License is valid till <?php echo date('F j, Y, g:i a',strtotime($result['expires'])) ?>
            </div>

        <?php
        }
        else{ ?>
            <div class="alert alert-danger">
            </strong>License Invalid or Missing</strong> Try checking your <a href="<?php echo CORE_URL.'settings.php#license'; ?>">settings</a> and try again.
            </div>
        <?php
        }
    } // END Check






    // Check Updates
    else {
        if ( $result['license_check'] == 'invalid' ){
            echo feedsync_license( $result['license_check'] );
        }

        elseif ( $result['new_version'] > $current_version && $result['license_check'] != 'invalid' ) { ?>
            <div class="alert alert-info">
                <strong>New Version Available!</strong> <?php echo $result['name'] , ' v', $result['new_version'] ?>, <a href="https://easypropertylistings.com.au/your-account/">Download it here</a>
            </div>

            <?php feedsync_license_the_changelog( $result ); ?>


        <?php
        }
        elseif ( $current_version >= $result['new_version'] ) { ?>
            <div class="alert alert-success">
            <strong>You're Up To Date!</strong> You are running the latest version of FeedSync.
            </div>
        <?php
        }
        else {


        } // END Check Updates
    }
}


/**
 * Site Without Valid License
 *
 * @since 2.0
 */
function feedsync_license( $check ) {

    if ( $check == 'invalid' ) { ?>
<div class="alert alert-warning">
    <p><strong>Bummer your application is not licensed or the key is invalid.</strong> Please enter a valid license key into <a href="<?php echo CORE_URL.'settings.php#license'; ?>">settings</a>. With a valid license you can download updates quickly and easily.</p>
</div>
<?php
    }
    elseif ( $check == 'invalid' ) { ?>
<div class="alert alert-warning">
    <p><strong>Bummer your application is not licensed or the key is invalid.</strong> Please enter a valid license key into <a href="<?php echo CORE_URL.'settings.php#license'; ?>">settings</a>. With a valid license you can download updates quickly and easily.</p>
</div>
<?php
    }

}
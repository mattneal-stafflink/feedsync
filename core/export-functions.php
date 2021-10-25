<?php

$feedsync_hook->add_action('feedsync_form_exporter','feedsync_form_exporter');

function feedsync_form_exporter() {

    $query = "SELECT * FROM ".fsdb()->listing." WHERE 1 = 1 ";


    $types      = array('rental','property','residential','commercial','land','rural','business','commercialLand','holidayRental');
    $type       = trim($_POST['listingtype']);

    $statuses       = array('leased','sold','withdrawn','current','offmarket','invalid','deleted');
    $status         = trim($_POST['listingstatus']);

    if( in_array($type,$types) ) {
        $query .= " AND type = '{$type}' ";
    }

    if( isset($_GET['recent']) ) {
        $query .= " AND STR_TO_DATE(mod_date,'%Y-%m-%d') = CURDATE() ";
    }

    if( in_array($status,$statuses) ) {
        $query .= " AND status = '{$status}' ";
    } elseif($status == 'all' ) {
        // Exclude Deleted and invalid
        $query .= " AND status NOT IN ('invalid') ";
    }else {
        $query .= " AND status NOT IN ('withdrawn','offmarket','invalid','deleted') ";
    }

    $results = fsdb()->get_results( trim( $query ) );
    export_data($results);

}

/*
    ** agents exporter form
    */
$feedsync_hook->add_action('feedsync_form_export_agents','feedsync_form_export_agents');

function feedsync_form_export_agents() {



    $query = "SELECT * FROM ".fsdb()->agent." WHERE 1 = 1 ";

    $results = fsdb()->get_results($query);
    export_data($results);

}

$feedsync_hook->add_action('feedsync_form_do_output','feedsync_form_do_output');

/**
 * Feedsync convert MS quotes to UTF-8 Quotes.
 *
 * @param  [type] $str [description]
 * @return [type]      [description]
 * @since 3.4.2
 */
function feedsync_convert_ms_quotes( $str ) {
    $chr_map = array(
        // Windows codepage 1252
        "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
        "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
        "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
        "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
        "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
        "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
        "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
        "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

        // Regular Unicode     // U+0022 quotation mark (")
                              // U+0027 apostrophe     (')
        "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
        "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
        "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
        "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
        "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
        "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
        "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
        "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
        "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
        "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
        "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
        "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
    );
    $chr = array_keys  ($chr_map); // but: for efficiency you should
    $rpl = array_values($chr_map); // pre-calculate these two arrays
    return str_replace($chr, $rpl, $str);
}

/**
 * Output Feedsync data
 * @return [type] [description]
 *
 * @since 3.4.0 transliterate MS Word quotes to UTF quotes
 * @since 3.4.2 replace iconv function to handle MS quotes.
 * @since 3.4.6 per_page & page attributes to filter do_output results.
 * @since 3.5 multiple agent_id supported as comma seperated

 */
function feedsync_form_do_output() {


    $type       = isset($_GET['type']) ? trim($_GET['type']) : '';

    $key_required = get_option('feedsync_enable_access_key');

    // settings not saved, get default
    if($key_required == false || $key_required == '') {
        $key_required = get_access_key_default_status();
    }

    if($key_required == 'on') {
        if( !isset($_GET['access_key']) ) {
            die( json_encode( array('status'    =>  'fail','message'    =>  'no access key provided')) );

        }

        if( isset($_GET['access_key']) && $_GET['access_key'] != get_option('feedsync_access_key')) {
            die( json_encode( array('status'    =>  'fail','message'    =>  'invalid access key')) );
        }
    }


    if($type == 'agents') {

        $query      = "SELECT * FROM ".fsdb()->agent." WHERE 1 = 1 ";

        $agent_id   = isset($_GET['agent_id']) ? trim( $_GET['agent_id'] ) : '';

        if( !empty( $agent_id ) ) {
            $agent_id = array_map( 'trim', explode( ',', $agent_id ) );
        
            if ( count( $agent_id ) ) {
                $query .= " AND ".fsdb()->agent.".office_id IN ('" . implode( "','", $agent_id ) . "') ";
            }
        }
    } else {

        $query      = "SELECT * FROM ".fsdb()->listing." WHERE 1 = 1 AND address != '' ";
        $types      = array('rental','property','residential','commercial','land','rural','business','commercialLand','holidayRental');
        $filters    = array('suburb','street','state','postcode','country');

        foreach($filters as $filter) {
            if( isset($_GET[$filter]) ){
                $query .= " AND ".fsdb()->listing.".{$filter} = '".fsdb()->escape($_GET[$filter])."' ";
            }
        }

        $statuses       = array('leased','sold','withdrawn','current','offmarket','invalid','deleted');
        $status         = isset($_GET['status']) ? trim($_GET['status']) : '';

        if( in_array($type,$types) ) {
        $query .= " AND ".fsdb()->listing.".type = '{$type}' ";
        }

        $agent_id   = isset($_GET['agent_id']) ? trim( $_GET['agent_id'] ) : '';

        if( !empty( $agent_id ) ) {
            $agent_id = array_map( 'trim', explode( ',', $agent_id ) );
        
            if ( count( $agent_id ) ) {
                $query .= " AND ".fsdb()->listing.".agent_id IN ('" . implode( "','", $agent_id ) . "') ";
            }
        }

        $listing_agent   = isset($_GET['listing_agent']) ? fsdb()->escape(trim($_GET['listing_agent'])): '';
        //$listing_agent   = ucwords( str_replace('-', ' ', $listing_agent) );
        if( $listing_agent != '' ) {
        $query .= " AND ".fsdb()->listing.".xml LIKE '%<agentUserName>{$listing_agent}</agentUserName>%' ";
        }

        $date   = isset($_GET['date']) ? fsdb()->escape(trim($_GET['date'])) : '';
        if( $date != '' ) {
        if($date == 'today') {
            $date = date ( 'Y-m-d' );
        }
        $query .= " AND DATE(`mod_date`) = '{$date}' ";
        }

        $days_back   = isset($_GET['days_back']) ? fsdb()->escape(trim($_GET['days_back'])) : '';
        if( intval($days_back) > 0 ) {
        $date_today = date ( 'Y-m-d' );
        $days_back  = date ( 'Y-m-d', strtotime('- '.$days_back.' days') );
        $query .= " AND DATE(`mod_date`) BETWEEN '{$days_back}' AND  '{$date_today}'";
        }

        // days before
        $days_before   = isset($_GET['days_before']) ? fsdb()->escape(trim($_GET['days_before'])) : '';
        if( intval($days_before) > 0 ) {
            $days_before  = date ( 'Y-m-d', strtotime('- '.$days_before.' days') );
            $query .= " AND DATE(`mod_date`) < '{$days_before}' ";
        }

        // days range
        $days_range   = isset($_GET['days_range']) ? fsdb()->escape(trim($_GET['days_range'])) : '';
        $days_range   = explode('-', $days_range );

        if( intval($days_range[0]) > 0 && intval($days_range[1]) > 0 ) {
        $range_end  = date ( 'Y-m-d', strtotime('- '.intval($days_range[0]).' days') );
        $range_start  = date ( 'Y-m-d', strtotime('- '.intval($days_range[1]).' days') );
        $query .= " AND DATE(`mod_date`) BETWEEN '{$range_start}' AND  '{$range_end}'";
        }

        $minutes_back   = isset($_GET['minutes_back']) ? fsdb()->escape(trim($_GET['minutes_back'])) : '';
        if( intval($minutes_back) > 0 ) {
            $now = date('Y-m-d H:i:s');
                $minutes_back  = date('Y-m-d H:i:s', strtotime('- '.$minutes_back.' minutes') );
            $query .= " AND `mod_date` BETWEEN '{$minutes_back}' AND  '{$now}'";
        }

        if( in_array($status,$statuses) ) {
            $query .= " AND status = '{$status}' ";
        }  elseif($status == 'all' ) {
            // exclude deleted and invalid
            $query .= " AND ".fsdb()->listing.".status NOT IN ('invalid') ";
        } else {
            $query .= " AND ".fsdb()->listing.".status NOT IN ('withdrawn','offmarket','invalid','deleted') ";
        }

        $records_per_page = (int) isset($_GET['per_page'])? $_GET['per_page'] : get_option('feedsync_pagination',false);
        $page = (int) isset( $_GET['page'] ) ? $_GET['page'] : 1;
        $limit_query = ' LIMIT ' . ( ($page - 1) * $records_per_page) . ', ' . $records_per_page . '';
        $query .= $limit_query;
    }
    
    $results = fsdb()->get_results($query);
    header("Content-type: text/xml");
    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE '.get_parent_element().' SYSTEM "http://reaxml.realestate.com.au/'.get_parent_element().'.dtd">
<'.get_parent_element().'>'."\n";

    if ( $results != '' ) {
        foreach($results as $listing) {
            echo feedsync_convert_ms_quotes( $listing->xml );
        }
    }
    echo '</'.get_parent_element().'>';
    $xml =  ob_get_clean();
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = FALSE;
    $dom->loadXML($xml);
    $dom->formatOutput = TRUE;
    echo $dom->saveXml();
    exit;
}

/**
 * { function_description }
 *
 * @param      string  $type    The type
 * @param      string  $status  The status
 *
 * @since 3.4  Fixed Rows count
 * @return     <type>  ( description_of_the_return_value )
 */
function feedsync_list_listing_type( $type = '', $status = '' ) {

    global $pagination;

    // instantiate the pagination object
    $pagination = new Zebra_Pagination();

    $query = "SELECT SQL_CALC_FOUND_ROWS * FROM ".fsdb()->listing." WHERE 1 = 1 ";

    $types      = array('rental','property','residential','commercial','land','rural','business','commercialLand','holidayRental');
    $statuses   = array('leased','sold','withdrawn','current','offmarket','deleted');

    if( in_array($type,$types) ) {
        $query .= " AND type = '{$type}' ";
    }

    if( in_array($status,$statuses) ) {
        $query .= " AND status = '{$status}' ";
    }  elseif($status == 'all' ) {
        // do nothing
    } else {
        $query .= " AND status NOT IN ('withdrawn','offmarket','deleted') ";
    }

    $orders = array('id','address','street','suburb','state','country','postcode','geocode','firstdate','status','type','mod_date','agent_id','unique_id','feedsync_unique_id');

    foreach($orders as $filter) {
        $filter_value   = isset($_GET[$filter]) ? fsdb()->escape(trim($_GET[$filter])): '';
        if( $filter_value != '' ) {
            $query .= " AND {$filter} LIKE '%{$filter_value}%' ";
        }
    }

    if( !empty($_GET['orderby']) && in_array($_GET['orderby'],$orders) ) {
        $order = (isset($_COOKIE['order']) && in_array($_COOKIE['order'], array('ASC','DESC')) ) ? $_COOKIE['order'] : 'DESC';
        $query .= " order by {$_GET['orderby']} $order";
    }

     // how many records should be displayed on a page?
    $records_per_page = get_option('feedsync_pagination',false);

    $limit_query = ' LIMIT ' . (($pagination->get_page() - 1) * $records_per_page) . ', ' . $records_per_page . '';
    $query .= $limit_query;

    $results    = fsdb()->get_results($query);
    $count_query = str_replace( 'SQL_CALC_FOUND_ROWS *', 'count(*) as `rows` ', $query);
    $count_query = str_replace( $limit_query, '', $count_query );
    $total_rows = fsdb()->get_results( $count_query );

    return array('results' =>  $results, 'total_rows'   =>  $total_rows);

}

function feedsync_get_import_logs() {

    global $pagination;

    // instantiate the pagination object
    $pagination = new Zebra_Pagination();

    $query = "SELECT * FROM ".fsdb()->logs." WHERE 1 = 1 order by id DESC ";

    return fsdb()->get_results($query);

}

/**
 * Renders log table for feedsync
 * @param  array $results
 *
 * @since 3.4.0 Fixed the incorrect log file extension, downloaded from log page
 * @since build number : 20-1020, changed extension of log file to txt, works with older log file with .log extension as well.
 */
function feedsync_render_log_table($results,$page) {
    global $pagination;
    ob_start();

    get_header('listings');
    get_listings_sub_header( $page );

    if( !empty($results) ) {

        // how many records should be displayed on a page?
        $records_per_page = get_option('feedsync_pagination',false);

        // set position of the next/previous page links
        $pagination->navigation_position(isset($_GET['navigation_position']) && in_array($_GET['navigation_position'], array('left', 'right')) ? $_GET['navigation_position'] : 'outside');

        // the number of total records is the number of records in the array
        $pagination->records(count($results));

        // records per page
        $pagination->records_per_page($records_per_page);

        // here's the magick: we need to display *only* the records for the current page
        $results = array_slice(
            $results,                                             //  from the original array we extract
            (($pagination->get_page() - 1) * $records_per_page),    //  starting with these records
            $records_per_page                                       //  this many records
        );

            $table = '<div class="listings-list-panel panel panel-default">
                        <table data-toggle="table" class="table table-hover">
                            <tr>
                                <th class="log-id" nowrap="">
                                    ID
                                </th>
                                <th class="log-file" nowrap="">
                                    File
                                </th>
                                <th us"class="log-action nowrap="">
                                    Action
                                </th>
                                <th us"class="log-stat nowrap="">
                                    Status
                                </th>
                                <th class="log-summary" nowrap="">
                                    Summary
                                </th>
                                <th class="log-download" nowrap="">
                                    Info & Details
                                </th>
                            </tr>';

            $sno = 1;
            foreach($results as $result) {

                $table .='
                <tr>
                    <td class="log-id" >'.$result->id.'</td>
                    <td class="log-file" >'.$result->file_name.'</td>
                    <td class="log-action" >'.$result->action.'</td>
                    <td class="log-status" >'.$result->status.'</td>
                    <td class="log-summary">'.nl2br($result->summary).'</td>
                    <td class="log-download">
                        <a download="'.basename($result->file_name, '.xml').'.txt" href="'.get_url('logs').$result->log_file.'"><span>Download</span>
                        </a>
                    </td>
                </tr>';

                $sno++;
            }

            $table .= '</table></div>';
            $table .= '<div class="row"> <div class="col-lg-12"> '.$pagination->render(true).' </div> </div>';
        //$table .= '</div>';
        echo $table;

    }
    get_footer();
    return ob_get_clean();
}

function feedsync_list_listing_agent( ) {

    global $pagination;
    $pagination = new Zebra_Pagination();
    $query = "SELECT * FROM ".fsdb()->agent." WHERE 1 = 1 ";

    return fsdb()->get_results($query);

}

function export_sorting_class($key) {
    $class = '';
    if( !empty($_GET['orderby']) && $_GET['orderby'] == $key ) {
        $class = ' feedsync-sorted ';
    }
    return $class;
}

function imported_files_sorting_class($key) {
    $class = '';
    if( isset($_GET['sort_what']) && $_GET['sort_what'] == $key ) {
        $class = ' feedsync-sorted ';
    }
    return $class;
}

function render_counter_block( $type='', $label='', $statues = [] ) {
    $total = get_listing_count( $type );
    if( $total <= 0) {
        return;
    } ?>
    <div class="listing-counter <?php echo $type; ?>-counter">
        <label>
            <?php echo $label; ?>
        </label>
        <?php
            foreach ($statues as $status_slug => $status_label ) {
                $count = get_listing_count( $type, $status_slug );
                if( $count <= 0) {
                    continue;
                } ?>
               <div class="residential-counter-<?php echo $status_slug; ?>">
                    <?php echo $count.' '.$status_label; ?>
                </div> <?php
            }
        ?>
    </div> <?php
}

function display_export_data($result_data , $page = 'all') {

    $results    = $result_data['results'];
    $total_rows = $result_data['total_rows'];


    ob_start();
    get_header('listings'); ?>

    <?php if( 'all' === $page ): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="listing-counter-widget">
                <?php
                    render_counter_block( 'residential', 'Residential', [ 'current' =>  'Current', 'sold'   =>  'Sold' ] );
                    render_counter_block( 'rental', 'Rental', [ 'current' =>  'Current', 'leased'   =>  'Leased' ] );
                    render_counter_block( 'land', 'Land', [ 'current' =>  'Current', 'sold'   =>  'Sold' ] );
                    render_counter_block( 'commercial', 'Commercial', [ 'current' =>  'Current', 'leased'   =>  'Leased', 'sold'   =>  'Sold' ] );
                    render_counter_block( 'rural', 'Rural', [ 'current' =>  'Current', 'sold'   =>  'Sold' ] );
                    render_counter_block( 'business', 'Business', [ 'current' =>  'Current', 'leased'   =>  'Leased', 'sold'   =>  'Sold' ] );
                    render_counter_block( 'commercialLand', 'Commercial Land', [ 'current' =>  'Current', 'leased'   =>  'Leased', 'sold'   =>  'Sold' ] );
                ?>
            </div>
        </div>
    </div> <?php
    endif;

    get_listings_sub_header( $page );

    $deleted_text = 'deleted' === $page ? 'Delete selected records?' : 'Mark as deleted?';
    $deleted_action = 'deleted' === $page ? 'delete_records_from_db' : 'delete_records';
    //$table = '<div class="row"> <div class="col-lg-12"> '.$pagination->render(true).' </div> </div>';
    ?>

    <form method="post">

        <div class="row" style="margin-bottom: 1em;">
            <div class="col-md-12">
                 <?php if( is_editing_allowed() ) : ?>
                <div class="col-md-5 pull-left no-padding">
                    <input type="hidden" name="action" value="<?php echo $deleted_action; ?>"/>
                    <button id="delete-records-btn" disabled class='btn btn-sm' type="submit" ><?php echo $deleted_text; ?></button>
                </div>
                <?php endif; ?>
                <div class="col-md-6 text-right pull-right">
                    <?php
                        $filters = array('id','address','street','suburb','state','country','postcode','geocode','firstdate','status','type','mod_date','agent_id','unique_id','feedsync_unique_id');

                        $filter_val = '';
                        echo '<select id="filter-type">';
                        foreach($filters as $filter) {
                            $label = ucwords( str_replace('id','ID ', str_replace('_',' ',$filter) ) );

                            $label = $filter == 'id' ? '#' : $label;
                            $filter_sel = '';

                            if( isset( $_GET[$filter] ) ){
                                $filter_sel = ' selected ' ;
                                $filter_val = fsdb()->escape(trim($_GET[$filter]));
                            }

                            echo '<option '.$filter_sel.' value="'.$filter.'" >'.$label.'</option>';
                        }
                        echo '</select>';

                    ?>
                    <input type="text"  id="filter-val" value="<?php echo $filter_val; ?>"/>
                    <button id="filter-listings" class='btn btn-sm btn-primary' type="submit" >Filter</button>
                </div>
            </div>
        </div>

        <?php

        $orderby = (isset($_COOKIE['order']) && in_array($_COOKIE['order'], array('ASC','DESC')) ) ? $_COOKIE['order'] : 'DESC';
        $orderclass = $orderby == 'DESC' ? 'sort-by-desc' : 'sort-by-asc';

        $table = "
            <div class='listings-list-panel panel panel-default'>
                <!--<div class='panel-heading'><span style='text-transform: capitalize;'>$page</span> Listings</div>-->
                     <table data-toggle='table' class='table table-hover'>
                        <thead>
                            <tr>";
                                if( is_editing_allowed() ){
                                    $table .= "<th class='cb'>
                                    <input  type=\"checkbox\" id=\"select_all_items\" />
                                    </th>";
                                }

                                $table .= "

                                <th nowrap class='id'>
                                    <a href='?orderby=id'><span>#</span>
                                    <i class=' ".export_sorting_class('id')." {$orderclass}' ></i>
                                    </a>
                                </th>
                                <th nowrap class='address'>
                                    <a href='?orderby=address'><span>Address</span>
                                    <i class=' ".export_sorting_class('address')." {$orderclass}' ></i>
                                    </a>
                                </th>
                                <th nowrap class='type'>
                                    <a href='?orderby=type'><span>Type</span><i class=' ".export_sorting_class('type')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='status'>
                                    <a href='?orderby=status'><span>Status</span><i class=' ".export_sorting_class('status')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='first-date'>
                                    <a href='?orderby=firstdate'><span>First Date</span><i class=' ".export_sorting_class('first_date')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='mod-date'>
                                    <a href='?orderby=mod_date'><span>Mod Date</span><i class=' ".export_sorting_class('mod_date')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='unique-id'>
                                    <a href='?orderby=unique_id'><span>ID</span><i class=' ".export_sorting_class('unique_id')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='agent-id'>
                                    <a href='?orderby=agent_id'><span>Agent</span><i class=' ".export_sorting_class('agent_id')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='geocode'>
                                    <a href='?orderby=geocode'><span>Map</span><i class=' ".export_sorting_class('geocode')." {$orderclass}' ></i></a>
                                </th>
                                <th nowrap class='details'>
                                    <a href='javascript:return false;'><span>Info</span></a>
                                </th>
                            </tr>
                        </thead>";

        if( !empty($results) ) {


            // how many records should be displayed on a page?
            $records_per_page = get_option('feedsync_pagination',false);

            global $pagination;

            // set position of the next/previous page links
            $pagination->navigation_position(isset($_GET['navigation_position']) && in_array($_GET['navigation_position'], array('left', 'right')) ? $_GET['navigation_position'] : 'outside');

            // the number of total records is the number of records in the array
            $pagination->records($total_rows[0]->rows);

            // records per page
            $pagination->records_per_page($records_per_page);

            $sno = 1;
            foreach($results as $result) {

                $map_img = get_option('site_url').'core/assets/images/feedsync-map-not-set.svg';
                $atts = ' class ="item-no-map"  ';
                if( !in_array($result->geocode, array('','NULL','-1,-1') ) ){
                     $map_img = get_option('site_url').'core/assets/images/feedsync-map.svg';
                     $atts = ' id="map-'.$result->unique_id.'" class="item-has-map" data-toggle="tooltip" data-html="true"  title="'.$result->geocode.'" data-placement="top" ';
                } else {
                    $atts = ' id="map-'.$result->unique_id.'" class="item-has-map" data-toggle="tooltip" data-html="true"  title="No coordinates set, click to process coordinates." data-placement="top" ';
                }

                $rated_class = strpos($result->xml, '<feedsyncFeaturedListing>') !== false ? 'rated' : '';
                $fav_title = strpos($result->xml, '<feedsyncFeaturedListing>') !== false ? 'Favourite Listing' : 'Mark Favourite';
                $editing_class = '';
                $table .='
                    <tr data-id="'.$result->id.'">';
                    if( is_editing_allowed() ){
                        $table .='
                        <td class="cb"><input type="checkbox" name="delete_items[]" value="'.$result->id.'" /></td>';
                        $editing_class = ' fs-editing-allowed ';
                    }
                        $table .='
                        <td data-id="'.$result->id.'" class="id">'.$result->id.'
                            <a href="javascript:void(0);" class="rating mark-fav" title="">
                                <span class="'.$rated_class.'">&#9734;</span>
                            </a>
                        </td>
                        <td class="address">'.$result->address.'</td>
                        <td class="type-'.$result->type.'">'.$result->type.'</td>
                        <td class="status fs-status-cell '.$editing_class.''.$result->status.'">';
                            if ( is_editing_allowed() ) {
                                $table .='<select style="display:none;" class="fs-status-dropdown">
                                <option '. (('current' == $result->status) ? ' selected ' : '') .' value="current">Current</option>';

                                if( 'rental' !== $result->type ) :
                                    $table .='<option '. (('sold' == $result->status) ? ' selected ' : '') .'value="sold">Sold</option>';
                                endif;
                                if( in_array( $result->type, ['rental', 'commercial', 'commercialLand'] ) ) :
                                    $table .='<option '. (('leased' == $result->status) ? ' selected ' : '') .'value="leased">Leased</option>';
                                endif;
                                $table .='<option '. (('withdrawn' == $result->status) ? ' selected ' : '') .'value="withdrawn">Withdrawn</option>
                                <option '. (('offmarket' == $result->status) ? ' selected ' : '') .'value="offmarket">OffMarket</option>
                                <option '. (('deleted' == $result->status) ? ' selected ' : '') .'value="deleted">Deleted</option>
                                </select>
                                <a href="#" style="display:none;" class="fs-change-status">Change</a>';
                            }

                        $table .= '<span class="fs-status-text">'.$result->status.'</span>
                        </td>
                        <td class="first-date">'.$result->firstdate.'</td>
                        <td class="mod-date">'.$result->mod_date.'</td>
                        <td class="unique-id">'.$result->unique_id.'</td>
                        <td class="agent-id">'.$result->agent_id.'</td>
                        <td class="geocode">
                            <a href="#"  '.$atts.'>
                                <img src="'.$map_img.'"/>
                            </a>
                        </td>
                        <td class="details">
                            <a target="_self" class="view-listing-images" href="'.CORE_URL.'sub-pages/listings-details.php?id='.$result->id.'">
                                <img src="'.get_option('site_url').'core/assets/images/feedsync-images-icon-v3.svg" />
                            </a>
                        </td>
                    </tr>';

                $sno++;
            }
        }

        $table .= '</table></div>';

        if( !empty($results) )
            $table .= '<div class="row"> <div class="col-lg-12"> '.$pagination->render(true).' </div> </div>';

    $table .= '</form>';
    echo $table;
    get_footer(); ?>
    <div id="confirm" class="modal fade" style="display: none">
      <div class="modal-body">
        Please confirm deletion
      </div>
      <div class="modal-footer">
        <button type="button" data-dismiss="modal" class="btn btn-primary" id="delete">Delete</button>
        <button type="button" data-dismiss="modal" class="btn">Cancel</button>
      </div>
    </div>
    <?php
    return ob_get_clean();

}

function display_agents($results) {

    global  $pagination;

    ob_start();
    get_header('listings');
    get_listings_sub_header( 'agents' );

    if( !empty($results) ) {

        // how many records should be displayed on a page?
        $records_per_page = defined('FEEDSYNC_PAGINATION') ? FEEDSYNC_PAGINATION : 1000;

        // set position of the next/previous page links
        $pagination->navigation_position(isset($_GET['navigation_position']) && in_array($_GET['navigation_position'], array('left', 'right')) ? $_GET['navigation_position'] : 'outside');

        // the number of total records is the number of records in the array
        $pagination->records(count($results));

        // records per page
        $pagination->records_per_page($records_per_page);

        // here's the magick: we need to display *only* the records for the current page
        $results = array_slice(
            $results,                                             //  from the original array we extract
            (($pagination->get_page() - 1) * $records_per_page),    //  starting with these records
            $records_per_page                                       //  this many records
        );

        ?>
        <form method="post"> <?php

        if( is_editing_allowed() ) : ?>
            <div class="row" style="margin-bottom: 1em;">
                <div class="col-md-12">
                    <input type="hidden" name="action" value="delete_agent_records"/>
                    <button id="delete-records-btn" disabled class='btn btn-sm' type="submit" >Delete selected records?</button>
                </div>
            </div>

            <?php endif;

        //$table = '<div class="row"> <div class="col-lg-12"> '.$pagination->render(true).' </div> </div>';
        $table = "

            <div class='listings-list-panel panel panel-default'>
                <!--<div class='panel-heading'><span style='text-transform: capitalize;'>Agents</span> Listings</div>-->
                     <table data-toggle='table' class='table table-hover'>
                        <thead>
                            <tr>";
                                if( is_editing_allowed() ){
                                    $table .= "<th class='cb'>
                                    <input  type=\"checkbox\" id=\"select_all_items\" />
                                    </th>";
                                }
                               $table .= "
                                <th class='id'>#</th>
                                <th class='agent_id'>Office ID</th>
                                <th class='name'>Name</th>
                                <th class='email'>Email</th>
                                <th class='telephone'>Telephone</th>
                            </tr>
                        </thead>";

        $sno = 1;
        foreach($results as $result) {

            $table .= '<tr>';
            if( is_editing_allowed() ){
                $table .='
                <td class="cb"><input type="checkbox" name="delete_items[]" value="'.$result->id.'" /></td>';
            }
            $table .='
                    <td class="id">'.$result->id.'</td>
                    <td class="agent_id">'.$result->office_id.'</td>
                    <td class="name '.$result->name.'">'.$result->name.'</td>
                    <td class="email '.$result->email.'">'.$result->email.'</td>
                    <td class="telephone">'.$result->telephone.'</td>
                </tr>';

            $sno++;
        }

        $table .= '</table></div>';
        $table .= '<div class="row"> <div class="col-lg-12"> '.$pagination->render(true).' </div> </div>';
        $table .= '</form>';

        echo $table;
        ?>
        <div id="confirm" class="modal fade" style="display: none">
          <div class="modal-body">
            Please confirm deletion
          </div>
          <div class="modal-footer">
            <button type="button" data-dismiss="modal" class="btn btn-primary" id="delete">Delete</button>
            <button type="button" data-dismiss="modal" class="btn">Cancel</button>
          </div>
        </div> <?php



    }

     get_footer();
     return ob_get_clean();

}
/**
 * [export_data description]
 * @param  [type] $results [description]
 * @return [type]          [description]
 * @since 3.4.2 replace iconv function to handle MS quotes.
 */
function export_data($results) {

    if( !empty($results) && is_user_logged_in() ) {

        header("Content-Type: application/force-download; name=\"export.xml");
        header("Content-type: text/xml");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename=\"export.xml");
        header("Expires: 0");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE '.get_parent_element().' SYSTEM "http://reaxml.realestate.com.au/'.get_parent_element().'.dtd">
<'.get_parent_element().'>'."\n";

        foreach($results as $listing) {
            echo feedsync_convert_ms_quotes( $listing->xml );
        }
        echo '</'.get_parent_element().'>';
        $xml =  ob_get_clean();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = FALSE;
        @$dom->loadXML($xml);
        $dom->formatOutput = TRUE;
        echo $dom->saveXml();
        exit;

    }
}

function get_parent_element() {
    $feedtype = get_option('feedtype');
    $path = 'propertyList';
    switch($feedtype) {

        case 'blm' :
            $path = 'propertyList';
        break;
        case 'reaxml' :
        case 'reaxml_fetch' :
            $path = 'propertyList';
        break;
        case 'expert_agent' :
           $path = 'properties';
        break;
        case 'rockend' :
           $path = 'Properties';
       case 'jupix' :
           $path = 'properties';
        break;

    }

    return $path;

}

function delete_records() {

    if( !empty($_POST['delete_items']) && is_editing_allowed() ){

        $ids = array_map('intval', $_POST['delete_items'] );
        $ids = join("','",$ids);
        $alllistings = fsdb()->get_results("SELECT * FROM ".fsdb()->listing." WHERE id IN ('$ids') ");

        if ( !empty( $alllistings ) ) {
            foreach ( $alllistings as $listing ) {
                $xmlFile = new DOMDocument('1.0', 'UTF-8');
                $xmlFile->preserveWhiteSpace = false;
                $xmlFile->loadXML($listing->xml);
                $xmlFile->formatOutput = true;
                $xpath = new DOMXPath($xmlFile);
                $item = $xmlFile->documentElement;

                $xmlFile->documentElement->setAttribute('status', 'deleted' );
                $newxml         = $xmlFile->saveXML($xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'status'    =>  'deleted'
                );

                $db_data    =   array_map(array( fsdb() ,'escape'), $db_data);

                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                status          = '{$db_data['status']}'
                                WHERE id        = '{$listing->id}'
                            ";
                fsdb()->query($query);
            }
        }
    }
}


$feedsync_hook->add_action('feedsync_form_delete_records','delete_records');

function delete_records_from_db() {

    if( !empty($_POST['delete_items']) && is_editing_allowed() ){

        $ids = array_map('intval', $_POST['delete_items'] );
        $ids = join("','",$ids);
        fsdb()->query("DELETE FROM ".fsdb()->listing." WHERE id IN ('$ids') ");
    }
}


$feedsync_hook->add_action('feedsync_form_delete_records_from_db','delete_records_from_db');

function delete_agent_records() {



    if( !empty($_POST['delete_items']) && is_editing_allowed() ){

        $ids = array_map('intval', $_POST['delete_items'] );
        $ids = join("','",$ids);
        fsdb()->query("DELETE FROM ".fsdb()->agent." WHERE id IN ('$ids') ");
    }
}


$feedsync_hook->add_action('feedsync_form_delete_agent_records','delete_agent_records');
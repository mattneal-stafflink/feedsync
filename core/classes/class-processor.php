<?php

class FEEDSYNC_PROCESSOR {


    /** handles db queries **/
    protected $db;

    /** xml file to be processed **/
    protected $xmlFile;

    /** path of xml file to be processed **/
    protected $path;

    /** xpath of object of xml file to be processed **/
    protected $xpath;

    /** total files pending to be processed **/
    protected $total_files;

    /** contains list of geocoded address in each iteration*/
    protected $geocoded_addreses_list;

    /** operating in cron mode ? **/
    protected $cron_mode = false;

    protected $log_report = array(
        'listings_created'    =>  0,
        'listings_updated'    =>  0,
        'listings_skipped'    =>  0
    );

    /**
     * Instantiate reaxml processor
     * @param $cron_mode boolean
     */
    function __construct($cron_mode = false){



        // setting cron mode to false so that it shows message & status for processing
        $cron_debug = ( isset($_GET['cron_debug']) && $_GET['cron_debug'] == 'true' ) ? true : false ;

        $this->cron_mode    = $cron_debug ? false : $cron_mode;
        $this->db           = fsdb();
        $this->init();
    }

    /**
     * Init the import process
     * @since  :      3.0.1
     * @return [type] [description]
     */
    function init() {

        $this->xmls         = $this->get_xmls();
        $this->total_files  = count($this->xmls);

        if( $this->get_xml_to_process() ){

            /** configuration **/
            $this->xmlFile = new DOMDocument('1.0','UTF-8');
            libxml_use_internal_errors(true);
            $this->xmlFile->formatOutput = true;
            $this->xmlFile->preserveWhiteSpace = false;
            $this->xmlFile->recover = TRUE;
            $this->xmlFile->load($this->path);
            $this->xpath = new DOMXPath($this->xmlFile);
            /** configuration - end **/

            /** check if xml is not empty & and is valid **/
            $this->handle_blank_xml();
            $this->handle_invalid_xml();

            /** extract dom elements and cache it as class property **/
            $this->dom_elements();

        } else {
            //@TODO return status message and die !
        }

    }

    /**
     * Resets class properties
     * @return [type]
     */
    function reset() {
        $this->elements = array();
    }

    function get_sub_path(){
        return '';
    }

    function get_path($folder) {
        $sub_path = $this->get_sub_path();

        switch($folder) {

            case 'input' :
                $path =  INPUT_PATH.$sub_path;
            break;

            case 'output' :
                $path =  OUTPUT_PATH.$sub_path;
            break;

            case 'processed' :
                $path =  PROCESSED_PATH.$sub_path;
            break;

            case 'temp' :
                $path =  TEMP_PATH.$sub_path;
            break;

            case 'zip' :
                $path =  ZIP_PATH.$sub_path;
            break;

            case 'images' :
                $path =  IMAGES_PATH.$sub_path;
            break;
        }

        return $path;
    }

    function get_url($folder) {
        $sub_path = $this->get_sub_path();

        switch($folder) {

            case 'input' :
                $path =  INPUT_URL.$sub_path;
            break;

            case 'output' :
                $path =  OUTPUT_URL.$sub_path;
            break;

            case 'procesessed' :
                $path =  PROCESSED_URL.$sub_path;
            break;

            case 'temp' :
                $path =  TEMP_URL.$sub_path;
            break;

            case 'zip' :
                $path =  ZIP_URL.$sub_path;
            break;

            case 'images' :
                $path =  IMAGES_URL.$sub_path;
            break;
        }
        return $path;
    }


    /**
     * @return [array] array of xml files to be processed
    */
    function get_xmls(){
        $files =  get_files_list(get_path('input'),"xml|XML");
        sort($files);
        return apply_filters( 'input_files_list', $files );
    }

    /** returns next xml file to process **/
    function get_xml_to_process(){

        if( !empty($this->xmls) ){
            $this->path =  current($this->xmls);
            return true;
        }

        return false;

    }

    /**
     * Process agents from a listing node
     *
     * @param  [domDocument Object]
     * @return [domDocument Object]
     *
     * @since 3.4.2 Fix for office_id not updating.
     */
    function process_agents( $item ) {

        $listing_agents = $this->get_nodes( $item, 'listingAgent' );

        if(!empty($listing_agents)) {
            foreach($listing_agents as $listing_agent) {

                /** init values **/
                $data_agent                 = array();
                $data_agent['agent_id']     = '';
                $data_agent['office_id']    = '';
                $data_agent['name']         = '';
                $data_agent['telephone']    = '';
                $data_agent['email']        = '';
                $data_agent['agent_id']     = $listing_agent->getAttribute('id');
                $data_agent['agent_id']     = $data_agent['agent_id'] == '' ? 1 : $data_agent['agent_id'];
                $data_agent['username']     = '';

                $listing_agent->setAttribute('id',$data_agent['agent_id']);

                if( $this->has_node($item,'agentID') ) {
                    $data_agent['office_id'] = $this->get_node_value($item,'agentID');

                    if( !$this->has_node($listing_agent,'office_id') ) {
                        $create_office_id       = $this->xmlFile->createElement('office_id', $data_agent['office_id']);
                        $listing_agent->appendChild($create_office_id);
                    }

                }

                if( $this->has_node($listing_agent,'name') ) {

                    $data_agent['name']     = $this->get_node_value($listing_agent,'name');
                    $agent_full_name        = explode(' ',$data_agent['name']);

                    if( !$this->has_node($listing_agent,'agentFirstName') ) {
                        $agent_first        = $agent_full_name[0];
                        $create_fname       = $this->xmlFile->createElement('agentFirstName',  htmlentities($agent_first) );
                        $listing_agent->appendChild($create_fname);
                    }
                    if( !$this->has_node($listing_agent,'agentLastName') ) {
                        $agent_last         = isset($agent_full_name[1]) ? $agent_full_name[1] : '';
                        $create_lname       = $this->xmlFile->createElement('agentLastName', htmlentities($agent_last) );
                        $listing_agent->appendChild($create_lname);
                    }
                    if( !$this->has_node($listing_agent,'agentUserName') ) {
                        $create_uname       = $this->xmlFile->createElement('agentUserName',sanitize_user_name($data_agent['name']));
                        $listing_agent->appendChild($create_uname);
                        $data_agent['username']         = sanitize_user_name($data_agent['name']);
                    } else {
                        $data_agent['username']         = $this->get_node_value($listing_agent,'agentUserName');
                    }

                }

                // Update email address.
                if( $this->has_node($listing_agent,'email') ) {
                    $data_agent['email'] = $this->get_node_value($listing_agent,'email');
                }

                // Update phone numbers.
                if( $this->has_node($listing_agent,'telephone') ) {
                    $tel_nos = array();
                    foreach($listing_agent->getElementsByTagName('telephone') as $agent_tel) {
                        $tel_nos[] =  $agent_tel->nodeValue;
                    }
                    $data_agent['telephone'] = implode(',',$tel_nos);
                }

                $data_agent['xml']  = $this->xmlFile->saveXML( $listing_agent);
                $data_agent         = array_map(array($this->db,'escape'), $data_agent);


                /** check if listing agent exists already **/
                $agent_exists = $this->db->get_row( $this->db->prepare("SELECT * FROM ".fsdb()->agent." where name = '%s'", $data_agent['name']) );

                if( empty($agent_exists) ) {

                    /** insert new data **/
                    $query = "INSERT INTO
                    ".fsdb()->agent." (office_id,name,telephone,email,xml,listing_agent_id,username)
                    VALUES (
                        '{$data_agent['office_id']}',
                        '{$data_agent['name']}',
                        '{$data_agent['telephone']}',
                        '{$data_agent['email']}',
                        '{$data_agent['xml']}',
                        '{$data_agent['agent_id']}',
                        '{$data_agent['username']}'
                    )";

                    $this->logger_log('Imported Agent : Name : '.$data_agent['name'].' ID : '.$data_agent['office_id']);
                    //print_exit($query);
                    $this->db->query($query);
                    //print_exit($this->db);
                } else {

                    /** update data **/
                    $query = "UPDATE ".fsdb()->agent." SET
                        office_id           = '{$data_agent['office_id']}',
                        telephone           = '{$data_agent['telephone']}',
                        email               =  '{$data_agent['email']}',
                        xml                 =  '{$data_agent['xml']}',
                        listing_agent_id    = '{$data_agent['agent_id']}',
                        username            = '{$data_agent['username']}'
                        WHERE name                =  '{$data_agent['name']}'
                    ";

                    $this->logger_log('Updated Agent : Name : '.$data_agent['name'].' ID : '.$data_agent['office_id']);
                    $this->db->query($query);
                }

            }
        }


    }

    /** handle invalid xml **/
    function handle_invalid_xml(){

        if($this->xmlFile->getElementsByTagName("RockendDataFeed")->length != 0) {
            try {
                if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {
                    if(!$this->cron_mode) {
                        die(
                            json_encode(
                                array(
                                    'status'    =>  'fail',
                                    'message'   =>  'Rockend File Format Detected and file skipped. Please ensure you select the RealEstate.com.au format as shown <a href="http://codex.easypropertylistings.com.au/article/40-rockend-rest-reaxml-setup-documentation">Here</a> when configuring Rockend.',
                                    'geocoded'  =>  '',
                                    'buffer'    =>  'processing'
                                )
                            )
                        );
                    }
                }
            } catch(Exception $e) {
                if(!$this->cron_mode) {
                    echo $e->getMessage(); die;
                }
            }
        }

    }

    /** handle blank xml **/
    function handle_blank_xml(){

        if($this->xmlFile->getElementsByTagName("propertyList")->length == 0) {
            try {
                if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {

                    $this->init_log();
                    $this->logger_log( basename($this->path)." Empty file, skipped");

                    if(!$this->cron_mode) {
                        die(
                            json_encode(
                                array(
                                    'status'    =>  'fail',
                                    'message'   =>  'empty file, skipped.',
                                    'geocoded'  =>  '',
                                    'buffer'    =>  'processing'
                                )
                            )
                        );
                    }
                }
            } catch(Exception $e) {
                if(!$cron_mode) {
                    echo $e->getMessage(); die;
                }
            }
        }


    }

    /**
     * parses dom elements to be procesessed in file
     * @return [type]
     */
    function dom_elements() {
        $this->elements = $this->xmlFile->documentElement;
        $this->item     = current($this->elements);
    }

    /**
     * check if parent node has a child node
     * @param  [domDocument Object]
     * @param  [string]
     * @return boolean
     */
    function has_node($item,$node){
        return $item->getElementsByTagName($node)->length == 0 ? false : true;
    }

    /**
     * get child nodes from parent nodes
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     */
    function get_nodes($item,$node){
        return $item->getElementsByTagName($node);
    }

    /**
     * Get first child node from parent node
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     */
    function get_first_node($item,$node){
        $nodes =$this->get_nodes($item,$node);
        return $nodes->item(0);

    }

    /**
     * add node to element
     * @param [domDocument Object]
     * @param [string]
     * @param [mixed]
     */
    function add_node($item,$node,$value){
        return $item->createElement($node, $value);
    }

    /**
     * get value of a node
     * @param  [domDocument Object]
     * @param  [string]
     * @return [mixed]
     */
    function get_node_value($item,$node){

        if( !$this->has_node($item,$node) ) {
            return '';
        }

        return !is_null($item) ?  $item->getElementsByTagName($node)->item(0)->nodeValue : '';
    }

    /**
     * set node value and returns it
     * @param [domDocument Object]
     * @param [string]
     * @param [domDocument Object]
     */
    function set_node_value($item,$node,$value){
        $item->getElementsByTagName($node)->item(0)->nodeValue = $value;
        return $item;
    }

    /**
     * check if force geocode mode is enabled
     * @return boolean
     */
    function force_geocode(){
        return get_option('force_geocode') == 'on' ? true : false;
    }

    /**
     * check if geocode mode is enabled
     * @return boolean
     */
    function geocode_enabled(){
        return get_option('geo_enabled') == 'on' ? true : false;
    }

    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @since 3.5 support for new zealand alternate reaxml version.
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        $address        = $this->get_first_node($item,'address');

        if( '' == $address->getAttribute('display') ) {
            $address->setAttribute('display', 'yes');
        }

        $this->address['streetnumber']      = $this->get_node_value($address,'streetNumber');
        $this->address['street']            = $this->get_node_value($address,'street');
        $this->address['suburb']            = $this->get_node_value($address,'suburb');
        $this->address['state']             = $this->get_node_value($address,'state');
        $this->address['postcode']          = $this->get_node_value($address,'postcode');

        if( $this->has_node($address,'lotNo') ) {
            // nz alt version
            $this->address['streetnumber']      = $this->get_node_value($address,'streetNo');
            $this->address['street']      = $this->get_node_value($address,'streetName');
            $this->address['area']      = $this->get_node_value($address,'area');
            $this->address['region']      = $this->get_node_value($address,'region');

        }

        if( $this->has_node($address,'country') ) {
            $this->address['country']        = $this->get_node_value($address,'country');
        } else {
            $this->address['country']        = "Australia";
        }

        $address_array = array_filter($this->address);

        $address_string =  $comma_seperated == true ? implode(", ", $address_array) : implode(" ", $address_array);

        $this->address['lotNumber']         = '';
        $this->address['subNumber']         = '';

        if( $this->has_node($address,'lotNumber') )
            $this->address['lotNumber']         = $this->get_node_value($address,'lotNumber');

        if( $this->has_node($address,'lotNo') )
            $this->address['lotNumber']         = $this->get_node_value($address,'lotNo');

        if( $this->has_node($address,'subNumber') )
            $this->address['subNumber']         = $this->get_node_value($address,'subNumber');

        if( $this->has_node($address,'unitNo') )
            $this->address['subNumber']         = $this->get_node_value($address,'unitNo');

        if( $this->address['lotNumber'] != '' && $this->address['streetnumber'] != ''){
            $address_string = $this->address['lotNumber'].'/'.$address_string;
        }

        if( $this->address['subNumber'] != '' && $this->address['streetnumber'] != ''){
            $address_string = $this->address['subNumber'].'/'.$address_string;
        }

        return $address_string;
    }

    /**
     * get address components parsed out from get_address
     * @param  [string]
     * @return [string]
     */
    function get_address_component($key){
        return isset($this->address[$key]) ? $this->address[$key] : '';
    }

    /**
     * update feedsync node of listing
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     * @since 3.4.2 Suppress permission error while saving error
     */
    function update_feedsync_node($item,$coord) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        if( ! $this->has_node($item,'feedsyncGeocode') ) {
            // if node not already exists, add it

            $element = $this->add_node($node_to_add,'feedsyncGeocode',$coord);
            $item->appendChild($element);
        } else {
            // if node already exists, just update the value
            $item = $this->set_node_value($item,'feedsyncGeocode',$coord);
        }

        /** in case or processing missing geocodes $this->xmlFile will be empty and also $this->path */
        if( !empty($this->xmlFile) && !empty($this->path) ) {

            if( is_writable( $this->path ) ) {
                // Suppress permission deined error
                @$this->xmlFile->save($this->path);
            }
            
        }
        $this->logger_log('updated feedsyncGeocode with value : '.$coord);
        // return item for further processing;
        return $item;

    }

    /**
    * Escape external links, escaping unwanted chars in URL
    * @since 3.5.0
    **/
    function escape_links( $item = null ) {

        $externals = $this->xpath->query('//externalLink[@href]');

        if( !empty($externals) ) {
            foreach ($externals as $k=>$external ) {
                $link_url = filter_var( $external->getAttribute('href'), FILTER_SANITIZE_URL );
                $link_text = filter_var( $external->getAttribute('title'), FILTER_SANITIZE_STRING );
                
                if( !empty($link_url) ) {
                    $externals->item($k)->setAttribute('href', $link_url );
                }

                if( !empty($link_text) ) {
                    $externals->item($k)->setAttribute('title', $link_text );
                }
            }
        }
        return $item;
    }

    /**
     * Add EPL nodes
     * @return [type]
     * 
     * @since 3.4.4 Permission check before saving XML.
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        /** process external link */
        $item = $this->escape_links( $item );
        
        /** Feedsync Unique ID ( Unique ID + Agent ID ) */

        $feedsync_unique_id = $this->get_node_value($item,'uniqueID');

        if( $this->has_node($item,'agentID') ) {

            $feedsync_unique_id = $this->get_node_value($item,'agentID').'-'.$this->get_node_value($item,'uniqueID');

        }

         // if node not already exists, add it
        if( ! $this->has_node($item,'feedsyncUniqueID') ) {

            // if node not already exists, add it

            $element = $this->add_node($node_to_add,'feedsyncUniqueID',$feedsync_unique_id);
            $item->appendChild($element);

        } else {
            // if node already exists, just update the value
            $item = $this->set_node_value($item,'feedsyncUniqueID',$feedsync_unique_id);
        }

        $this->logger_log('feedsyncUniqueID processed : '.$feedsync_unique_id);


        /** Feedsync Image Mod Time */
        $image_mod_date = false;

        $imgs = $this->get_nodes($item,'img');

        if( $imgs->length > 0 ) {

            foreach ($imgs as $k=>$img) {
                $this_mod_date = trim($img->getAttribute('modTime'));
                if(!empty($this_mod_date)) {
                    $this_mod_date = feedsync_format_date( $this_mod_date );
                    if($image_mod_date != false) {
                        $image_mod_date = strtotime($this_mod_date) >  strtotime($image_mod_date) ? $this_mod_date : $image_mod_date;
                    } else {
                        $image_mod_date = $this_mod_date;
                    }
                }
            }
            
            if($image_mod_date) {
                if( ! $this->has_node($item,'feedsyncImageModtime') ) {
                    // if node not already exists, add it

                    $element = $this->add_node($node_to_add,'feedsyncImageModtime',$image_mod_date);

                    $item->appendChild($element);
                } else {
                    // if node already exists, just update the value
                    $item = $this->set_node_value($item,'feedsyncImageModtime',$image_mod_date);
                }

                // update listing mod date if not changed & image mod date is greater than listing mod date.

                /** fetch listing **/
                $fs_unique_id = $this->get_node_value($item,'feedsyncUniqueID');
                
                if( !empty( $fs_unique_id ) ) :

                    $listing_from_db = $this->db->get_row( $this->db->prepare( "SELECT * FROM ".fsdb()->listing." where feedsync_unique_id = '%s'", $fs_unique_id ) );
                    
                    if( !empty( $listing_from_db ) ) :
                    /** Load existing xml from database */
                        $existing_xml = new DOMDocument('1.0', 'UTF-8');
                        $existing_xml->preserveWhiteSpace = FALSE;
                        $existing_xml->recover = TRUE;
                        $existing_xml->loadXML($listing_from_db->xml);
                        $existing_xml->formatOutput = TRUE;
                        $existing_listing = $existing_xml->getElementsByTagName('*');
                        $existing_listing_item  = $existing_listing->item(0);
                        
                        if( $this->has_node($existing_listing_item,'feedsyncImageModtime') ) {

                            $db_img_mod_time = $this->get_node_value($existing_listing_item,'feedsyncImageModtime');
                            $listing_mod_date                               = $item->getAttribute('modTime');
                            $listing_mod_date                               = feedsync_format_date( $listing_mod_date );
                            
                            if( ( strtotime($image_mod_date ) > strtotime( $db_img_mod_time ) ) ) {
                                
                                // image mod date has been updated.
                                // check if listing mod date has been updated or not
                                if( ( strtotime($listing_mod_date ) > strtotime( $listing_from_db->mod_date ) ) ) {
                                    // all good. Listing mod date has been updated as well..
                                } else {
                                    //update listing mod date so that it doesn't get skipped during import.
                                    
                                    $listing_mod_date = date("Y-m-d H:i:s",strtotime($listing_mod_date) + 5 );
                                    $this->logger_log('Increment mod date by 5 seconds as image mod date is changed but listing mod date is still same : '.$listing_mod_date );
                                    $item->setAttribute('modTime', $listing_mod_date );
                                }
                            
                            }
                        }
                    endif;
                endif;

                $this->logger_log('feedsyncImageModtime processed : '.$image_mod_date);
            }

        }

        /** Image order change */

        $item = $this->detect_image_order_change( $item );

        /** Sold Date  */

        if( $this->has_node($item,'soldDetails') ) {

            $sold_details           = $this->get_first_node($item,'soldDetails');
            if( $this->has_node($sold_details,'date') ){
                $sold_date              = $this->get_node_value($sold_details,'date');
                $sold_date              = feedsync_format_sold_date($sold_date);
                $this->set_node_value($sold_details,'date',$sold_date);
                $this->logger_log('soldDetails processed : '.$sold_date);
            }

        }

        /** price display */

        if( $this->has_node($item,'price') ) {

            $price_details           = $this->get_first_node($item,'price');
            $price_display           = $price_details->getAttribute('display');

            if( empty($price_display) ){
                $price_details->setAttribute('display','yes');
            }

        }

        if( $this->has_node($item,'rent') ) {

            $rent_details           = $this->get_first_node($item,'rent');
            $rent_display           = $rent_details->getAttribute('display');

            if( empty($rent_display) ){
                $rent_details->setAttribute('display','yes');
            }

        }
        
        $status         = $item->getAttribute('status');

        $publish_status = $this->get_publish_status($status);
        
        // add feedsyncPostStatus if its not there already
         if( ! $this->has_node($item,'feedsyncPostStatus') ) {
            $element    = $this->add_node($node_to_add,'feedsyncPostStatus',$publish_status);
            $item->appendChild($element);

        } else {
            // if node already exists, just update the value
            $item = $this->set_node_value($item,'feedsyncPostStatus',$publish_status);
        }


        if(!empty($this->xmlFile) ) {

            if( is_writable( $this->path ) )
                $this->xmlFile->save($this->path);
        }

        return $item;
    }

    public function detect_image_order_change( $item ) {

        /** Image order change detection */
        $fs_unique_id = $this->get_node_value($item,'feedsyncUniqueID');
                
        if( !empty( $fs_unique_id ) ) {

                $listing_from_db        = $this->db->get_row( $this->db->prepare( "SELECT * FROM ".fsdb()->listing." where feedsync_unique_id = '%s'", $fs_unique_id ) );
                $listing_mod_date       = $item->getAttribute('modTime');
                $listing_mod_date       = feedsync_format_date( $listing_mod_date );

                if( ( strtotime($listing_mod_date ) > strtotime( $listing_from_db->mod_date ) ) ) {
                        // Listing mod date is already updated.
                } else {

                        if( !empty( $listing_from_db ) ) :
                                
                                /** Load existing xml from database */
                                $existing_xml = new DOMDocument('1.0', 'UTF-8');
                                $existing_xml->preserveWhiteSpace = FALSE;
                                $existing_xml->recover = TRUE;
                                $existing_xml->loadXML($listing_from_db->xml);
                                $existing_xml->formatOutput = TRUE;
                                $existing_listing = $existing_xml->getElementsByTagName('*');
                                $existing_listing_item  = $existing_listing->item(0);

                                // existing images
                                $existing_imgs = $this->get_nodes($existing_listing_item,'img');

                                $existing_img_array = [];

                                if( $existing_imgs->length > 0 ) {
                        
                                        foreach ($existing_imgs as $k => $existing_img ) {
                                                $existing_img_array[ $existing_img->getAttribute('id') ] = $existing_img->getAttribute('url');
                                        }
                                }

                                // new images
                                $new_imgs = $this->get_nodes($item,'img');

                                $new_img_array = [];

                                if( $new_imgs->length > 0 ) {
                        
                                        foreach ($new_imgs as $k => $new_img ) {
                                                $new_img_array[ $new_img->getAttribute('id') ] = $new_img->getAttribute('url');
                                        }
                                }

                                if( $existing_img_array !== $new_img_array ) {

                                        $listing_mod_date = date("Y-m-d H:i:s",strtotime($listing_mod_date) + 5 );
                                        $this->logger_log('Increment mod date by 5 seconds as image order is changed but listing mod date is still same : '.$listing_mod_date );
                                        $item->setAttribute('modTime', $listing_mod_date );
                                        
                                }

                        endif;
                        //check & update listing mod date if image order is changed so that it doesn't get skipped during import.
                }

        }

        return $item;
    }

    function get_publish_status($status) {

        if( empty($status) )
            return 'publish';

        $key = 'reaxml_map_status_'.$status;
        
        return get_option($key);
    }

    function process_publish() {

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {
                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->recover = TRUE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);
                $listingXml     = $this->epl_nodes($this->xmlFile->documentElement);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}'
                                WHERE id        = '{$listing->id}'
                            ";

               $this->db->query($query);
            }
            update_option('reaxml_publish_processed', 'yes');
            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Status process completed, listing processing will follow...',
                        'buffer'    =>  'processing'
                    )
                )
            );

        }  else {

            update_option('reaxml_publish_processed', 'yes');
            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Status process completed, listing processing will follow...',
                        'buffer'    =>  'processing'
                    )
                )
            );
        }
    }

    /**
     * process image modtime
     * @return
     */
    function process_image_modtime(){

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {
                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);
                $listingXml     = $this->epl_nodes($this->xmlFile->documentElement);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}'
                                WHERE id        = '{$listing->id}'
                            ";

               $this->db->query($query);

                // die(
                //     json_encode(
                //         array(
                //             'status'    =>  'success',
                //             'message'   =>  'image mod time updated for listing ID : '.$listing->id.' <br>',
                //             'buffer'    =>  'processing'
                //         )
                //     )
                // );

            }

        }  else {

            /*die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Geocode process complete',
                        'buffer'    =>  'complete'
                    )
                )
            );*/
        }
    }

    /**
     * process missing feedsync_unique_id < 3.2
     * @since 3.2
     * @return
     */
    function upgrade_for_version_3_2(){

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 AND feedsync_unique_id  = '' ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {
                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);
                $listingXml     = $this->epl_nodes($this->xmlFile->documentElement);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'                   =>  $newxml,
                    'feedsync_unique_id'    =>  $this->get_node_value($this->xmlFile->documentElement,'feedsyncUniqueID')
                );

                if( $this->has_node($this->xmlFile->documentElement,'address') ) {
                    $db_data['address']     = $this->get_address($this->xmlFile->documentElement,true);
                }

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                address                         = '{$db_data['address']}',
                                xml                             = '{$db_data['xml']}',
                                feedsync_unique_id              = '{$db_data['feedsync_unique_id']}'
                                WHERE id                        = '{$listing->id}'
                            ";

               $this->db->query($query);

            }

        }  else {

            /*die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Listing upgrade process complete, checking for other upgrades',
                        'buffer'    =>  'processing'
                    )
                )
            );*/
        }
    }

    /**
     * process agents for extra columns < 3.2
     * @since 3.2
     * @return
     */
    function agent_upgrade_for_version_3_2(){

        $agents = $this->db->get_results("select * from ".fsdb()->agent." where 1=1 ");

        if( !empty( $agents ) ) {

            foreach($agents as $agent) {

                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->preserveWhiteSpace = FALSE;
                $dom->loadXML($agent->xml);
                $dom->formatOutput = TRUE;

                $db_data = array(
                    'listing_agent_id'  =>  '',
                    'username'          =>  ''
                );

                $agent_id_node = 'agentid';

                /** Save office_id
                if( $this->has_node($dom->documentElement,'office_id') ) {
                    $db_data['office_id']     = $this->get_node_value($dom->documentElement,'office_id');
                }
                */

                /** Save listing_agent_id */
                if( $this->has_node($dom->documentElement,$agent_id_node) ) {
                    $db_data['listing_agent_id']     = $this->get_node_value($dom->documentElement,$agent_id_node);
                }

                /** Save username */
                if( $this->has_node($dom->documentElement,'agentUserName') ) {
                    $db_data['username']     = $this->get_node_value($dom->documentElement,'agentUserName');
                }

                $db_data    =   array_map(array($this->db,'escape'), $db_data);

                $query = "UPDATE ".fsdb()->agent." SET
                                listing_agent_id                = '{$db_data['listing_agent_id']}',
                                username                        = '{$db_data['username']}'
                                WHERE id                        = '{$agent->id}'
                            ";

               $this->db->query($query);


            }
        } else {

        }
    }

    /**
     * attempts to fetch geocode from geocode node, if present
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode_from_geocode_node($item){

        $geocodenode            = $this->get_first_node($item,'Geocode');

        if( !$this->has_node($geocodenode,'Latitude') ) {

            // if coordinates are saved in Geocode node as value
            $this->coord              = $this->get_node_value($item,'Geocode');
        } else {

            // if coordinates are saved in childnodes
            $lat                = $this->get_node_value($geocodenode,'Latitude');
            $long               = $this->get_node_value($geocodenode,'Longitude');

            // make coordinates class wide available
            $this->coord        = $lat.','.$long;
        }

        $this->logger_log('Geocoded from geocode node : '.$this->coord);

        return $this->update_feedsync_node($item,$this->coord);
    }

    /**
     * attempts to fetch geocode from geocode node, if present
     * @param  [domDocument Object]
     * @return [domDocument Object]
     * 
     * @since 3.4.5 Fixed : Use google geocode if no geo fields in extra fields.
     */
    function geocode_from_extrafields_node($item){

        $extraFields = $this->get_nodes($item,'extraFields');

        if( $extraFields->length > 0 ) {

            $geoLat = ''; $geoLong = '';

            foreach ($extraFields as $k => $extraField) {

                $field_name = trim( $extraField->getAttribute('name') );

                if( $field_name == 'geoLat' ) {

                    $geoLat = trim( $extraField->getAttribute('value') );
                }
                 if( $field_name == 'geoLong' ) {

                    $geoLong = trim( $extraField->getAttribute('value') );
                }
            }

        }

        if( $geoLat != '' && $geoLong != '' ) {

            // make coordinates class wide available
            $this->coord        = $geoLat.','.$geoLong;

            $this->logger_log('Geocoded from extraFields node : '.$this->coord);

            return $this->update_feedsync_node($item,$this->coord);
        } else {
            $item = $this->geocode_from_google($item);
        }


       return $item;
    }

    /**
     * Geocode address from google geocode API
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode_from_google($item){

        $addr_readable  = $this->get_address($item);
        $addr           = urlencode(strtolower($addr_readable));
        $this->coord    = 'NULL';

        /** try to get lat & long from google **/
        if( trim($addr) != '') {

            $query_address  = trim($addr);
             $googleapiurl = "https://maps.google.com/maps/api/geocode/json?address=$query_address&sensor=false";
            if( get_option('feedsync_google_api_key') != '' ) {
                $googleapiurl = $googleapiurl.'&key='.get_option('feedsync_google_api_key');
            }

            $geo_response   = feedsync_remote_get( $googleapiurl );
            $geocode        = feedsync_remote_retrive_body( $geo_response );

            $this->geocoded_addreses_list .= "\n $query_address";

            $output         = json_decode($geocode);

            /** if address is validated & google returned response **/
            if( !empty($output->results) && $output->status == 'OK' ) {

                $lat            = $output->results[0]->geometry->location->lat;
                $long           = $output->results[0]->geometry->location->lng;
                $this->coord    = $lat.','.$long;
                $this->logger_log('Google Geocoded Result : '.$this->coord);
                return $this->update_feedsync_node($item,$this->coord);
            } else {
                /** cant geocode set to '' */

                return $this->update_feedsync_node($item,'');
            }
        }



    }

    /**
     * Geocode the listing item :)
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode($item,$process_missing = false){

        $this->geocoded_addreses_list = "\n";

        /** add feedsyncGeocode node if not already there or if force geocode mode is on **/

        if( !$this->has_node($item,'feedsyncGeocode') || $this->force_geocode() || $this->get_node_value($item,'feedsyncGeocode') == '' ) {

            // if item has geocode node, extract value from it and save it to feedsyncGeocode node
            if( $this->has_node($item,'Geocode') ) {


                $item = $this->geocode_from_geocode_node($item);

            }
            // if item has extraFields node, check for geoLat & geoLong
            else if( $this->has_node($item,'extraFields') ) {


                $item = $this->geocode_from_extrafields_node($item);

            } else {

                // if item doesnt have geocode node, geocode it
                if( $this->has_node($item,'address') && ( $this->geocode_enabled() || $this->force_geocode()  || $process_missing ) )
                    $item = $this->geocode_from_google($item);
            }
        } else {
           $this->coord = $this->get_node_value($item,'feedsyncGeocode');
        }

        return $item; // return processed item
    }

    /**
     * Regenerate coordinates for listing.
     * @return json mixed
     * @since 3.4.0
     */
    function regenerate_coordinates() {

        $id = intval($_POST['id']);

        if($id <= 0)
            return;

        $alllistings = fsdb()->get_results("select * from ".fsdb()->listing." where id = {$id} ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->coord    = '';
                $this->xpath    = new DOMXPath($this->xmlFile);
                $listingXml     = $this->geocode($this->xmlFile->documentElement,true);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'geocode'   =>  $this->coord
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                geocode         = '{$db_data['geocode']}'
                                WHERE id        = '{$listing->id}'
                            ";
                $this->db->query($query);

                if( !empty( $this->coord ) ) {
                    $geocode_status = json_encode(
                        array(
                            'status'        =>  'success',
                            'coordinates'   =>  $this->coord,
                            'buffer'        =>  'complete'
                        )
                    );
                } else {
                    $geocode_status = json_encode(
                        array(
                            'status'        =>  'fail',
                            'coordinates'   =>  $this->coord,
                            'buffer'        =>  'complete'
                        )
                    );
                }

                die($geocode_status);

            }

        }  else {

            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Geocode process complete',
                        'buffer'    =>  'complete'
                    )
                )
            );
        }
    }

    /**
     * Regenerate coordinates for listing.
     * @return json mixed
     * @since 3.4.0
     */
    function switch_status() {

        $id         = intval($_POST['id']);
        $new_status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);

        if($id <= 0)
            return;

        if( empty($new_status) )
            return;

        $alllistings = fsdb()->get_results("select * from ".fsdb()->listing." where id = {$id} ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath    = new DOMXPath($this->xmlFile);
                $this->xmlFile->documentElement->setAttribute('status', $new_status );
                
                $mod_date = date("Y-m-d H:i:s",strtotime($listing->mod_date) + 5 );
                $this->xmlFile->documentElement->setAttribute('modTime', $mod_date );
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'status'    =>  $new_status,
                    'mod_date'  =>  $mod_date
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                status         = '{$db_data['status']}',
                                mod_date        = '{$db_data['mod_date']}'
                                WHERE id        = '{$listing->id}'
                            ";
                

                if( $this->db->query($query) ) {
                    $query_status = json_encode(
                        array(
                            'status'        =>  'success',
                            'buffer'        =>  'complete'
                        )
                    );
                } else {
                    $query_status = json_encode(
                        array(
                            'status'        =>  'fail',
                            'buffer'        =>  'complete'
                        )
                    );
                }

                die($query_status);

            }

        }  else {

            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Status Changed successfully',
                        'buffer'    =>  'complete'
                    )
                )
            );
        }
    }

    /**
     * Checks geocode status prior to geocoding, avoiding null coordinates
     * @return [type] [description]
     *
     * @since 3.4.0
     */
    function check_geocode_status() {

        $addr = '1600 Amphitheatre Parkway, Mountain View, CA';
        $addr = str_replace(" ", "+", $addr);
        $googleapiurl = "https://maps.google.com/maps/api/geocode/json?address=$addr&sensor=false&key=".get_option('feedsync_google_api_key');
        $geo_response   = feedsync_remote_get( $googleapiurl );
        $geocode        = feedsync_remote_retrive_body( $geo_response );
        $result         = (array) json_decode($geocode);

        if( 'OK' == $result['status'] ) {
            return $result['status'];
        } else {
            return '<strong>'.$result['status'].'</strong> : '.$result['error_message'];
        }
    }

    /**
     * process missing geocode of existing listings in db
     * @return domDocument Object
     *
     * @since 3.4.0 Skip process, if geocode not working.
     * @since 3.4.2 Fixed cookie geocode cookie not working.
     */
    function process_missing_geocode(){

        // Make sure geocode works, before geocode
        if( !isset( $_COOKIE['fs_geoapi_status'] ) ) {

            $gecode_status = $this->check_geocode_status();

            if( 'OK' == $gecode_status ) {
                setcookie("fs_geoapi_status", 1);
                $_COOKIE['fs_geoapi_status'] = 1;
            } else {
                setcookie("fs_geoapi_status", 0);
                $_COOKIE['fs_geoapi_status'] = 0;
                setcookie("fs_geoapi_error", $gecode_status);
                $_COOKIE['fs_geoapi_error'] = $gecode_status;
            }

        }

        if( $_COOKIE['fs_geoapi_status'] != 1 ) {

            $api_fail_msg = 'Geocoding failed with following message <br>';
            $geocode_api_status = json_encode(
                array(
                    'status'        =>  'fail',
                    'message'       =>  $api_fail_msg.$_COOKIE['fs_geoapi_error'],
                    'buffer'        =>  'complete'
                )
            );

            die( $geocode_api_status );
        }

        // if force geocode mode is on delete all coordinates
        if( $this->force_geocode()  && (!isset($_COOKIE['coordinates_resetted']) || $_COOKIE['coordinates_resetted'] != 1) ) {
            $this->db->query("update feedsync set geocode = '' where 1 = 1");
            setcookie("coordinates_resetted", 1);
        }

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where geocode = '' AND address != '' LIMIT 1");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->coord    = '';
                $this->xpath    = new DOMXPath($this->xmlFile);
                $listingXml     = $this->geocode($this->xmlFile->documentElement,true);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'geocode'   =>  $this->coord
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                geocode         = '{$db_data['geocode']}'
                                WHERE id        = '{$listing->id}'
                            ";
                $this->db->query($query);

                $geocode_status = json_encode(
                                        array(
                                            'status'    =>  'success',
                                            'message'   =>  '<strong>Geocode Status</strong> <br>
                                                                    Address : <em>'.$this->get_address($this->xmlFile).'</em> <br>
                                                                    Geocode : <em>'.$this->coord.'</em> <br>',
                                            'buffer'    =>  'processing'
                                        )
                                    );

                die($geocode_status);

            }

        }  else {

            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Geocode process complete',
                        'buffer'    =>  'complete'
                    )
                )
            );
        }
    }

    /**
     * process missing listing agent of existing listings in db
     * @return domDocument Object
     */
    function process_missing_listing_agents() {

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing);

        if( !empty( $alllistings ) ) {

            foreach($alllistings as $listing) {

                $listingXml = new DOMDocument('1.0', 'UTF-8');
                $listingXml->preserveWhiteSpace = FALSE;
                $listingXml->loadXML($listing->xml);
                $listingXml->formatOutput = TRUE;
                $this->xmlFile = $listingXml;

                $this->process_agents($listingXml);
            }
        }
        die(
            json_encode(
                array(
                    'status'    =>  'success',
                    'message'   =>  'Listing Agents Update Completed!',
                    'buffer'    =>  'processed'
                )
            )
        );
    }


    /**
     * Processes image
     * @return [domDocument Object]
     */
    function process_image($item = null) {

        $imgs = $this->xpath->query('//img[@file]');

        if(!empty($imgs)) {
            foreach ($imgs as $k=>$img) {
                $img_name = trim($img->getAttribute('file'));
                if(!empty($img_name)) {
                    $img_name = basename($img_name);
                    $img_path = $this->get_url('images').$img_name;
                    $imgs->item($k)->setAttribute('url', $img_path);
                    $this->logger_log('Image processed : '.$img_path);
                }

            }
        }

        /** some formats have image as base 64 encoded format - process it */

        $imgs_encoded = $this->xpath->query('//img/base64Content');

        if(!empty($imgs_encoded)) {

            $img_name_prefix   = $this->get_node_value($item,'uniqueID');
            $img_name_prefix    = $img_name_prefix == '' ? uniqid() : $img_name_prefix;

            foreach ($imgs_encoded as $k=>$img_encoded) {

                $img_format     = trim( $img_encoded->parentNode->getAttribute('format') );
                $img_content    = $img_encoded->nodeValue;

                // decode content
                $img_content    = base64_decode($img_content);
                $img_name       = $img_name_prefix. '-'.$k . '.'. $img_format;
                $img_path       = $this->get_path('images') . $img_name;
                $img_url        = $this->get_url('images') . $img_name;
                file_put_contents($img_path, $img_content);
                $img_encoded->parentNode->setAttribute('url', $img_url);
                $img_encoded->parentNode->removeChild($img_encoded);

            }
        }

        /** some formats have image as base 64 encoded format - process it - End */

        $node_found = false;

        $imgs = $this->get_nodes($item,'img');

        if( $imgs->length > 1 ) {

            foreach ($imgs as $k=>$img) {
                if( $img->getAttribute('id') == 'm' ) {
                   $featured_img =  $img->parentNode->removeChild($img);
                   $node_found = true;
                   break;
                }

            }

            if( $node_found == true ) {
                $this->get_first_node($item,'img')->parentNode->insertBefore( $featured_img,$this->get_first_node($item,'img') );
            }
        }


        return $item;

    }

    function convert_html_chars( $source ) {

        $table = array(
            '&nbsp;'     => '&#160;',  # no-break space = non-breaking space, U+00A0 ISOnum
            '&iexcl;'    => '&#161;',  # inverted exclamation mark, U+00A1 ISOnum
            '&cent;'     => '&#162;',  # cent sign, U+00A2 ISOnum
            '&pound;'    => '&#163;',  # pound sign, U+00A3 ISOnum
            '&curren;'   => '&#164;',  # currency sign, U+00A4 ISOnum
            '&yen;'      => '&#165;',  # yen sign = yuan sign, U+00A5 ISOnum
            '&brvbar;'   => '&#166;',  # broken bar = broken vertical bar, U+00A6 ISOnum
            '&sect;'     => '&#167;',  # section sign, U+00A7 ISOnum
            '&uml;'      => '&#168;',  # diaeresis = spacing diaeresis, U+00A8 ISOdia
            '&copy;'     => '&#169;',  # copyright sign, U+00A9 ISOnum
            '&ordf;'     => '&#170;',  # feminine ordinal indicator, U+00AA ISOnum
            '&laquo;'    => '&#171;',  # left-pointing double angle quotation mark = left pointing guillemet, U+00AB ISOnum
            '&not;'      => '&#172;',  # not sign, U+00AC ISOnum
            '&shy;'      => '&#173;',  # soft hyphen = discretionary hyphen, U+00AD ISOnum
            '&reg;'      => '&#174;',  # registered sign = registered trade mark sign, U+00AE ISOnum
            '&macr;'     => '&#175;',  # macron = spacing macron = overline = APL overbar, U+00AF ISOdia
            '&deg;'      => '&#176;',  # degree sign, U+00B0 ISOnum
            '&plusmn;'   => '&#177;',  # plus-minus sign = plus-or-minus sign, U+00B1 ISOnum
            '&sup2;'     => '&#178;',  # superscript two = superscript digit two = squared, U+00B2 ISOnum
            '&sup3;'     => '&#179;',  # superscript three = superscript digit three = cubed, U+00B3 ISOnum
            '&acute;'    => '&#180;',  # acute accent = spacing acute, U+00B4 ISOdia
            '&micro;'    => '&#181;',  # micro sign, U+00B5 ISOnum
            '&para;'     => '&#182;',  # pilcrow sign = paragraph sign, U+00B6 ISOnum
            '&middot;'   => '&#183;',  # middle dot = Georgian comma = Greek middle dot, U+00B7 ISOnum
            '&cedil;'    => '&#184;',  # cedilla = spacing cedilla, U+00B8 ISOdia
            '&sup1;'     => '&#185;',  # superscript one = superscript digit one, U+00B9 ISOnum
            '&ordm;'     => '&#186;',  # masculine ordinal indicator, U+00BA ISOnum
            '&raquo;'    => '&#187;',  # right-pointing double angle quotation mark = right pointing guillemet, U+00BB ISOnum
            '&frac14;'   => '&#188;',  # vulgar fraction one quarter = fraction one quarter, U+00BC ISOnum
            '&frac12;'   => '&#189;',  # vulgar fraction one half = fraction one half, U+00BD ISOnum
            '&frac34;'   => '&#190;',  # vulgar fraction three quarters = fraction three quarters, U+00BE ISOnum
            '&iquest;'   => '&#191;',  # inverted question mark = turned question mark, U+00BF ISOnum
            '&Agrave;'   => '&#192;',  # latin capital letter A with grave = latin capital letter A grave, U+00C0 ISOlat1
            '&Aacute;'   => '&#193;',  # latin capital letter A with acute, U+00C1 ISOlat1
            '&Acirc;'    => '&#194;',  # latin capital letter A with circumflex, U+00C2 ISOlat1
            '&Atilde;'   => '&#195;',  # latin capital letter A with tilde, U+00C3 ISOlat1
            '&Auml;'     => '&#196;',  # latin capital letter A with diaeresis, U+00C4 ISOlat1
            '&Aring;'    => '&#197;',  # latin capital letter A with ring above = latin capital letter A ring, U+00C5 ISOlat1
            '&AElig;'    => '&#198;',  # latin capital letter AE = latin capital ligature AE, U+00C6 ISOlat1
            '&Ccedil;'   => '&#199;',  # latin capital letter C with cedilla, U+00C7 ISOlat1
            '&Egrave;'   => '&#200;',  # latin capital letter E with grave, U+00C8 ISOlat1
            '&Eacute;'   => '&#201;',  # latin capital letter E with acute, U+00C9 ISOlat1
            '&Ecirc;'    => '&#202;',  # latin capital letter E with circumflex, U+00CA ISOlat1
            '&Euml;'     => '&#203;',  # latin capital letter E with diaeresis, U+00CB ISOlat1
            '&Igrave;'   => '&#204;',  # latin capital letter I with grave, U+00CC ISOlat1
            '&Iacute;'   => '&#205;',  # latin capital letter I with acute, U+00CD ISOlat1
            '&Icirc;'    => '&#206;',  # latin capital letter I with circumflex, U+00CE ISOlat1
            '&Iuml;'     => '&#207;',  # latin capital letter I with diaeresis, U+00CF ISOlat1
            '&ETH;'      => '&#208;',  # latin capital letter ETH, U+00D0 ISOlat1
            '&Ntilde;'   => '&#209;',  # latin capital letter N with tilde, U+00D1 ISOlat1
            '&Ograve;'   => '&#210;',  # latin capital letter O with grave, U+00D2 ISOlat1
            '&Oacute;'   => '&#211;',  # latin capital letter O with acute, U+00D3 ISOlat1
            '&Ocirc;'    => '&#212;',  # latin capital letter O with circumflex, U+00D4 ISOlat1
            '&Otilde;'   => '&#213;',  # latin capital letter O with tilde, U+00D5 ISOlat1
            '&Ouml;'     => '&#214;',  # latin capital letter O with diaeresis, U+00D6 ISOlat1
            '&times;'    => '&#215;',  # multiplication sign, U+00D7 ISOnum
            '&Oslash;'   => '&#216;',  # latin capital letter O with stroke = latin capital letter O slash, U+00D8 ISOlat1
            '&Ugrave;'   => '&#217;',  # latin capital letter U with grave, U+00D9 ISOlat1
            '&Uacute;'   => '&#218;',  # latin capital letter U with acute, U+00DA ISOlat1
            '&Ucirc;'    => '&#219;',  # latin capital letter U with circumflex, U+00DB ISOlat1
            '&Uuml;'     => '&#220;',  # latin capital letter U with diaeresis, U+00DC ISOlat1
            '&Yacute;'   => '&#221;',  # latin capital letter Y with acute, U+00DD ISOlat1
            '&THORN;'    => '&#222;',  # latin capital letter THORN, U+00DE ISOlat1
            '&szlig;'    => '&#223;',  # latin small letter sharp s = ess-zed, U+00DF ISOlat1
            '&agrave;'   => '&#224;',  # latin small letter a with grave = latin small letter a grave, U+00E0 ISOlat1
            '&aacute;'   => '&#225;',  # latin small letter a with acute, U+00E1 ISOlat1
            '&acirc;'    => '&#226;',  # latin small letter a with circumflex, U+00E2 ISOlat1
            '&atilde;'   => '&#227;',  # latin small letter a with tilde, U+00E3 ISOlat1
            '&auml;'     => '&#228;',  # latin small letter a with diaeresis, U+00E4 ISOlat1
            '&aring;'    => '&#229;',  # latin small letter a with ring above = latin small letter a ring, U+00E5 ISOlat1
            '&aelig;'    => '&#230;',  # latin small letter ae = latin small ligature ae, U+00E6 ISOlat1
            '&ccedil;'   => '&#231;',  # latin small letter c with cedilla, U+00E7 ISOlat1
            '&egrave;'   => '&#232;',  # latin small letter e with grave, U+00E8 ISOlat1
            '&eacute;'   => '&#233;',  # latin small letter e with acute, U+00E9 ISOlat1
            '&ecirc;'    => '&#234;',  # latin small letter e with circumflex, U+00EA ISOlat1
            '&euml;'     => '&#235;',  # latin small letter e with diaeresis, U+00EB ISOlat1
            '&igrave;'   => '&#236;',  # latin small letter i with grave, U+00EC ISOlat1
            '&iacute;'   => '&#237;',  # latin small letter i with acute, U+00ED ISOlat1
            '&icirc;'    => '&#238;',  # latin small letter i with circumflex, U+00EE ISOlat1
            '&iuml;'     => '&#239;',  # latin small letter i with diaeresis, U+00EF ISOlat1
            '&eth;'      => '&#240;',  # latin small letter eth, U+00F0 ISOlat1
            '&ntilde;'   => '&#241;',  # latin small letter n with tilde, U+00F1 ISOlat1
            '&ograve;'   => '&#242;',  # latin small letter o with grave, U+00F2 ISOlat1
            '&oacute;'   => '&#243;',  # latin small letter o with acute, U+00F3 ISOlat1
            '&ocirc;'    => '&#244;',  # latin small letter o with circumflex, U+00F4 ISOlat1
            '&otilde;'   => '&#245;',  # latin small letter o with tilde, U+00F5 ISOlat1
            '&ouml;'     => '&#246;',  # latin small letter o with diaeresis, U+00F6 ISOlat1
            '&divide;'   => '&#247;',  # division sign, U+00F7 ISOnum
            '&oslash;'   => '&#248;',  # latin small letter o with stroke, = latin small letter o slash, U+00F8 ISOlat1
            '&ugrave;'   => '&#249;',  # latin small letter u with grave, U+00F9 ISOlat1
            '&uacute;'   => '&#250;',  # latin small letter u with acute, U+00FA ISOlat1
            '&ucirc;'    => '&#251;',  # latin small letter u with circumflex, U+00FB ISOlat1
            '&uuml;'     => '&#252;',  # latin small letter u with diaeresis, U+00FC ISOlat1
            '&yacute;'   => '&#253;',  # latin small letter y with acute, U+00FD ISOlat1
            '&thorn;'    => '&#254;',  # latin small letter thorn, U+00FE ISOlat1
            '&yuml;'     => '&#255;',  # latin small letter y with diaeresis, U+00FF ISOlat1
            '&fnof;'     => '&#402;',  # latin small f with hook = function = florin, U+0192 ISOtech
            '&Alpha;'    => '&#913;',  # greek capital letter alpha, U+0391
            '&Beta;'     => '&#914;',  # greek capital letter beta, U+0392
            '&Gamma;'    => '&#915;',  # greek capital letter gamma, U+0393 ISOgrk3
            '&Delta;'    => '&#916;',  # greek capital letter delta, U+0394 ISOgrk3
            '&Epsilon;'  => '&#917;',  # greek capital letter epsilon, U+0395
            '&Zeta;'     => '&#918;',  # greek capital letter zeta, U+0396
            '&Eta;'      => '&#919;',  # greek capital letter eta, U+0397
            '&Theta;'    => '&#920;',  # greek capital letter theta, U+0398 ISOgrk3
            '&Iota;'     => '&#921;',  # greek capital letter iota, U+0399
            '&Kappa;'    => '&#922;',  # greek capital letter kappa, U+039A
            '&Lambda;'   => '&#923;',  # greek capital letter lambda, U+039B ISOgrk3
            '&Mu;'       => '&#924;',  # greek capital letter mu, U+039C
            '&Nu;'       => '&#925;',  # greek capital letter nu, U+039D
            '&Xi;'       => '&#926;',  # greek capital letter xi, U+039E ISOgrk3
            '&Omicron;'  => '&#927;',  # greek capital letter omicron, U+039F
            '&Pi;'       => '&#928;',  # greek capital letter pi, U+03A0 ISOgrk3
            '&Rho;'      => '&#929;',  # greek capital letter rho, U+03A1
            '&Sigma;'    => '&#931;',  # greek capital letter sigma, U+03A3 ISOgrk3
            '&Tau;'      => '&#932;',  # greek capital letter tau, U+03A4
            '&Upsilon;'  => '&#933;',  # greek capital letter upsilon, U+03A5 ISOgrk3
            '&Phi;'      => '&#934;',  # greek capital letter phi, U+03A6 ISOgrk3
            '&Chi;'      => '&#935;',  # greek capital letter chi, U+03A7
            '&Psi;'      => '&#936;',  # greek capital letter psi, U+03A8 ISOgrk3
            '&Omega;'    => '&#937;',  # greek capital letter omega, U+03A9 ISOgrk3
            '&alpha;'    => '&#945;',  # greek small letter alpha, U+03B1 ISOgrk3
            '&beta;'     => '&#946;',  # greek small letter beta, U+03B2 ISOgrk3
            '&gamma;'    => '&#947;',  # greek small letter gamma, U+03B3 ISOgrk3
            '&delta;'    => '&#948;',  # greek small letter delta, U+03B4 ISOgrk3
            '&epsilon;'  => '&#949;',  # greek small letter epsilon, U+03B5 ISOgrk3
            '&zeta;'     => '&#950;',  # greek small letter zeta, U+03B6 ISOgrk3
            '&eta;'      => '&#951;',  # greek small letter eta, U+03B7 ISOgrk3
            '&theta;'    => '&#952;',  # greek small letter theta, U+03B8 ISOgrk3
            '&iota;'     => '&#953;',  # greek small letter iota, U+03B9 ISOgrk3
            '&kappa;'    => '&#954;',  # greek small letter kappa, U+03BA ISOgrk3
            '&lambda;'   => '&#955;',  # greek small letter lambda, U+03BB ISOgrk3
            '&mu;'       => '&#956;',  # greek small letter mu, U+03BC ISOgrk3
            '&nu;'       => '&#957;',  # greek small letter nu, U+03BD ISOgrk3
            '&xi;'       => '&#958;',  # greek small letter xi, U+03BE ISOgrk3
            '&omicron;'  => '&#959;',  # greek small letter omicron, U+03BF NEW
            '&pi;'       => '&#960;',  # greek small letter pi, U+03C0 ISOgrk3
            '&rho;'      => '&#961;',  # greek small letter rho, U+03C1 ISOgrk3
            '&sigmaf;'   => '&#962;',  # greek small letter final sigma, U+03C2 ISOgrk3
            '&sigma;'    => '&#963;',  # greek small letter sigma, U+03C3 ISOgrk3
            '&tau;'      => '&#964;',  # greek small letter tau, U+03C4 ISOgrk3
            '&upsilon;'  => '&#965;',  # greek small letter upsilon, U+03C5 ISOgrk3
            '&phi;'      => '&#966;',  # greek small letter phi, U+03C6 ISOgrk3
            '&chi;'      => '&#967;',  # greek small letter chi, U+03C7 ISOgrk3
            '&psi;'      => '&#968;',  # greek small letter psi, U+03C8 ISOgrk3
            '&omega;'    => '&#969;',  # greek small letter omega, U+03C9 ISOgrk3
            '&thetasym;' => '&#977;',  # greek small letter theta symbol, U+03D1 NEW
            '&upsih;'    => '&#978;',  # greek upsilon with hook symbol, U+03D2 NEW
            '&piv;'      => '&#982;',  # greek pi symbol, U+03D6 ISOgrk3
            '&bull;'     => '&#8226;', # bullet = black small circle, U+2022 ISOpub
            '&hellip;'   => '&#8230;', # horizontal ellipsis = three dot leader, U+2026 ISOpub
            '&prime;'    => '&#8242;', # prime = minutes = feet, U+2032 ISOtech
            '&Prime;'    => '&#8243;', # double prime = seconds = inches, U+2033 ISOtech
            '&oline;'    => '&#8254;', # overline = spacing overscore, U+203E NEW
            '&frasl;'    => '&#8260;', # fraction slash, U+2044 NEW
            '&weierp;'   => '&#8472;', # script capital P = power set = Weierstrass p, U+2118 ISOamso
            '&image;'    => '&#8465;', # blackletter capital I = imaginary part, U+2111 ISOamso
            '&real;'     => '&#8476;', # blackletter capital R = real part symbol, U+211C ISOamso
            '&trade;'    => '&#8482;', # trade mark sign, U+2122 ISOnum
            '&alefsym;'  => '&#8501;', # alef symbol = first transfinite cardinal, U+2135 NEW
            '&larr;'     => '&#8592;', # leftwards arrow, U+2190 ISOnum
            '&uarr;'     => '&#8593;', # upwards arrow, U+2191 ISOnum
            '&rarr;'     => '&#8594;', # rightwards arrow, U+2192 ISOnum
            '&darr;'     => '&#8595;', # downwards arrow, U+2193 ISOnum
            '&harr;'     => '&#8596;', # left right arrow, U+2194 ISOamsa
            '&crarr;'    => '&#8629;', # downwards arrow with corner leftwards = carriage return, U+21B5 NEW
            '&lArr;'     => '&#8656;', # leftwards double arrow, U+21D0 ISOtech
            '&uArr;'     => '&#8657;', # upwards double arrow, U+21D1 ISOamsa
            '&rArr;'     => '&#8658;', # rightwards double arrow, U+21D2 ISOtech
            '&dArr;'     => '&#8659;', # downwards double arrow, U+21D3 ISOamsa
            '&hArr;'     => '&#8660;', # left right double arrow, U+21D4 ISOamsa
            '&forall;'   => '&#8704;', # for all, U+2200 ISOtech
            '&part;'     => '&#8706;', # partial differential, U+2202 ISOtech
            '&exist;'    => '&#8707;', # there exists, U+2203 ISOtech
            '&empty;'    => '&#8709;', # empty set = null set = diameter, U+2205 ISOamso
            '&nabla;'    => '&#8711;', # nabla = backward difference, U+2207 ISOtech
            '&isin;'     => '&#8712;', # element of, U+2208 ISOtech
            '&notin;'    => '&#8713;', # not an element of, U+2209 ISOtech
            '&ni;'       => '&#8715;', # contains as member, U+220B ISOtech
            '&prod;'     => '&#8719;', # n-ary product = product sign, U+220F ISOamsb
            '&sum;'      => '&#8721;', # n-ary sumation, U+2211 ISOamsb
            '&minus;'    => '&#8722;', # minus sign, U+2212 ISOtech
            '&lowast;'   => '&#8727;', # asterisk operator, U+2217 ISOtech
            '&radic;'    => '&#8730;', # square root = radical sign, U+221A ISOtech
            '&prop;'     => '&#8733;', # proportional to, U+221D ISOtech
            '&infin;'    => '&#8734;', # infinity, U+221E ISOtech
            '&ang;'      => '&#8736;', # angle, U+2220 ISOamso
            '&and;'      => '&#8743;', # logical and = wedge, U+2227 ISOtech
            '&or;'       => '&#8744;', # logical or = vee, U+2228 ISOtech
            '&cap;'      => '&#8745;', # intersection = cap, U+2229 ISOtech
            '&cup;'      => '&#8746;', # union = cup, U+222A ISOtech
            '&int;'      => '&#8747;', # integral, U+222B ISOtech
            '&there4;'   => '&#8756;', # therefore, U+2234 ISOtech
            '&sim;'      => '&#8764;', # tilde operator = varies with = similar to, U+223C ISOtech
            '&cong;'     => '&#8773;', # approximately equal to, U+2245 ISOtech
            '&asymp;'    => '&#8776;', # almost equal to = asymptotic to, U+2248 ISOamsr
            '&ne;'       => '&#8800;', # not equal to, U+2260 ISOtech
            '&equiv;'    => '&#8801;', # identical to, U+2261 ISOtech
            '&le;'       => '&#8804;', # less-than or equal to, U+2264 ISOtech
            '&ge;'       => '&#8805;', # greater-than or equal to, U+2265 ISOtech
            '&sub;'      => '&#8834;', # subset of, U+2282 ISOtech
            '&sup;'      => '&#8835;', # superset of, U+2283 ISOtech
            '&nsub;'     => '&#8836;', # not a subset of, U+2284 ISOamsn
            '&sube;'     => '&#8838;', # subset of or equal to, U+2286 ISOtech
            '&supe;'     => '&#8839;', # superset of or equal to, U+2287 ISOtech
            '&oplus;'    => '&#8853;', # circled plus = direct sum, U+2295 ISOamsb
            '&otimes;'   => '&#8855;', # circled times = vector product, U+2297 ISOamsb
            '&perp;'     => '&#8869;', # up tack = orthogonal to = perpendicular, U+22A5 ISOtech
            '&sdot;'     => '&#8901;', # dot operator, U+22C5 ISOamsb
            '&lceil;'    => '&#8968;', # left ceiling = apl upstile, U+2308 ISOamsc
            '&rceil;'    => '&#8969;', # right ceiling, U+2309 ISOamsc
            '&lfloor;'   => '&#8970;', # left floor = apl downstile, U+230A ISOamsc
            '&rfloor;'   => '&#8971;', # right floor, U+230B ISOamsc
            '&lang;'     => '&#9001;', # left-pointing angle bracket = bra, U+2329 ISOtech
            '&rang;'     => '&#9002;', # right-pointing angle bracket = ket, U+232A ISOtech
            '&loz;'      => '&#9674;', # lozenge, U+25CA ISOpub
            '&spades;'   => '&#9824;', # black spade suit, U+2660 ISOpub
            '&clubs;'    => '&#9827;', # black club suit = shamrock, U+2663 ISOpub
            '&hearts;'   => '&#9829;', # black heart suit = valentine, U+2665 ISOpub
            '&diams;'    => '&#9830;', # black diamond suit, U+2666 ISOpub
            '&quot;'     => '&#34;',   # quotation mark = APL quote, U+0022 ISOnum
            '&amp;'      => '&#38;',   # ampersand, U+0026 ISOnum
            '&lt;'       => '&#60;',   # less-than sign, U+003C ISOnum
            '&gt;'       => '&#62;',   # greater-than sign, U+003E ISOnum
            '&OElig;'    => '&#338;',  # latin capital ligature OE, U+0152 ISOlat2
            '&oelig;'    => '&#339;',  # latin small ligature oe, U+0153 ISOlat2
            '&Scaron;'   => '&#352;',  # latin capital letter S with caron, U+0160 ISOlat2
            '&scaron;'   => '&#353;',  # latin small letter s with caron, U+0161 ISOlat2
            '&Yuml;'     => '&#376;',  # latin capital letter Y with diaeresis, U+0178 ISOlat2
            '&circ;'     => '&#710;',  # modifier letter circumflex accent, U+02C6 ISOpub
            '&tilde;'    => '&#732;',  # small tilde, U+02DC ISOdia
            '&ensp;'     => '&#8194;', # en space, U+2002 ISOpub
            '&emsp;'     => '&#8195;', # em space, U+2003 ISOpub
            '&thinsp;'   => '&#8201;', # thin space, U+2009 ISOpub
            '&zwnj;'     => '&#8204;', # zero width non-joiner, U+200C NEW RFC 2070
            '&zwj;'      => '&#8205;', # zero width joiner, U+200D NEW RFC 2070
            '&lrm;'      => '&#8206;', # left-to-right mark, U+200E NEW RFC 2070
            '&rlm;'      => '&#8207;', # right-to-left mark, U+200F NEW RFC 2070
            '&ndash;'    => '&#8211;', # en dash, U+2013 ISOpub
            '&mdash;'    => '&#8212;', # em dash, U+2014 ISOpub
            '&lsquo;'    => '&#8216;', # left single quotation mark, U+2018 ISOnum
            '&rsquo;'    => '&#8217;', # right single quotation mark, U+2019 ISOnum
            '&sbquo;'    => '&#8218;', # single low-9 quotation mark, U+201A NEW
            '&ldquo;'    => '&#8220;', # left double quotation mark, U+201C ISOnum
            '&rdquo;'    => '&#8221;', # right double quotation mark, U+201D ISOnum
            '&bdquo;'    => '&#8222;', # double low-9 quotation mark, U+201E NEW
            '&dagger;'   => '&#8224;', # dagger, U+2020 ISOpub
            '&Dagger;'   => '&#8225;', # double dagger, U+2021 ISOpub
            '&permil;'   => '&#8240;', # per mille sign, U+2030 ISOtech
            '&lsaquo;'   => '&#8249;', # single left-pointing angle quotation mark, U+2039 ISO proposed
            '&rsaquo;'   => '&#8250;', # single right-pointing angle quotation mark, U+203A ISO proposed
            '&euro;'     => '&#8364;', # euro sign, U+20AC NEW
            '&amp;#xD'   => ''         # Carriage return  
        );

        return strtr($source, $table );
    }

    function fix_encoding($html) {
       $html = str_replace('andbull;', '&bull;', $html);
       return $this->convert_html_chars( $html );
    }

    /**
     * insert listing in feedsync
     * @param  array
     * @return boolean
     */
    function insert_listing($db_data){

        $db_data        =   array_map(array($this,'fix_encoding'), $db_data);

        $query = "INSERT INTO
        ".fsdb()->listing." (type, agent_id,unique_id,feedsync_unique_id, mod_date, status,xml,firstdate,street,suburb,state,postcode,country,geocode,address)
        VALUES (
            '{$db_data['type']}',
            '{$db_data['agent_id']}',
            '{$db_data['unique_id']}',
            '{$db_data['feedsync_unique_id']}',
            '{$db_data['mod_date']}',
            '{$db_data['status']}',
            '{$db_data['xml']}',
            '{$db_data['firstdate']}',
            '{$db_data['street']}',
            '{$db_data['suburb']}',
            '{$db_data['state']}',
            '{$db_data['postcode']}',
            '{$db_data['country']}',
            '{$db_data['geocode']}',
            '{$db_data['address']}'
        )";
        return $this->db->query($query);
    }

    /**
     * update existing listing in feedsync database
     * @param  array
     * @return boolean
     */
    function update_listing($db_data){

        $db_data        =   array_map(array($this,'fix_encoding'), $db_data);

        $query = "UPDATE ".fsdb()->listing." SET
            type            = '{$db_data['type']}',
            mod_date        = '{$db_data['mod_date']}',
            status          = '{$db_data['status']}',
            xml             = '{$db_data['xml']}',
            geocode         = '{$db_data['geocode']}',
            address         = '{$db_data['address']}',
            street          = '{$db_data['street']}',
            suburb          = '{$db_data['suburb']}',
            postcode        = '{$db_data['postcode']}',
            country         = '{$db_data['country']}',
            unique_id       = '{$db_data['unique_id']}'
            WHERE feedsync_unique_id = '{$db_data['feedsync_unique_id']}'
        ";

        return $this->db->query($query);

    }

    /**
     * get initial values required to be updated / insereted for listing
     * @param  domDocument Object
     * @return domDocument Object
     */
    function get_initial_values($item){

        $db_data                                = array();
        $db_data['type']                        = $item->tagName;
        $db_data['feedsync_unique_id']          = $this->get_node_value($item,'feedsyncUniqueID');
        $db_data['unique_id']                   = $this->get_node_value($item,'uniqueID');
        $db_data['agent_id']                    = $this->get_node_value($item,'agentID');
        $mod_date                               = $item->getAttribute('modTime');
        $db_data['mod_date']                    = feedsync_format_date( $mod_date );
        $db_data['firstdate']                    = $this->convert_time_to_timezone( $db_data['mod_date'], get_option('feedsync_timezone') );

        $db_data['status']                      = strtolower($item->getAttribute('status'));
        $db_data['xml']                         = $this->xmlFile->saveXML( $item);
        $db_data['xml']                         = $db_data['xml'];
        $db_data['geocode']                     = $this->get_node_value($item,'feedsyncGeocode');
        $db_data['street']                      = '';
        $db_data['suburb']                      = '';
        $db_data['state']                       = '';
        $db_data['postcode']                    = '';
        $db_data['country']                     = '';
        $db_data['address']                     = '';
        if( $this->has_node($item,'address') ) {
            $db_data['address']     = $this->get_address($item,true);
            // address components are available only after get_address method call **/
            $db_data['street']      = $this->get_address_component('street');
            $db_data['suburb']      = $this->get_address_component('suburb');
            $db_data['state']       = $this->get_address_component('state');
            $db_data['postcode']    = $this->get_address_component('postcode');
            $db_data['country']     = $this->get_address_component('country');
        }

        return $db_data;

    }

    function logger_log($msg) {

        if( is_logging_enabled() && !is_null($this->logger) ) {
            $this->logger->log($msg);
        }
    }

    function complete_log() {

        if( !is_logging_enabled() )
            return;

        $summary    = "Processing completed : ".date('[Y-m-d H:i:s]').PHP_EOL;
        $summary    .= $this->log_report['listings_created']." listings created ".PHP_EOL;
        $summary    .= $this->log_report['listings_updated']." listings updated ".PHP_EOL;
        $summary    .= $this->log_report['listings_skipped']." listings skipped ".PHP_EOL;

        if( intval($this->log_id) > 0) {

            $query = "UPDATE ".fsdb()->logs." SET
                status              = 'complete',
                summary             = '{$summary}'
                WHERE id            = '{$this->log_id}'";

            $this->db->query($query);
        }

    }

    function force_limited_logs() {

        $max_logs = get_option('feedsync_max_logs');
        $max_logs = $max_logs == '' ? 1000 : intval($max_logs);
        $query = "SELECT * FROM
            ".fsdb()->logs." ORDER BY id DESC LIMIT $max_logs,99999999";

        $old_logs =  $this->db->get_results($query);

        if( !empty($old_logs) ) {
            foreach ($old_logs as $key => $details) {
                $log_path = get_path('logs').$details->log_file;

                if (file_exists($log_path)) {
                    @unlink($log_path);
                }

                $del_query = "DELETE FROM ".fsdb()->logs." WHERE id = {$details->id}";

                $this->db->get_results($del_query);
            }
        }
    }

    /**
     * @since build number : 20-1020, changed extension of log file to txt.
     */
    function init_log($file = '') {

        if( !is_logging_enabled() )
            return;

        if( defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) ) {
            $action = filter_var($_REQUEST['action'], FILTER_SANITIZE_STRING);
        } elseif( defined('DOING_CRON') && DOING_CRON && isset($_REQUEST['action']) ) {
            $action = filter_var($_REQUEST['action'], FILTER_SANITIZE_STRING);
        }else{
            $action = 'cron import';
        }

        if($file == '' && isset($this->path) ) {
            $file = basename($this->path);
        }

        $log_file = generate_uuid().'.txt';

        $log_file_path = get_path('logs').$log_file;

        if ( $file_handle = @fopen($log_file_path , 'a' ) ) {
            fwrite( $file_handle, '' );
            fclose( $file_handle );
            @chmod($log_file_path, 0644);
        }


        if($file_handle) {

            $query = "INSERT INTO
            ".fsdb()->logs." (file_name,action,status,summary,log_file)
            VALUES (
                '{$file}',
                '{$action}',
                'pending',
                '',
                '{$log_file}'
            )";

            $entry_created =  $this->db->query($query);

            /** log file is created && entry to db as well */
            if($entry_created) {

                $this->log_file     = $log_file;
                $this->log_id       = $this->db->insert_id;
                $this->logger       = new PHPLogger($log_file_path);
            }

            $this->force_limited_logs();
        }
    }

    /**
     * Converts a given date time from one timezone to other.
     *
     * @param      string  $time   The time
     * @param      string  $to     Timezone to
     * @param      string  $from   Timezone from
     *
     * @return     string  converted time.
     * @since      3.4
     */
    function convert_time_to_timezone( $time='', $to='', $from='Australia/Sydney') {
        $date = new DateTime( $time, new DateTimeZone( $from ) );
        $date->setTimezone(new DateTimeZone( $to ) );
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Move processed file from input to processed/{year}/{month} folder.
     *
     * @param      string  $path   path
     *
     * @return     bool  true if file moved successfully.
     * @since      3.5
     */
    function move_processed_file( $path ) {

        $name = basename($this->path);
        $abs_path = $this->get_path('processed');

        $year = date('Y');
        $month = date('m');

        $full_year_path     = $abs_path.DS.$year;
        $full_month_path    = $full_year_path.DS.$month.DS;


        // make sure year & month folder exists.
        if ( !file_exists( $full_year_path ) || !file_exists( $full_month_path ) ) {

            if( defined( 'FEEDSYNC_FOLDER_PERMISSIONS') ) {
                $permissions = FEEDSYNC_FOLDER_PERMISSIONS;
            }
        
            if( empty( $permissions ) ) {
                $permissions = 0755;
            }
        
            $paths = array( $full_year_path, $full_month_path );
        
            foreach($paths as $single_path) {
        
                if ( !file_exists( $single_path ) ) {
                    @mkdir($single_path, $permissions, true);
        
                } else {
                    @chmod($single_path, $permissions );
        
                }
            }
            
        }

        return @rename( $path, $full_month_path.$name );
        
    }

    /**
     * Import listings to database
     * @return json
     * @since 3.5 fix the issue with < & > in existing listing in db.
     */
    function import(){

        if( empty($this->elements) ) {
            die( json_encode(array('status' =>  'success', 'message'    =>  'All files have been processed.', 'buffer'   =>  'complete')) );
        }

        $this->init_log();

        $this->logger_log('==== File processing Initiated  : '.basename($this->path).' ===='.PHP_EOL);

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                $this->logger_log('---- Listing Process Initiated ----'.PHP_EOL);

                /** process agents **/
                $this->logger_log('Agent processing initiated...');
                $this->process_agents($item);
                $this->logger_log('Agent processing completed');

                /** process geocode **/
                $this->logger_log('Geocode processing initiated...');
                $this->geocode($item);
                $this->logger_log('Geocode processing completed');

                /** process image **/
                $this->logger_log('Image processing initiated...');
                $this->process_image($item);
                $this->logger_log('Image processing completed');

                /** add nodes */
                $this->logger_log('Node processing initiated...');
                $this->epl_nodes($item);
                $this->logger_log('Node processing completed');

                $db_data = $this->get_initial_values($item);

                $this->logger_log('Fetched initial values');

                /** check if listing exists already **/
                $exists = $this->db->get_row( $this->db->prepare( "SELECT * FROM ".fsdb()->listing." where feedsync_unique_id = '%s'", $db_data['feedsync_unique_id'] ) );

                if( !empty($exists) ) {

                    $this->logger_log('Duplicate listing detected with ID : '.$exists->id);

                    /** update if we have updated data **/
                    if(  strtotime($exists->mod_date) <= strtotime($db_data['mod_date']) ) {

                        $this->logger_log('Updated content detected. New Mode Time : '.$db_data['mod_date'].'. Old Mode Time : '.$exists->mod_date);

                        /** add firstDate node to xml if its already not there **/

                        if ( !$this->has_node($item,'firstDate')) {

                            $firstDateValue             = $this->add_node($this->xmlFile,'firstDate',$exists->firstdate);
                            $item->appendChild($firstDateValue);
                            $db_data['xml']             = $this->xmlFile->saveXML( $item);
                            $this->logger_log('First Date not found, added firstDate:'.$exists->firstdate);
                        }

                        if ( get_post_meta($exists->id,'fav_listing',true) == 'yes' ) {

                            if ( !$this->has_node($item,'feedsyncFeaturedListing')) {

                                $fav             = $this->add_node($this->xmlFile,'feedsyncFeaturedListing','yes');
                                $item->appendChild($fav);
                                $db_data['xml']             = $this->xmlFile->saveXML( $item);
                                $this->logger_log('Fav listing detected, Set as fav');
                            }
                        }

                        /** check if this xml has img node */
                        if ( !$this->has_node($item,'img') ) {

                            $this->logger_log('No images detected, checking in existing xml in DB');

                            /** remove blank images node from XML */
                            if ( $this->has_node($item,'images') ) {
                                $parent_image_node = $this->get_first_node($item,'images');
                                $parent_image_node->parentNode->removeChild($parent_image_node);
                                $this->logger_log('blank images node removed');
                            }

                            /** Load existing xml from database */
                            $existing_xml = new DOMDocument('1.0', 'UTF-8');
                            $existing_xml->preserveWhiteSpace = FALSE;
                            $existing_xml->loadXML($exists->xml);
                            $existing_xml->formatOutput = TRUE;

                            /** Import images node to XML if its not empty */
                            if( $this->has_node($existing_xml,'img') && $this->has_node($existing_xml,'images') ) {

                                /** fetch images node from xml in DB */
                                $backup_images = $this->get_first_node($existing_xml,'images');

                                $this->logger_log('images found in existing xml in DB');
                                $backup_images_node  = $this->xmlFile->importNode($backup_images, true);
                                $item->appendChild($backup_images_node);
                                $db_data['xml']             = $this->xmlFile->saveXML( $item);
                                $this->logger_log('Copied images from existing xml in DB');
                            }

                        }


                        /** dont update whole xml if address is missing **/
                        if ( !$this->has_node($item,'address') ) {

                            $this->logger_log('Address missing, skip updating whole xml');

                            $existing_xml = new DOMDocument('1.0', 'UTF-8');
                            libxml_use_internal_errors(true);
                            $existing_xml->preserveWhiteSpace = FALSE;
                            $exists->xml = html_entity_decode($exists->xml, ENT_QUOTES | ENT_HTML5);
                            $exists->xml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $exists->xml);
                            $existing_xml->recover = TRUE;
                            $existing_xml->loadXML( $exists->xml );
                            $existing_xml->formatOutput = TRUE;
                            $existing_listing = $existing_xml->getElementsByTagName('*');
                            $existing_listing->item(0)->setAttribute('modTime', $db_data['mod_date']);
                            $existing_listing->item(0)->setAttribute('status', $db_data['status']);
                            $db_data['xml'] = $existing_xml->saveXML($existing_listing->item(0));
                            $existing_listing_item  = $existing_listing->item(0);

                            if($existing_listing->item(0)->getElementsByTagName("address")->length != 0) {
                                $db_data['address']     = $this->get_address($existing_listing_item);
                            }

                            if( $this->has_node($existing_listing_item,'feedsyncGeocode'))
                                $db_data['geocode'] = $this->get_node_value($existing_listing_item,'feedsyncGeocode');

                        }

                        $db_data    =   array_map(array($this->db,'escape'), $db_data);

                        $this->update_listing($db_data);
                        $this->log_report['listings_updated']++;
                        $this->logger_log('---- Updated listing ----'.PHP_EOL);
                    } else {
                        $this->log_report['listings_skipped']++;
                        $this->logger_log('---- No Updated content, Skipping ---- '.PHP_EOL);
                    }

                } else {

                    $this->logger_log('New listing detected');

                    /** insert firstDate node **/

                    if ( !$this->has_node($item,'firstDate')) {

                        $firstDate      = $this->xmlFile->createElement('firstDate', $db_data['firstdate']);
                        $item->appendChild($firstDate);
                        $this->logger_log('First Date added firstDate:'.$db_data['mod_date']);
                    }

                    $db_data['xml'] = $this->xmlFile->saveXML( $item);
                    $db_data        =   array_map(array($this->db,'escape'), $db_data);

                    $this->insert_listing($db_data);
                    $this->log_report['listings_created']++;
                    $this->logger_log('---- Inserted listing ----'.PHP_EOL);
                }
            }

        }

        $this->logger_log('---- File processing complete ----');

        try {
            if( file_exists( $this->path ) && $this->move_processed_file( $this->path ) ) {

                $this->logger_log('---- File successfully moved to processed folder ----');
                $this->complete_log();

                if(!$this->cron_mode) {
                    die(
                        json_encode(
                            array(
                                'status'    =>  'success',
                                'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. Currently processing your files.',
                                'geocoded'  =>  $this->geocoded_addreses_list,
                                'buffer'    =>  'processing'
                            )
                        )
                    );

                } else {
                    echo json_encode(
                        array(
                            'status'    =>  'success',
                            'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. <br /> Currently processing your files, do not navigate away from this page.',
                            'geocoded'  =>  $this->geocoded_addreses_list,
                            'buffer'    =>  'processing'
                        )
                    );

                    $this->reset();
                    $this->init();
                    $this->import();
                }
            }
        } catch(Exception $e) {
            if(!$cron_mode) {
                $this->logger_log('---- file moving error : '.$e->getMessage() );
                echo $e->getMessage(); die;
            }
        }

    }


}


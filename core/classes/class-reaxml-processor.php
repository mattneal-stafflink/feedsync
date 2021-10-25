<?php
include('class-processor.php');
class REAXML_PROCESSOR extends FEEDSYNC_PROCESSOR {

    private $connection_id;

    /**
     * Init FTP sync process
     */
    function init_ftp_sync() {

        $host       = get_option('feedsync_reaxml_remote_host');
        $user       = get_option('feedsync_reaxml_remote_user');
        $pass       = get_option('feedsync_reaxml_remote_pass');
        $passive    = get_option('feedsync_reaxml_remote_passive');
        $ssl        = get_option('feedsync_reaxml_remote_is_ssl');
        $port       = get_option('feedsync_reaxml_remote_port');

        if( empty( $port ) ) {
            $port = 21;
        }

        if( empty( $host ) || empty( $user ) || empty( $pass ) ) {
            return;
        }

        $_from  = '.';
        $_to    = get_path('input');
        
        if( 'yes' === $ssl ) {
            $this->connection_id = ftp_ssl_connect( $host, $port );
        } else {
            $this->connection_id = ftp_connect( $host, $port );
        }
        
        $login_result = ftp_login($this->connection_id, $user, $pass );
        $passive = 'yes' === $passive ? true : false;
        ftp_pasv($this->connection_id, $passive );

        $this->ftp_sync( $_from, $_to );
    }

    /**
     * copy files from ftp to input folder recursively
     */
    function ftp_sync($_from = null, $_to = null) {

        if( is_null( $_to ) ) {
            $_to    = get_path('input');
        }
        
        if ( isset($_from) ) {
    
            if ( !ftp_chdir( $this->connection_id, $_from ) ) {
                die("Dir on FTP not found: $_from");
            }
        }
        
        $contents = ftp_mlsd($this->connection_id, '.');

        if( !empty( $contents ) ) {
            foreach ($contents as $p) {
            
                if( $p['type'] != 'dir' && $p['type'] != 'file' ) 
                    continue;
                
                $file = $p['name'];

                if( $p['type'] == 'file' && @ftp_get($this->connection_id, $_to.$file, $file, FTP_BINARY) ) {
                    
                    if( '1' == get_option( 'feedsync_reaxml_remote_move_files' ) ) {
                        @ftp_delete( $this->connection_id, $file);
                    }
                }
                elseif ( $p['type'] == 'dir' && @ftp_chdir($this->connection_id, $file) ) {
                   
                    $this->ftp_sync();
                    @ftp_chdir($this->connection_id, '..');
                }
            }
        }
        
    }

    /**
     * Import listings to database
     * @return json
     * @since 3.5 fix the issue with < & > in existing listing in db.
     */
    function import(){

        if( empty($this->elements) ) {

            if( 'reaxml_fetch' === get_option('feedtype') && (!isset($_COOKIE['reaxml_feed_fetched']) || $_COOKIE['reaxml_feed_fetched'] != 1) ) {

                $this->init_ftp_sync();
                // set cookie for 30 mins
                setcookie("reaxml_feed_fetched", 1, time()+60*30);
                die( json_encode(array('status' =>  'success', 'message'    =>  'Feed Fetched, Processing will follow...', 'buffer'   =>  'processing')) );
            }
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


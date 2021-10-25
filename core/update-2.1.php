<?php

/**
 * for version 2.1 to fil data in new cols street, suburb, state, country, postcode
 * @return [type] [description]
 */

function upgrade_version_2_1() {

    
    $alllistings = fsdb()->get_results("
            select * from ".fsdb()->listing."
            WHERE 1 = 1
            AND agent_id = ''
            AND street = ''
            AND suburb = ''
            AND state = ''
            AND postcode = ''
            AND country = ''
            AND address != ''
            LIMIT 1"
        );

    if( !empty( $alllistings ) ) {
        foreach($alllistings as $listing) {
            //print_exit($listing);
            $xmlFile = new DOMDocument;
            $xmlFile->preserveWhiteSpace = FALSE;
            $xmlFile->loadXML($listing->xml);
            $xmlFile->formatOutput = TRUE;
            $newxml = $listing->xml;
            $coord  = '';
            /** geocoding - start **/
            if($xmlFile->getElementsByTagName("address")->length != 0) {
                $addresses = $xmlFile->getElementsByTagName("address");
                foreach($addresses as $address) {

                    $db_data['agent_id']        = $address->parentNode->getElementsByTagName('agentID')->item(0)->nodeValue;
                    $db_data['street']          = $address->getElementsByTagName('street')->item(0)->nodeValue;
                    $db_data['suburb']          = $address->getElementsByTagName('suburb')->item(0)->nodeValue;
                    $db_data['state']           = $address->getElementsByTagName('state')->item(0)->nodeValue;
                    $db_data['postcode']        = $address->getElementsByTagName('postcode')->item(0)->nodeValue;
                    $db_data['country']         = $address->getElementsByTagName('country')->item(0)->nodeValue;

                    $streetNumber   = $address->getElementsByTagName('streetNumber')->item(0)->nodeValue;
                    $addr_readable =
                        $streetNumber. ",".$db_data['street'] .",".$db_data['suburb'] .",".$db_data['state'] .",".$db_data['postcode'] ;
                }

                $db_data    =   array_map(array(fsdb(),'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                agent_id            = '{$db_data['agent_id']}',
                                street          = '{$db_data['street']}',
                                suburb          = '{$db_data['suburb']}',
                                state           = '{$db_data['state']}',
                                postcode        = '{$db_data['postcode']}',
                                country         = '{$db_data['country']}'
                                WHERE id        = '{$listing->id}'
                            ";
                fsdb()->query($query);

            } else {
            	
            }
        }
    } 
}

upgrade_version_2_1();
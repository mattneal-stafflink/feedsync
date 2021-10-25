<?php

/**
 * DB upgrade for 3.0.5 feedsyncImageModtime node 
 * 
 */
function upgrade_version_3_0_5() {

    

    $feedtype = get_option('feedtype');
    switch($feedtype) {

        case 'blm' :
            $rex = new BLM_PROCESSOR();
            $rex->process_image_modtime();
        break;
        case 'reaxml' :
            $rex = new REAXML_PROCESSOR();
            $rex->process_image_modtime();
        break;
        case 'expert_agent' :
            $rex = new Expert_Agent_PROCESSOR();
            $rex->process_image_modtime();
        break;
        case 'eac' :
             $rex = new EAC_API(false);
            $rex->process_image_modtime();
        break;
        case 'rockend' :
            $rex = new ROCKEND_PROCESSOR();
            $rex->process_image_modtime();
        break;
        case 'jupix' :
            $rex = new JUPIX_PROCESSOR();
            $rex->process_image_modtime();
        break;

    }
}

upgrade_version_3_0_5();
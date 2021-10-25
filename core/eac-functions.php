<?php

function eac_node_mapping() {

	$feedsync_eac_lookups = array(

		'FEATURES' => array(
			'2' 				=> '3 phase power',
			'16' 				=> 'Gas Water Heater',
			'32' 				=> 'Electric Water Heater',
			'64' 				=> 'Solar Water Heater',
			'128' 				=> 'Skylight(s)',
			'256' 				=> 'Satellite Dish',
			'8192' 				=> 'Cable TV Available',
			'32768' 			=> 'Intercom',
			'65536' 			=> 'Fire Sprinkler System',
			'131072' 			=> 'Central Fire Alarm',
			'1048576' 			=> 'Security Fence/Perimeter',
			'2097152' 			=> 'Mezzanine Floor',
			'8388608' 			=> 'High Ceilings',
			'134217728' 		=> 'Container Access',
			'268435456'	 		=> 'Yard',
			'536870912' 		=> 'Double Glazed Windows',
			'1073741824' 		=> 'Smoke Detectors'
		),

		'HEATING' => array(
			'1' 		=> 	'None',
			'2' 		=> 'Gas',
			'4' 		=> 'Electric',
			'8' 		=> 'Bottled Gas',
			'16' 		=> 'Solar',
			'32' 		=> 'Oil',
			'64' 		=> 'Central Air Conditioning',
			'2048' 		=> 'More Than One Zone',
			'4096' 		=> 'Fireplace',
			'8192' 		=> 'Ducted Gas',
			'16384' 	=> 'Room Air Conditioner(s)',
			'32768' 	=> 'Ceiling Fans'
		),

		'OTHAREA' => array(
			'1' 		=> 'Laundry Area- Internal',
			'2' 		=> 'Laundry Area- External',
			'4' 		=> 'Formal Entry',
			'16' 		=> 'Attic',
			'32' 		=> 'Basement',
			'64' 		=> 'Wine Cellar',
			'128' 		=> 'Separate Living Unit',
			'1024' 		=> 'Walk-in-Pantry',
			'2048'		=> 'Loft',
			'8192' 		=> 'Solarium',
			'16384' 	=> 'Balcony(s)',
			'65536' 	=> 'Pergola',
			'131072' 	=> 'Workshop',
			'262144' 	=> 'Courtyard'
		),

		'OTHROOM' => array(
			'1' 		=> 'Lounge/Living',
			'2' 		=> 'Library',
			'4' 		=> 'Den or Study',
			'8' 		=> 'Office',
			'16' 		=> 'Sun Room',
			'32' 		=> 'Rumpus Room',
			'64' 		=> 'Workshop',
			'128' 		=> 'Utility Room',
			'256' 		=> 'Family Room',
			'512' 		=> 'Dining Room',
			'1024' 		=> 'Sleepout'
		),

		'COMPLX_FEAT' => array(),

		// Unknown
		'FLCOVER' => array(),

		'LIST_INCLUDES' => array(),

		'LOCAL_AMEN' => array()

	);

	return $feedsync_eac_lookups;

}
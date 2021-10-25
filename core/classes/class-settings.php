<?php
class FEEDSYNC_SETTINGS {

	public $notices = array();

	function __construct() {

		$this->render();
	}

	function render() {

		$sections = $this->get_sections();
		 ?>

		<ul class="nav nav-tabs">
			<?php
				$first = key($sections);
				foreach( $sections as $section_id	=>	 $section ) {

					if(
						($section_id == 'expert_agent' && get_option('feedtype') != 'expert_agent') ||
						($section_id == 'eac-api' && get_option('feedtype') != 'eac') ||
						($section_id == 'jupix' && get_option('feedtype') != 'jupix') ||
						($section_id == 'blm' && get_option('feedtype') != 'blm')
					) {
						continue;
					}

					$class = $section_id == $first ? 'active' : '';
					echo '<li class="'.$class.'"><a data-toggle="tab" href="#'.$section_id.'">'.$section['label'].'</a></li>';
				}
			?>
		</ul>
		<div class="jumbotron jumbotron-left">
			<form class="form-feedsync-settings" id="form-feedsync-settings" method="post">
				<fieldset>


					<div class="tab-content">

						<?php
							foreach( $sections as $section_id	=>	 $section ) {

								if(
									($section_id == 'expert_agent' && get_option('feedtype') != 'expert_agent') ||
									($section_id == 'eac-api' && get_option('feedtype') != 'eac') ||
									($section_id == 'jupix' && get_option('feedtype') != 'jupix')
								) {
									continue;
								}

								if($section_id == 'general-settings') {
									$classes = 'tab-pane fade in active';
								} else {
									$classes = 'tab-pane fade';
								}
								echo '<div class="'.$classes.'" id="'.$section_id.'">';
									echo '<legend>'.$section['label'].'</legend>';
									$this->Render_section($section);
								echo '</div>';
							}
						?>

					</div>

					<div class="form-group">
					  <label class="col-md-12 control-label" for="exportlisting">

					  </label>
					  <div class="col-md-4">
					  	<input type="hidden" name="action" value="feedsync_settings" />
						<input type="submit" id="submit_feedsync_Settings" name="submit_feedsync_Settings" class="btn btn-success" value="Save" />
					  </div>
					</div>
				</fieldset>
			</form>
		</div> <?php

	}

	function get_sections() {

		$get_google_maps_api_key_uri = '<a target="_blank" href="https://developers.google.com/maps/documentation/javascript/get-api-key">' . __( 'Google Maps API Key' , 'easy-feedsync') . '</a>';

		$feedsync_section = array(
			'label'		=>	'Settings',
			'intro'		=>	'Adjust Feedsync Settings Here',
		);

		$fields = apply_filters('feedsync_general_settings',array(

			array(
				'name'	=>	'site_url',
				'label'	=>	__('Site URL','epl-feedsync'),
				'type'	=>	'text',
			),

			array(
				'name'	=>	'feedtype',
				'label'	=>	__('Feed Type','epl-feedsync'),
				'type'	=>	'select',
				'opts'	=>	array(
					'reaxml'		=>	__('REAXML (Australian)','epl-feedsync'),
					'rockend'		=>	__('Rockend Rest (Australian)','epl-feedsync'),
					'eac'			=>	__('EAC (Australian)','epl-feedsync'),

					'blm'			=>	__('BLM (UK)','epl-feedsync'),
					'expert_agent'		=>	__('Expert Agent (UK)','epl-feedsync'),
					'jupix'			=>	__('Jupix (UK)','epl-feedsync')
				),
				'default'	=>	'reaxml',
				'help'		=>	__('Select the format of your listing feed.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_google_api_key',
				'label'		=>	__('Google Maps API Key', 'easy-property-listings' ),
				'type'		=>	'text',
				'help'		=>	'<strong>'.__('FeedSync requires a Google API key for generating listing coordinates.' , 'epl-feedsync' ) . '</strong> ' . __("As of June 22, 2016 Google has made API keys required for the Google Maps Geocoding API. To allow geocoding to function correctly please create a $get_google_maps_api_key_uri and enter it here." , 'epl-feedsync' )
			),
			array(
				'name'		=>	'geo_enabled',
				'label'		=>	__('Geocoding: Generate Listing Coordinates During Import','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'off',
				'opts'		=>	array(
					'on'	=>	__('Enable','epl-feedsync'),
					'off'	=>	__('Disable','epl-feedsync')
				),
				'default'	=>	'on',
				'help'		=>	__('This will generate coordinates for your listings. Warning! Google allows for 2,500 records per day per server. So if you have a huge number of records, begin by processing the input files with geocoding disabled.','epl-feedsync')
			),
			array(
				'name'		=>	'force_geocode',
				'label'		=>	__('Force Geocode','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'off',
				'opts'		=>	array(
					'on'	=>	__('Enable','epl-feedsync'),
					'off'	=>	__('Disable','epl-feedsync')
				),
				'help'		=>	__('If your listings coordinates are incorrect, enabling this will re-process coordinates for all your listings. Visit the Process page and press <b>Delete and Process all Coordinates</b>. Once complete set this to disable.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_pagination',
				'label'		=>	__('Pagination','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'1000',
				'help'		=>	__('Pagination for browsing listings.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_gallery_pagination',
				'label'		=>	__('Image Gallery Pagination','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'24',
				'help'		=>	__('Pagination for browsing listing image gallery.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_timezone',
				'label'		=>	__('Timezone','epl-feedsync'),
				'type'		=>	'timezone',
				'timezones' 	=>	get_timezone_array(),
				'default'	=>	'Australia/Melbourne',
				'help'		=>	__('Timezone Setting.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_enable_access_key',
				'label'		=>	__('Access key required','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	get_access_key_default_status(),
				'opts'		=>	array(
					'on'	=>	__('Enable','epl-feedsync'),
					'off'	=>	__('Disable','epl-feedsync')
				),
				'help'		=>	__('This option will allow you to restrict access to your listing output data. When enabled your output script will include the access_key={your_key} in the output URL. See the help page to get the output URL\'s.','feedsync')
			),
			/*array(
				'name'		=>	'feedsync_enable_permalinks',
				'label'		=>	__('Enable Permalinks ?','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'false',
				'opts'		=>	array(
					'true'	=>	__('Enable','epl-feedsync'),
					'false'	=>	__('Disable','epl-feedsync')
				),
				'help'		=>	__('Disable / enable pretty permalinks for feedsync','feedsync')
			),*/
			array(
				'name'		=>	'feedsync_access_key',
				'label'		=>	__('Access Key','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	uniqid(),
				'help'		=>	__('The Access Key will be appended to your output URL preventing unauthorised access to listing data.','epl-feedsync')
			),

			array(
				'name'		=>	'feedsync_max_filesize',
				'label'		=>	__('Max File Size','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'512000',
				'help'		=>	__('Define the file size in order to process large XML files. Large files will be split into smaller chunks to avoid server time-outs. Default is 512000 bytes i.e 512 kb','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_chunk_size',
				'label'		=>	__('Chunk Size','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'50',
				'help'		=>	__('Split file into chunks containing the specified number of records. Default is 50.','epl-feedsync')
			),

		) );

		$feedsync_section['fields'] 		= $fields;
		$feedsync_fields['general-settings'] 		= $feedsync_section;

		/** eac setting section **/
		$feedsync_section = array(
			'label'		=>	'EAC API Configuration',
			'intro'		=>	'Please configure settings to fetch listings from API',
		);

		$fields = apply_filters('feedsync_eac_settings',array(
			array(
				'name'		=>	'feedsync_eac_api_mode',
				'label'		=>	__('API Mode','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'live',
				'opts'		=>	array(
					'test'	=>	__('Test API','epl-feedsync'),
					'live'	=>	__('Live API','epl-feedsync')
				),
				'help'		=>	__('Live API is recommended for production environment','feedsync')
			),
			array(
				'name'		=>	'feedsync_eac_listings_per_request',
				'label'		=>	__('Listings Per Request','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	5,
				'help'		=>	__('Number of listings to process in per request, default is 5','feedsync')
			),
			array(
				'name'		=>	'EAC_APIKEY',
				'label'		=>	__('API KEY','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'validation'	=>	'required',
				'help'		=>	__('','epl-feedsync')
			),
			// array(
			// 	'name'		=>	'EAC_PerPage',
			// 	'label'		=>	__('Per Page','epl-feedsync'),
			// 	'type'		=>	'number',
			// 	'default'	=>	'10',
			// 	'help'		=>	__('The total results per page.')
			// ),
			// array(
			// 	'name'		=>	'EAC_PageNumber',
			// 	'label'		=>	__('Page Number','epl-feedsync'),
			// 	'type'		=>	'number',
			// 	'default'	=>	'1',
			// 	'help'		=>	__('The page number.')
			// ),
			array(
				'name'		=>	'EAC_LN',
				'label'		=>	__('Listing Number','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Single or multiple Listing Numbers can be provided to search against. Overwrites any other parameter.','epl-feedsync')
			),
			array(
				'name'		=>	'EAC_AGT_ID',
				'label'		=>	__('Agent ID','epl-feedsync'),
				'type'		=>	'text',
				'validation'	=>	'required',
				'default'	=>	'',
				'help'		=>	__('Single or multiple Agent IDs. Exclude this parameter completely for REWM.','epl-feedsync')
			),
			array(
				'name'		=>	'EAC_PROPCLASS',
				'label'		=>	__('Property Class','epl-feedsync'),
				'type'		=>	'checkbox',
				'opts'		=>	array(
					//'0'			=>	__('All','epl-feedsync'),
					'1'			=>	__('Residential','epl-feedsync'),
					'2'			=>	__('Land','epl-feedsync'),
					'3'			=>	__('Rural','epl-feedsync'),
					'4'			=>	__('Business','epl-feedsync'),
					'5'			=>	__('Commercial','epl-feedsync'),
					'6'			=>	__('All Rentals','epl-feedsync')
				),
				'default'	=>	'0',
				'help'		=>	__('Any comma delimited array of the integers 0-6 can be provided. It is advisable to only provide one however, but if multiple are required,
they should be 1-5 only. If rentals are required, pass through 6 on its own. Values are:
0. All, 1. Residential, 2. Land, 3. Rural, 4. Business, 5. Commercial, 6. All Rentals.')
			),
			array(
				'name'		=>	'EAC_STAT',
				'label'		=>	__('Property Status','epl-feedsync'),
				'type'		=>	'checkbox',
				'opts'		=>	array(
					''			=>	__('Any','epl-feedsync'),
					'CR'			=>	__('Current','epl-feedsync'),
					'SOLD'			=>	__('Sold','epl-feedsync'),
					'WD'			=>	__('Withdrawn','epl-feedsync'),
					'EX'			=>	__('Expired','epl-feedsync'),
					'CANC'			=>	__('Cancelled','epl-feedsync'),
				),
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of property status to search against. Values are:
Empty String: Any, "CR": Current, "SOLD": Sold, "WD": Withdrawn, "EX": Expired, "CANC": Cancelled.')
			),
			array(
				'name'		=>	'EAC_RSTAT',
				'label'		=>	__('Property Rental Status','epl-feedsync'),
				'type'		=>	'checkbox',
				'opts'		=>	array(
					''				=>	__('Any','epl-feedsync'),
					'AVAIL'			=>	__('Available','epl-feedsync'),
					'UNADC,UANDC'	=>	__('Up and Coming','epl-feedsync'),
					'LEASED'		=>	__('Leased','epl-feedsync'),
					'SOLD'			=>	__('Sold','epl-feedsync'),
					'NLM'			=>	__('No Longer Managed','epl-feedsync'),
				),
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of rental status to search against. Values are:
Empty String: Any, "AVAIL": Available, "UNADC,UANDC": Up and Coming, "LEASED": Leased, "SOLD": Sold, "NLM": No Longer Managed.')
			),/**
			array(
				'name'		=>	'EAC_STATE',
				'label'		=>	__('State','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=>	array(
					''				=>	__('Any','epl-feedsync'),
					'NSW'			=>	__('NSW','epl-feedsync'),
					'ACT'			=>	__('ACT','epl-feedsync'),
					'QLD'			=>	__('QLD','epl-feedsync'),
					'VIC'			=>	__('VIC','epl-feedsync'),
					'TAS'			=>	__('TAS','epl-feedsync'),
					'NT'			=>	__('NT','epl-feedsync'),
					'SA or WA'		=>	__('SA or WA','epl-feedsync'),
				),
				'default'	=>	'',
				'help'		=>	__('String representing the state to search against. Accepted values are: "NSW,ACT,QLD,VIC,TAS,NT,SA or WA"')
			),
			array(
				'name'		=>	'EAC_SUB',
				'label'		=>	__('Suburbs','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of suburbs to search against.')
			),
			array(
				'name'		=>	'EAC_STNME',
				'label'		=>	__('Street Name','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of street names to search against.')
			),
			array(
				'name'		=>	'EAC_BATH',
				'label'		=>	__('Bathroom','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of bath numbers to search against.')
			),
			array(
				'name'		=>	'EAC_BEDS',
				'label'		=>	__('Bedrooms','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Integer representing n or more bedrooms. E.g. passing through 3 will return any property with 3 or more bedrooms.')
			),
			array(
				'name'		=>	'EAC_NOPS',
				'label'		=>	__('Parking spaces','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Integer representing n or more parking spaces. E.g. passing through 3 will return any property with 3 or more parking spaces.')
			),
			array(
				'name'		=>	'EAC_PRICEFROM',
				'label'		=>	__('Price From','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Lower bounds of a price search. If PRICETO is not included, PRICE >= PRICEFROM logic is implied.')
			),
			array(
				'name'		=>	'EAC_PRICETO',
				'label'		=>	__('Price To','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Upper bounds of a price search. If PRICEFROM is not included, PRICE <= PRICETO logic is implied.')
			),
			array(
				'name'		=>	'EAC_RENTFROM',
				'label'		=>	__('Rent From','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Lower bounds of a rent search. If RENTTO is not included, RENT >= RENTFROM logic is implied.')
			),
			array(
				'name'		=>	'EAC_RENTTO',
				'label'		=>	__('Rent To','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Upper bounds of a rent search. If RENTFROM is not included, RENT <= RENTTO logic is implied.')
			),
			array(
				'name'		=>	'EAC_PROPTYPE',
				'label'		=>	__('Property Type','epl-feedsync'),
				'type'		=>	'checkbox',
				'opts'		=>	array(
					''	=>	'Any',
					'A'	=>	'Apartment',
					'BLK'	=>	'Block of Units' ,
					'C/H'	=>	'Cluster Homes' ,
					'CR'	=>	'Country Residence',
					'DO'	=>	'Dual Occupancy',
					'D'	=>	'Duplex',
					'F'	=>	'Flat',
					'H'	=>	'House',
					'RELOC'	=>	'Relocatable Home',
					'S'	=>	'Semi-detached',
					'T'	=>	'Terrace',
					'TWN'	=>	'Townhouse',
					'U'	=>	'Unit',
					'V'	=>	'Villa',
					'O'	=>	'Other'
				),
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of property types to search against.')
			),
			array(
				'name'		=>	'EAC_RENTTYPE',
				'label'		=>	__('Rent Type','epl-feedsync'),
				'type'		=>	'checkbox',
				'opts'		=>	array(
					''		=>	'Any',
					'RES'	=>	'Residential',
					'HOL'	=>	'Holiday',
					'COMM'	=>	'Commercial'

				),
				'default'	=>	'',
				'help'		=>	__('Comma delimited array of rental types to search against.')
			),
			array(
				'name'		=>	'EAC_SOLDDAYSBACK',
				'label'		=>	__('Sold Days Back','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Integer representing how many days back to retrieve sold listings from. E.g. 30 will return all sold listings in the past 30 days. Use in
conjunction with STAT: "SOLD".')
			),
			array(
				'name'		=>	'EAC_OFIDays',
				'label'		=>	__('Open for inspection days','epl-feedsync'),
				'type'		=>	'number',
				'default'	=>	'',
				'help'		=>	__('Integer representing listings which have Open for Inspections in the next n days.')
			),
			array(
				'name'		=>	'EAC_SortBy',
				'label'		=>	__('Sort By','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=> array(
					'SUB'	=>	'Suburb',
					'PRICE'	=>	'Price',
					'RENT'	=>	'Rent',
					'LDATE'	=>	'Listing Date'
				),
				'default'	=>	'',
				'help'		=>	__('Field to sort by. Defaults to Suburb. Accepted values are:
"SUB": Suburb, "PRICE": Price, "RENT": Rent, "LDATE": Listing Date.')
			),
			array(
				'name'		=>	'EAC_SortDirection',
				'label'		=>	__('Sort Direction','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=> array(
					'ASC'	=>	'Ascending',
					'DESC'	=>	'Descending'
				),
				'default'	=>	'ASC',
				'help'		=>	__('Direction to sort. Defaults to ASCENDING. Accepted values are: "ASC": Ascending, "DESC": Descending.')
			),
			**/

		) );

		$feedsync_section['fields'] 		= $fields;
		$feedsync_fields['eac-api'] 		= $feedsync_section;

		/** BLM setting section **/
		$feedsync_section = array(
			'label'		=>	'BLM',
			'intro'		=>	'',
		);

		$fields = apply_filters('feedsync_blm_fields',array(

			array(
				'name'		=>	'feedsync_blm_unknown_listings_action',
				'label'		=>	__('What to do with undetermined listings','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=> array(
					'leave'			=>	'Leave as is',
					'withdrawn'		=>	'Mark as withdrawn',
					'offmarket'		=>	'Mark as offmarket'
				),
				'default'	=>	'withdrawn',
			)

		) );

		$feedsync_section['fields'] 			= $fields;
		$feedsync_fields['blm'] 				= $feedsync_section;


		/** Expert agent setting section **/
		$feedsync_section = array(
			'label'		=>	'Expert Agent',
			'intro'		=>	'',
		);

		$fields = apply_filters('feedsync_expert_agent',array(

			array(
				'name'		=>	'feedsync_remote_host',
				'label'		=>	__('Host Address','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('If your feed provider requires you to pull files remotely, please fill host, username & pass.Once done you can set cron job to fetch files periodically.Cron Url for fetching files would be '.CORE_URL.'copy-files.php','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_remote_user',
				'label'		=>	__('Host Username','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('Only requires if your feed needs to be fetch remotely')

			),
			array(
				'name'		=>	'feedsync_remote_pass',
				'label'		=>	__('Host Password','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('Only requires if your feed needs to be fetch remotely')

			),
			array(
				'name'		=>	'feedsync_expert_agent_unknown_listings_action',
				'label'		=>	__('What to do with undetermined listings','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=> array(
					'leave'			=>	'Leave as is',
					'withdrawn'		=>	'Mark as withdrawn',
					'offmarket'		=>	'Mark as offmarket'
				),
				'default'	=>	'withdrawn',
			)

		) );

		$feedsync_section['fields'] 			= $fields;
		$feedsync_fields['expert_agent'] 		= $feedsync_section;

		/** Jupix setting section **/
		$feedsync_section = array(
			'label'		=>	'Jupix',
			'intro'		=>	'',
		);

		$fields = apply_filters('feedsync_jupix',array(

			array(
				'name'		=>	'feedsync_jupix_feed_url',
				'label'		=>	__('Feed URL','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('Feed URL of jupix','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_jupix_client_id',
				'label'		=>	__('Client ID','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('Your client ID for jupix','epl-feedsync')

			),
			array(
				'name'		=>	'feedsync_jupix_pass',
				'label'		=>	__('Pass Phrase','epl-feedsync'),
				'type'		=>	'text',
				'help'		=>	__('Passphrase to access jupix feed','epl-feedsync')

			),
			array(
				'name'		=>	'feedsync_jupix_unknown_listings_action',
				'label'		=>	__('What to do with undetermined listings','epl-feedsync'),
				'type'		=>	'select',
				'opts'		=> array(
					'leave'			=>	'Leave as is',
					'withdrawn'		=>	'Mark as withdrawn',
					'offmarket'		=>	'Mark as offmarket'
				),
				'default'	=>	'withdrawn',
			),



		) );

		$feedsync_section['fields'] 			= $fields;
		$feedsync_fields['jupix'] 				= $feedsync_section;

		/** License setting section **/
		$feedsync_section = array(
			'label'		=>	'License Key',
			'intro'		=>	'License keys for updates and support
Software License. Set these so you can register this website with your license so you can receive software support',
		);

		$fields = apply_filters('feedsync_license_settings',array(

			array(
				'name'		=>	'feedsync_license_url',
				'label'		=>	__('License URL','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Copy the URL from your browser to set the Licensed Site URL.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_license_key',
				'label'		=>	__('License Key','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('','epl-feedsync')
			),

		) );

		$feedsync_section['fields'] 		= $fields;
		$feedsync_fields['license'] 		= $feedsync_section;


		/** Loggging & Debugging section **/
		$feedsync_section = array(
			'label'		=>	'Logging / Debugging',
			'intro'		=>	'',
		);

		$fields = apply_filters('feedsync_logging_debugging_settings',array(

			array(
				'name'		=>	'feedsync_enable_logging',
				'label'		=>	__('Log Report','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'on',
				'opts'		=>	array(
					'on'	=>	__('Enable','epl-feedsync'),
					'off'	=>	__('Disable','epl-feedsync')
				),
				'help'		=>	__('Log detailed report of every file processed. Logs are saved to the logs folder.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_max_logs',
				'label'		=>	__('Max Logs','epl-feedsync'),
				'default'	=>	'1000',
				'type'		=>	'number',
				'help'		=>	__('The number of logs to keep, older ones will be automatically deleted.','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_enable_debug_report',
				'label'		=>	__('Debug Report by Email','epl-feedsync'),
				'type'		=>	'radio',
				'default'	=>	'off',
				'opts'		=>	array(
					'on'	=>	__('Enable','epl-feedsync'),
					'off'	=>	__('Disable','epl-feedsync')
				),
				'help'		=>	__('Setting this on will send you an email in case of FeedSync failure / errors.','epl-feedsync')
			),

			array(
				'name'		=>	'feedsync_debug_receiver',
				'label'		=>	__('Send Debug Report to','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Email address where debug report should be sent.','epl-feedsync')
			),

			array(
				'name'		=>	'feedsync_mail_mode',
				'label'		=>	__('Email Mode','epl-feedsync'),
				'type'		=>	'select',
				'default'	=>	'server',
				'opts'		=>	array(
					'server'	=>	__('Server','epl-feedsync'),
					'smtp'		=>	__('SMTP','epl-feedsync')
				),
				'help'		=>	__('While using SMTP & gmail account, make sure you "allow less secure app" in your gmail settings. Email sending is handled automatically unless set below.','epl-feedsync')
			),

			array(
				'name'		=>	'feedsync_mailer_host',
				'label'		=>	__('SMTP Host','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Used when mail mode is SMTP, example for gmail : smtp.gmail.com','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_mailer_port',
				'label'		=>	__('SMTP Port','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Used when mail mode is SMTP, example for gmail : 587','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_mailer_username',
				'label'		=>	__('SMTP Email ID','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Used when mail mode is SMTP','epl-feedsync')
			),
			array(
				'name'		=>	'feedsync_mailer_password',
				'label'		=>	__('SMTP Password','epl-feedsync'),
				'type'		=>	'text',
				'default'	=>	'',
				'help'		=>	__('Used when mail mode is SMTP','epl-feedsync')
			),


		) );

		$feedsync_section['fields'] 				= $fields;
		$feedsync_fields['logging_debugging'] 		= $feedsync_section;

		return apply_filters('feedsync_settings_array',$feedsync_fields);
	}

	function render_Section($section) {
		echo '<section class="feedsync-setting-section">';
			foreach($section['fields'] as $field) {
				echo '<fieldset class="form-group">';
				echo '<label for="'.$field["name"].'">'.$field["label"].'</label>';
				$this->render_field($field, get_option($field["name"],false) );
				echo '</fieldset>';
			}
		echo '<section/>';
	}

	function render_field ( $field = array() , $val = '' ) {

		$validated = true;

		if($val == '' && isset($field['default']) ) {
			$val = $field['default'];
		}

		$validations = isset( $field['validation']) ? $field['validation'] : false;

		if($validations){

			$validated = GUMP::is_valid(array('value'	=>	$val), array(
			    'value' => $validations
			));

		}

	 	switch($field['type']) {

			case 'select':

				echo '<select name="'.$field['name'].'" class="form-control" id="'.$field['name'].'">';

					if(isset($field['opts']) && !empty($field['opts'])) {
						foreach($field['opts'] as $k=>$v) {
							$selected = '';
							if($val == $k || ($val=='' && !empty($field['default']) && $field['default'] == $k) ) {
								$selected = 'selected="selected"';
							}
							if(is_array($v)) {

								$v = $v['label'];
							}
							echo '<option value="'.$k.'" '.$selected.'>'.__($v, 'epl-feedsync' ).'</option>';
						}
					} else {
						echo '<option value=""> </option>';
					}
				echo '</select>';
			break;

			case 'timezone':

				echo '<select name="'.$field['name'].'" class="form-control" id="'.$field['name'].'">';

					foreach($field['timezones'] as $region => $list) {
						echo '<optgroup label="' . $region . '">' . "\n";
						foreach($list as $k 	=> 	$v) {
							$selected = '';
							if($val == $k || ($val=='' && !empty($field['default']) && $field['default'] == $k) ) {
								$selected = 'selected="selected"';
							}
							if(is_array($v)) {

								$v = $v['label'];
							}
							echo '<option value="'.$k.'" '.$selected.'>'.__($v, 'epl-feedsync' ).'</option>';
						}

					}
				echo '</select>';
			break;

			case 'checkbox':
				if(!empty($field['opts'])) {
					echo '<div class="">';
					echo '<input type="hidden" name="'.$field['name'].'" value=""/>';
					foreach($field['opts'] as $k=>$v) {
						$checked = '';
						if(!empty($val)) {
							if( in_array($k, $val) ) {
								$val = (array) $val;
								$checked = 'checked="checked"';
							}
						}
						echo '<label class="checkbox-inline" for="'.$field['name'].'_'.$k.'"><input class="" type="checkbox" name="'.$field['name'].'[]" id="'.$field['name'].'_'.$k.'" value="'.$k.'" '.$checked.' /> '.__($v, 'epl-feedsync' ).'</label>';
					}
					echo '</div>';
				}
				break;
			case 'checkbox_single':
				if(!empty($field['opts'])) {
					foreach($field['opts'] as $k=>$v) {
						$checked = '';
						if(!empty($val)) {
							$checkbox_single_options = apply_filters('epl_checkbox_single_check_options', array(1,'yes','on','true'));
							if( $k == $val || in_array($val,$checkbox_single_options) ) {
								$checked = 'checked="checked"';
							}
						}
						if(count($field['opts']) == 1)
							$v = $field['label'];
						echo '<input type="checkbox" class="form-control" name="'.$field['name'].'" id="'.$field['name'].'_'.$k.'" value="'.$k.'" '.$checked.' /> <label for="'.$field['name'].'_'.$k.'">'.__($v, 'epl-feedsync' ).'</label>';
					}
				}
				break;
			case 'radio':
				//print_exit($field);
				if(!empty($field['opts'])) {
					foreach($field['opts'] as $k=>$v) {
						$checked = '';

						if(strtolower($val) == strtolower($k) ) {
							$checked = 'checked="checked"';

						}
						echo '<div class="radio"><label><input class="" type="radio" name="'.$field['name'].'" id="'.$field['name'].'_'.$k.'" value="'.$k.'" '.$checked.' /> '.__($v, 'epl-feedsync' ).'</label></div>';
					}
				}
				break;
			case 'textarea':
				$atts = '';
				if(isset($field['maxlength'] ) && $field['maxlength'] > 0) {
					$atts = ' maxlength="'.$field['maxlength'].'"';
				}
				echo '<textarea class="form-control" name="'.$field['name'].'" id="'.$field['name'].'" '.$atts.'>'.stripslashes($val).'</textarea>';
				break;
			case'decimal':
				$atts = '';
				if($field['maxlength'] > 0) {
					$atts = ' maxlength="'.$field['maxlength'].'"';
				}
				echo '<input type="text" name="'.$field['name'].'" id="'.$field['name'].'" value="'.stripslashes($val).'" class="form-control validate[custom[onlyNumberWithDecimal]]" '.$atts.' />';
				break;
			case 'number':
				$atts = '';
				if(isset($field['maxlength']) && $field['maxlength'] > 0) {
					$atts = ' maxlength="'.$field['maxlength'].'"';
				}
				echo '<input type="number" name="'.$field['name'].'" id="'.$field['name'].'" value="'.stripslashes($val).'" class="form-control validate[custom[onlyNumber]]" '.$atts.' />';
				break;

			case 'email':
				echo '<input type="text" name="'.$field['name'].'" id="'.$field['name'].'" value="'.stripslashes($val).'" class="form-control validate[custom[email]]" />';
				break;
			case 'url':
				echo '<input type="text" name="'.$field['name'].'" id="'.$field['name'].'" value="'.stripslashes($val).'" class="form-control validate[custom[url]]" />';
				break;
			case 'button':
				$classes = isset($field['class']) ? $field['class'] : '';
				echo '<input type="button" name="'.$field['name'].'" id="'.$field['name'].'" value="'.$field['value'].'" class="form-control '.$classes.'" />';
				break;
			case 'locked':
				$atts = '';
				echo '<span>'.stripslashes($val).'</span>';
				break;
			case 'help':
				echo '<small class="text-muted" id="' . ( isset( $field['name'] ) ? $field['name'] : '' ) . '">' . ( isset( $field['content'] ) ? $field['content'] : '' ) . '</small>';
				break;
			default:
				$atts = '';
				if(isset($field['maxlength']) &&  $field['maxlength'] > 0) {
					$atts .= ' maxlength="'.$field['maxlength'].'"';
				}
	            $classes = isset($field['class']) ? $field['class'] : '';
				foreach($field as $temp_key	=>	$temp_value) {
					if (0 === strpos($temp_key, 'data-')) {
					  $atts .= ''.$temp_key.'="'.$temp_value.'"';
					}
				}
	            echo '<input type="'.$field['type'].'" name="'.$field['name'].'" id="'.$field['name'].'" class="form-control '.$classes.'"  value="'.stripslashes($val).'" '.$atts.' />';
		}

		if($validated !== true) {
		   foreach($validated as $v_error){
		   		echo '<span class="feedsync-field-error-msg alert alert-danger">'.$v_error.'</span>';
		   }
		}

		if(isset($field['help'])) {
			$field['help'] = trim($field['help']);
			if(!empty($field['help'])) {
				echo '<small class="text-muted">'.__($field['help'], 'epl-feedsync' ).'</small>';
			}
		}
	}
}

new FEEDSYNC_SETTINGS;


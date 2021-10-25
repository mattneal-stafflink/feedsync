<?php

	include_once(CORE_PATH.'classes/class-array-to-xml.php');


	/** parses a .bml document and creates xml from it **/
	
	class BML_PARSER {


		public $properties = array();

        public $EOF = '^';

        public $EOR = '~';

        public $STATUS_ID = array(  
			"current",
            "sold",
            "sold",
            "current",
            "sold",
            "leased",
            "sold",
            "leased"
        );

        public $PRICE_QUALIFIER = array(
            0 => "Default",
            1 => "POA",
            2 => "Guide Price",
            3 => "Fixed Price",
            4 => "Offers in Excess of",
            5 => "OIRO",
            6 => "Sale by Tender",
            7 => "From",
            9 => "Shared Ownership",
            10 => "Offers Over",
            11 => "Part Buy Part Rent",
            12 => "Shared Equity"
        );
        
        public $PUBLISHED_FLAG      = array(0 => "Hidden/invisible", 1 => "Visible");
        public $LET_TYPE_ID         = array(0=>"Not Specified", 1=>"Long Term", 2=>"Short Term", 3=>"Student", 4=>"Commercial");
        public $LET_FURN_ID         = array(0 => "Furnished", 1 => "Part Furnished", 2 => "Unfurnished", 3 => "Not Specified", 4=>"Furnished/Un Furnished");
        public $LET_RENT_FREQUENCY  = array(0 => "Weekly", 1 => "Monthly", 2 => "Quarterly", 3 => "Annual");
        public $TENURE_TYPE_ID      = array(1 => "Freehold", 2 => "Leasehold", 3 => "Feudal", 4 => "Commonhold", 5 => "Share of Freehold");
        public $TRANS_TYPE_ID       = array(1 => "residential", 2=> "rental");
        public $NEW_HOME_FLAG       = array("Y" => "New Home", "N" => "Non New Home");
        public $PROP_SUB_ID         = array(
            0=>"Not Specified",
            1=>"Terraced",
            2=>"End of Terrace",
            3=>"Semi-Detached",
            4=>"Detached",
            5=>"Mews",
            6=>"Cluster House",
            7=>"Ground Flat",
            8=>"Flat",
            9=>"Studio",
            10=>"Ground Maisonette",
            11=>"Maisonette",
            12=>"Bungalow",
            13=>"Terraced Bungalow",
            14=>"Semi-Detached Bungalow",
            15=>"Detached Bungalow",
            16=>"Mobile Home",
            17=>"Hotel",
            18=>"Guest House",
            19=>"Commercial Property",
            20=>"Land",
            21=>"Link Detached House",
            22=>"Town House",
            23=>"Cottage",
            24=>"Chalet",
            27=>"Villa",
            28=>"Apartment",
            29=>"Penthouse",
            30=>"Finca",
            43=>"Barn Conversion",
            44=>"Serviced Apartments",
            45=>"Parking",
            46=>"Sheltered Housing",
            47=>"Retirement Property",
            48=>"House Share",
            49=>"Flat Share",
            50=>"Park Home",
            51=>"Garages",
            52=>"Farm House",
            53=>"Equestrian",
            56=>"Duplex",
            59=>"Triplex",
            62=>"Longere",
            65=>"Gite",
            68=>"Barn",
            71=>"Trulli",
            74=>"Mill",
            77=>"Ruins",
            80=>"Restaurant",
            83=>"Cafe",
            86=>"Mill",
            89=>"Trulli",
            92=>"Castle",
            95=>"Village House",
            101=>"Cave House",
            104=>"Cortijo",
            107=>"Farm Land",
            110=>"Plot",
            113=>"Country House",
            116=>"Stone House",
            117=>"Caravan",
            118=>"Lodge",
            119=>"Log Cabin",
            120=>"Manor House",
            121=>"Stately Home",
            125=>"Off-Plan",
            128=>"Semi-detached Villa",
            131=>"Detached Villa",
            134=>"Bar",
            137=>"Shop",
            140=>"Riad",
            141=>"House Boat",
            142=>"Hotel Room",
        );



		function __construct() {

			$files = $this->get_bml_files();
			if( !empty($files) ) {
				foreach($files as $file) {

					$this->create_xml($file);
				}
			}
		}


        function get_special_flags() {
            return array('STATUS_ID','PRICE_QUALIFIER','PUBLISHED_FLAG','LET_TYPE_ID','LET_FURN_ID','LET_RENT_FREQUENCY','TENURE_TYPE_ID','TRANS_TYPE_ID','NEW_HOME_FLAG','PROP_SUB_ID');
        }

        function get_flag_value(&$value,$flag) {
            $value = isset($this->{$flag}[$value]) ? $this->{$flag}[$value] :$value;
        }

		function get_definition_regex() {
			return '#DEFINITION#([\s\S]*?)'.$this->EOR;
		}

		function get_data_regex() {
			return '#DATA#([\s\S]*?)#END#';
		}

		function get_header_regex() {
			return '#HEADER#([\s\S]*?)#DEFINITION';
		}

		function get_bml_files() {

			$files =  get_files_list(get_path('input'),"blm|BLM");

             if( !empty($files) ) {

                foreach($files as $file) {
                    rename(
                        get_path('input').basename($file),
                        get_path('input').date("Y-m-d-H-i-s-T-").basename($file));
                }
             }

             return get_files_list(get_path('input'),"blm|BLM");
		}

		function parse_sections($link) {
            $geo_response       = feedsync_remote_get( $link );
            $text               = feedsync_remote_retrive_body( $geo_response );
			$text               = utf8_encode( $text );
            $this->parse_header($text);
			$this->parse_definition($text);
			$this->parse_data($text);
		}

        /**
         * Try to parse and get field & row endings from file
         */
        function parse_header($text) {
            $regex = $this->get_header_regex();
            preg_match('/'.$regex.'/', $text, $matches);
            $header = explode("\n",$matches[1]);
            $header = array_map( array($this,'explode_map'),$header );
            foreach ($header as $key => $value) {
                $value = array_map( 'trim',$value );

                if( in_array('EOF',$value) ) {
                    $this->EOF = str_replace("'", "", $value[1]);
                }

                if( in_array('EOR',$value) ) {
                    $this->EOR = str_replace("'", "", $value[1]);
                }
            }
        }

        function explode_map($a) {
            return explode(':',$a);
        }

		function parse_definition($text) {
			$regex = $this->get_definition_regex();
			preg_match('/'.$regex.'/', $text, $matches, PREG_OFFSET_CAPTURE);
			
			//deninitions as string
			$this->definitions = isset($matches[1][0]) ? $matches[1][0] : array() ;
		}

		function parse_data($text) {
			$regex = $this->get_data_regex();
			preg_match('/'.$regex.'/', $text, $matches, PREG_OFFSET_CAPTURE);
			
			//deninitions as string
			$this->data = isset($matches[1][0]) ? $matches[1][0] : array() ;
		}

		function get_definition_array() {

			return array_filter( explode( $this->EOF,trim($this->definitions) ) );
		}

		function create_data_array() {

			$rows = array_filter( explode( $this->EOR,trim($this->data) ) );

			if(!empty($rows)) {
				foreach($rows as $row) {
					$property = $this->get_row_data($row);
                    array_walk($property, array($this,'get_flag_value' ));
                    $this->properties[] = $property;
				}
			}
		}

        /** prepares each property data as an array **/
		function get_row_data($row) {
			$row = explode( $this->EOF,trim($row) );
            array_pop($row);

			// make it associative with headers
			return array_combine($this->definition_array,$row);

			
		}

		function make_array(){

			if( isset($this->definitions) ) {
				$this->definition_array = $this->get_definition_array();
			}

			if( isset($this->data) ) {
				$this->create_data_array();
			}

		}

		function make_xml($file_name) {

            $file_name_date = $file_name.'-'.date("Y-m-d-H-i-s-T") ;
			$xml = Array2XML::createXML('propertyList', array('property'	=>	$this->properties) );
 			$fp = fopen(get_path('input').$file_name_date.'.xml','w');
			if(!$fp) {
				die('unable to create xml');
			} else {
                $fw=fwrite($fp,$xml->saveXML());
                fclose($fp);

                if( rename(get_path('input').$file_name,get_path('processed').$file_name ) ) {

                }
            }
		}

		function create_xml($link){

            $file_info = pathinfo($link);
            $file_name = isset($file_info['filename']) ? $file_info['filename'] : 'propertylist_'.date("Y-m-d-H-i-s-T");

			$this->parse_sections($link);

			$this->make_array();
            $file_name = $file_name.'.'.$file_info['extension'];
			$this->make_xml($file_name);
		}
	}


	new BML_PARSER();

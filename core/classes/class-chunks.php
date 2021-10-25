<?php
	
	/**
		Parses a big xml file and breaks it into smaller chunks
	**/

	class XMl_CHUNKS {
		
		protected $file_path;
		protected $count;
		protected $xml;
		protected $input_path = INPUT_PATH;
		
		function __construct($file_path,$count = FEEDSYNC_CHUNK_SIZE ) {
		
			$this->file_path 	= $file_path;
			$this->count 		= (int) $count;
			$this->filename		= basename($file_path);
			
		}
		
		function create_chunk($input_path = INPUT_PATH, $processed_path = PROCESSED_PATH) {
			
			$xmlFile = new DOMDocument('1.0', 'UTF-8');
			libxml_use_internal_errors(true);
			$xpath = new DOMXPath($xmlFile);
			$xmlFile->formatOutput = true;
			$xmlFile->recover = TRUE;
			$xmlFile->load($this->file_path);
			$items = $xmlFile->getElementsByTagName($this->get_parent_element())->item(0);
			
			//echo $xmlFile->documentElement->childNodes->length; die;
			
			if( !empty($items) ) {
				$item_counter = 1;
				$this->xml = $this->header();
				foreach($items->childNodes as $item) { 
					
					if( isset($item->tagName) && !is_null($item->tagName) ) {
						
						$this->xml .= $xmlFile->saveXML( $item);
						
						if($item_counter == $this->count) {
						
							$this->xml .= $this->footer();
							
							//** create chunk files
							$chunk_name = $input_path.'/'.uniqid().$this->filename;
							if ( $file_handle = @fopen($chunk_name , 'a' ) ) {
								fwrite( $file_handle, $this->xml );
								fclose( $file_handle );
								@chmod($chunk_name, 0644); 
							}
							
							/** reset counter **/
							$item_counter = 0;
							
							/** add header **/
							$this->xml = $this->header();
							
							continue;
						}
						
						$item_counter++;
						
					}
				}
				$this->xml .= $this->footer();
			}
			
			//** create chunk files
			$chunk_name = $input_path.'/'.uniqid().$this->filename;
			if ( $file_handle = @fopen($chunk_name , 'a' ) ) {
				fwrite( $file_handle, $this->xml );
				fclose( $file_handle );
				@chmod($chunk_name, 0644); 
			}
			
			@rename($this->file_path,$processed_path.basename($this->file_path) ); 
		}
		
		function get_parent_element() {
		    $feedtype = get_option('feedtype');
		    $path = 'propertyList';
		    switch($feedtype) {

		        case 'blm' :
		            $path = 'propertyList';
		        break;
		        case 'reaxml' :
		            $path = 'propertyList';
		        break;
		        case 'expert_agent' :
		           $path = 'properties';
		        break;
		        case 'rockend' :
		           $path = 'Properties';
		        break;
		        case 'jupix' :
		           $path = 'properties';
		        break;

	        }

	        return $path;

		}
		
		function header() {
			$header =  '<?xml version="1.0" standalone="no"?>'."\n";
			$header .=  '<!DOCTYPE '.$this->get_parent_element().' SYSTEM "http://reaxml.realestate.com.au/'.$this->get_parent_element().'.dtd">'."\n";
			$header .=  '<'.$this->get_parent_element().'>'."\n";

			return $header;
		}
		
		function footer() {
			return '</'.$this->get_parent_element().'>';
		}
		
	}


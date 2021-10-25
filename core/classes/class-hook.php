<?php

	class hook {
		
		public $filters;
		public $current_filter;
		
		function hooks_api() {
		
		}

		/**
		 * Build Unique ID for storage and retrieval.
		 *
		 *
		 * Functions and static method callbacks are just returned as strings and
		 * shouldn't have any speed penalty.
		 *
		 * @access private
		 *
		 * @param string   $tag      Unused. The name of the filter to build ID for.
		 * @param callable $function The function to generate ID for.
		 * @param int      $priority Unused. The order in which the functions
		 *                           associated with a particular action are executed.
		 * @return string Unique function ID for usage as array key.
		 *
		 * @since 3.4.0
		 */
		function _build_unique_id( $tag, $function, $priority ) {

			if ( is_string( $function ) ) {
				return $function;
			}

			if ( is_object( $function ) ) {
				// Closures are currently implemented as objects.
				$function = array( $function, '' );
			} else {
				$function = (array) $function;
			}

			if ( is_object( $function[0] ) ) {
				// Object class calling.
				return spl_object_hash( $function[0] ) . $function[1];
			} elseif ( is_string( $function[0] ) ) {
				// Static calling.
				return $function[0] . '::' . $function[1];
			}
		}

		/**
		 * Check if callback is added for a filter
		 * @param  string  $tag   filter
		 * @param  mixed  $value callback
		 * @return boolean        true if filter is set
		 * @since 3.4.0
		 */
		function has_filter($tag,$value) {

			if( empty( $this->filters[$tag] ) ) {
				return false;
			}

			$unique_id = $this->_build_unique_id( $tag, $value, false );

			if ( ! $unique_id ) {
				return false;
			}

			foreach ( $this->filters[$tag] as $priority => $callbacks ) {
				if ( isset( $callbacks[ $unique_id ] ) ) {
					return $priority;
				}
			}
		}
		
		function add_filter($tag,$callback,$priority=10,$accepted_args=1) {
			$unique_id = $this->_build_unique_id( $tag, $callback, $priority );
			$this->filters[$tag][$priority][$unique_id] = array(
				'function'			=>	$callback,
				'accepted_args'		=>	$accepted_args
			);
		}
		
		function apply_filters($tag,$value) {
			
			$args = func_get_args();

			if( !empty($this->filters) ) {
				if( array_key_exists($tag,$this->filters) ) {
					$this->current_filter = $this->filters[$tag];
					
					krsort($this->current_filter);

					foreach( $this->current_filter as $priority ) {
					
						foreach( $priority as $callbacks ) {

							$func_args = array_slice($args, $callbacks['accepted_args']);
							
							if( is_array( $callbacks['function'] ) ) {

								if( method_exists($callbacks['function'][0],$callbacks['function'][1]) ) {
									$value =  call_user_func_array($callbacks['function'], $func_args);
								}

							} else {
								if( function_exists($callbacks['function']) ){
									$value =  call_user_func_array($callbacks['function'], $func_args);
								}
							}

						}

					}
				}
			}
				
			
			return $value;
		}
		
		function do_action($tag) {
			
			$args = func_get_args();

			if( array_key_exists($tag,$this->actions) ) {
				$this->current_action = $this->actions[$tag];
				
				krsort($this->current_action);

				foreach( $this->current_action as $priority ) {
				
					foreach( $priority as $callbacks ) {
						
						$func_args = array_slice($args, $callbacks['accepted_args']);
						
						if( function_exists($callbacks['function']) )
							call_user_func_array($callbacks['function'], $func_args);

					}

				}
			}
			
		}
		
		function add_action($tag,$callback,$priority=10,$accepted_args=1) {
			$unique_id = $this->_build_unique_id( $tag, $callback, $priority );
			$this->actions[$tag][$priority][$unique_id] = array(
				'function'			=>	$callback,
				'accepted_args'		=>	$accepted_args
			);
		}


	}
	
	$feedsync_hook = new hook();
?>

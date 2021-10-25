<?php

/**
 * A simple class extending PHP default date time class to recognise all date time patterns across multiple feed types for FeedSync.
 * @since 3.4.5
 * 
 */
class Feedsync_DateTime extends DateTime {


    /**
     * Supported date time formats in feedsync.
     * 'pattern for datetime format'    =>  'date time format compatible with dateTime class'
     */
    public static function fs_get_regex_patterns() {
        
        $patterns = [
            '(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})'   =>  'Y-m-d H:i:s',
            '(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})'   =>  'Y-m-d\TH:i:s',
            '(\d{4})-(\d{2})-(\d{2})-(\d{2}):(\d{2}):(\d{2})'   =>  'Y-m-d-H:i:s',
            '(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2}).(\d{1,6})'   =>  'Y-m-d\TH:i:s.u',
            '(\d{4})-(\d{2})-(\d{2}) (\d{2})-(\d{2})-(\d{2})'   =>  'Y-m-d H-i-s',
            '(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})'   =>  'Y-m-d-H-i-s',
            '(\d{4})-(\d{2})-(\d{2})T(\d{2})-(\d{2})-(\d{2})'   =>  'Y-m-d\TH-i-s',
            '(\d{4})-(\d{2})-(\d{2})'                           =>  'Y-m-d'
        ];

        return $patterns;
    }

    /**
     * Returns the matching dateTime format for input date time string
     */
    public static function fs_get_format_from_datetime( $datetime = '' ) {

        $patterns = self::fs_get_regex_patterns();
        $format = false;

        if( empty( $datetime ) ) {
            return $format;
        }

        foreach( $patterns as $pattern =>   $pattern_format ) {

            if( preg_match('/^'.$pattern.'$/', $datetime ) ) {
                $format = $pattern_format;
                break;
            }
        }

        return $format;
    }

    /**
     * Returns the datetime object for date time string.
     */
    public static function fs_create_from_format( $datetime ) {
        
        $format = self::fs_get_format_from_datetime( $datetime );

        return self::createFromFormat($format, $datetime );
    }

    public static function fallback( $date, $format='Y-m-d H:i:s' ) {

        // supress any timezone related notice/warning;
        error_reporting(0);
        $date_example = '2014-07-22-16:45:56';

        $pos = strpos($date, 'T');

        if ($pos !== false) {
            $date = new dateTime($date);
            return $date->format('Y-m-d H:i:s');
        } else {
            $tempdate = explode('-',$date);
            $date = $tempdate[0].'-'.$tempdate[1].'-'.$tempdate[2];

            if( isset($tempdate[3]) ) {
                $date .= ' '.$tempdate[3];
            }
            
            return  date($format,strtotime($date) );
        }
    }

    /**
     * method to convert recognisable date time formats to another format with fallback for unrecognised format.
     */
    public static function fs_convert_format( $datetime, $format='Y-m-d H:i:s' ) {

        $date = self::fs_create_from_format( $datetime );
        
        // if datetime format is recognised, returned the formatted date.
        if( $date instanceof DateTime ) {

            return $date->format( $format );
            
        } else {

            return self::fallback( $datetime );
        }

    }
    
}
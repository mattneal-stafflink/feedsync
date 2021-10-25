=== FeedSync XML Pre-Processor ===
Author URI: https://www.realestateconnected.com.au/
Plugin URI: https://easypropertylistings.com.au/extensions/feedsync/
Contributors: mervb
Tags: importer, reaxml, real estate, property, jupix, blm, eac, expert agent, rockend rest, xml2u
Donate link: https://easypropertylistings.com.au/support-the-site/

Stable tag: 3.5.4

License: GNU Version 2 or Any Later Version

Use FeedSync to merge and process real estate listing formats like REAXML, Jupix, BLM, xml2u into a dynamic data url that you can import into your Real Estate website, property portal or custom application.

== Description ==

FeedSync lets you quickly process merge and process real estate listing formats like REAXML, Jupix, BLM, xml2u into a dynamic data url that you can import into your custom application or WordPress website project and display your clients real estate property listings quickly and easily.

This is the only real estate data Pre-Processor that you can install yourself and it will automatically add Geocode coordinates (Latitude/Long ) to your property, elements during processing.

What you get with FeedSync REAXML pre-processor:
* Quick to install
* Low server usage
* Work with all REAXML elements
* Automatic Geocoding during import
* Merge XML files into specific use import output files
* Save merged properties into: Current, Sold/Leased, Withdrawn/Offmarket
* All contents of the REAXML elements copied
* Simple GUI to review FeedSync status
* Easy to update the software
* Software support
* Set and forget
* Output a List of agents for import.
* Filter listings by office id, street, suburb, state, postcode, country.
* Output listings by current date.
* Output listings by number of days back.

== Installation ==
Uncompress your feedsync.zip and upload the feedsync directory into a directory on your server like public_html/XML/

Rename config-sample.php to config.php and configure your database settings. Next visit the feedsync directory from your browser.

*Advanced Feature*

Add this to the config.php file to disable and hide the settings page:
define('FEEDSYNC_SETTINGS_DISABLED' , true );

Add this to the config.php file to disable and hide the help pages:
define('FEEDSYNC_HELP_DISABLED' , true );

Add this to the config.php file to display errors:
define('FEEDSYNC_DEBUG_DISPLAY', true );

== Change log ==

3.5.4, October 13, 2021

* New: We are now tracking image id so if the image id's order is changed then the image mod time is incremented which will trigger an update.

3.5.3, October 4, 2021

* Tweak: Added a global notice for the mbstring PHP library requirement.
* Tweak: Added the mbstring php requirement to the system info page.
* Fix: Carriage return XML encoding character.

3.5.2, July 29, 2021

* Fix: XML Recovery process added in the event the XML has some unknown characters that could prevent processing the file.

3.5.1, July 16, 2021

* New: Support for FeedSync plugin system to add additional functionality to FeedSync through plugins.
* Tweak: Update help page with info on per_page setting to limit the output of records. Default records is 1000 in the output URL.
* Fix: Pagination notices with PHP 8.

3.5, May 31, 2021

* Extensive upgrades and enhancements throughout FeedSync to make XML processing a seamless process.
* New: Manage listing status with a click with FEEDSYNC_EDIT set true in config.php.
* New: Error reporting and display on installation to assist with issue diagnosis.
* New: Summary row displaying listing counts.
* New: Option to define folder permissions in config.php using FEEDSYNC_FOLDER_PERMISSIONS constant.
* New: Database upgrading class added to streamline new installs and upgrade database process.
* New: Listing Info page displaying attached images, image sizes and resolution along with map location.
* New: Deleting listings are now moved to a deleted status instead of removing.
* New: WordPress publishing settings to set a new feedsyncPostStatus field to allow incremental importing. Incremental import scripts required and use of status=all is required.
* New: Commands to export by range using &days_range=100-365 output listings that have mod date in range between specified days.
* New: Commands to export by days before using &days_before=100-365 output listings for that have mod date smaller than then specified days.
* New: XML2U Format added. Supports multiple location fetching.
* New: REAXML FTP Fetch option. Allows xml fetching from FTP location and processing. Files can be deleted once fetch has completed.
* New: Support for New Zealand REAXML format address differences.
* New: Processed files are now stored in YYYY/MM folders.
* New: Multiple agent_id supported as comma separated.
* Tweak: Jupix Format - Added option for let agreed & Sold STC listing status.
* Tweak: REAXML Format - Use google geocode if no geo fields in extra fields.
* Tweak: Expert Agent Format: Improvements to status handling and options to alter them.
* Tweak: Jupix Format: Improvements to status handling and options to alter them.
* Tweak: Improvements to date processing and detecting a wide range of date formats.
* Tweak: Escape external links, escaping unwanted chars in URL.
* Tweak: Log files are now .txt instead of .log file extension.
* Tweak: Use of COOKIEPATH constant based on site url.
* Tweak: Removed gettext dependency.
* Tweak: Random access key is set during installation.
* Tweak: Implemented additional checks before renaming files.
* Tweak: Context prefix in server path check.
* Tweak: Set a random access key during installation.
* Fix: Permission denied notices while saving files.
* Fix: Hidden file permissions issue.
* Fix: Pagination divide by zero warning.

3.4.4, April 14, 2020

* Tweak: Permission check before saving XML.
* Tweak: Load updater on demand to optimise page load speed.
* Fix: Set a default setting for is_request_available set to yes for fresh installs.

3.4.3, April 3, 2020

* Fix: Undefined error relating to FEEDSYNC_DEBUG_DISPLAY constant.
* Fix: Removed depreciated and unused FILTER_FLAG_HOST_REQUIRED emailing function.
* Fix: Updater notice error.

3.4.2, March 28, 2020

* New: Checks geocode status prior to force geocoding, avoiding null coordinates.
* Tweak: Suppress permission error while saving.
* Tweak: Replacing iconv PHP module for encoding MS quotes.
* Tweak: Delete geocode API cookie, in case API is resaved.
* Tweak: Hide geocode button if API key is not set.
* Fix: Stricter upgrade functions to help older versions upgrading to latest failing on some servers.
* Fix: Pagination divide by zero warning.

3.4.1, March 12, 2020

* Tweak: Build version to 3.4.1 to allow sites running pre-release 3.4 versions to update to official latest version.

3.4, March 12, 2020

* New: Ability to process coordinates for a specific listings by pressing the map icon.
* New: Support for geo Latitude and Longitude supplied in extra fields by some providers.
* New: Add price display yes to data feeds when not provided as this is the default.
* New: curl in feedsync updater.
* New: Log empty files to logging system.
* New: Added http library to handle remote requests.
* New: Replace cURL with http requests.
* New: Convert first date as per modified time and implement better timezone conversions.
* New: Database structure created replacing EZ SQL.
* New: Database upgraded to utc8.
* New: Pagination library replaced.
* New: Debug constant added, use define('FEEDSYNC_DEBUG_DISPLAY', true ); in the config.php file to enable it.
* New: Display system wide notice in footer, if required PHP modules are not loaded.
* Tweak: File permissions changed to be more consistent across different servers.
* Tweak: On clean install, logging enabled by default and log records increased to 1,000 entries.
* Tweak: On clean install, Access key set to enabled by default.
* Tweak: Session path variable made independent of SITE_URL constant.
* Tweak: Improvements to creating a unique ID for each listing.
* Fix: Reconfigured server and browser sessions to better allow upgrade process to not log the user out.
* Fix: Correct file permissions to 644 once updated.
* Fix: Spelling issue in log files.
* Fix: Log files were downloading as .xml now correctly altered to .log files.
* Fix: Server out of memory issue in case of large data set.
* Fix: Login issue fixed when logging to WordPress on same server.
* Fix: Curl error, incomplete curl response, connection closed by server.
* Fix: Suppress notices when licensing server is not responding, preventing unnecessary error log entries.
* Fix: Row counter.
* Fix: Encoding issues of html entities.
* Fix: Logging page missing Footer info when empty.
* Fix: Session overlapping of multiple FeedSync installations on same server.
* Fix: Upgrade bug fix.
* Fix: Footer area added to imported and agents pages.
* Fix: HTML decoding removed for existing xml files. Preventing xml loading error.
* Fix: Jupix Format - Improvements to file splitting process.
* Fix: BLM - Format - Improvements to file splitting process.
* Fix: Added check for empty option objects to prevent unnecessary error messages.
* Fix: Strict UTF-8 data output parsing preventing errors with ASCII supplied data.

3.3, December 3, 2018

* New: Database conversion to ensure better formatting of characters like bullets. Database upgrade is run on first file processing.
* New: Removed MLS functionality due to legality of providing MLS connection system.
* New: All Formats - Implemented a minutes_back command to the output URL.
* Tweak: EAC Format - Altered Test and Live API locations.
* Tweak: EAC Format - Improved importing system and optimised data fetching.
* Tweak: BLM Format - Implemented systems to handle missing listings and mark as withdrawn or offmarket.
* Tweak: Expert Agent Format - Implemented systems to handle missing listings and mark as withdrawn or offmarket.
* Tweak: Improved date formatting for importing dates that are formatted with a T separator 2018-11-19T00:00:00
* Fix: Corrected the first date sorting column when reviewing listings.
* Fix: Improvements to displaying images with upper and lower case file extension formatting.
* Fix: Replaced glob with get_files_list for better compatibility with servers such as Alpine Linux..
* Fix: For depreciated Autoloader in PHP 7.2.
* Fix: Undefined variables for updater.

3.2.2, March 23, 2018

* New: All Formats - Adjustment to the updater class and send an email notice if configured.
* Tweak: EAC Format - Updated EAC API to detect http or https.
* Tweak: EAC Format - Added support for RET (Retirement) property type.
* Tweak: All Formats - Correct file processing permissions to 644.
* Tweak: All Formats - Update agent details if changed and log results.
* Tweak: All Formats - Added a notice for PHP 5.6 requirement.
* Fix: BLM Format - Correctly process BLM files in Zip Format.
* Fix: Corrected an issue when FeedSync is installed on a server with LightSpeed database.

3.2.1, October 23, 2017

* Tweak: Order files be date before processing, especially handy when testing lots of import files.
* Tweak: Adjusted error handler for sending email notification allowing error to be correctly logged to error.log file.
* Fix: Correction in detecting latest image modified time and mapping to node.
* Fix: Corrected Rockend REAXML processing error due to image re-writing.
* Fix: MLS Format - Corrected an issue with processing imports due to logging.

3.2, October 17, 2017

* New: Ability to hide the settings page by defining FEEDSYNC_SETTINGS_DISABLED to true in the config.php file.
* New: Ability to hide the help pages by defining FEEDSYNC_HELP_DISABLED to true in the config.php file.
* New: Ability to delete agents now when FEEDSYNC_RESET is enabled.
* New: EAC Format - Support for the PHOTO_DATE node to track image modified time mapped to feedsyncImageModtime node.
* New: All Formats - Normalise Sold date format during processing.
* New: REAXML Format - Support for the unit and lot numbers when viewing your listings.
* New: REAXML Format - Better support for multi office processing of agentID when listings have the same uniqueID.
* New: Logging system to track listing processing steps along with processing type.
* New: Logging tab to display processing results and download detailed log reports. Files saved to logs directory.
* New: Automatically set file permissions during processing.
* New: Logging Error handling system implemented allowing notification by email of FeedSync processing errors.
* New: Upgrade enhanced to automatically update existing database during update process.
* New: Jupix Format - Setting to set the status of undetermined listings.
* New: Jupix Format - Better support for Jupix format status.
* New: Expert Agent Format - Better support for Expert Agent listing types.
* New: Filter and search listings.
* New: During update FeedSync will upgrade your database to the new version.
* New: Base64 Image converter implemented to convert base64encoded images to image files and add to the listing.
* New: Ability to filter listings by listing agent username. Append &#38;listing_agent=first-last to filter listings.
* Tweak: Better support for Microsoft Azure folders.
* Tweak: All Formats - When using ?date=today listings will output based on your specified timezone in settings.
* Tweak: Logger for debugging made available globally for internal usage.
* Tweak: All Formats: Renamed the feedsync_image_modtime node to feedsyncImageModtime for consistency.
* Tweak: REAXML Format: During processing the latest image modified time is now used and mapped to feedsyncImageModtime.
* Tweak: Display a notice if your username or password is incorrect on the login page.
* Tweak: Jupix Format - Alter status to FeedSync format during processing.
* Tweak: Enhancements made to the Help page depending on the selected format.
* Fix: REAXML Format: Re-ordering of images caused an error when only one image was detected.

3.1.2, August 7, 2017

* Fix: Featured Listing Star icon corrected now using html character code.
* Fix: Implemented adjustments for Microsoft Azure Server compatibility.
* Tweak: Expert Agent Format - Corrected handling of incremental updates allowing storing of historical listings.

3.1.1, July 6, 2017

* Fix: Jupix Format - Updated xml was not getting saved correctly preventing listing updates.

3.1, July 4, 2017

* New: Notices system to notify users of settings input and display error messages when needed.
* New: Adjusted the displayed notices when resetting the FeedSync database.
* New: REAXML Format - Re-order the images when the primary image is not the first image.
* New: All Formats - Created a new image modified date/time feedsync node applied to all imported listings.
* New: All Formats - During update FeedSync will process all existing listings to set the image modified date/time.
* New: All Formats - Created a custom node so that Featured Listings can be controlled from FeedSync. Click the star to feature a listing.
* New: All Formats - Added the Office/Agent ID column for easier management of multi office setups.
* New: All Formats - Added sorting options to listings list.
* New: All Formats - Display a colour map icon when coordinates are set, hover over the icon to see the results.
* New: MLS Format - Added a listing count depending on the selected settings.
* Tweak: REAXML Format - With geocode disabled and your listing provider adds the lat/long coordinates, they are copied correctly.
* Tweak: Expert Agent Format - Adjust the department to different listing types.
* Tweak: Expert Agent Format - Implemented the FTP connection in the cron command.
* Tweak: Expert Agent Format - Implemented a 30 min cookie for fetching from Expert Agent source.
* Tweak: REAXML Format - Adjusted geocoding country processing.
* Tweak: REAXML Format - Adjusted existing geocoding data when present during processing.
* Tweak: MLS Format - Adjusted Residential Rental to rental listing type.
* Tweak: MLS Format - Removed the need for the agent ID when fetching listings.
* Tweak: MLS Format - agent id not required allowing import of all MLS listings.
* Tweak: BLM Format - Removing blank lines to file.
* Tweak: All Formats - Added a notice if PHP zip is not enabled.
* Fix: Added additional checks to non logged in users preventing access to listings.
* Fix: BLM Format - Address Lat/Long Coordinate Processing.
* Fix: BLM Format - Images correctly moved to output/images folder during processing.
* Fix: BLM Format - Assigning modified and first date to data during initial processing.

3.0.2, May 24, 2017

* Tweak: Implemented a check for the upgrades folder and will create if absent.
* Tweak: Added additional PHP modules checks to the System Status Page.
* Tweak: During upgrade will check for the hidden __MACOSX folder and remove.
* Tweak: EAC API added additional house category processing for Holiday Rental type.
* Tweak: Removed encoding from export to prevent html characters from being altered.
* Tweak: Exclude invalid and deleted status from export.

3.0.1, May 20, 2017

* Tweak: Deleting listings on sub categories and pages corrected with FEEDSYNC_RESET is enabled.
* Tweak: Pagination setting for Imported listings table now uses pagination setting.
* Tweak: Altered the 'true' settings to true without quotes in the config-sample.php file.
* Tweak: Corrected encoding of andbull; during processing to &bull;
* Tweak: Adjustments to wording of status messages.
* Tweak: Shortened listing table label Unique Id to ID.
* Tweak: Adjustment to Jupix format processing status.
* Fix: Implemented a check for the PHP iconv function to prevent error during install, most servers should have this PHP function enabled.
* Fix: Adjusted so that the cron trigger will process more than one file at a time.

3.0, May 17, 2017

* New: Login system which allows you to limit access to FeedSync by configuring a username and password from the config.php file. Default login is admin/password.
* New: Ability to add a secret access key to your FeedSync output URL. When enabled ensure your import URLs have the new access_key in the URL.
* New: EAC (Australia) format added, enabling import from EAC (Estate Agents Co-Operative) API into FeedSync ready for import.
* New: Rockend REST (Australia) native format added, enabling import from Rockent REST files into FeedSync ready for import.
* New: Jupix (UK) Format added, enabling import from Jupix URL into FeedSync ready for import.
* New: BLM (UK) Format added, enabling import from BLM format files into FeedSync ready for import.
* New: Expert Agent (UK) format added, enabling import from Expert Agent URL into FeedSync ready for import.
* New: MLS Matrix LAS (USA) Format added, enabling import from MLS Matrix (USA) LAS Format (beta into FeedSync ready for import.
* New: Several MLS vendors processing systems added allowing us to update FeedSync with support for other MLS vendors on request.
* New: Resetting and deleting of listing entries now possible when FEEDSYNC_RESET is true. To enable edit the config.php file.
* New: FeedSync settings are now stored in option database allowing settings to change from Settings page.
* New: PHP Gump added for field validations instead of requiring additional PHP modules.
* New: ZIP class added for processing zip files without requiring additional PHP modules.
* New: Upgrade processes added allowing copying of previous versions of FeedSync into the settings database during install.
* New: Update system added allowing FeedSync to be updated from the Easy Property Listings servers with a valid license key.
* New: Debugging and error system implemented allowing for diagnosis of issues and logging to file.
* New: Dual cron processing system implemented to handle EAC API and future API systems.
* New: Better coded the agent processing system for extracting agent details.
* New: Enabled Google Geocode API key setting as Google requires an API key for all mapping APIs'
* New: Updater system will notify you when logged in if an update is available.
* New: Status page to confirm server compatibility with FeedSync displaying directory permissions and PHP modules required.
* New: Cron commands when run in browser now output results of process.
* New: Automatically set write permissions on input, processed and output folders and log files.
* Tweak: Improvements to REAXML processing to handle new FeedSync features.
* Tweak: Import files will support any case provided, XML, xml, ZIP, zip.
* Tweak: Updated EZ_SQL code for PHP 7.1 support.
* Fix: Timezone issue with days_back option in output url.

2.2.2, May 8, 2017

* Tweak: Geocoding set to on as default.
* Tweak: Allows ZIP files in capital.
* Fix: Class compatibility fix with Easy MySQL library and PHP 7.
* Fix: Agent processing telephone numbers.

2.2.1, January 16, 2017

* Tweak: Prevent output of listings with the status of deleted as supplied by some providers.

2.2, April 22, 2016

* New: Filter listings using days_back allows you to output listings restricted by the number of days.
* New: Automatic skipping of empty xml files.
* New: Correctly process XML files with incorrect encoding.
* New: Added a FORCE_GEOCODE Constant to the config file which will enable re-processing of all coordinates if they are incorrect.
* New: Processing Missing Coordinates button will highlight orange when FORCE_GEOCODE is enabled.
* New: Automatic Skipping of incorrect Rockend REST format. Use RealEstate.com.au formate when configuring Rockend REST Exports.
* New: Improvements to licensing system to better display new available version and change log.
* Tweak: Improvements to date filter and timezone.
* Tweak: Correct coordinate generation of street address corrected for some REAXML formats.
* Tweak: Improved geocode request of full address.
* Tweak: Added additional help and adjusted links to documentation.
* Tweak: Updated help page link to correct http://codex.easypropertylistings.com.au/ pages.
* Tweak: Improved help page documentation.
* Fix: Undefined variable when no files are located in the processed folder.

2.1, September 10, 2015

* New: Config file has a place to save your providers FTP account details.
* New: Added listing agents processing and export capability which will also extract the listing agent office id, extract first name, last name.
* New: Added listing export filters to enable you to export by office id, street name, suburb, postcode, country.
* New: Output listings by current date.
* New: Timezone for directory imported list.
* New: Process listing agents for upgraders.
* New: Tab to display unique listing agents present in FeedSync database.
* New: Database upgrade button to allow exporting with the new address and office features.
* New: Blank entries are no longer output.
* Tweak: Sold and leased listings are red and current are green.
* Tweak: Improved Geocoder fallback for lat long coordinates.

2.0.3, June 18, 2015

* Tweak: Correctly process Console and Rockend REST leased, withdrawn and offmarket listings as they only send limited info which removes the listing contents. We now just change the status of these listings retaining the listing information.
* Tweak: Removed ZIP_FORMAT setting. FeedSync now processes zip and non zip formats automatically.
* Tweak: Settings for GEOCODE are no longer case sensitive for ON or on.
* Tweak: Improvements to permissions for server compatibility.
* Tweak: FeedSync now checks for output/images folder and creates if necessary when using ZIP_FORMAT for Console/REST REAXML formats.
* New: Image preview tab appears when using zip providers, good to check image import from the FeedSync Listing/Images tab.
* New: Added animation image and text during processing.
* New: Added ability to enable error_log FEEDSYNC_DEBUG.
* New: Additional settings added to config.php but defaults apply if you don't update your config.php file.

2.0.2, May 22, 2015

* Tweak: We have configured the default output action to omit withdrawn and offmarket listings. If you want to output all status types you can use status=all to the output command.
* New: Added status=all command to output all available status types including withdrawn and offmarket listings.

2.0.1, May 19, 2015

* New: Added pagination to list of listings to reduce memory usage when displaying thousands of listings.

2.0, May 16, 2015

* New: Complete rebuild of FeedSync to use a database for better file processing.
* New: Added first date field to improve importing and retaining original list date.

1.3.1, March 22, 2015

* Fix: Corrected issue in processing files with all CAPS .XML

1.3, February 13, 2015

* New: Added ability to output single merged file.
* Tweak: Moved licence checker to Easy Property Listings.

1.2, December 12, 2014

* Fix: Improved processing speed and can handle tens of thousands of records per hour.

1.1.4, August 08, 2014

* Fix: Added option to enable or disable REAXML Zip format

1.1.3, July 27, 2014

* Fix: Version numbering corrected and old files removed

1.1.2, July 27, 2014

* Bug: Status for leased/sold was not updating correctly.

1.1.1, June 27, 2014

* New: Added support for Rockend REST REAXML format.
* New: Ability to merge current listings when only a tag is changed to leased/sold. The REST REAXML format only sends a status change so we are now copying the last entry on the listing.

1.1 June 1, 2014

* New: Zip Support to process REAXML files saved as .zip with included images

1.0.2, March 27, 2014

* Changed download update link

1.0.1, March 25, 2014

* Added ability to have custom input and output directories so you can use your own output dir for security.
* Added additional instructions to user settings.php file. Created template functions.
* Added ability for end user to change header name from settings.php file.
* Created index.php files if input/output/processed directory

1.0.0, March 22, 2014

* Complete re-write and folder re-structure based on users feedback where feed providers would purge feed directory. So now your input files are nested within the application preventing accidental deletion.
* New directory Structure.

0.3.3, March 25, 2014

* Added Geocode options to user settings file.
* Added Template functions for easier usage.
* Added Licensing and Update Notification.
* Added page and ability to check for updates.
* Added template functions. Re-structured for larger real estate portal needs.
* Help file updated

0.3.2, March 24, 2014

* Created function to handle all XML elements.
* Added beta Software licensing. Re-named files.
* Added feedsync-templates.php functions

0.3.2, March 19, 2014

* Fixed bug with long file names not processing

0.3.1, Feb 20, 2014 Created Help page.

* Removed old files.
* Added Readme File. Created Geocode test option.

0.3, Feb 12, 2014

* Wrapped application in Bootstrap GUI.

0.2.1, October 9, 2013

* Fixed a bug and added output page so the user can review the feedSync process.
* Reset Content on this version.

0.2, September 27, 2013

* Renamed the source location from aggregated to > wc-processor, renamed mydesktop > imported.
* Made changes to all feedsync.php files. Added notes to feedSync.php Created History.txt

0.1

* Initial Release

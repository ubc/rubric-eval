<?php
/**
  Plugin Name: CTLT Rubric Evaluation
  Plugin URI: http://localhost/rubric_evaluation
  Version: 0.1
  Text Domain: ctlt_rubric_evaluation
  Description: Creates simple LMS
  Author: CTLT, loongchan
  Author URI: http://ctlt.ubc.ca
  Licence: GPLv2
 */

//make sure no direct link here
if ( ! defined( 'ABSPATH' ) )
    die( '-1' );
		
define('RUBRIC_EVALUATION_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RUBRIC_EVALUATION_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_admin.php');	//settings page
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_spreadsheet.php');	//grades
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_dashboard_widget.php');	//dashboard widget

//TODO: need to regsiter deactivation and deletion hook to clean up stuff, like options, taxonomies
//TODO: need to add settings to plugins page

<?php
/**
  Plugin Name: CTLT Rubric Evaluation
  Plugin URI: http://localhost/rubric_evaluation
  Version: 0.1
  Text Domain: ctlt_rubric_evaluation
  Description: Creates simple way to track people's posts and pages for marks
  Author: CTLT, loongchan
  Author URI: http://ctlt.ubc.ca
  Licence: GPLv2
 */

//make sure no direct link here
if ( ! defined( 'ABSPATH' ) )
    die( '-1' );
		

define('RUBRIC_EVALUATION_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RUBRIC_EVALUATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RUBRIC_EVALUATION_MARK_TABLE_SUFFIX', 'rubric_evaluation_mark');
define('RUBRIC_EVALUATION_TAXONOMY', 'ctlt_rubric_evaluation');
define('RUBRIC_EVALUATION_COLUMN_KEY', 'rubric_eval_column');

require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_util.php');	//utils consts, functions, etc should be first
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_admin.php');	//settings page
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_front.php');	//mark interactions
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_spreadsheet.php');	//grades
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_dashboard_widget.php');	//dashboard widget
require_once(RUBRIC_EVALUATION_PLUGIN_PATH.'class/class.rubric_evaluation_lists.php');	//other admin non setting pages

register_activation_hook(__FILE__, array('CTLT_Rubric_Evaluation_Util', 'ctlt_rubric_activate'));
//TODO: need to regsiter deactivation and deletion hook to clean up stuff, like options, taxonomies
//TODO: need to add settings to plugins page

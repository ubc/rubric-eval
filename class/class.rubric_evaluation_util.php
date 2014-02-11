<?php 

define('RUBRIC_EVALUATION_MARK_TABLE_SUFFIX', 'rubric_evaluation_mark');
define('RUBRIC_EVALUATION_TAXONOMY', 'ctlt_rubric_evaluation');


class CTLT_Rubric_Evaluation_Util {
	
	/**
	 * Returns the user's role based on user_id
	 * 
	 * @param string $user_id
	 * @return string 
	 */
	public static function ctlt_rubric_get_user_role($user_id = null) {
		if (is_null($user_id)) {
			$user_id = get_current_user_id();
			if (empty($user_id)) {
				return false;
			}
		}

		$user = new WP_User(get_current_user_id($user_id));
		$user_role = $user->roles;

		return reset($user_role);
	}
	
	/**
	 * Gets the mark
	 * 
	 * @param unknown $object_type
	 * @param unknown $object_id
	 * @param unknown $term_id
	 * @param number $user_id
	 * @return string|Ambigous <mixed, NULL, multitype:>
	 */
	public static function ctlt_rubric_get_mark($object_type, $object_id, $term_id, $user_id = 0) {
		global $wpdb;
	
		if (empty($object_type) || empty($object_id) || empty($term_id)) {
			return '';
		}
	
		$table_name = $wpdb->prefix . RUBRIC_EVALUATION_MARK_TABLE_SUFFIX;
		if ($user_id == 0) {
			$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE object_type = %s AND object_id = %d AND term_id = %d",
					$object_type,
					$object_id,
					$term_id
			);
		} else {
			$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE object_type = %s AND object_id = %d AND user_id = %d AND term_id = %d",
					$object_type,
					$object_id,
					$user_id,
					$term_id
			);
		}
	
		$result = $wpdb->get_row( $query );
		return $result;
	}
	
	/**
	 * Converts role to capabilities for this plugin
	 * 
	 * @param string $role
	 * @return if true, string.  if an error is produced, returns false
	 */
	public static function ctlt_rubric_get_capability_for_role($role = 'author') {
		$valid_roles = array('subscriber', 'contributer', 'author', 'editor', 'section_editor', 'administration');
		$return_value = false;
		if (!empty($role) && in_array($role, $valid_roles)) {
			switch ($role) {
				case 'administration':
					$return_value = 'activate_plugin';
					break;
				case 'section_editor':
				case 'editor':
					$return_value = 'moderate_comments';
					break;
				case 'author':
					$return_value = 'edit_published_posts';
					break;
				case 'contributer':
					$return_value = 'edit_posts';
					break;
				case 'subscriber':
					$return_value = 'read';
					break;
				default:
					$return_value = 'read';
					

			}
		}
		return $return_value;
	}
	
	/**
	 * Setup rubric eval DB Table
	 * 
	 * @return void
	 */
	public static function ctlt_rubric_activate() {
		global $wpdb;
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		 
		$table_name = $wpdb->prefix . RUBRIC_EVALUATION_MARK_TABLE_SUFFIX;
		$sql = "CREATE TABLE $table_name (
		id bigint(11) NOT NULL AUTO_INCREMENT,
		user_id bigint(11) NOT NULL,
		object_type varchar(40) NOT NULL,
		object_id bigint(11) NOT NULL,
		term_id bigint(11) NOT NULL,
		mark varchar(40) NOT NULL,
		deleted tinyint(1) NOT NULL DEFAULT '0',
		created datetime,
		modified datetime,
		PRIMARY KEY  (id) );";
		 
		dbDelta( $sql );
		add_option( 'rubric_evaluation_db_version', CTLT_Rubric_Evaluation_Admin::DB_VERSION );
	
	}
	
}
<?php
/**
 * started first with http://codex.wordpress.org/Creating_Options_Pages
* @author loongchan
*
*/
class CTLT_Rubric_Evaluation_Spreadsheet
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    private $roles;
    private $rubric;
    private $students;

    /**
     * Start up
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_filter( 'the_content', array($this, 'front_grade_box'));
 
        //get student role
  		$this->_setup_author_and_options();
	}

    /**
     * Add options page
     */
    public function add_plugin_page() {
    	$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
    	$teacher = $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'];
    	$student = $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'];
    	$ta = $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_ta'];
    	if ((isset($teacher) && ( $user == $teacher )) || (isset($ta) && ( $user == $ta ))) {
    		//add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    		add_submenu_page(
	    		'rubric_evaluation_settings',
	    		__('Spreadsheet', 'ctlt_rubric_evaluation'),
	    		__('Spreadsheet', 'ctlt_rubric_evaluation'),
	    		CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_capability_for_role($user),
	    		'rubric_evaluation_subpage_settings',
	    		array( $this, 'create_rubric_evaluate_page')
    		);
    	} elseif (isset($student) && ( $user == $student )) {
    		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    		add_menu_page(
	    		_x('Rubric Evaluation', 'page title', 'ctlt_rubric_evaluation'), //page title
	    		_x('Rubric Eval', 'menu title', 'ctlt_rubric_evaluation'), //menu title
	    		CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_capability_for_role($user), //capability
	    		'rubric_evaluation_settings', //slug
	    		array( $this, 'create_rubric_evaluate_page'), //output callback
	    		'dashicons-book' //icon
    		);
    	}
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        add_settings_section(
            'rubric_evaluation_spreadsheet_group', // ID
            __('Spreadsheet Section', 'ctlt_rubric_evaluation'), // Title
            array( $this, 'print_section_info' ), // Callback
            'rubric_evaluation_spreadsheet' // Page
        );  

        add_settings_field(
            'rubric_evaluation_spreadsheet', // ID
            __('Spreadsheet', 'ctlt_rubric_evaluation'), // Title 
            array( $this, 'output_spreadsheet' ), // Callback
            'rubric_evaluation_spreadsheet', // Page
            'rubric_evaluation_spreadsheet_group' // Section           
        );      
        
        register_setting(
	        'rubric_evaluation_spreadsheet', // Option group
	        'rubric_evaluation_spreadsheet_name', // Option name
	        array( $this, 'sanitize' ) // Sanitize
        );
        
        /*
         * cleans up student array
         * 1. if student, only show that student's data.
         * 
         * NOTE: done here because in construct too early!
         */
        $user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
        $student = $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'];
        if (isset($student) && ( $user == $student )) {
        	$current_user_id = get_current_user_id();
        	foreach ($this->students as $per_student) {	
        		if ($per_student->ID == $current_user_id) {
        			$this->students = array($per_student);
        			break;
        		} 
        	}
        }
    }

    //======================================================================
    //
    // Output functions
    //
    //======================================================================
    /**
     * Options page callback
     */
    public function create_rubric_evaluate_page()
    {
		// Set class property
		$this->options = get_option( 'my_option_name' );
    	?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php _e('Spreadsheet', 'ctlt_rubric_evaluation');?></h2>           
				<form method="post" action="options.php">
				<?php
					// This prints out all hidden setting fields
					settings_fields( 'rubric_evaluation_spreadsheet' );
    				do_settings_sections( 'rubric_evaluation_spreadsheet' );
					submit_button(); 
				?>
				</form>
			</div>
			<?php
	}

    /** 
     * Print the Section text
     */
    public function print_section_info() {
//         print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function output_spreadsheet()
    {
    	echo "<table class='spreadsheet'><tr><th>".__('Students', 'ctlt_rubric_evaluation')."</th>";
    	
    	//columns
    	$column_name = array();
    	if (!empty($this->rubric)) {
    		$column_name = array_keys($this->rubric['rubric_evaluation_rubric_name']);
    	} 
    	$terms = array();
    	foreach ($column_name as $cols) {
    		echo '<th>'.$cols.'</th>'; 
    		$terms[$cols] = get_term_by('name', $cols, RUBRIC_EVALUATION_TAXONOMY);
    	}
    	echo '<th>'.__('Total', 'ctlt_rubric_evaluation').'</th>';
    	echo '</tr>';

    	//rows
    	$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
    	$teacher = $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'];
    	foreach ($this->students as $row => $student_info) {
    		//need to make the link show up ONLY for teachers so they can edit students or tas
    		$user_link = $student_info->display_name;
    		if (isset($teacher) && ( $user == $teacher )) {
    			$user_link = '<a href="/wp-admin/user-edit.php?user_id='.$student_info->ID.'">'.$student_info->display_name.'</a>';
    		}
    		echo '<tr><td>'.$user_link.'</td>';
    		foreach ($column_name as $col => $mark) {    			
    			$id_name = 'rubric_evaluation_spreadsheet_value_'.($row + 1).'_'.($col + 1);
    			$value = 'a';
    			$term = $terms[$mark];
    			$term_description = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_term_meta($term);
				
				//get postID
    			//@TODO: need to make more generic, page or posts or custom_type
    			//@thanks http://www.webdevdoor.com/wordpress/get-posts-custom-taxonomies-terms/
    			$args = array(
    				'post_type' => $term_description['posttype'],
    				'tax_query' => array(
    					array(
    						'taxonomy' => RUBRIC_EVALUATION_TAXONOMY,
    						'field' => 'slug',
    						'terms' => array($term->slug)
    					)
    				)
    			);
    			$posts_array = array();
    			if ($term_description['posttype'] != 'page') {
					$posts_array = get_posts($args);
				} else {
					//page, so we need to filter it still....
					$page_array = get_pages($args);
					foreach($page_array as $page) {
						$page_terms = wp_get_object_terms($page->ID, RUBRIC_EVALUATION_TAXONOMY);
						if (!empty($page_terms)) {
							$posts_array[] = $page;
							break;
						}
					}
				}

    			$post_id = 0;
    			$post_url = '#na';
    			$post_title = __('Not Completed', 'ctlt_rubric_evaluation');
    			$author = $this->students[$row];
    			$author_id = $author->ID;
    			foreach ($posts_array as $post_info) {
    				if ($post_info->post_author == $author_id) {
    					$post_id = $post_info->ID;
    					$post_url = wp_get_shortlink($post_id);
    					$post_title = $post_info->post_title;
    						
    					//now add grade if applicable
    					$grade_mark = CTLT_Rubric_Evaluation_Front::get_rubric_evaluation_mark(get_post_type($post_info), $post_info->ID, $term->term_id);    					
    					if (!is_null($grade_mark)) {
    						$post_title .= ' ('.esc_attr($grade_mark->mark).')';
	    					$current_sub_mark = ($grade_mark->mark / CTLT_Rubric_Evaluation_Front::RUBRIC_EVAL_MAX_DROPDOWN_MARK) * ($this->rubric['rubric_evaluation_rubric_name'][$mark]['Total'] / 100);
	    					$this->students[$row]->total_mark = $this->students[$row]->total_mark + $current_sub_mark;

    					}
    				}
    			}
    			echo '<td>';
    			if ($post_id == 0) {
    				echo $post_title;
    			} else {
    				echo '<a target="_blank" href="'.$post_url.'">'.$post_title.'</a>';
    			}
    			echo '</td>';
    		}
    		//now to add total row
    		$grade = $this->students[$row]->total_mark;
    		echo '<td>'.($grade * 100).'%</td>';
    		
    		echo '</tr>';
    			
    	}
    	
    	echo "</table>";
    }

    //======================================================================
    //
    // Sanitization callback functions
    //
    //======================================================================
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
//     	error_log('sanitize spreadsheet input: '.print_r($input,true));
    	//         $new_input = array();
    	//         if( isset( $input['id_number'] ) )
    		//             $new_input['id_number'] = absint( $input['id_number'] );
    	//         if( isset( $input['title'] ) )
    		//             $new_input['title'] = sanitize_text_field( $input['title'] );
    
    		return $new_input;
    }
    
    
    //======================================================================
    //
    // Private functions
    //
    //======================================================================
    private function _setup_author_and_options() {
    	$this->roles = $this->options = $this->rubric = array();
    	
		//get options
		$options = get_option('rubric_evaluation_spreadsheet_name');
		if ($options !== false) {
			$this->options = $options;
		}
		
		//get roles
		$roles = get_option('rubric_evaluation_roles_settings');
		if ($roles !== false) {
			$this->roles = $roles;
			//@TODO: temp placeholder to force teacher to have some sort of role
			if (!isset($this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'])) {
				$this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_teacher'] = 'administrator';
			}
		}
		
		//get rubric
		$rubric = get_option('rubric_evaluation_rubric_name');
		if ($rubric !== false) {
			$this->rubric = $rubric;
		}
		
		//get students
		if ($roles !== false) { 
			$blog_id = get_current_blog_id();
			$fields = array('ID', 'user_login', 'user_nicename', 'display_name');
			$this->students = get_users(array('blog_id' => $blog_id, 'role' => $this->roles['rubric_evaluation_roles_settings']['rubric_evaluation_roles_settings']['rubric_evaluation_role_student'], 'fields' => $fields));
		} else {
			$this->students = array();
		}
	}
}


if( is_admin() )
	$ctlt_rubric_evaluation_spreadsheet = new CTLT_Rubric_Evaluation_Spreadsheet();
<?php
/**
 * started first with: http://codex.wordpress.org/Creating_Options_Pages
 * help with multi-section on one page with: http://www.mendoweb.be/blog/wordpress-settings-api-multiple-sections-on-same-page/
 * create tabs with: http://wp.tutsplus.com/tutorials/theme-development/the-complete-guide-to-the-wordpress-settings-api-part-5-tabbed-navigation-for-your-settings-page/
 * @author loongchan
 * 
 * TODO:
 * - link to spreadsheet via anchor for type column names?
 * - think about forcing usage of evaluate plugin
 * - deal with mu stuff.....
 * - deal with language stuff (make last priority) - LAST
 * - hide student weight for now?
 * - need to uninstall DB in uninstall.php
 * - make rubric evaluation column linkable for teachers
 * - think about whether to make new taxonomy editable.......
 * - detach taxonomy with everything else and add custom metabox to page/post/etc manually
 * - for class.front, need to make singular check for post types pulled form admin class???? 
 * - make list of terms ordered by duedate????? 
 * - need to calculate grades
 * 
 * - brake it up into smaller files.
 * - I think you can shorten the id to not include grade book
 * - more comments please... helps with readability of code... 
 * 
 *
 * PARTIALLY DONE:
 * - Only include css and js on pages that you need it. - only js done. need to do css (http://make.wordpress.org/core/handbook/coding-standards/css/)
 * - duedate is done on the front end for save-post.  can't create or edit a post and select term past duedate.
 * - - need to make saving post smarter:
 * - - - ensure that if you edit post past duedate, that it saves rubric eval term properly (don't change)
 * - do uninstall or disable plugin functions - partially done, need to deal with page
 * - need to display grades - does display, but is not pretty right now 
 * - need to customize dashboard for various roles - done, but need to make it show useful data
 * 
 * BUGS:
 *
 * 
 * DONE:
 * - rough out dashboard widget
 * - need a way to enter grades - DONE
 * - check that taxonomy is removed as per removal of rows! - DONE!
 * - start fleshing out spreadsheet - just the look, need to add data
 * - need to setup DB - done
 * - page listing shows rubric eval column even if no page taxonomies! - FIXED
 * - add datepicker for due date / extended date - Done (Due date only)
 * - using quick edit on page/post, removes the rubric eval info - FIXED
 * - need a way to edit taxonomy on edit/create post page into radio button - DONE
 * - need to add filter for posts/page list pages - done, but just for posts - DONE
 * - ids and classes shouldn't have underscores but dashes - DONE
 *  
 */
class CTLT_Rubric_Evaluation_Admin
{
    /**
     * Holds the values to be used in the fields callbacks
     */
	const DB_VERSION = "1.0";
	const MAX_ROWS = 20;
	const MAX_GRADES_DROPPED = 5;
    private $options;
    private $active_tab;
    private $rubric_headers = array();
    private $rubric_post_types = array();	//@TODO need to flesh out
    private $grading_types = array();

    /**
     * Start up
     */
    public function __construct() {
    	//setup rubric_headers
    	$this->rubric_headers = array(
    		__('Student Weight', 'ctlt_rubric_evaluation'),
    		__('Instructor Weight', 'ctlt_rubric_evaluation'),
    		__('Total', 'ctlt_rubric_evaluation')
    	);
    	$this->grading_types = array(
    		'Text',
    		'Dropdown'
    	);

        // Set class property
        $this->setup_options();
        $this->active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'display_advanced'? $_GET['tab'] : 'display_rubric');
        //setup action hooks
        add_action( 'admin_menu', array( $this, 'rubric_evaluation_menu' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'init', array( $this, 'create_taxonomy' ) );
       
        //@TODO: flesh out
        //setup rubric_post_types... need to modularize and make extandable
        $this->rubric_post_types = array('post', 'page');
        
        //register scripts (depends on google CDN!)
        wp_register_style('jquery-ui-1.10.1', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/smoothness/jquery-ui.css');
        wp_register_script('CTLT_Rubric_Evaluation_Settings_Script', RUBRIC_EVALUATION_PLUGIN_URL.'js/ctlt_rubric_settings.js', array('jquery', 'jquery-ui-datepicker'), false, true);
        wp_register_style('CTLT_Rubric_Evaluation_Css', RUBRIC_EVALUATION_PLUGIN_URL.'css/ctlt_rubric_evaluation.css');
    }

    /**
     * Add rubric_evaluation settings page
     */
    public function rubric_evaluation_menu() {
    	$user = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_user_role();
    	$teacher = $this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher'];
    	$student = $this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student'];
    	$ta = $this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-ta'];

    	if ((isset($teacher) && ( $user == $teacher )) || (isset($ta) && ( $user == $ta ))) {
    		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    		add_menu_page(
	    		_x('Rubric Evaluation', 'page title', 'ctlt_rubric_evaluation'), //page title
	    		_x('Rubric Eval', 'menu title', 'ctlt_rubric_evaluation'), //menu title
	    		CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_capability_for_role($user), //capability
	    		'rubric_evaluation_settings', //slug
	    		array( $this, 'create_rubric_evaluation_settings_page'), //output callback
	    		'dashicons-book' //icon
    		);
    	} elseif (isset($student) && ( $user == $student )) {
			// do nothing???
			//we don't want to show anything becuase students cannot do things!
    	}
    }

    public function setup_options() {
    	$rubric_evaluation_rubric_name = get_option('rubric-evaluation-rubric-name');
    	if ($rubric_evaluation_rubric_name !== false) {
    		$this->options[reset(array_keys($rubric_evaluation_rubric_name))] = $rubric_evaluation_rubric_name[reset(array_keys($rubric_evaluation_rubric_name))];
    	} else {
    		$this->options['rubric-evaluation-rubric-name'] = array();
    	}
    	$rubric_evaluation_roles_settings = get_option('rubric-evaluation-roles-settings');
    	if ($rubric_evaluation_roles_settings !== false && !empty($rubric_evaluation_roles_settings)) {
			$this->options[reset(array_keys($rubric_evaluation_roles_settings))] = $rubric_evaluation_roles_settings[reset(array_keys($rubric_evaluation_roles_settings))];
    	} else {
    		//set to default!  Should only be done when first installed
    		$this->options['rubric-evaluation-roles-settings'] = array('rubric-evaluation-roles-settings' => array(
	    			'rubric-evaluation-role-ta' => 'editor',
	    			'rubric-evaluation-role-student' => 'author',
	    			'rubric-evaluation-role-teacher' => 'administrator',
    			),
    				'rubric-evaluation-grading-type' => array('rubric-evaluation-grading-type' => 'Text'));
    		add_option('rubric-evaluation-roles-settings', array('rubric-evaluation-roles-settings' => $this->options['rubric-evaluation-roles-settings']));
    	}
    }

    /**
     * Register and add settings
     */
    public function page_init() {
		$this->setup_rubric_section();
		$this->setup_roles_section();
		$this->setup_grading_section();
    }

	public function setup_rubric_section() {
    	//===============
    	//Rubric section
    	//===============
    	add_settings_section(
	    	'rubric_evaluation_section_rubric', // ID
	    	__('Rubric Settings', 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'print_section_info' ), // Callback
	    	'rubric_evaluation_settings_rubric' // Page
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_rubric_values', // ID
	    	__('Rubric', 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_rubric' ), // Callback
	    	'rubric_evaluation_settings_rubric', // Page
	    	'rubric_evaluation_section_rubric' // Section
    	);

    	//======================
    	//Grading Group section
    	//======================
    	add_settings_section(
    		'rubric_evaluation_grading_group', 
    		__('Grading Group', 'ctlt_rubric_evaluation'), 
    		array($this, 'print_section_info'), 
    		'rubric_evaluation_settings_rubric'
    	);
    	
    	add_settings_field(
    		'rubric_evaluation_grading_group_field', 
    		__('Add Grading Group', 'ctlt_rubric_evaluation'), 
    		array($this, 'output_grading_group'), 
    		'rubric_evaluation_settings_rubric', 
    		'rubric_evaluation_grading_group'
    	);

    	register_setting(
	    	'rubric_evaluation_rubric', // Option group
	    	'rubric-evaluation-rubric-name', // Option name
	    	array( $this, 'sanitize_rubric' ) // Sanitize
    	);
    }
    
    public function setup_roles_section() {
		//get initial roles for various roles
    	$ta = (isset($this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-ta']) ? 
	    	$this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-ta'] :
	    	'editor');
    	$student = (isset($this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student']) ? 
	    	$this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-student'] :
	    	'author');
    	$teacher = (isset($this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher']) ? 
	    	$this->options['rubric-evaluation-roles-settings']['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher'] :
	    	'administrator');

    	//settings API display fields
    	add_settings_section(
	    	'rubric_evaluation_section_roles',
	    	__('Role Assignment', 'ctlt_rubric_evaluation'),
	    	array( $this, 'print_section_info'),
	    	'rubric_evaluation_settings_role'
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_teacher', // ID
	    	__("Teacher's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric-evaluation-role-teacher', $this->_get_role('rubric-evaluation-role-teacher', $teacher), 'disabled')
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_ta', // ID
	    	__("TA's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric-evaluation-role-ta', $this->_get_role('rubric-evaluation-role-ta', $ta))
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_role_student', // ID
	    	__("Student's Role", 'ctlt_rubric_evaluation'), // Title
	    	array( $this, 'output_roles'),
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_roles', // Section
	    	array('rubric-evaluation-role-student', $this->_get_role('rubric-evaluation-role-student', $student))
    	);
    	 
    	register_setting(
	    	'rubric_evaluation_roles', // Option group
	    	'rubric-evaluation-roles-settings', // Option name
	    	array( $this, 'sanitize_roles' ) // Sanitize
    	);
    }
    
    public function setup_grading_section() {
    	//===============
    	//grading type section
    	//===============
    	add_settings_section(
	    	'rubric_evaluation_section_grading',
	    	__('Grading Settings', 'ctlt_rubric_evaluation'),
	    	array( $this, 'print_section_info'),
	    	'rubric_evaluation_settings_role'
    	);
    	
    	add_settings_field(
	    	'rubric_evaluation_grading_value', // ID
	    	__("Grading Type", 'output_grading'), // Title
	    	array( $this, 'output_grading'), //Callback
	    	'rubric_evaluation_settings_role', // Page
	    	'rubric_evaluation_section_grading' // Section
		);
    }

    public function create_taxonomy() {
    	//register custom taxonomy if not set
    	if (!taxonomy_exists(RUBRIC_EVALUATION_TAXONOMY)) {
    		// Add new taxonomy, NOT hierarchical (like tags)
    		$labels = array(
    				'name'                       => _x( 'CTLT rubric_evaluation', 'Taxonomy General Name', 'ctlt_rubric_evaluation' ),
    				'singular_name'              => _x( 'CTLT rubric_evaluation', 'Taxonomy Singular Name', 'ctlt_rubric_evaluation' ),
    				'menu_name'                  => __( 'rubric_evaluation', 'ctlt_rubric_evaluation' ),
    				'all_items'                  => __( 'All Items', 'ctlt_rubric_evaluation' ),
    				'parent_item'                => __( 'Parent Item', 'ctlt_rubric_evaluation' ),
    				'parent_item_colon'          => __( 'Parent Item:', 'ctlt_rubric_evaluation' ),
   					'new_item_name'              => __( 'New Item Name', 'ctlt_rubric_evaluation' ),
   					'add_new_item'               => __( 'Add New Item', 'ctlt_rubric_evaluation' ),
   					'edit_item'                  => __( 'Edit Item', 'ctlt_rubric_evaluation' ),
    				'update_item'                => __( 'Update Item', 'ctlt_rubric_evaluation' ),
    				'separate_items_with_commas' => __( 'Separate items with commas', 'ctlt_rubric_evaluation' ),
    				'search_items'               => __( 'Search Items', 'ctlt_rubric_evaluation' ),
    				'add_or_remove_items'        => __( 'Add or remove items', 'ctlt_rubric_evaluation' ),
    				'choose_from_most_used'      => __( 'Choose from the most used items', 'ctlt_rubric_evaluation' ),
   					'not_found'                  => __( 'Not Found', 'ctlt_rubric_evaluation' ),
    		);
    		$args = array(
    				'labels'                     => $labels,
    				'hierarchical'               => true,
    				'public'                     => true,
    				'show_ui'                    => false,
    				'show_admin_column'          => false,
   					'show_in_nav_menus'          => false,
   					'show_tagcloud'              => false,
			);
			
    		//NOTE: need to change in uninstall.php as well, as it's currently hardcoded for posts only.
			register_taxonomy( RUBRIC_EVALUATION_TAXONOMY, array('post', 'page'), $args );
			return;
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
	public function create_rubric_evaluation_settings_page() {
		$current_screen = get_current_screen();
		if ($current_screen->parent_base != 'rubric_evaluation_settings') {
			return;
		}
		if ($this->active_tab === 'display_rubric') {
    		wp_enqueue_style('CTLT_Rubric_Evaluation_Css');
    		wp_enqueue_style('jquery-ui-1.10.1');
    		wp_enqueue_script('CTLT_Rubric_Evaluation_Settings_Script');
		}

		//add active class to tabs based on get parameter tab
		$active_rubric_class = $this->active_tab === 'display_rubric'? 'nav-tab-active' : '';
		$active_advanced_class = $this->active_tab === 'display_advanced'? 'nav-tab-active' : '';

    	?>
            <div class="wrap">
                <h2><?php _e('Rubric Evaluation Settings');?></h2>
                <?php 
                	if ($this->active_tab === 'display_advanced') {
						settings_errors('rubric-evaluation-roles'); 
					} else {
						settings_errors('rubric-evaluation-rubric');
					}
				?>    
                
                <h2 class="nav-tab-wrapper">

                	<a href="?page=rubric_evaluation_settings&tab=display_rubric" class="nav-tab <?php echo $active_rubric_class; ?>"><?php echo _x('Rubric', 'rubric tab title', 'ctlt_rubric_evaluation');?></a>
                	<a href="?page=rubric_evaluation_settings&tab=display_advanced" class="nav-tab <?php echo $active_advanced_class; ?>"><?php echo _x('Advanced', 'advanced tab title', 'ctlt_rubric_evaluation');?></a>
                </h2> 
                <form method="post" action="options.php">
                <?php
    				if ($this->active_tab === 'display_advanced') {
    					settings_fields( 'rubric_evaluation_roles' );
    					do_settings_sections( 'rubric_evaluation_settings_role' );
    				} else {
    					settings_fields('rubric_evaluation_rubric');
    					do_settings_sections( 'rubric_evaluation_settings_rubric' );
                    }
                    submit_button(); 
                ?>
                </form>
            </div>
            <?php
	}
        
    public function output_grading_group() {
    	?>
    	<a href="#add" class="button action-button" id="add-grading-group-btn">+ <?php _e('Grading Group', 'ctlt_rubric_evaluation');?></a>
    	<div class="add-grading-group" style="display:none;">
	    	<div class="grading-group-label">
	    		<label class="rubric-evaluation-grading-group-field"><?php _e('Label (eg.Assignment)', 'ctlt_rubric_evaluation');?></label>
	    		<br>
	    		<input id="rubric-evaluation-grading-group-field-label" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-field-label]" value="">
	    	</div>
			<div class="grading-group-note">
				<label class="rubric-evaluation-grading-group-field"><?php _e('Note', 'ctlt_rubric_evaluation');?></label>
				<br>
				<input id="rubric-evaluation-grading-group-field-note" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-field-note]" value="">
			</div>
			<div class="grading-group-duedate">
				<label class="rubric-evaluation-grading-group-field"><?php _e('Due Date', 'ctlt_rubric_evaluation');?></label>
				<br>
				<input id="rubric-evaluation-grading-group-field-duedate" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-field-duedate]" value="">
			</div>
			<!-- Hide this one for now as I don't think it quite makes sense -->
			<div class="grading-group-total" style="display:none;">
				<label class="rubric-evaluation-grading-group-field"><?php _e('% of Total', 'ctlt_rubric_evaluation');?></label>
				<br>
				<input id="rubric-evaluation-grading-group-field-total" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-field-total]" value="">
			</div>
			<br>
			<a href="#add-advanced" class="button action-button" id="add-grading-group-advanced-btn">+ <?php _e('Advanced Options', 'ctlt_rubric_evaluation');?></a>
			<div class="add-grading-group-advanced" style="display:none;">
				<div class="grading-group-advanced-droptop">
					<label class="rubric-evaluation-grading-group-advanced-field"><?php _e('Drop Top #', 'ctlt_rubric_evaluation');?></label>
					<br>
					<select id="rubric-evaluation-grading-group-advanced-field-droptop" class="rubric-evaluation-select" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-advanced-field-droptop]">
					<?php  
						$key_value = range(0,CTLT_Rubric_Evaluation_Admin::MAX_GRADES_DROPPED);
						//TODO: need to set the default value correctly!
						echo CTLT_Rubric_Evaluation_Util::ctlt_rubric_create_html_options(array_combine($key_value, $key_value), '0');
					?>
					</select>
				</div>
				<div class="grading-group-advanced-dropbottom">
					<label class="rubric-evaluation-grading-group-advanced-field"><?php _e('Drop Bottom #', 'ctlt_rubric_evaluation');?></label>
					<br>
					<select id="rubric-evaluation-grading-group-advanced-field-dropbottom" class="rubric-evaluation-select" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-advanced-field-dropbottom]">
					<?php  
						$key_value = range(0,CTLT_Rubric_Evaluation_Admin::MAX_GRADES_DROPPED);
						//TODO: need to set the default value correctly!
						echo CTLT_Rubric_Evaluation_Util::ctlt_rubric_create_html_options(array_combine($key_value, $key_value), '0');
					?>
					</select>
				</div>
				<div class="grading-group-advanced-posttype" >
					<label class="rubric-evaluation-grading-group-advanced-field"><?php _e('Post Type', 'ctlt_rubric_evaluation');?></label>
					<br>
					<select id="rubric-evaluation-grading-group-advanced-field-posttype" class="rubric-evaluation-select" name="rubric-evaluation-rubric-name[rubric-evaluation-grading-group-advanced-field-posttype]">
					<?php
						//@TODO need to figure out how to pull this stuff out.....
						echo CTLT_Rubric_Evaluation_Util::ctlt_rubric_create_html_options(array_combine($this->rubric_post_types, $this->rubric_post_types), 'post');
					?>
					</select>
				</div>
			</div>
		</div>
		<?php 
    }
    /**
     * Print the Section text
     */
    public function print_section_info() {
    	//         print 'Enter your settings below:';
    }

    public function output_roles($options) {
		$select_extras = (isset($options[2])? $options[2] : '');
		$value = isset($this->options['rubric-evaluation-roles-settings'][$options[0]])? $this->options['rubric-evaluation-roles-settings'][$options[0]] : $options[1];
    	echo "<select $select_extras id='".$options[0]."' name='rubric-evaluation-roles-settings[".$options[0]."]'>";
    	echo wp_dropdown_roles($value);
    	echo "</select>";
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function output_rubric() {
		$vertical_titles = !empty($this->options['rubric-evaluation-rubric-name'])? array_keys($this->options['rubric-evaluation-rubric-name']) : array();
		
		echo "<table class='rubric'>\n<tr><th>".__('Actions', 'ctlt_rubric_evaluation')."</th><th>".__('Type', 'ctlt_rubric_evaluation')."</th>";
    	//table horizontal headers
    	foreach ($this->rubric_headers as $h_title) {
			echo '<th class="Type '.$this->_sanitize_class_name($h_title).'">'.$h_title.'</th>';
		}
		echo "</tr>\n";
		
		//table rows
		$row = 1; //row count
		foreach ($vertical_titles as $v_title) {
			echo '<tr>';
			echo '<td class="action"><a href="#delete" class="ctlt-rubric-delete-row" data-row="'.$row.'">x</a></td>';
			echo '<td class="heading '.$this->_sanitize_class_name($v_title).'">'.
					'<label id="rubric-evaluation-rubric-label_'.$row.'" value="'.$v_title.'" />'.$v_title.'</label>'.
					'<input type="hidden" id="rubric-evaluation-rubric-values-'.$row.'" name="rubric-evaluation-rubric-name[rubric-evaluation-rubric-values-'.$row.']" value="'.$v_title.'"/>'.
				'</td>';
			for ($column = 1; $column < (sizeof($this->rubric_headers) + 1); $column++) {
				$value = isset($this->options['rubric-evaluation-rubric-name'][$v_title][$this->rubric_headers[($column - 1)]]) ? $this->options['rubric-evaluation-rubric-name'][$v_title][$this->rubric_headers[($column - 1)]] : '';
 				echo '<td class="'.$this->_sanitize_class_name($v_title).' '.$this->_sanitize_class_name($this->rubric_headers[($column - 1)]).'">';
				echo '<input type="text" id="rubric-evaluation-rubric-values-'.$row.'_'.$column.'" name="rubric-evaluation-rubric-name[rubric-evaluation-rubric-values-'.$row.'-'.$column.']" value="'.$value.'" />';
				echo '</td>';
        	}
        	echo '</tr>';
        	$row++;
        }
        echo '</table>';
    }
	
	public function output_grading() {
		$selected = 'Text';
		$options = get_option('rubric-evaluation-roles-settings');
		if (isset($options['rubric-evaluation-roles-settings']['rubric-evaluation-grading-type']['rubric-evaluation-grading-type'])) {
			$selected = $options['rubric-evaluation-roles-settings']['rubric-evaluation-grading-type']['rubric-evaluation-grading-type'];
		}
		echo "<select id='rubric-evaluation-grading-type' name='rubric-evaluation-roles-settings[rubric-evaluation-grading-type]'>";
		$keyval = array();
		foreach ($this->grading_types as $val) {
			$keyval[] = __($val, 'ctlt_rubric_evaluation');
		}
		
		echo CTLT_Rubric_Evaluation_Util::ctlt_rubric_create_html_options(array_combine($keyval, $keyval), $selected);
		echo "</select>";
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
     * @
     */
    public function sanitize_rubric( $input ) {
    	global $wp_taxonomies;
    	$new_input = array();

    	//format the rubric data
    	foreach (range(1, CTLT_rubric_evaluation_Admin::MAX_ROWS) as $row) {
    		if (isset($input['rubric-evaluation-rubric-values-'.$row])) {
    			$new_input[$input['rubric-evaluation-rubric-values-'.$row]] = '';
    
    			foreach (range(1, sizeof($this->rubric_headers)) as $column) {
    				if (isset($input['rubric-evaluation-rubric-values-'.$row.'-'.$column])) {
    					$new_input[$input['rubric-evaluation-rubric-values-'.$row]][$this->rubric_headers[($column - 1)]] = $input['rubric-evaluation-rubric-values-'.$row.'-'.$column];
    				}
    			}
    		}
    	}

    	/** steps to add new labels
    	 * 0. need to check for deleted stuff!
    	 * 1. check required and validate fields for new field (currently only label???)
    	 * 2. add to taxonomy term
    	 * 3. add to rubric data so that it saves properly with new line if appropriate
    	 */

    	//step 0
    	$submitted_table_types = array_keys($new_input);
		$tax = get_taxonomies(array('name' => RUBRIC_EVALUATION_TAXONOMY));
		$taxterms = get_terms($tax, array('hide_empty' => false), 'names', 'and');
    	$saved_table_types = array();
    	foreach( $taxterms as $key => $term) {
			$saved_table_types[] = $term->name;
		}
		$to_be_removed_terms = array_diff($saved_table_types, $submitted_table_types);

		foreach ($to_be_removed_terms as $index => $name) {
			$term_id = $taxterms[$index]->term_id;
			$worked = wp_delete_term($term_id, RUBRIC_EVALUATION_TAXONOMY);
			if (is_wp_error($worked)) {
				add_settings_error('rubric_evaluation_rubric', 'term_delete_failed', __('Failed to remove row(s).', 'ctlt_rubric_evaluation'));
			}
		}

  		//step 1
    	if (isset($input['rubric-evaluation-grading-group-field-label']) && !empty($input['rubric-evaluation-grading-group-field-label'])) {
			$has_errors = false;
			//need to make sure that if duedate is set that it needs to be in the future!			
			if (isset($input['rubric-evaluation-grading-group-field-duedate']) && !empty($input['rubric-evaluation-grading-group-field-duedate'])) {
				if (time() >= strtotime($input['rubric-evaluation-grading-group-field-duedate'])) {
					add_settings_error('rubric_evaluation_rubric', 'due_date_past', __('Please choose a due date that is in the future', 'ctlt_rubric_evaluation'));
					$has_errors = true;
				}
			}
			
			$term_description = array(
				'label' => $input['rubric-evaluation-grading-group-field-label'],
				'note' => $input['rubric-evaluation-grading-group-field-note'],
				'total' => $input['rubric-evaluation-grading-group-field-total'],
				'duedate' => $input['rubric-evaluation-grading-group-field-duedate'],
				'droptop' => $input['rubric-evaluation-grading-group-advanced-field-droptop'],
				'dropbottom' => $input['rubric-evaluation-grading-group-advanced-field-dropbottom'],
				'posttype' => $input['rubric-evaluation-grading-group-advanced-field-posttype']
			);
			$term_description = base64_encode(serialize($term_description));
			
			//step 2
			$added_term = '';
			if (!$has_errors) {
				$term_exists = term_exists($input['rubric-evaluation-grading-group-field-label'], RUBRIC_EVALUATION_TAXONOMY);
				if (is_null($term_exists) || !$term_exists){
					$added_term = wp_insert_term($input['rubric-evaluation-grading-group-field-label'], RUBRIC_EVALUATION_TAXONOMY, array('description' => $term_description) );
				} else {
// 					$added_term = wp_update_term($input['rubric-evaluation-grading-group-field-label'], RUBRIC_EVALUATION_TAXONOMY, array('description' => $term_description) );
				}
			}

			//now add to mucked array
			if (!empty($added_term) && !is_wp_error($added_term) ) {
				//step 3
				foreach (range(1, sizeof($this->rubric_headers)) as $column) {
	    			$new_input[$input['rubric-evaluation-grading-group-field-label']][$this->rubric_headers[($column - 1)]] = 0;
				}
				add_settings_error('rubric-evaluation-rubric', 'term_updated', __('Successfully added new row', 'ctlt_rubric_evaluation'), 'updated');
			} else {
				add_settings_error('rubric-evaluation-rubric', 'add_term_error', __('Problem adding new row', 'ctlt_rubric_evaluation'));
			}
		}
    	//format the add new row stuff
    	return array('rubric-evaluation-rubric-name' => $new_input);
    }
    
    public function sanitize_roles( $input ) {
    	$new_input = array();
    	foreach($input as $role => $selected_role) {
			if (stristr($role, 'rubric-evaluation-role-')) {
	    		$new_input['rubric-evaluation-roles-settings'][$role] = $selected_role;
    		} elseif (stristr($role, 'rubric-evaluation-grading-type')) {
    			$new_input['rubric-evaluation-grading-type'][$role] = $selected_role;
    		}
    	}
    	
    	//hardcode teacher to admin for now.....
    	$new_input['rubric-evaluation-roles-settings']['rubric-evaluation-role-teacher'] = 'administrator';

    	return array('rubric-evaluation-roles-settings' => $new_input);
    }

    public function save_rubric_evaluation( $post_id, $post, $update ) {

		// don't save for autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}
		
		// dont save for revisions
		if ( isset( $post->post_type ) && $post->post_type == 'revision' ) {
			return $post_id;
		}

		$taxterms = CTLT_Rubric_Evaluation_Util::ctlt_rubric_get_terms_for($post->post_type);
    	if (isset($_POST['rubric_eval_info']) && !empty($_POST['rubric_eval_info']) ) {
			$term_slug = '';
			foreach ($taxterms as $term) {
				if ($term->term_id == $_POST['rubric_eval_info']) {
					$term_slug = $term->slug;
					break;
				}
			}

			if (!empty($term_slug)) {
				wp_set_object_terms($post_id, $term_slug, RUBRIC_EVALUATION_TAXONOMY);
			}
			
		} else {	//since it is NOT set, we need to remove it (check for date for due date????)
			$terms = wp_get_object_terms($post_id, RUBRIC_EVALUATION_TAXONOMY);

			if (!empty($terms)) {
				$term = reset($terms);
				wp_remove_object_terms($post_id, $term->slug, RUBRIC_EVALUATION_TAXONOMY);
			}
		}
    }
    
    //======================================================================
    //
    // Private utility functions for the class
    //
    //======================================================================
    private function _get_role($id, $default_role) {
		return isset($this->option['role'][$id])? $this->option['role'][$id] : $default_role;
	}
	
	private function _sanitize_class_name($to_sanitize) {
		return preg_replace('/[^-_a-zA-Z0-9]+/','_', $to_sanitize);
	}
}

//I don't wrap around via is_admin because need to register taxonomy????
$ctlt_rubric_evaluation_settings = new CTLT_Rubric_Evaluation_Admin();
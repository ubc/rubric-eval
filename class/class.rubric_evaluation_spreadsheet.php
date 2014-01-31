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

    /**
     * Start up
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        
        //get student role
  		$this->_setup_author_and_options();
        $blog_id = get_current_blog_id();
        $fields = array('ID', 'user_login', 'user_nicename', 'display_name');
        $this->authors = get_users(array('blog_id' => $blog_id, 'role' => 'author', 'fields' => $fields));
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
    	//add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    	add_submenu_page(
	    	'rubric_evaluation_settings',
	    	__('Spreadsheet', 'ctlt_rubric_evaluation'),
	    	__('Spreadsheet', 'ctlt_rubric_evaluation'),
	    	'activate_plugins',
	    	'rubric_evaluation_subpage_settings',
	    	array( $this, 'create_rubric_evaluate_page')
    	);
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
        echo 'some ouitput for psreadsheet';
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
    	error_log('sanitize spreadsheet input: '.print_r($input,true));
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
			$this->roles = $roles['rubric_evaluation_roles_settings'];
		}
		
		//get rubric
		$rubric = get_option('rubric_evaluation_rubric_name');
		if ($rubric !== false) {
			$this->rubric = $rubric;
		}

		error_log('options: '.print_r($this->options,true));
		error_log('roles: '.print_r($this->roles,true));
	}
}


if( is_admin() )
	$ctlt_rubric_evaluation_spreadsheet = new CTLT_Rubric_Evaluation_Spreadsheet();
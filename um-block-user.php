<?php
/**
 * Plugin Name:       Block & Report User for Ultimate Member
 * Plugin URI:        https://www.mansurahamed.com/um-block-report-user/
 * Description:       Block any user from viewing your profile page or report any user to admin. Toxic user account will be automatically deactivated after getting blocked or reported frequently. 
 * Version:           1.0.2
 * Author:            mansurahamed
 * Author URI:        https://www.upwork.com/freelancers/~013259d08861bd5bd8
 * Text Domain:       um-block-user
 */


if(!class_exists('UMBlockUser'))
{
	class UMBlockUser
	{
		function __construct()
		{
			add_action('wp_enqueue_scripts', array(&$this, 'register_styles'),999999 );
			add_action('um_after_header_meta', array(&$this, 'buttons'));
			add_filter( 'um_profile_tabs', array( &$this, 'blocked_listing_profile_tab' ), 999999,1 );
			add_action( 'um_profile_content_blocked_default', array( &$this, 'blocked_listing_profile_tab_default' ) );
			add_filter( 'um_profile_tabs', array( &$this, 'reported_listing_profile_tab' ), 999999,1 );
			add_action( 'um_profile_content_reported_default', array( &$this, 'reported_listing_profile_tab_default' ) );
			add_filter( 'manage_users_columns', array( &$this,'user_table_add_title' ) );
			add_filter( 'manage_users_custom_column', array( &$this,'modify_user_table_row'), 10, 3 );
			add_action( 'template_redirect', array( &$this, 'access_redirect' ), 9999 );
			add_filter( 'um_prepare_user_results_array', array( &$this,'hide_in_directory'), 10, 1 );
			add_action( 'delete_user', array( &$this,'delete_user_meta_keys') );
		}
		
		public function register_styles() //register plugin css file
		{
			wp_register_style( 'um-block-user', plugins_url( '/assets/css/um-block-user.css', __FILE__ ) );
		}
		
		/**
		/*Redirects blocked user to home when they try to view profile
		/*@filter redirection url using 'ubu_blocked_redirect_url'
		*/
		function access_redirect()
		{		
			if($this->is_blocked(um_profile_id(),get_current_user_id()) )
			{
				wp_redirect(apply_filters('ubu_blocked_redirect_url',home_url()));
				exit;
			}
		}
		
		/**
		/*Hides user listing from directory for blocked user
		/*@disable this 'with upu_enable_hide_in_directory' filter returning false
		*/
		function hide_in_directory($user_ids)
		{
			if(!is_user_logged_in()) return $user_ids;
			if(apply_filters('upu_enable_hide_in_directory', true) === false) return $user_ids;
			$current_user = get_current_user_id();
			foreach($user_ids as $key => $user_id)
			{
				if($this->is_blocked($user_id,$current_user)) unset($user_ids[$key] );
			}
			return $user_ids;
		}
		
		/**
		/*Checks if a user is blocked by another
		/*@param $user_id1, integer - User ID of user who blocking
		/*@param $user_id2, integer - User ID of user who getting blocked
		/*@return true if $user_id2 is blocked else false
		*/
		function is_blocked($user_id1, $user_id2) 
		{
			if(!$blocked_users = get_user_meta($user_id1, 'um_blocked_user_list', true)) $blocked_users = array();
			return in_array($user_id2, $blocked_users);
		}
		
		/**
		/*Checks if a user is reported by another
		/*@param $user_id1, integer - User ID of user who reporting
		/*@param $user_id2, integer - User ID of user who getting reported
		/*@return true if $user_id2 is reported else false
		*/
		function is_reported($user_id1, $user_id2)
		{
			if(!$reported_users = get_user_meta($user_id1, 'um_reported_user_list', true)) $reported_users = array() ;
			return in_array($user_id2, $reported_users);
		}
		
		/**
		/*Blocks a user for another user
		/*@param $user_id1, integer - User ID of user who is blocking
		/*@param $user_id2, integer - User ID of user who is getting blocked
		*/
		function block($user_id1, $user_id2)
		{
			if(!$blocked_users = get_user_meta($user_id1, 'um_blocked_user_list', true)) $blocked_users = array();
			$blocked_users[] = $user_id2;
			update_user_meta($user_id1, 'um_blocked_user_list', $blocked_users);
			if(!$total_block = get_user_meta($user_id2, 'um_total_blocked_by', true)) $total_block = 0;
			$total_block++;
			update_user_meta($user_id2, 'um_total_blocked_by', abs($total_block)); //Maximum block until user is deactivated
			if($total_block > apply_filters('ubu_max_block_limit',10)) 
			{
				um_fetch_user($user_id2);
				UM()->user()->deactivate(); //Deactivate User
			}
			do_action('ubu_after_user_blocked',$user_id1, $user_id2); //Hook runs after user is blocked
		}
		
		/**
		/*Unblocks a blocked user
		/*@param $user_id1, integer - User ID of user who is blocking
		/*@param $user_id2, integer - User ID of user who is getting blocked
		*/
		function unblock($user_id1, $user_id2)
		{
			if(!$blocked_users = get_user_meta($user_id1, 'um_blocked_user_list', true)) $blocked_users = array();
			$new_arr = array_diff($blocked_users, (array)$user_id2);
			update_user_meta($user_id1, 'um_blocked_user_list', $new_arr);
			$total_block = get_user_meta($user_id2, 'um_total_blocked_by', true);
			$total_block--;
			update_user_meta($user_id2, 'um_total_blocked_by', abs($total_block));
			do_action('ubu_after_user_unblocked',$user_id1, $user_id2);
		}
		
		/**
		/*Reports a user by another
		/*@param $user_id1, integer - User ID of user who is reporting
		/*@param $user_id2, integer - User ID of user who is getting reported
		*/
		function report($user_id1, $user_id2)
		{
			if(!$reported_users = get_user_meta($user_id1, 'um_reported_user_list', true)) $reported_users = array() ;
			$reported_users[] = $user_id2;
			update_user_meta($user_id1, 'um_reported_user_list', $reported_users);
			if(!$total_report = get_user_meta($user_id2, 'um_total_reported_by', true)) $total_report = 0;
			$total_report--;
			update_user_meta($user_id2, 'um_total_reported_by', abs($total_report));
			if($total_report > apply_filters('ubu_max_report_limit',20)) //Maximum report until user is deactivated
			{
				um_fetch_user($user_id2);
				UM()->user()->deactivate(); //Deactivate user
			}
			do_action('ubu_after_user_report',$user_id1, $user_id2); //Hook runs after user is reported
		}
		
		/**
		/*Renders the block & report button
		/*@filter  'ubu_can_user_block' , to apply custom codition for user can block
		/*@filter 'block_button_active_color' , change the red color for blocked buttons
		*/
		function buttons()
		{
			$user_id = get_current_user_id();
			$profile_id = um_profile_id();
			wp_enqueue_style('um-block-user'); //Enqueue Style
			if(!is_user_logged_in()) return; //Logged out user can't have this feature
			if($user_id == $profile_id) return; //Own profile can't be blocked 
			if(user_can( $profile_id, 'manage_options' )) return; //Admins can't be blocked
			if(apply_filters('ubu_can_user_block',true) === false) return; //Custom block condition
			if(isset($_GET['ubu_action']))
			{
				switch($_GET['ubu_action'])
				{
					case 'block':
						$this->block($user_id,$profile_id);
					break;
					case 'unblock':
						$this->unblock($user_id,$profile_id);
					break;
					case 'report':
						$this->report($user_id,$profile_id);
					break;
				}
			}
			$red_color = apply_filters('block_button_active_color','background:red !important;');
			if($this->is_blocked($user_id, $profile_id)): ?>
            <a href="?ubu_action=unblock" class="um-block-btn um-button" style="<?php echo esc_attr($red_color); ?>"><?php echo esc_html__('Unblock','um-block-user') ?></a>
            <?php else: ?>
            <a href="?ubu_action=block" class="um-block-btn um-button"><?php echo esc_html__('Block','um-block-user') ?></a>
            <?php endif; 
            if($this->is_reported($user_id, $profile_id)): ?>
            <a href="#reported_user" id="reported_user" class="um-report-btn um-button" style="<?php echo esc_attr($red_color); ?>"><?php echo esc_html__('Reported','um-block-user') ?></a>
            <?php else: ?>
            <a href="?ubu_action=report" class="um-report-btn um-button"><?php echo esc_html__('Report','um-block-user') ?></a>
            <?php endif; 
		}
		
		function blocked_listing_profile_tab($tabs) //Add blocked profile tab
		{
			if(get_current_user_id()!=um_profile_id()) return $tabs;
			if( !get_user_meta(um_profile_id(),'um_blocked_user_list',true)) return $tabs;
			
			$tabs[ 'blocked' ] = array(
			'name'   => __('Blocked', 'um-block-user'),
			'icon'   => 'um-icon-eye-disabled',
			'custom' => true
			);
	
			UM()->options()->options[ 'profile_tab_' . 'blocked' ] = true;
	
			return $tabs;
		}
	
		function blocked_listing_profile_tab_default( $args ) { //blocked profile tab content
			if($blocked_list = get_user_meta(um_profile_id(),'um_blocked_user_list',true))
			{	
				foreach($blocked_list as $user_id)
				{ 
					um_fetch_user($user_id);
				?>
                	<div class="um-profile-blocked-listing-item">
						<a href="<?php echo esc_url(um_user_profile_url( $user_id )) ?>" title="<?php echo esc_attr(um_user('display_name')) ?>"><?php echo get_avatar($user_id,40); echo esc_html(um_user('display_name')) ?></a>
                        
                    </div>
					<?php
				}
			}
	
		}
		function reported_listing_profile_tab($tabs) //Add reports profile tab
		{
		
			if(get_current_user_id()!=um_profile_id()) return $tabs;
			if( !get_user_meta(um_profile_id(),'um_reported_user_list',true)) return $tabs;
			
			$tabs[ 'reported' ] = array(
			'name'   => __('Reported', 'um-block-user'),
			'icon'   => 'um-faicon-exclamation-circle',
			'custom' => true
		);
	
			UM()->options()->options[ 'profile_tab_' . 'reported' ] = true;
	
			return $tabs;
		}
	
		function reported_listing_profile_tab_default( $args ) //Reports profile tab content
		{
			if($reported_list = get_user_meta(um_profile_id(),'um_reported_user_list',true))
			{	
				foreach($reported_list as $user_id)
				{ 
					um_fetch_user($user_id);
				?>
					<div class="um-profile-blocked-listing-item">
						<a href="<?php echo esc_url(um_user_profile_url( $user_id )) ?>" title="<?php echo esc_attr(um_user('display_name')) ?>"><?php echo get_avatar($user_id,40); echo esc_html(um_user('display_name')) ?></a>
						
					</div>
					<?php
				}
			}
		}
		//custom columns in user table
		function user_table_add_title( $column )
		{
			$column['block'] = esc_html__('Block', 'um-block-user');
			$column['report'] = esc_html__('Report', 'um-block-user');
			return $column;
		}
		//custom column content
		function modify_user_table_row( $val, $column_name, $user_id )
		{
			switch ($column_name)
			{
				case 'block' :
					return (int)esc_html(get_user_meta( $user_id, 'um_total_blocked_by', true ));
				case 'report' :
					return (int)esc_html(get_user_meta( $user_id, 'um_total_reported_by', true ));
				default:
			}
			return $val;
		}
		//Delete User Meta at user delete
		function delete_user_meta_keys($user_id)
		{
			delete_user_meta($user_id, 'um_blocked_user_list');
			delete_user_meta($user_id, 'um_reported_user_list');
			delete_user_meta($user_id, 'um_total_blocked_by');
			delete_user_meta($user_id, 'um_total_reported_by');
		}

	}
}

if(function_exists( 'UM' )) $um_block_user = new UMBlockUser();  //Only if UM is active this plugin will work

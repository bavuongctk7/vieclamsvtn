<?php
/*
Plugin Name: Simplr User Registration Form Plus
Version: 2.4.5
Description: This a simple plugin for adding a custom user registration form to any post or page using shortcode.
Author: Mike Van Winkle
Author URI: http://www.mikevanwinkle.com
Plugin URI: http://www.mikevanwinkle.com/simplr-registration-form-plus/
License: GPL
Text Domain: simplr-registration-form
Domain Path: /lang/
*/


/*
 * TODO
 *
 * - Upgrade reCAPTCHA (PHP 5.3+) ?
 * - Support Shortcode UI.
 * - Switch to CMB2 some day?
 * - Auto-login user after registering/activating?
 * - Fix some PHP Notices that still exist.
 */


// constants
define("SIMPLR_URL", rtrim(plugins_url()) . '/' . plugin_basename(dirname(__FILE__)) );
define("SIMPLR_DIR", rtrim(dirname(__FILE__), '/'));

// setup options global
global $simplr_options;
$simplr_options = get_option('simplr_reg_options');

// Includes
include_once(SIMPLR_DIR.'/lib/fields.class.php');
include_once(SIMPLR_DIR.'/lib/fields-table.class.php');
include_once(SIMPLR_DIR.'/simplr_form_functions.php');
include_once(SIMPLR_DIR.'/simplr_reg_options.php');
require_once(SIMPLR_DIR.'/lib/profile.php');
require_once(SIMPLR_DIR.'/lib/messages.php');
require_once(SIMPLR_DIR.'/lib/wp-session-manager/wp-session-manager.php');
//require_once(SIMPLR_DIR.'/lib/login.php');

// API
add_action('wp_print_styles','simplr_reg_styles');
add_action('admin_init','simplr_admin_style');
add_action('init','simplr_admin_scripts');
add_action('admin_menu','simplr_reg_menu');
add_shortcode('register', 'sreg_figure');
add_shortcode('Register', 'sreg_figure');
add_shortcode('login_page','simplr_login_page');
add_shortcode('profile_page','simplr_profile_page');
add_action('admin_init','simplr_action_admin_init');
//add_action('init','simplr_reg_default_fields');
register_activation_hook(__FILE__, 'simplr_reg_install');
add_action('wp','simplr_fb_auto_login',0);
add_action('login_head','simplr_fb_auto_login');
add_filter('login_message','get_fb_login_btn');
add_action('login_head','simplr_fb_login_style');
add_action('init','simplr_register_redirect');
//add_action('template_redirect','simplr_includes');
add_action('login_footer','simplr_fb_login_footer_scripts');
add_action('wp','simplr_profile_redirect',10);
add_action('save_post', 'simplr_save_role_lock');


if( is_admin() ) {
	add_action( 'show_user_profile', 'simplr_reg_profile_form_fields' );
	add_action( 'edit_user_profile', 'simplr_reg_profile_form_fields' );
}

// moderation related hooks
if( @$simplr_options->mod_on == 'yes' ) {
	add_action('admin_action_sreg-activate-selected', 'simplr_activate_users');
	add_action('admin_action_sreg-resend-emails', 'simplr_resend_emails' );
	if( $simplr_options->mod_activation == 'auto' ) {
		add_action('wp','simplr_activation_listen');
	}
}

<?php

function simplr_validate($data,$atts) {

	global $wp_version;

	//include necessary files, not required for WP3.1+
	if( version_compare( $wp_version, "3.1", "<") ) {
		require_once(ABSPATH . WPINC . '/registration.php' );
		require_once(ABSPATH . WPINC . '/pluggable.php' );
	}

	//empty errors array
	$errors = array();

	//setup options and custom fields
	$options = get_option('simplr_reg_options');
	$custom = new SREG_Fields();

	//recaptcha check/
	if( isset($options->recap_on) AND $options->recap_on == 'yes') {
		$spam = recaptcha_check($data);
		if($spam != false) {
			$errors[] = $spam;
		}
	}

	// Make sure passwords match
	if('yes' == @$atts['password']) {
		if($data['password'] != $data['password_confirm']) {
			$errors[] = __('The passwords you entered do not match.', 'simplr-registration-form');
			add_filter('password_error_class','_sreg_return_error');
		} elseif(empty($data['password'])) {
			$errors[] = __('You must enter a password to register for this site.', 'simplr-registration-form');
			add_filter('password_error_class','_sreg_return_error');
		}
	}

	// Validate username
	if(!$data['username']) {
		$errors[] = __("You must enter a username.",'simplr-registration-form');
		add_filter('username_error_class','_sreg_return_error');
	} else {
		// check whether username is valid
		$user_test = validate_username($data['username']);
		if($user_test != true) {
			$errors[] .= __('Invalid Username.','simplr-registration-form');
			add_filter('username_error_class','_sreg_return_error');
		}
		// check whether username already exists
		$user_id = username_exists( $data['username'] );
		if($user_id) {
			$errors[] .= __('This username already exists.','simplr-registration-form');
			add_filter('username_error_class','_sreg_return_error');
		}
	} // end username validation


	// Validate email
	if(!$data['email']) {
		$errors[] = __("You must enter an email.",'simplr-registration-form');
		add_filter('email_error_class','_sreg_return_error');
	} elseif($data['email'] !== $data['email_confirm']) {
		$errors[] = __("The emails you entered do not match.",'simplr-registration-form');
	} else {
		$email_test = email_exists($data['email']);
		if($email_test != false) {
			$errors[] .= __('An account with this email has already been registered.','simplr-registration-form');
			add_filter('email_error_class','_sreg_return_error');
		}
		if( !is_email($data['email']) ) {
			$errors[] .= __("Please enter a valid email.",'simplr-registration-form');
			add_filter('email_error_class','_sreg_return_error');
		}
	} // end email validation

	if($atts['fields']) {
		$fields = explode(',',$atts['fields']);
		foreach($fields as $cf) {
			$field = @$custom->fields->custom[$cf];
			if($field['required'] == 'yes') {
				if($field['type'] == 'date') {
					if($data[$field['key'].'-mo'] == '' || $data[$field['key'].'-dy'] == '' || $data[$field['key'].'-yr'] == '') {
						$errors[] = $field['label'] . __(" is a required field. Please enter a value.", 'simplr-registration-form');
						add_filter($field['key'].'_error_class','_sreg_return_error');
					}
				} elseif(!isset($data[$field['key']]) || $data[$field['key']] == '' ) {
					$errors[] = $field['label'] . __(" is a required field. Please enter a value.", 'simplr-registration-form');
					add_filter($field['key'].'_error_class','_sreg_return_error');
				}
			}
		}
	}
  
  if (isset($atts['role'])) {
    $role_lock = get_post_meta(get_the_ID(), 'simplr_role_lock', true);
    if ($role_lock != '') {
      if ($atts['role'] != $role_lock) {
        $errors[] = __("Security: Role specified doesn't match form's designated role", 'simplr-registration-form');
      }
    } else {
      // find the role lock from the current content
      $role_lock = simplr_find_role_lock(get_the_content());
      if ($atts['role'] != $role_lock) {
        $errors[] = __("Security: Role specified doesn't match form's designated role", 'simplr-registration-form');
      }
    }
  }

	// Use this filter to apply custom validation rules. Example:
	// add_filter( 'simplr_validate_form', 'my_simplr_validate_form', 10, 3 );
	$errors = apply_filters('simplr_validate_form', $errors, $data, $atts );
	return $errors;
}

function sreg_process_form($atts) {
	//security check
	global $sreg;
	if( !$sreg ) { $sreg = new stdClass; }
	if (!wp_verify_nonce($_POST['simplr_nonce'], 'simplr_nonce') ) { die('Security check'); }

	$sreg->errors = simplr_validate($_POST,$atts);
  
	if( !empty($sreg->errors) ) {
		$sreg->message = $sreg->errors;
	}

	if(!@$sreg->message) {
		$sreg->output = simplr_setup_user($atts,$_POST);
	}
}


function simplr_setup_user($atts,$data) {
	//check options
	global $simplr_options, $wp_version;
  
	$custom = new SREG_Fields();
	$admin_email = @$atts['from'];
	$emessage = @$atts['message'];
	$role = @$atts['role'];
	if('' == $role) { $role = get_option('default_role', 'subscriber'); }
	if('administrator' == $role) { wp_die('Do not use this form to register administrators'); }
	if ( version_compare($wp_version, "3.1", "<" ) ) {
		require_once(ABSPATH . WPINC . '/registration.php' );
	}
	require_once(ABSPATH . WPINC . '/pluggable.php' );

	//Assign POST variables
	$user_name = @$data['username'];
	$fname = @$data['fname'];
	$lname = @$data['lname'];
	$user_name = sanitize_user($user_name, true);
	$email = @$data['email'];
	$user_url = @$data['url'];


	//This part actually generates the account
	if(isset($data['password'])) {
		$passw = $data['password'];
	} else {
		$passw = wp_generate_password( 12, false );
	}

	$userdata = array(
		'user_login' => $user_name,
		'first_name' => $fname,
		'last_name'  => $lname,
		'user_pass'  => $passw,
		'user_email' => $email,
		'user_url'   => $user_url,
		'role'       => $role,
	);
  
	// create user
	$user_id = wp_insert_user( $userdata );

	//multisite support add user to registration log and associate with current site
	if(defined('WP_ALLOW_MULTISITE') OR is_multisite())	{
		global $wpdb;
		$ip = getenv('REMOTE_ADDR');
		$site = get_current_site();
		$sid = $site->id;
		$query = $wpdb->prepare("
			INSERT INTO $wpdb->registration_log
			(ID, email, IP, blog_ID, date_registered)
			VALUES (%d, %s, %s, %d, NOW() )
			",$user_id,$email,$ip,$sid);
		$results = $wpdb->query($query);
	}

	//set users status if this is a moderated user
	if( is_object($simplr_options) && isset($simplr_options->mod_on) && $simplr_options->mod_on == 'yes' ) {
		$user_status = 2;
		$user_activation_key = $data['activation_key'] = md5( @constant('AUTH_SALT') . $user_email . rand() );
		global $wpdb;
		$wpdb->update($wpdb->users, array(
			'user_status'	=> $user_status,
			'user_activation_key'	=> $user_activation_key,
			), array('ID'=>$user_id), array('%d','%s'), array('%d') );
	}

	//Process additional fields (Deprecated)
	$pro_fields = get_option('simplr_profile_fields');
	if($pro_fields) {
		foreach($pro_fields as $field) {
			$key = $field['name'];
			$val = $data[$key];
			if(isset( $val )) { add_user_meta($user_id,$key,$val); }
		}
	}

	//save custom fields
	foreach($custom->custom_fields as $field) {
		if($field['type'] == 'date') {
			$dy = $data[$field['key'].'-dy'];
			$mo = $data[$field['key'].'-mo'];
			$yr = $data[$field['key'].'-yr'];
			$dateinput = implode('-', array($yr,$mo,$dy));
			update_user_meta($user_id,$field['key'],$dateinput);
		} else {
			if(isset($data[$field['key']])) {
				update_user_meta($user_id, $field['key'], $data[$field['key']]);
			}
		}
	}

	if(isset($data['fbuser_id'])) {
		update_user_meta($user_id, 'fbuser_id', $data['fbuser_id']);
	}

	//Save Hook for custom profile fields
	do_action('simplr_profile_save_meta', $user_id,$data);

	// if password was auto generated, add flag for the user to change their auto-generated password
	if(!@$data['password']) {
		$update = update_user_option($user_id, 'default_password_nag', true, true);
	}

	//notify admin of new user
	$userdata = get_userdata( $user_id );
	$data = array_merge( (array) $userdata->data , $data );
	simplr_send_notifications($atts,$data,$passw);

	$extra = __(" Please check your email for confirmation.", 'simplr-registration-form');
	$extra = apply_filters('simplr_extra_message', __($extra,'simplr-registration-form'));
	$confirm = '<div class="alert simplr-message success">' . __("Your Registration was successful.", 'simplr-registration-form') . $extra .'</div>';
  
	//Use this hook for multistage registrations
	do_action('simplr_reg_next_action', array($data, $user_id, $confirm));

	//return confirmation message.
	return apply_filters('simplr_reg_confirmation', $confirm);
}

/* Send notifications to admins and user when registering. */
function simplr_send_notifications($atts, $data, $passw) {
	global $simplr_options;
	$site = get_option('siteurl');
	$name = get_option('blogname');
	$user_name = @$data['username'];
	$email = @$data['email'];
	$notify = @$atts['notify'];
	$emessage = @$atts['message'];
	if ( isset( $simplr_options->default_email ) ) {
		$from = $simplr_options->default_email;
	} else {
		$from = get_option('admin_email');
	}

	/* Send mail to admins who are listed in the shortcode for notifications */
	$content = __("A new user has registered for", 'simplr-registration-form') . " " . $name."\r". __("Username", 'simplr-registration-form') . ": $user_name\r" . __("Email", 'simplr-registration-form') . ": $email \r\r";
	$fields = explode(',', $atts['fields']);
	if ( is_array($fields) && ! empty($fields) ) {
		foreach ( $fields as $field ) {
			foreach ( $data as $postfield => $postvalue ) {
				if ( $field == $postfield ) {
					$content .= $field . ": " . $postvalue . "\r";
				} else if ( $field . "-mo" == $postfield ) {
					$content .= $field . ": " . $postvalue;
				} else if ( $field . "-dy" == $postfield ) {
					$content .= "-" . $postvalue;
				} else if ( $field . "-yr" == $postfield ) {
					$content .= "-" . $postvalue . "\r";
				}
			}
		}
	}
	$headers = "From: $name <$from>\r\n";
	wp_mail($notify, __("A new user registered for", 'simplr-registration-form') . " " . $name, $content ,$headers);

	/* Send initial mail to the subscribed user */
	$emessage = $emessage . "\r\r---\r";
	if(!isset($data['password'])) {
		$emessage .= __("You should login and change your password as soon as possible.", 'simplr-registration-form') . "\r\r";
	}
	$emessage .= __("Username:", 'simplr-registration-form') . " $user_name\r";
	$emessage .= (isset($data['fbuser_id'])) ? __("Registered with Facebook", 'simplr-registration-form') : __("Password", 'simplr-registration-form') . ": $passw\r" . __("Login", 'simplr-registration-form') . ": $site";
	if( @$simplr_options->mod_on == 'yes' AND @$simplr_options->mod_activation == 'auto') {
		$data['blogname'] = get_site_option('blogname');
		$data['link'] = get_home_url( $blog_id, '/?activation_key='.$data['activation_key'] );
		$content = simplr_token_replace( $simplr_options->mod_email, $data );
		$subject = simplr_token_replace( $simplr_options->mod_email_subj, $data );
		wp_mail( $data['user_email'], $subject, $content, $headers);
	} else {
		$emessage = simplr_token_replace( $emessage, $data );
		wp_mail($data['email'],"$name - " . __("Registration Confirmation", 'simplr-registration-form'), apply_filters('simplr_email_confirmation_message',$emessage), $headers);
	}

}

function simplr_token_replace( $content, $data ) {
	global $blog_id;
	foreach( $data as $k => $v ) {
		if( is_array($v) OR is_object($v) ) {
			simplr_token_replace( $content, (array) $v );
		}
		if ( !is_array($v) ) {
			$content = str_replace( "%%{$k}%%" , $v, $content );
		}
	}
	return $content;
}

function simplr_build_form( $data, $atts ) {
	include_once(SIMPLR_DIR.'/lib/form.class.php');
	if( get_option('users_can_register') != '1' ) {
		print('Registrations have been disabled');
	} else {
		// retrieve fields and options
		$custom = new SREG_Fields();
		$soptions = get_option('simplr_reg_options');

		$fb_user = sreg_fb_connect();
		if( isset($fb_user) && is_array(@$fb_user)) {
			$fb_button = '<span="fb-window">' . __("Connected via Facebook as", 'simplr-registration-form') . ' <fb:name useyou="false" uid="'.$fb_user['id'].'" /></span>';
			$data['username'] = $fb_user['username'];
		} elseif( isset($fb_user) && is_string($fb_user)) {
			$fb_button = $fb_user;
			$fb_user = null;
		}

		$label_email = apply_filters('simplr_label_email', __('Email Address:','simplr-registration-form') );
		$label_confirm_email = apply_filters('simplr_label_confirm_email', __('Confirm Email:','simplr-registration-form') );
		$label_username = apply_filters('simplr_label_username', __('Your Username:','simplr-registration-form') );
		$label_pass = apply_filters('simplr_label_password', __('Choose a Password','simplr-registration-form'));
		$label_confirm = apply_filters('simplr_label_confirm', __('Confirm Password','simplr-registration-form'));

		//POST FORM
		$form = '';
    if ($session_messages = SREG_Messages::getAll()) {
      foreach($session_messages as $message) {
        $form .= "<div class='alert simplr-message ".$message['class']."'>".__($message['text'], 'simplr-registration-form')."</div>";
      } 
    }
		$form .= apply_filters('simplr-reg-instructions', __('', 'simplr-registration-form'));
		$form .=  '<div id="simplr-form">';
		if(isset($fb_button)) {
			$form .= '<div class="fb-button">'.$fb_button.'</div>';
		}

		$fields = explode(',',@$atts['fields']);
		$form .=  '<form method="post" action="" id="simplr-reg">';
		$form .= apply_filters('simplr-reg-first-form-elem','');

		//if the user has not added their own user name field lets force one
		if( !in_array('username',$fields) OR empty($custom->fields->custom['username']) ) {
			$form .=  '<div class="simplr-field '.apply_filters('username_error_class','') .'">';
			$form .=  '<label for="username" class="left">' .@esc_attr($label_username ).' <span class="required">*</span></label>';
			$form .=  '<input type="text" name="username" class="right" value="'.@esc_attr($data['username']) .'" /><br/>';
			$form .=  '</div><div class="simplr-clr"></div>';
		}

		foreach(@$fields as $field) {
			if ( preg_match("#^\{(.*)\}#",$field, $matches) ) {
				$form .= "<h3 class='registration'>".$matches[1]."</h3>";
			}
			$cf = @$custom->fields->custom[$field];
			$out = '';
			$key_val = '';
			if($cf['key'] != '') {
				if($fb_user != null) {
					$key_val = (array_key_exists($cf['key'],$fb_user)) ? $fb_user[$cf['key']] : $data[$cf['key']];
				}
				$args = array(
					'name'     => $cf['key'],
					'label'    => $cf['label'],
					'required' => $cf['required']
				);

				ob_start();
				//setup specific field values for date and callback
				if(isset($data[$cf['key']])) {
					if($cf['type'] == 'date') {
						$key_val = implode('-',array($data[$cf['key'].'-yr'],$data[$cf['key'].'-mo'],$data[$cf['key'].'-dy']));
					} elseif($cf['key'] != 'user_login' AND $cf['key'] != 'user_password' AND $cf['key'] != 'user_email') {
						$key_val = $data[$cf['key']];
					}
				}

				if($cf['type'] == 'callback') {
					$cf['options_array'][1] = array( @$data[$cf['key']] );
				}

				// do field
				if($cf['type'] != '' && isset($cf['options_array'])) {
					$sreg_form = new SREG_Form();
					$type = $cf['type'];
					$sreg_form->$type($args, @esc_attr($key_val), '', $cf['options_array']);
				}

				$form .= ob_get_contents();
				ob_end_clean();
			}
		}

		$form = apply_filters('simplr-add-personal-fields', $form);

		//only insert the email fields if the user hasn't specified them.
		if( !in_array('email',$fields) ) {
			$form .=  '<div class="simplr-field email-field '.apply_filters('email_error_class','').'">';
			$form .=  '<label for="email" class="left">' .$label_email .' <span class="required">*</span></label>';
			$form .=  '<input type="text" name="email" class="right" value="'.esc_attr(@$data['email']) .'" /><br/>';
			$form .=  '</div><div class="simplr-clr"></div>';
		}

		if( !in_array('email_confirm', $fields) ) {
			$form .=  '<div class="simplr-field email-field '.apply_filters('email_error_class','').'">';
			$form .=  '<label for="email" class="left">' .$label_confirm_email .' <span class="required">*</span></label>';
			$form .=  '<input type="text" name="email_confirm" class="right" value="'.esc_attr(@$data['email_confirm']) .'" /><br/>';
			$form .=  '</div><div class="simplr-clr"></div>';
		}

		$form = apply_filters('simplr-add-contact-fields', $form);


		if('yes' == @$atts['password']) {
			$form .=  '<div class="simplr-field '.apply_filters('password_error_class','').'">';
			$form .=  '<label for="password" class="left">' .$label_pass .'</label>';
			$form .=  '<input type="password" name="password" class="right" value="'.esc_attr(@$data['password']) .'"/><br/>';
			$form .=  '</div>';

			$form .=  '<div class="simplr-field '.apply_filters('password_error_class','').'">';
			$form .=  '<label for="password-confirm" class="left">' .$label_confirm .'</label>';
			$form .=  '<input type="password" name="password_confirm" class="right" value="'.esc_attr(@$data['password_confirm']) .'"/><br/>';
			$form .=  '</div>';
		}

		//filter for adding profile fields
		$form = apply_filters('simplr_add_form_fields', $form);
		if( isset( $soptions->recap_on ) AND $soptions->recap_on == 'yes') {
			$form .= sreg_recaptcha_field();
		}

		//add attributes to form
		if(!empty($atts)) {
			foreach($atts as $k=>$v) {
				$form .= '<input type="hidden" name="atts['.$k.']" value="'.$v.'" />';
			}
		}

		//submission button. Use filter to custommize
		$form .=  apply_filters('simplr-reg-submit', '<input type="submit" name="submit-reg" value="' . __('Register','simplr-registration-form') . '" class="submit button">');

		//wordress nonce for security
		$nonce = wp_create_nonce('simplr_nonce');
		$form .= '<input type="hidden" name="simplr_nonce" value="' .$nonce .'" />';

		if(!empty($fb_user)) {
			$form .= '<input type="hidden" name="fbuser_id" value="'.$fb_user['id'].'" />';
		}
    
    // store the current post_id
    $form .= '<input type="hidden" name="post_id" value="'.get_the_ID().'" />';

		$form .= '<div style="clear:both;"></div>';
		$form .=  '</form>';
		$form .=  '</div>';
		if( isset($options->fb_connect_on) AND $soptions->fb_connect_on == 'yes') {
			$form .= sreg_load_fb_script();
		}
		return $form;
	}
}

function sreg_basic($atts) {
	require_once dirname(__FILE__).'/lib/sreg.class.php';
	//Check if the user is logged in, if so he doesn't need the registration page
	if ( is_user_logged_in() AND !current_user_can('administrator') ) {
		global $user_ID;
		$first_visit = get_user_meta($user_ID, 'first_visit',true);
		if(empty($first_visit)) {
			$message = !empty($atts['message'])?$atts['message']:__("Thank you for registering.", 'simplr-registration-form');
			update_user_meta($user_ID,'first_visit',date('Y-m-d'));
			echo '<div id="message" class="alert success"><p>'.$message.'</p></div>';
		} else {
			_e('You are already registered for this site!!!', 'simplr-registration-form');
		}
	} else {
		//Then check to see whether a form has been submitted, if so, I deal with it.
		global $sreg;
		if( !is_object($sreg) ) {
			$sreg = new Sreg_Submit();
		}
		$out = '';
		if(isset($sreg->success)) {
			return $sreg->output;
		} elseif( isset($sreg->errors) AND is_array($sreg->errors)) {
			foreach($sreg->errors as $mes) {
				$out .= '<div class="alert simplr-message error">'.$mes .'</div>';
			}
		} elseif(is_string($sreg->errors)) {
			$out = '<div class="simplr-message error">'.$message .'</div>';
		}
		return $out.simplr_build_form($_POST,$atts);

	} //Close LOGIN Conditional

}

add_action('wp','simplr_process_form');
function simplr_process_form() {
	if(isset($_POST['submit-reg'])) {
		global $sreg,$simplr_options;

		$atts = $_POST['atts'];
		sreg_process_form($atts);

		if(empty($sreg->errors)) {
			if( ( is_object($simplr_options) && isset($simplr_options->fb_connect_on) ) AND !empty($_POST['fbuser_id']) ) {
				simplr_fb_auto_login();
			} elseif(!empty($atts['thanks']) ) {
        SREG_Messages::set('success', $sreg->output);
				$page = get_permalink($atts['thanks']);
				wp_redirect($page);
        exit;
			} elseif( !empty($simplr_options->thank_you) ) {
        SREG_Messages::set('success', $sreg->output);
				$page = get_permalink($simplr_options->thank_you);
				wp_redirect($page);
        exit;
			} else {
				$sreg->success = $sreg->output;
			}
		}
	}
}

//this function determines which version of the registration to call
function sreg_figure($atts) {
	global $options;
	extract(shortcode_atts(array(
		'role'    => 'subscriber',
		'from'    => get_option('sreg_admin_email'),
		'message' => __('Thank you for registering','simplr-registration-form'),
		'notify'  => get_option('sreg_email'),
		'fields'  => null,
		'fb'      => false,
		), $atts));
	if($role != 'admin') {
		$function = sreg_basic($atts);
	} else {
		$function = __('You should not register admin users via a public form','simplr-registration-form');
	}
	return $function;
}

function sreg_recaptcha_field() {
	require_once(SIMPLR_DIR .'/lib/recaptchalib.php');
	$options = get_option('simplr_reg_options');
	$publickey = $options->recap_public; // you got this from the signup page
	return recaptcha_get_html($publickey);
}

function recaptcha_check($data) {
	include_once(SIMPLR_DIR .'/lib/recaptchalib.php');
	$options = get_option('simplr_reg_options');
	$privatekey = $options->recap_public;
	$resp = recaptcha_check_answer($options->recap_private,$_SERVER["REMOTE_ADDR"],$data["recaptcha_challenge_field"],$data["recaptcha_response_field"]);

	if (!$resp->is_valid) {
		// What happens when the CAPTCHA was entered incorrectly
		return __("The reCAPTCHA wasn't entered correctly. Go back and try it again. (reCAPTCHA said:", 'simplr-registration-form') .
			" " . $resp->error . ")";
	} else {
		return false;
	}
}

function sreg_fb_connect() {
	$options = get_option('simplr_reg_options');
	if( !isset($options->fb_connect_on) OR $options->fb_connect_on != 'yes') {
		return null;
	} else {

		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		# Creating the facebook object

		$facebook = new Facebook(Simplr_Facebook::get_fb_info());

		# Active session, let's try getting the user id (getUser()) and user info (api->('/me'))
		try {
			$uid = $facebook->getUser();
			$user = $facebook->api('/me');
		} catch (FacebookApiException $e) {}

		if(!empty($user)) {
			# User info ok? Let's print it (Here we will be adding the login and registering routines)
			$out = $user;
		} else {
			# For testing purposes, if there was an error, let's kill the script
			# There's no active session, let's generate one
			$login_url = $facebook->getLoginUrl();
			$perms = implode(',',$options->fb_request_perms);
			$out = __('Register using Facebook', 'simplr-registration-form') . ' <fb:login-button scope="'.$perms.'"></fb:login-button>';
		}

		return $out;
	}
}

function sreg_load_fb_script() {
	require_once(SIMPLR_DIR .'/lib/facebook.php');
	include_once(SIMPLR_DIR .'/lib/fb.class.php');
	$ap_info = Simplr_Facebook::get_fb_info();
	ob_start();
	?>
	<div id="fb-root"></div>
	<script>
	window.fbAsyncInit = function() {
		FB.init({
			appId  : '<?php echo $ap_info['appId']; ?>',
			status : true, // check login status
			cookie : <?php echo $ap_info['cookie']; ?>, // enable cookies to allow the server to access the session
			xfbml  : true,  // parse XFBML
			oauth  : true //enables OAuth 2.0
		});
		FB.Event.subscribe('auth.login', function(response) {
			window.location.reload();
		});
		FB.Event.subscribe('auth.logout', function(response) {
			window.location.reload();
		});
	};

	(function() {
		var e = document.createElement('script');
		e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
		e.async = true;
		document.getElementById('fb-root').appendChild(e);
	}());
	</script>
	<?php
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function _sreg_return_error($class) {
	return apply_filters('sreg_global_error_class','error');
}

/* 
 * function simplr_save_role_lock - runs on save_post hook to ensure there is a role lock associated with the post
 *
 * @since 2.4.4
 * @params $post_id int 
 * @return NULL
 */
function simplr_save_role_lock($post_id) {
  global $post;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  
  // save the role 
  if (isset($_POST['simplr_role_lock'])) {
    update_post_meta($post_id, 'simplr_role_lock', sanitize_text_field($_POST['simplr_role_lock']));
    return true;
  }
  
  // for the sake of backwards compatibility, check existing forms for roles settings and save them as role locks instead
  if (!isset($_POST['simplr_role_lock']) AND get_post_meta($post_id, 'simplr_role_lock', true) == "") {
    update_post_meta($post_id,'simplr_role_lock',simplr_find_role_lock($post->post_content));
    return;
  }
}


/*
 * get role lock from specified content 
 */
 function simplr_find_role_lock($content) {
   if (has_shortcode($content, 'register')) {
      $pattern = get_shortcode_regex();
      preg_match("@$pattern@i", $content, $matches);
      $atts = shortcode_parse_atts(preg_replace("@\[([^\]]+)\]@", "$1", str_replace("register ",'',$matches[0])));
      if (isset($atts['role'])) {
        return $atts['role'];
      } 
    }
    return false; 
 }
 

/*
**
** Plugin Activation Hook
**
**/

function simplr_reg_install() {
	//validate
	global $wp_version;
	$exit_msg = "Dude, upgrade your stinkin WordPress Installation.";

	if(version_compare($wp_version, "2.8", "<"))
		exit($exit_msg);

	//setup some default fields
	simplr_reg_default_fields();
}


/**
**
** Load Settings Page
**
**/

function simplr_reg_set() {
	include_once(SIMPLR_DIR.'/lib/form.class.php');
	include_once( SIMPLR_DIR . '/main_options_page.php' );
}


/**
**
** Add Settings page to admin menu
**
**/

function simplr_reg_menu() {
	$page = add_submenu_page('options-general.php','Registration Forms', __('Registration Forms', 'simplr-registration-form'), 'manage_options','simplr_reg_set', 'simplr_reg_set');
	add_action('admin_print_styles-' . $page, 'simplr_admin_style');
	register_setting ('simplr_reg_options', 'sreg_admin_email', '');
	register_setting ('simplr_reg_options', 'sreg_email', '');
	register_setting ('simplr_reg_options', 'sreg_style', '');
	register_setting ('simplr_reg_options', 'simplr_profile_fields', 'simplr_fields_settings_process');
}


/**
**
** Add Settings link to the main plugin page
**/

function simplr_plugin_link( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/simplr_reg_page.php' ) ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=simplr_reg_set' ) . '">' . __( 'Settings', 'simplr-registration-form' ) . '</a>';
	}
	return $links;
}
add_filter( 'plugin_action_links', 'simplr_plugin_link', 10, 2 );


/**
**
** Process Saved Settings (Deprecated)
**
**/

function simplr_fields_settings_process($input) {
	if($input[aim][name] && $input[aim][label] == '') {$input[aim][label] = 'AIM';}
	if($input[yim][name] && $input[yim][label] == '') {$input[yim][label] = 'YIM';}
	if($input[website][name] && $input[website][label] == '') {$input[website][label] = __('Website', 'simplr-registration-form');}
	if($input[nickname][name] && $input[nickname][label] == '') {$input[nickname][label] = __('Nickname', 'simplr-registration-form');}
	return $input;
}

/**
**
** Register and enqueue plugin styles
**
**/

function simplr_reg_styles() {
	$options = get_option('simplr_reg_options');
	if( is_object($options) && isset($options->styles) && $options->styles != 'yes') {
		if( @$options->style_skin ) {
			$src = SIMPLR_URL .'/assets/skins/'.$options->style_skin;
		} else {
			$src = SIMPLR_URL .'/assets/skins/default.css';
		}
		wp_register_style('simplr-forms-style',$src);
		wp_enqueue_style('simplr-forms-style');
	} elseif(is_object($options) || !empty($options->stylesheet)) {
		$src = $options->stylesheet;
		wp_register_style('simplr-forms-custom-style',$src);
		wp_enqueue_style('simplr-forms-custom-style');
	} else {
		wp_register_style('simplr-forms-style', SIMPLR_URL .'/assets/skins/default.css');
		wp_enqueue_style('simplr-forms-style');
	}
}

/**
 * Handle admin styles and JS
 */
function simplr_admin_style() {
	wp_register_style( 'simplr-admin-style', SIMPLR_URL . '/assets/admin-style.css' );

	$url = parse_url($_SERVER['REQUEST_URI']);
	$parts = explode('/', trim($url['path']));
	if(is_admin()) {
		if( isset($_GET['page']) AND ( $_GET['page'] == 'simplr_reg_set' ||  $_GET['page'] == 'post.php' ||  $_GET['page'] == 'post-new.php' ) ) {
			wp_register_style('chosen',SIMPLR_URL.'/assets/js/chosen/chosen.css');
			wp_register_script('chosen',SIMPLR_URL.'/assets/js/chosen/chosen.jquery.js',array('jquery'));
			add_action('admin_print_footer_scripts','simplr_footer_scripts');
			wp_enqueue_style('chosen');
			wp_enqueue_script('chosen');

			wp_enqueue_style('simplr-admin-style');
		 } elseif( end($parts) == 'users.php' ) {
			add_action('admin_print_footer_scripts','simplr_footer_scripts');
		}
	}
}

/*
 * Print Admin Footer Scripts
 */
function simplr_footer_scripts() {
	$screen = get_current_screen();
	if( $screen->id == 'users' AND @$_GET['view_inactive'] == 'true' ) {
		?>
		<script>
			jQuery(document).ready(function($) {
				//add bulk actions
				$('input[name="simplr_resend_activation"]').click( function(e) { e.preventDefault(); });
				$('select[name="action"]').append('<option value="sreg-activate-selected"><?php _e('Activate', 'simplr-registration-form'); ?></option>\n<option value="sreg-resend-emails"><?php _e('Resend Email', 'simplr-registration-form'); ?></option>').after('<input name="view_inactive" value="true" type="hidden" />');
			});

		</script>
		<?php
	} else {
		?>
		<script>
			jQuery(document).ready(function($) {
				$('.chzn').chosen({
					width: "95%"
				});
			});
		</script>
		<?php
	}
}

/**
**
** Enqueue Scripts
**
**/

function simplr_admin_scripts() {
	if(is_admin() AND @$_REQUEST['page'] == 'simplr_reg_set') {
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-sortable');
	}
}

/**
**
** Load language files for frontend and backend
**/
function simplr_load_lang() {
	load_plugin_textdomain( 'simplr-registration-form', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}
add_action('plugins_loaded', 'simplr_load_lang');


/**
**
** Add User Info
**
**/
function simplr_action_admin_init() {
	global $simplr_options;

	if( @$simplr_options->mod_on == 'yes') {
		//only add these hooks if moderation is on
		$mod_access = false;

		//if roles haven't been saved use default
		if( empty($simplr_options->mod_roles) )
			$simplr_options->mod_roles = array('administrator');

		foreach( $simplr_options->mod_roles as $role ) {
			if( $mod_access) continue;
			$mod_access = current_user_can($role);
		}

		if( $mod_access ) {
			require_once(SIMPLR_DIR.'/lib/mod.php');
			add_action('views_users', 'simplr_views_users');
			add_action('pre_user_query','simplr_inactive_query');
			add_filter('bulk_actions-users','simplr_users_bulk_action');
		}
	}

	add_filter('manage_users_columns', 'simplr_column');
	add_filter('manage_users_custom_column','simplr_column_output',10,3);
	add_filter('manage_users_sortable_columns','simplr_sortable_columns');
	add_filter('pre_user_query','simplr_users_query');
}

/**
 * Adds default fields upon installation
*/

function simplr_reg_default_fields() {
	if(!get_option('simplr_reg_fields')) {
		$fields = new StdClass();
		$custom = array(
			'first_name'=>array('key'=>'first_name','label'=> __('First Name', 'simplr-registration-form'),'required'=>false,'type'=>'text'),
			'last_name'=>array('key'=>'last_name','label'=> __('Last Name', 'simplr-registration-form'),'last_name'=> __('Last Name', 'simplr-registration-form'),'required'=>false,'type'=>'text')
		);
		$fields->custom = $custom;
		update_option('simplr_reg_fields',$fields);
	}

	//unset profile from free version
	if(get_option('simplr_profile_fields')) {
		delete_option('simplr_profile_fields');
	}

}

/*
**
** Facebook Autologin
**
*/

function simplr_fb_auto_login() {
	global $simplr_options;
	//require_once(SIMPLR_DIR.'/lib/login.php');
	global $facebook;
	if( isset($simplr_options->fb_connect_on)
		AND $simplr_options->fb_connect_on == 'yes'
		AND !is_user_logged_in()
		AND !current_user_can('administrator')) {
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		$facebook = new Facebook(Simplr_Facebook::get_fb_info());
		try {
			$uid = $facebook->getUser();
			$user = $facebook->api('/me');
		} catch (FacebookApiException $e) {}
		$auth = (isset($user))?simplr_fb_find_user($user):false;
		$first_visit = get_user_meta($auth->ID,'first_visit',true);
		if(isset($user) && (@$_REQUEST['loggedout'] == 'true' OR @$_REQUEST['action'] == 'logout')) {
			wp_redirect($facebook->getLogoutUrl(array('next'=>get_bloginfo('url'))));
      exit;
		} elseif(isset($user) AND !is_wp_error($auth) ) {
			wp_set_current_user($auth->ID, $auth->user_login);
			wp_set_auth_cookie($auth->ID);
			if(isset($simplr_options->thank_you) AND !is_page($simplr_options->thank_you)  ) {
				update_user_meta($auth->ID,'first_visit',date('Y-m-d'));
				$redirect = $simplr_options->thank_you != ''?get_permalink($simplr_options->thank_you):home_url();
				wp_redirect($redirect);
        exit;
			} elseif(isset($simplr_options->thank_you) AND is_page($simplr_options->thank_you)) {
				//do nothing
			} elseif(isset($first_visit)) {
				wp_redirect(!$simplr_options->fb_login_redirect?get_bloginfo('url'):$simplr_options->register_redirect);
        exit;
			}
		} elseif(isset($user) AND is_wp_error($auth)) {
			global $error;
			$error = __($auth->get_error_message(),'simplr-registration-form');
		} else {

			return;
		}
	} else {
		return;
	}
}


/*
**
** Find Facebook User
**
*/

function simplr_fb_find_user($fb_obj) {
	global $wpdb,$simplr_options;
	$query = $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuser_id' AND meta_value = %d", $fb_obj['id'] );
	$user_id = $wpdb->get_var($query);

	if(empty($user_id) AND isset($simplr_options->fb_auto_register)) {
		$user_id = simplr_fb_auto_register();
	}

	$user_obj = get_userdata($user_id);
	if(empty($user_obj)) {
		return new WP_Error( 'login-error', __('No facebook account registered with this site', 'simplr-registration-form') );
	} else {
		return $user_obj;
	}
}

function simplr_fb_auto_register() {
	global $simplr_options;
	require_once(SIMPLR_DIR .'/lib/facebook.php');
	include_once(SIMPLR_DIR .'/lib/fb.class.php');
	$facebook = new Facebook(Simplr_Facebook::get_fb_info());
	try {
		$uid = $facebook->getUser();
		$user = $facebook->api('/me');
	} catch (FacebookApiException $e) {}

	if(!empty($user)) {
		$userdata = array(
			'user_login' 	=> $user['username'],
			'first_name' 	=> $user['first_name'],
			'last_name' 	=> $user['last_name'],
			'user_pass' 	=> wp_generate_password( 12, false ),
			'user_email' 	=> 'fb-'.$user['id']."@website.com",
		);

		// create user
		$user_id = wp_insert_user( $userdata );
		update_user_meta($user_id, 'fbuser_id', $user['id']);
		update_user_meta($user_id, 'fb_object', $user);
		if(!is_wp_error($user_id)) {
			//return the user
			wp_redirect($simplr_options->fb_login_redirect?$simplr_options->fb_login_redirect:home_url());
      exit;
		}
	}

}

/*
**
** Facebook Login Button
**
*/

function get_fb_login_btn($content) {
	$option = get_option('simplr_reg_options');
	if( isset($option->fb_connect_on) AND $option->fb_connect_on == 'yes') {
		$out = '';
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		global $facebook;
		$login_url = $facebook->getLoginUrl();
		$perms = implode(',',$option->fb_request_perms);
		$out .= '<fb:login-button scope="'.$perms.'"></fb:login-button>';
		//$out = '<p><div id="fblogin"><a href="'.$login_url.'"><img src="'.plugin_dir_url(__FILE__).'assets/images/fb-login.png" /></a></div></p>';
		echo $out;
	}
	return $content;
}

/*
**
** Facebook Login Button Styles
**
*/

function simplr_fb_login_style() {
	?>
	<style>
	a.fb_button {
		margin:10px 0px 10px 240px;
	}
	</style>
	<?php
}

/*
**
** Login Footer Script
**
*/

function simplr_fb_login_footer_scripts() {
	$option = get_option('simplr_reg_options');
	if(isset($option->fb_connect_on) AND $option->fb_connect_on == 'yes') {
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		$ap_info = Simplr_Facebook::get_fb_info();
		?>
		<div id="fb-root"></div>
		<script>
		window.fbAsyncInit = function() {
			FB.init({
				appId  : '<?php echo $ap_info['appId']; ?>',
				status : true, // check login status
				cookie : <?php echo $ap_info['cookie']; ?>, // enable cookies to allow the server to access the session
				xfbml  : true,  // parse XFBML
				oauth : true //enables OAuth 2.0
			});

			FB.Event.subscribe('auth.login', function(response) {
				window.location.reload();
			});
			FB.Event.subscribe('auth.logout', function(response) {
				window.location.reload();
			});
		};
		(function() {
			var e = document.createElement('script');
			e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
			e.async = true;
			document.getElementById('fb-root').appendChild(e);
		}());
		</script>
	<?php
	}
}

/*
**
** Add Fields to Profile Page
**
*/
function simplr_reg_profile_form_fields($user) {
	if(!class_exists('Form')) {
		include_once(SIMPLR_DIR.'/lib/form.class.php');
	}
	$custom = new SREG_Fields();
	if(!current_user_can('promote_users')) {
		$fields = simplr_filter_profile_fields($custom->get_custom());
	} else {
		$fields = $custom->get_custom();
	}
	?>
	<h3><?php _e('Other Information', 'simplr-registration-form'); ?></h3>
	<?php
	wp_enqueue_style('simplr-admin-style');

	foreach($fields as $field) {
		if(!in_array($field['key'] ,array('first_name','last_name', 'user_login','username'))) {
			$out = '';
			if($field['key'] != '') {
				$args = array(
					'name'		=>$field['key'],
					'label'		=>$field['label'],
					'required'	=>$field['required']
					);
				//setup specific field values for date and callback
				$sreg_form = new SREG_Form();
				$type = $field['type'];
				if($type == 'callback') {
					$field['options_array'][1] = array( get_user_meta($user->ID,$field['key'],true) ) ;
					$sreg_form->$type( $args, get_user_meta($user->ID,$field['key'],true), '', $field['options_array']);
				} elseif($type != '') {
					$sreg_form->$type($args, get_user_meta($user->ID,$field['key'],true), '', $field['options_array']);
				}
			}
		}
	}
}


/*
**
** Save Fields in Profile Page
**
*/
add_action( 'personal_options_update', 'simplr_reg_profile_save_fields' );
add_action( 'edit_user_profile_update', 'simplr_reg_profile_save_fields' );

function simplr_reg_profile_save_fields($user_id ) {
	$custom = new SREG_Fields();
	$data = $_POST;
	$fields = $custom->fields->custom;
	foreach($fields as $field):
		if(!in_array($field['key'] , simplr_get_excluded_profile_fields() )) {
			if($field['type'] == 'date')
			{
				$dy = $data[$field['key'].'-dy'];
				$mo = $data[$field['key'].'-mo'];
				$yr = $data[$field['key'].'-yr'];
				$dateinput = implode('-', array($yr,$mo,$dy));
				update_user_meta($user_id,$field['key'],$dateinput);
			} else {
				update_user_meta($user_id, $field['key'], $data[$field['key']]);
			}
		}
	endforeach;
}


/*
**
** Exclude Fields From Profile
**
*/
function simplr_get_excluded_profile_fields() {
	$fields = array(
		'about_you','first_name','last_name','aim','yim','jabber','nickname','display_name','user_login','username','user_email',
	);
	return apply_filters('simplr_excluded_profile_fields', $fields);
}

/*
**
** Register Redirect Function
**
*/

function simplr_register_redirect() {
	$file = parse_url($_SERVER['REQUEST_URI']);
	$path = explode('/',@$file['path']);
	global $simplr_options;
	parse_str(@$file['query']);
	if( @$simplr_options->login_redirect ) {
		$post = get_post($simplr_options->login_redirect);
		set_transient('login_post_data',$post);
	}
	if( ((end($path) == 'wp-login.php' AND @$_GET['action'] == 'register') OR (end($path) == 'wp-signup.php')) AND $simplr_options->register_redirect != '' ) {
		wp_redirect(get_permalink($simplr_options->register_redirect));
    exit;
	} elseif(end($path) == 'profile.php' AND $simplr_options->profile_redirect != '') {
		if(!current_user_can('administrator')) {
			wp_redirect(get_permalink($simplr_options->profile_redirect.'?'.$file['query']));
      exit;
		}
	} else {

	}
}

function simplr_profile_redirect() {
	global $simplr_options,$wpdb;
	if ( is_object($simplr_options) &&  isset($simplr_options->profile_redirect) ) {
		$profile = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->prefix}posts WHERE ID = %d",$simplr_options->profile_redirect));
	}
	$file = parse_url($_SERVER['REQUEST_URI']);
	$path = explode('/',@$file['path']);
	if(isset($profile) AND end($path) == $profile) {
		if(!is_user_logged_in()) {
			wp_redirect(home_url('/wp-login.php?action=register'));
		}
	}
	wp_deregister_script('password-strength-meter');
	do_action('simplr_profile_actions');
}


/*
**
** Ajax save sort
**
*/
add_action('wp_ajax_simplr-save-sort','simplr_save_sort');
function simplr_save_sort() {
	extract($_REQUEST);
	if(isset($sort) and $page = 'simple_reg_set') {
		update_option('simplr_field_sort',$sort);
	}
	// debugging code as the response.
	echo "php sort: ";
	print_r($sort);
	die();
}

/*
** Print admin messages
**
*/

function simplr_print_message() {
	$simplr_messages = @$_COOKIE['simplr_messages'] ? $_COOKIE['simplr_messages'] : false;
	$messages = stripslashes($simplr_messages);
	$messages = str_replace('[','',str_replace(']','',$messages));
	$messages = json_decode($messages);
	if(!empty($messages)) {
		if(count($messages) > 1) {
			foreach($messages as $message) {
				?>

				<?php
			}
		} else {
			?>
			<div id="message" class="<?php echo $messages->class; ?>"><p><?php echo $messages->content; ?></p></div>
			<?php
		}
	}
}


/*
** Set Admin Messages
**
*/

function simplr_set_message($class,$message) {
	if(!session_id()) { session_start(); }

	$messages = $_COOKIE['simplr_messages'];
	$messages = stripslashes($simplr_messages);
	$messages = str_replace('[','',str_replace(']','',$messages));
	$messages = json_decode($messages);
	$new = array();
	$new['class'] = $class;
	$new['content'] = $message;
	$messages[] = $new;
	setcookie('simplr_messages',json_encode($messages),time()+10,'/');
	return true;
}

/*
** Process admin forms
**	@TODO consolidate steps
*/
add_action('admin_init','simplr_admin_actions');
function simplr_admin_actions() {
	if(isset($_GET['page']) AND $_GET['page'] == 'simplr_reg_set') {

		$data = $_POST;
		$simplr_reg = get_option('simplr_reg_options');

		//
		if(isset($data['recaptcha-submit'])) {

			if(!wp_verify_nonce(-1, $data['reg-api']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}
			$simplr_reg->recap_public = $data['recap_public'];
			$simplr_reg->recap_private = $data['recap_private'];
			$simplr_reg->recap_on = $data['recap_on'];
			update_option('simplr_reg_options',$simplr_reg);
		} elseif(isset($data['fb-submit'])) {
			if(!wp_verify_nonce(-1, @$data['reg-fb']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}
			$simplr_reg->fb_connect_on = $data['fb_connect_on'];
			$simplr_reg->fb_app_id = @$data['fb_app_id'];
			$simplr_reg->fb_app_key = @$data['fb_app_key'];
			$simplr_reg->fb_app_secret = @$data['fb_app_secret'];
			$simplr_reg->fb_login_allow = @$data['fb_login_allow'];
			$simplr_reg->fb_login_redirect = @$data['fb_login_redirect'];
			$simplr_reg->fb_request_perms = @$data['fb_request_perms'];
			$simplr_reg->fb_auto_register = @$data['fb_auto_register'];
			update_option('simplr_reg_options',$simplr_reg);
			simplr_set_message('updated notice is-dismissible', __("Your settings were saved.", 'simplr-registration-form') );
			wp_redirect($_SERVER['REQUEST_URI']);
      exit;
		}

		if(isset($data['main-submit'])) {
			//security check
			if(!wp_verify_nonce(-1, $data['reg-main']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}

			$simplr_reg->email_message = $data['email_message'];
			$simplr_reg->default_email = $data['default_email'];
			$simplr_reg->stylesheet = $data['stylesheet'];
			$simplr_reg->styles = $data['styles'];
			$simplr_reg->style_skin = @$data['style_skin'] ? $data['style_skin'] : 'default.css';
			$simplr_reg->register_redirect = $data['register_redirect'];
			$simplr_reg->thank_you = $data['thank_you'];
			$simplr_reg->profile_redirect = $data['profile_redirect'];
			update_option('simplr_reg_options',$simplr_reg);
			simplr_set_message('updated notice is-dismissible', __("Your settings were saved.", 'simplr-registration-form') );
			wp_redirect($_SERVER['REQUEST_URI']);
      exit;

		}

		if(@$_GET['action'] == 'delete') {

			/*Security First*/
			if( !check_admin_referer('delete','_wpnonce') ) { wp_die('Death to hackers'); }
			$del = new SREG_Fields();
			$del->delete_field($_GET['key']);
			simplr_set_message('updated notice is-dismissible', __("Field deleted.", 'simplr-registration-form') );
			wp_redirect(remove_query_arg('action'));
      exit;

		} elseif(isset($_POST['mass-submit'])) {

			if(!check_admin_referer(-1,'_mass_edit')) { wp_die('Death to hackers'); }
			foreach($_POST['field_to_delete'] as $key):
				$del = new SREG_Fields();
				$del->delete_field($key);
			endforeach;
			simplr_set_message('updated notice is-dismissible', __("Fields were deleted.", 'simplr-registration-form') );
			wp_redirect(remove_query_arg('action'));
      exit;

		}

		if(isset($_POST['submit-field'])) {
			if( !check_admin_referer(-1, 'reg-field' ) ) wp_die("Death to Hackers");
			$new = new SREG_Fields();
			$key = $_POST['key'];
			$response = $new->save_custom($_POST);
			simplr_set_message('updated notice is-dismissible', __("Your Field was saved.", 'simplr-registration-form') );
			wp_redirect(remove_query_arg('action'));
      exit;
		}

		add_action('admin_notices','simplr_print_message');
	}

}

/*
 * Activate a user(s)
 * @params $ids (array) | an array of user_ids to activate.
 */
function simplr_activate_users( $ids = false ) {
	if( !$ids ) {
		if( @$_REQUEST['action'] == 'sreg-activate-selected' AND !empty($_REQUEST['users']) ) {
			simplr_activate_users( $_REQUEST['users'] );
		}
	} else {
		global $wpdb,$simplr_options;
		foreach( $ids as $id ) {
			$return = $wpdb->update( $wpdb->users, array( 'user_status'=> 0 ), array( 'ID' => $id ), array('%d'), array('%d') );
			if( !$return ) {
				return new WP_Error( "error", __("Could not activate requested user.", 'simplr-registration-form') );
			}
			$userdata = get_userdata( $id );
			$data = (array) $userdata;
			$data = (array) $data['data'];
			$data['blogname'] = get_option('blogname');
			$data['username'] = $userdata->user_login;
			do_action('simplr_activated_user', $data);
			$subj = simplr_token_replace( $simplr_options->mod_email_activated_subj, $data );
			$content = simplr_token_replace( $simplr_options->mod_email_activated, $data );
			if ( isset( $simplr_options->default_email ) ) {
				$from = $simplr_options->default_email;
			} else {
				$from = get_option('admin_email');
			}
			$headers = "From: " . $data['blogname'] . " <$from>\r\n";
			wp_mail( $data['user_email'], $subj, $content, $headers);
			return $return;
		}
	}
}

/*
 * Sends user moderation emails to selected users
 */
function simplr_resend_emails() {
	if( @$_REQUEST['action'] == 'sreg-resend-emails' AND !empty($_REQUEST['users']) ) {
		include_once(SIMPLR_DIR.'/lib/mod.php');
		foreach( $_REQUEST['users'] as $user ) {
			simplr_resend_email($user);
			simplr_set_notice('success', __("Emails resent", 'simplr-registration-form') );
		}
	}
}

/*
 * Activation Listener
 */
function simplr_activation_listen() {
	if( isset( $_REQUEST['activation_key'] ) ) {
		wp_enqueue_script('simplr-mod', SIMPLR_URL.'/assets/mod.js', array('jquery') );
		wp_enqueue_style('simplr-mod', SIMPLR_URL.'/assets/mod.css');
		global $wpdb,$sreg;
		$user_id = $wpdb->get_var($wpdb->prepare("SELECT ID from $wpdb->users WHERE `user_activation_key` = %s", $_REQUEST['activation_key']));
		$done = simplr_activate_users( array($user_id) );
		if ( !$user_id OR is_wp_error($done) ) {
			wp_localize_script('simplr-mod', 'sreg', array('state'=>'failure', 'message'=>__("Sorry, We could not find the requested account.",'simplr-registration-form')) );
		} else {
			wp_localize_script('simplr-mod', 'sreg', array('state'=>'success', 'message'=>__("Congratulations! Your Account was activated!",'simplr-registration-form')) );
		}
	}
}


function simplr_set_notice( $class, $message ) {
	add_action( "admin_notices" , create_function('',"echo '<div class=\"updated notice is-dismissible $class\"><p>$message</p></div>';") );
}

/**
 * Filter custom column output
 * @params $out string (optional) | received output from the wp hook
 * @params $column_name string (required) | unique column name corresponds to the field name
 * @params $user_id INT
 */
if(!function_exists('simplr_column_output')):
	function simplr_column_output( $out='', $column_name, $user_id ) {
		$out = get_user_meta( $user_id, $column_name, true );
		return $out;
	}
endif;

/**
 * Add custom columns
 * @params $columns (array) | received from manage_users_columns hook
 */
if(!function_exists('simplr_column')):
	function simplr_column($columns) {
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		foreach( $cols as $col ) {
			if( @$col['custom_column'] != 'yes' ) continue;
			$columns[$col['key']] = $col['label'];
		}
		return $columns;
	}
endif;

/**
 * Filter sortable columns
 * @params $columns (array) | received from manage_users_sortable_columns hook
*/
if( !function_exists('simplr_sortable_columns') ) {
	function simplr_sortable_columns($columns) {
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		unset($columns['posts']);
		foreach( $cols as $col ) {
			if( @$col['custom_column'] != 'yes' ) continue;
			$columns[$col['key']] = $col['key'];
		}
		$columns['post'] = 'Posts';
		return $columns;
	}
}

/**
 * Modify the users query to sort columns on custom fields
 * @params $query (array) | passed by pre_user_query hook
*/
if(!function_exists('simplr_users_query')):
	function simplr_users_query($query) {
		//if not on the user screen lets bail
		$screen = get_current_screen();
		if( !is_admin() ) return $query;
		if( $screen->base != 'users' ) return $query;

		$var = @$_REQUEST['orderby'] ? $_REQUEST['orderby'] : false;
		if( !$var ) return $query;
		//these fields are already sortable by wordpress
		if( in_array( $var, array('first_name','last_name','email','login','name') ) ) return $query;
		$order = @$_REQUEST['order'] ? esc_attr($_REQUEST['order']) : '';
		//get our custom fields
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		if( array_key_exists( $var, $cols ) ) {
			global $wpdb;
			$query->query_from .= $wpdb->prepare(" LEFT JOIN {$wpdb->usermeta} um ON um.user_id = ID AND `meta_key` = %s", $var);
			$query->query_orderby = " ORDER BY um.meta_value $order";
		}
		return $query;
	}
endif;

//add_filter('query','simplr_log');
function simplr_log($query) {
	if( @$_REQUEST['debug'] == 'true' ) {
		print $query;
	}
	return $query;
}

add_filter('wp_authenticate_user','simplr_disable_login_inactive', 0);
function simplr_disable_login_inactive($user) {

	if( empty($user) || is_wp_error($user) ) {
		return $user;
	}

	if( $user->user_status == 2 ) {
		return new WP_Error("error", __("<strong>ERROR</strong>: This account has not yet been approved by the moderator", 'simplr-registration-form') );
	}

	return $user;
}
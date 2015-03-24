<?php
/*
Plugin Name: IMGE Password Change
Plugin URI: 
Description: Force Password Reset on Intervals
Version: 1
Author: Christian Miele - imge

*/


add_action('admin_menu', 'imge_pwc_users_menu');

function imge_pwc_users_menu() {

	add_users_page('Change Password Settings', 'Password Settings', 'read', 'imge-pwc', 'imge_pwc_options_page');

	$nonce = filter_input(INPUT_POST, 'imge_pwc_nonce', FILTER_SANITIZE_STRING);
	  if ( ! empty($nonce) && wp_verify_nonce($nonce, 'imge_pwc') ) {
	     $limit =  $_POST['imge_pwc_limit'];
	     $type =  $_POST['imge_pwc_type'];

	     update_option('imge_pwc_limit', $limit);
	     update_option('imge_pwc_type', $type);
	     add_action('admin_notices', 'imge_pwc_update_notice');
	  }

	$blogusers = get_users();

	if (!get_option('imge_pwc_reg_users') || (count($blogusers) != count( get_option('imge_pwc_reg_users') )) ){
		$timestamp = time();
		$user_id = $user->data->ID;
		foreach ( $blogusers as $user ) {
			if (!get_option('imge_pwc_reg_users')){
				$u[$user->id]['pw_mod_date'] = $timestamp; 
				$u[$user->id]['display_name'] = $user->display_name; 
				update_user_meta( $user_id, 'imge_pwc_mod_date', $timestamp );
			}else{
				$u = get_option('imge_pwc_reg_users');
				if ( !$u[$user->id] ){
					$u[$user->id]['pw_mod_date'] = $timestamp; 
					$u[$user->id]['display_name'] = $user->display_name; 
					update_user_meta( $user_id, 'imge_pwc_mod_date', $timestamp );
				} 
			}		
		}

		$imge_pwc_reg_users = $u;
		update_option('imge_pwc_reg_users', $imge_pwc_reg_users);
	}
	
	// update_option('imge_pwc_reg_users', '');

	global $pagenow;
	
	if ($pagenow != 'profile.php') add_action('admin_head', 'imge_pwc_add_overlay');





}

function imge_pwc_profile_update( $user_id ) {
    if ( ! isset( $_POST['pass1'] ) || '' == $_POST['pass1'] ) {
        return;
    }

	$opt = get_option('imge_pwc_reg_users');
	$opt[$user_id]['pw_mod_date'] = time();
	update_option('imge_pwc_reg_users', $opt);
	update_user_meta( $user_id, 'imge_pwc_mod_date', time() );
}
add_action( 'profile_update', 'imge_pwc_profile_update' );

function imge_pwc_password_reset( $user ) {

	// update_user_meta( $user->data->ID, 'imge_pwc_mod_date', time() );

	$opt = get_option('imge_pwc_reg_users');
	$opt[$user->ID]['pw_mod_date'] = time();
	update_option('imge_pwc_reg_users', $opt);
	update_user_meta( $user->ID, 'imge_pwc_mod_date', time() );
}
add_action( 'password_reset', 'imge_pwc_password_reset' );


function imge_pwc_handle_log_in( $user, $username, $password ) {
	echo get_user_meta( $user_id, 'imge_pwc_mod_date', true );
	$limit = (get_option('imge_pwc_limit')) ? get_option('imge_pwc_limit') : 90;
	$type  = (get_option('imge_pwc_type')) ? get_option('imge_pwc_type') : 'day';

	// Check if an error has already been set
	if ( is_wp_error( $user ) )
		return $user;
	// Check we're dealing with a WP_User object
	if ( ! is_a( $user, 'WP_User' ) )
		return $user;
	// This is a log in which would normally be succesful
	$user_id = $user->data->ID;
	// If no timestamp set, it's probably the user's first log in attempt since this plugin was installed, so set the timestamp to now
	$timestamp = (int) get_user_meta( $user_id, 'imge_pwc_mod_date', true );
	if ( empty( $timestamp ) ) {
		$timestamp = time();
		update_user_meta( $user_id, 'imge_pwc_mod_date', $timestamp );
	}
	// Compare now to time stored in meta
	$diff         = time() - $timestamp;
	$login_expiry = 60 * 60 * 24 * $limit; 

	if ($type == "min") $type = floor($diff / 60);
	if ($type == "hrs") $type = floor($diff / 3600);
	if ($type == "day") $type = floor(($diff / 3600) / 24);

	$opt = get_option('imge_pwc_reg_users');


	// Expired
	// if ( $diff >= $login_expiry )
	// echo $type . "<br>" . $limit . "<br>" . get_user_meta( $user_id, 'imge_pwc_mod_date', true ) . '<br>' . $opt[$user_id]['pw_mod_date'];
	if ( $type >= $limit )
		$user = new WP_Error( 'authentication_failed', sprintf( __( '<strong>ERROR</strong>: You must <a href="%s">reset your password</a>.', 'imge_pwc_mod_date' ), site_url( 'wp-login.php?action=lostpassword', 'login' ) ) );
	return $user;
}
add_filter( 'authenticate', 'imge_pwc_handle_log_in', 30, 3 );









function imge_pwc_add_overlay(){
	
	$current_user = wp_get_current_user();
	$reg_users = get_option('imge_pwc_reg_users');
	$mod_date = ($reg_users) ? $reg_users[$current_user->id]['pw_mod_date'] : "null";

	$limit = (get_option('imge_pwc_limit')) ? get_option('imge_pwc_limit') : 90;
    $type = (get_option('imge_pwc_type')) ? get_option('imge_pwc_type') : 'day';

	$overlay = '<div id="imge_pwc-overlay">';
	$overlay .= '<div class="imge_pwc-alert wp-core-ui">';
	$overlay .= '<div class="imge_pwc-desc">Hi ' . $current_user->display_name . ', please update your password.</div>';
	$overlay .= '<a href="/wp-admin/profile.php" class="button button-primary button-large">Update Now</a>';
	$overlay .= '</div></div>';


	if ($mod_date == 'null'){
		wp_enqueue_style( 'imge_pwc-style', plugins_url( 'imge-passchange/css/imge_pwc.css', dirname(__FILE__) ) );
		echo $overlay;
		return;
	}
	

	$t = $mod_date;
	$diff = time() - $t;
	$s = $diff % 60;
	$m = floor(($diff / 60) % 60 );
	$h = floor($diff / 3600);
	$d = floor($h / 24);

	if ($type == "min") $type = floor($diff / 60);
	if ($type == "hrs") $type = floor($diff / 3600);
	if ($type == "day") $type = floor(($diff / 3600) / 24);


	if ($type >= ($limit-5) && $type < $limit) add_action('admin_notices', 'imge_pwc_nag_notice');

	if ($type >= $limit) {
		// wp_enqueue_style( 'imge_pwc-style', plugins_url( 'imge-passchange/css/imge_pwc.css', dirname(__FILE__) ) );
		// echo $overlay;
		wp_logout();
	}

	 
	// echo $d . ' : ' . $h . ' : ' . $m . ' : ' . $s;

}

function imge_pwc_nag_notice(){
	$current_user = wp_get_current_user();
	echo '<div class="update-nag"><p>Hi ' . $current_user->display_name . ', you need to update your password soon. <a href="/wp-admin/profile.php" style="margin:0 10px 0 25px;" class="button button-primary button-large">Update Now</a></p></div>';
}

function imge_pwc_update_notice() {
	echo '<div class="updated"><p>Updated.</p></div>';
}

function imge_pwc_options_page(){

	if (! current_user_can('edit_users') ) return;

	$blogusers = get_users();

	$current_user = wp_get_current_user();
	$opt = get_option('imge_pwc_reg_users');
	$mod_date = $opt[$current_user->id]['pw_mod_date'];

	?>
	<div class="wrap">
		<h2>Password Management</h2><p></p>
	    <form id="imge_pwc_form" method="post">
		    <?php
		    wp_nonce_field('imge_pwc', 'imge_pwc_nonce');
		    $limit = (get_option('imge_pwc_limit')) ? get_option('imge_pwc_limit') : 90;
		    $type = (get_option('imge_pwc_type')) ? get_option('imge_pwc_type') : 'day';
		    ?>
		    <div class="formRow"><label>Force password update every </label><input type="text" name="imge_pwc_limit" value="<?php echo $limit;?>" />
		    	<select name="imge_pwc_type">
		    		<option <?php echo ($type == 'min') ? 'selected' : ''; ?> value="min">Minutes</option>
		    		<option <?php echo ($type == 'hrs') ? 'selected' : ''; ?> value="hrs">Hours</option>
		    		<option <?php echo ($type == 'day') ? 'selected' : ''; ?> value="day">Days</option>
		    	</select>
		    	<input name="submit" class="button button-primary" value="Save" type="submit">
		    	<p><hr></p>
		    </div>
		</form>
	</div>
    
    <?php

    echo '<i>' . $opt[$current_user->id]['display_name'] . ' your password was updated <b>' . human_time_diff( $mod_date ) . ' ago</b></i>';

	// foreach ($opt as $user) {
	// 	if ($user['pw_mod_date'] == 'null'){
	// 		echo $user['display_name'] . ' please update your password<br>';
	// 	}else{
	// 		echo $user['display_name'] . ' your password was updated ' . human_time_diff( $mod_date ) . ' ago<br>';	
	// 	} 
	// }

}
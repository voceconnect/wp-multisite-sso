<?php
/*
  Plugin Name: WP Multisite SSO
  Version: 0.1
  Plugin URI: http://voceconnect.com/
  Description: Single sign on for a multisite network. Users are authenticated across all sites within the network.
  Author: Voce Platforms, Sean McCafferty
  Author URI: http://voceconnect.com/
 */

/*
 * This plugin uses the main blog as the control for if a user's authentication
 * with the sub sites of the network. It currently assumes that a user is can be
 * authorized on the main blog of the network.
 */
class WP_MultiSite_SSO {
	const USER_META_KEY = 'multisite-sso';
	const LOGIN_ACTION  = 'sso-login';
	const LOGOUT_ACTION = 'sso-logout';

	private static $user_hash_md5_format = 'multsite_sso-user_id-%s';

	public static function init() {
		// no need to run if this is not a multisite install
		if ( !is_multisite() )
			return;

		// if requesting to sso login
		if ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION === $_REQUEST['action'] ) && isset( $_REQUEST['sso'] ) ) {
			self::authenticate_user_on_blog();
			return;
		} elseif ( isset( $_REQUEST['action'] ) && ( self::LOGOUT_ACTION === $_REQUEST['action'] ) ) {
			wp_logout();
			// forcing redirect to cookies are removed
			wp_safe_redirect();
			die;
		}

		// hook in to login/logout
		add_action( 'wp_login', array( __CLASS__, 'handle_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'handle_logout' ) );
	}

	public static function get_network_sites( $network_sites = array() ) {
		// get list of sites
		$site_list = wp_get_sites();

		$network_sites = array_map( function( $site_object ) {
			$blog_id = ( $site_object['blog_id'] ) ? $site_object['blog_id'] : false;

			$blog_url = false;
			if ( !empty( $blog_id ) )
				$blog_url = get_site_url( $blog_id );

			return $blog_url;
		}, $site_list );

		// remove any duplicates or empty values
		$network_sites = array_unique( $network_sites );

		return $network_sites;
	}

	/**
	 * Handles sign a user into the main blog when a user logins in to a sub site.
	 * @global type $current_site
	 * @param type $username
	 * @param type $user
	 */
	public static function handle_login( $username, $user ) {
		global $current_site;
		
		// setup variables
		$time      = time();
		$user_hash = md5( sprintf( self::$user_hash_md5_format, $user->ID ) );

		// add reference to hash to the user's meta, mask names in meta
		$user_meta = array(
			'key'   => $user_hash,
			'value' => $time
		);
		update_user_meta( $user->ID, self::USER_META_KEY, $user_meta );

		// build the sso object to send
		$sso_object = array(
			'user_hash' => $user_hash,
			'user_id'   => $user->ID,
			'blog_id'   => get_current_blog_id()
		);

		// encode the sso object
		$sso_object = json_encode( $sso_object );

		// encrypt the sso object
		$iv  = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB ), MCRYPT_RAND );
		$sso = base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso_object, MCRYPT_MODE_ECB, $iv ) );

		$action = self::LOGIN_ACTION;

		include __DIR__ . '/inc/sso.php';
		
		die;
	}

	public static function authenticate_user_on_blog() {
		// verify is a sso login request
		if ( !isset( $_REQUEST['action'] ) || ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION !== $_REQUEST['action'] ) ) || !isset( $_REQUEST['sso'] ) )
			return;

		// setup vars
		$sso = base64_decode( esc_attr( $_REQUEST['sso'] ) );

		// decrypt the sso object
		$iv  = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB ), MCRYPT_RAND );
		$sso = mcrypt_decrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso, MCRYPT_MODE_ECB, $iv );

		$sso_object = json_decode( $sso );

		// dont continue if sso_object doesn't exist
		if ( empty( $sso_object ) )
			return;

		$sso_user_hash = isset( $sso_object->user_hash ) ? $sso_object->user_hash : false;
		$sso_user_id   = isset( $sso_object->user_id ) ? $sso_object->user_id : false;
		$sso_blog_id   = isset( $sso_object->blog_id ) ? $sso_object->blog_id : false;

		// dont continue if one of these sso object values do not exist
		if ( empty( $sso_user_hash ) || empty( $sso_user_id ) || empty( $sso_blog_id ) )
			return;

		// obtain multisite sso user_meta of the specified user
		$user_meta = get_user_meta( $sso_user_id, self::USER_META_KEY, true );

		// dont continue if the value does not exist
		if ( !$user_meta )
			return;

		// dont continue if one of the user meta objects fo not exist
		$user_hash = isset( $user_meta['key'] ) ? $user_meta['key'] : false;
		$timestamp = isset( $user_meta['value'] ) ? $user_meta['value'] : false;

		if ( !$user_hash || !$timestamp )
			return;

		// dont continue if the timestamp has expired (is older than 2 minutes) or user hashes do not match
		if ( ( ( $timestamp + 60 * 2 ) < time() ) || $user_hash !== $sso_user_hash ) {
			// remove the meta, to keep everything clean
			delete_user_meta( $sso_user_id, self::USER_META_KEY );
			return;
		}

		// everything checks out, so authenticate the user in on the main blog
		wp_set_auth_cookie( $sso_user_id, true );

		// force redirect so ensure cookies apply
		wp_safe_redirect();

		die;
	}

	public static function handle_logout() {
		// create a blank sso object for logout
		$sso = array();
		
		// set logout action
		$action = self::LOGOUT_ACTION;

		include __DIR__ . '/inc/sso.php';

		die;
	}
}
add_action( 'init', array( 'WP_MultiSite_SSO', 'init' ) );
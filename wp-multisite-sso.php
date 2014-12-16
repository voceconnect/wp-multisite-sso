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

	private static $user_hash_md5_format = 'multsite_sso-user_id-%s';

	public static function init() {
		// no need to run if this is not a multisite install
		if ( !is_multisite() )
			return;

		// if requesting to sso login
		if ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION === $_REQUEST['action'] ) && isset( $_REQUEST['sso'] ) && isset( $_REQUEST['redirect_to'] ) ) {
			// need to allow the cross domain redirect for the network
			add_action( 'allowed_redirect_hosts', array( __CLASS__, 'allow_network_redirects' ) );
			self::authenticate_user_on_main_blog();
			return;
		}

		// no need to run if on the main site
		if ( is_main_site() )
			return;

		add_action( 'wp_login', array( __CLASS__, 'handle_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'handle_logout' ) );
	}

	public static function allow_network_redirects( $allowed_redirects = array() ) {
		// get list of blogs
		$blog_list = wp_get_sites();
		$blog_list = array_map( function( $site_object ) {
			return isset( $site_object['domain'] ) ? $site_object['domain'] : false;
		}, $blog_list );

		$blog_list = array_unique( $blog_list );

		// if domain mapping exists, attempt to map the sites to the mapped domain
		$mapped_domains = self::get_domain_mapped_blogs();
		$mapped_domains = array_map( function( $mapped_domain ) {
			return isset( $mapped_domain->domain ) ? $mapped_domain->domain : false;
		}, $mapped_domains );

		$mapped_domains = array_unique( $mapped_domains );

		// merge sites and mapped domains
		$allowed_redirects = array_merge( $allowed_redirects, $blog_list, $mapped_domains );

		$allowed_redirects = array_unique( $allowed_redirects );

		return $allowed_redirects;
	}

	public static function get_domain_mapped_blogs() {
		global $wpdb;

		if ( !function_exists( 'dm_text_domain' ) )
			return array();

		$mapped_domains = $wpdb->get_results( "SELECT blog_id, domain FROM {$wpdb->dmtable} WHERE active = 1", OBJECT_K );

		return empty( $mapped_domains ) ? array() : (array) $mapped_domains;
	}

	/**
	 * Handles sign a user into the main blog when a user logins in to a sub site.
	 * @global type $current_site
	 * @param type $username
	 * @param type $user
	 */
	public static function handle_login( $username, $user ) {
		global $current_site;

		// don't run if on the main site
		if ( is_main_site() )
			return;
		
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
		$sso = mcrypt_encrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso_object, MCRYPT_MODE_ECB, $iv );

		// get the main blog's login url to submit the request
		$main_blog_login_url = get_site_url( $current_site->blog_id , 'wp-login.php', 'login' );
		
		$redirect_to = ( isset( $_REQUEST['redirect_to'] ) && '/' !== $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : get_site_url( get_current_blog_id() );
		
		// add the sso object to login url
		$sso_args = array(
			'redirect_to' => $redirect_to,
			'action'      => self::LOGIN_ACTION,
			'sso'         => base64_encode( $sso )
		);
		$main_blog_login_url = add_query_arg( $sso_args, $main_blog_login_url );

		// send user to main blog, where the request will be intercepted, authenticated and redirected back
		wp_safe_redirect( $main_blog_login_url );

		exit;
	}

	public static function authenticate_user_on_main_blog() {
		// verify is a login request - todo check for wp-login.php
		if ( !isset( $_REQUEST['action'] ) || ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION !== $_REQUEST['action'] ) ) || !isset( $_REQUEST['sso'] ) || !isset( $_REQUEST['redirect_to'] ) )
			return;

		// setup vars
		$sso         = base64_decode( esc_attr( $_REQUEST['sso'] ) );
		$redirect_to = $_REQUEST['redirect_to'];

		// decrypt the sso object
		$iv  = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB ), MCRYPT_RAND );
		$sso = mcrypt_decrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso, MCRYPT_MODE_ECB, $iv );

		$sso_object = json_decode( $sso );

		// dont continue if sso_object doesn't exist
		if ( empty( $sso_object ) ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$sso_user_hash = isset( $sso_object->user_hash ) ? $sso_object->user_hash : false;
		$sso_user_id   = isset( $sso_object->user_id ) ? $sso_object->user_id : false;
		$sso_blog_id   = isset( $sso_object->blog_id ) ? $sso_object->blog_id : false;

		// dont continue if one of these sso object values do not exist
		if ( empty( $sso_user_hash ) || empty( $sso_user_id ) || empty( $sso_blog_id ) ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		// obtain multisite sso user_meta of the specified user
		$user_meta = get_user_meta( $sso_user_id, self::USER_META_KEY, true );

		// dont continue if the value does not exist
		if ( !$user_meta ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		// dont continue if one of the user meta objects fo not exist
		$user_hash = isset( $user_meta['key'] ) ? $user_meta['key'] : false;
		$timestamp = isset( $user_meta['value'] ) ? $user_meta['value'] : false;

		if ( !$user_hash || !$timestamp ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		// dont continue if the timestamp has expired (is older than 2 minutes) or user hashes do not match
		if ( ( ( $timestamp + 60 * 2 ) < time() ) || $user_hash !== $sso_user_hash ) {
			// remove the meta, to keep everything clean
			delete_user_meta( $sso_user_id, self::USER_META_KEY );

			wp_safe_redirect( $redirect_to );
			exit;
		}

		// everything checks out, so authenticate the user in on the main blog
		wp_set_auth_cookie( $sso_user_id, true );
		is_user_logged_in();
		wp_safe_redirect( $redirect_to );
		exit;
	}

	public static function handle_logout() {

	}

	public static function remove_users_authentication_on_main_blog() {
		
	}
}
add_action( 'init', array( 'WP_MultiSite_SSO', 'init' ) );
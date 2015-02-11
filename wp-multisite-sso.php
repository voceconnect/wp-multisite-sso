<?php
/*
  Plugin Name: WP Multisite SSO
  Version: 1.0.1
  Plugin URI: http://voceconnect.com/
  Description: Single sign on for a multisite network. Users are authenticated across all sites within the network.
  Author: Voce Platforms, Sean McCafferty
  Author URI: http://voceconnect.com/
 */

/*
 * This plugin will login/logout a user to the network sites using WP login/logout
 * functions. Usually implemented on a domain mapped environment - This plugin makes
 * use of JSONP in order to make cross domain calls, while this protocol is usually
 * enabled by default on web servers, this plugin will fail to work if it is not enabled.
 */
class WP_MultiSite_SSO {
	const USER_META_KEY = 'multisite-sso';
	const LOGIN_ACTION  = 'sso-login';
	const LOGOUT_ACTION = 'sso-logout';
	const SETTINGS_SLUG = 'wp_multisite_sso_settings';

	private static $user_hash_md5_format = 'multsite_sso-user_id-%s';

	public static function init() {
		// no need to run if this is not a multisite install
		if ( !is_multisite() )
			return;

		// if requesting to sso login/logout
		if ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION === $_REQUEST['action'] ) && isset( $_REQUEST['sso'] ) ) {
			self::authenticate_user_on_blog();
			return;
		} elseif ( isset( $_REQUEST['action'] ) && ( self::LOGOUT_ACTION === $_REQUEST['action'] ) ) {
			self::unauthenticate_user_on_blog();
			return;
		}

		// hook in to login/logout
		add_action( 'wp_login', array( __CLASS__, 'handle_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'handle_logout' ) );
		add_action( 'login_enqueue_scripts', function() {
			wp_enqueue_script( 'jquery' );
		} );
	}

	/**
	 * Gets a list of the sites on the network, using the domain mapping plugin
	 * domains if the plugin is in use.
	 * @param type $network_sites
	 * @return type
	 */
	public static function get_network_sites( $network_sites = array() ) {
		$network_sites = array();

		// get list of sites
		$sites = wp_get_sites();
		// assign domain to site associated by blog id
		foreach( $sites as $site ) {
			if ( !isset( $site['blog_id'] ) || !isset( $site['domain'] ) )
				continue;

			$network_sites[$site['blog_id']] = esc_url( $site['domain'] );
		}

		// if domain mapping exists, attempt to map the sites to the mapped domain
		$mapped_domains = self::get_domain_mapped_blogs();

		foreach( $mapped_domains as $mapped_domain ) {
			if ( !isset( $mapped_domain->domain ) || !isset( $mapped_domain->blog_id ) )
				continue;

			$network_sites[$mapped_domain->blog_id] = esc_url( $mapped_domain->domain );
		}

		return $network_sites;
	}

	/**
	 * Get the mapped domains if the Domain Mapping plugin is used
	 * @global type $wpdb
	 * @return type
	 */
	public static function get_domain_mapped_blogs() {
		global $wpdb;

		if ( !function_exists( 'dm_text_domain' ) )
			return array();

		$mapped_domains = $wpdb->get_results( "SELECT blog_id, domain FROM {$wpdb->dmtable} WHERE active = 1", OBJECT_K );

		return empty( $mapped_domains ) ? array() : (array) $mapped_domains;
	}

	/**
	 * Provides the functionality to sign the user in to the network sites once
	 * they have signed in to the current blog.
	 * @global type $current_site
	 * @param type $username
	 * @param type $user
	 */
	public static function handle_login( $username, $user ) {
		global $current_site;

		// setup variables
		$time      = time();
		$user_hash = md5( sprintf( self::$user_hash_md5_format, $user->ID ) );

		$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( home_url() ) );

		$current_blog_id = get_current_blog_id();

		// IP address.
		$ip_address = '';
		if ( !empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}

		// User-agent.
		$user_agent = '';
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
		}

		foreach( array_keys( $network_sites ) as $blog_id ) {
			// build the sso objects to send
			$sso_objects[$blog_id] = array(
				'user_hash'    => $user_hash,
				'user_id'      => $user->ID,
				'src_blog_id'  => $current_blog_id,
				'dest_blog_id' => $blog_id,
				'timestamp'    => $time,
				'ip_address'   => $ip_address,
				'user_agent'   => $user_agent
			);
		}

		// encrypt the sso object
		$iv  = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB ), MCRYPT_RAND );

		$sso_objects = array_map( function( $sso_object ) use ( $iv ) {
			// encode the sso object
			$sso_object = json_encode( $sso_object );
			return base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso_object, MCRYPT_MODE_ECB, $iv ) );
		}, $sso_objects );

		// add reference to hash to the user's meta, store the time and all sso objects
		$user_meta = array(
			'hash'  => $user_hash,
			'value' => array(
				'timestamp' => $time,
				'keys'      => $sso_objects
			)
		);

		update_user_meta( $user->ID, self::USER_META_KEY, $user_meta );

		$action = self::LOGIN_ACTION;

		include __DIR__ . '/inc/sso.php';
		
		die;
	}

	/**
	 * Logic to authenticate a user if the request is a `self:LOGIN_ACTION`. Acting as a JSONP response
	 * to allow a cross domain request
	 */
	private static function authenticate_user_on_blog() {
		header('Content-type: application/javascript; charset=utf-8');

		// verify is a sso login request
		if ( !isset( $_REQUEST['action'] ) || ( isset( $_REQUEST['action'] ) && ( self::LOGIN_ACTION !== $_REQUEST['action'] ) ) || !isset( $_REQUEST['sso'] ) )
			return;

		// setup vars
		$request_sso = $_REQUEST['sso'];
		$sso         = base64_decode( esc_attr( $request_sso ) );

		// decrypt the sso object
		$iv  = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB ), MCRYPT_RAND );
		$sso = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_128, substr( AUTH_SALT, 0, 32 ), $sso, MCRYPT_MODE_ECB, $iv ), "\0");

		$sso_object = json_decode( $sso );

		// dont continue if sso_object doesn't exist
		if ( empty( $sso_object ) )
			return;

		$sso_user_hash    = isset( $sso_object->user_hash ) ? $sso_object->user_hash : false;
		$sso_user_id      = isset( $sso_object->user_id ) ? $sso_object->user_id : false;
		$sso_src_blog_id  = isset( $sso_object->src_blog_id ) ? $sso_object->src_blog_id : false;
		$sso_dest_blog_id = isset( $sso_object->dest_blog_id ) ? $sso_object->dest_blog_id : false;
		$sso_timestamp    = isset( $sso_object->timestamp ) ? $sso_object->timestamp : false;
		$sso_ip_address   = isset( $sso_object->ip_address ) ? $sso_object->ip_address : '';
		$sso_user_agent   = isset( $sso_object->user_agent ) ? $sso_object->user_agent : '';

		// dont continue if the ip address does not match the sso object's
		$ip_address = '';
		if ( !empty( $_SERVER['REMOTE_ADDR'] ) )
			$ip_address = $_SERVER['REMOTE_ADDR'];
		if ( $ip_address !== $sso_ip_address )
			return;

		// dont continue if the user-agent does not match the sso object's
		$user_agent = '';
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) )
			$user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
		if ( $user_agent !== $sso_user_agent )
			return;

		// dont continue if one of these sso object values do not exist
		if ( empty( $sso_user_hash ) || empty( $sso_user_id ) || empty( $sso_src_blog_id ) || empty( $sso_dest_blog_id ) || empty( $sso_timestamp ) )
			return;

		// obtain multisite sso user_meta of the specified user
		$user_meta = get_user_meta( $sso_user_id, self::USER_META_KEY, true );

		// dont continue if the value does not exist
		if ( empty( $user_meta ) )
			return;

		// dont continue if one of the user meta objects does not exist
		$user_hash = isset( $user_meta['hash'] ) ? $user_meta['hash'] : false;
		$meta_data = isset( $user_meta['value'] ) ? $user_meta['value'] : false;

		if ( empty( $user_hash ) || empty( $meta_data ) )
			return;

		$timestamp       = isset( $meta_data['timestamp'] ) ? $meta_data['timestamp'] : false;
		$encryption_keys = isset( $meta_data['keys'] ) ? $meta_data['keys'] : false;
		// make sure the encryption keys and timestamp exist
		if ( empty( $timestamp ) || empty( $encryption_keys ) )
			return;

		// dont continue if the timestamp has expired (is older than 2 minutes) or user hashes do not match
		if ( ( ( $timestamp + 60 * 2 ) < time() ) || $user_hash !== $sso_user_hash ) {
			// remove the meta, to keep everything clean
			delete_user_meta( $sso_user_id, self::USER_META_KEY );
			return;
		}

		// dont continue if the encryption key does not exist in the keys
		// if it does exist, remove the key from the list of keys to ensure
		// the key is only used once
		if ( in_array( $request_sso, $encryption_keys ) ) {
			$encryption_keys = array_diff( $encryption_keys, array( $request_sso ) );
			$user_meta['value']['keys'] = $encryption_keys;
			update_user_meta( $sso_user_id, self::USER_META_KEY, $user_meta );
		} else {
			return;
		}

		// everything checks out, so authenticate the user in on the main blog
		wp_set_auth_cookie( $sso_user_id, true );

		echo 'loadSitesCB' . "({'user_status': 'auth'})";

		die;
	}

	/**
	 * Provides the functionality to log a user out of the network sites when they have
	 * signed out of the current blog.
	 */
	public static function handle_logout() {
		// create a blank sso objects for logout
		$sso_objects = array();
		
		// set logout action
		$action = self::LOGOUT_ACTION;

		include __DIR__ . '/inc/sso.php';

		die;
	}

	/**
	 * Logic to unauthenticate a user is the request is a `self:LOGOUT_ACTION'. Acting as a JSONP response
	 * to allow a cross domain request
	 */
	private static function unauthenticate_user_on_blog() {
		header('Content-type: application/javascript; charset=utf-8');

		wp_logout();

		echo 'loadSitesCB' . "({'user_status': 'unauth'})";

		die;
	}
}
add_action( 'init', array( 'WP_MultiSite_SSO', 'init' ) );

if ( is_admin() )
	include __DIR__ . '/admin/admin.php';
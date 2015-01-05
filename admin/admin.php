<?php

if ( !is_admin() )
	return;

class WP_MultiSite_SSO_Admin {

	public static function init() {
		add_action( 'admin_menu', array( 'WP_MultiSite_SSO_Admin', 'admin_menu' ) );
		add_action( 'admin_init', array( 'WP_MultiSite_SSO_Admin', 'admin_init' ) );
	}

	public static function admin_init() {
		register_setting(
			WP_MultiSite_SSO::SETTINGS_SLUG,
			WP_MultiSite_SSO::SETTINGS_SLUG,
			array( 'WP_MultiSite_SSO_Admin', 'sanitize_settings' )
		);

		add_settings_section( 'default', '', '', WP_MultiSite_SSO::SETTINGS_SLUG );

		add_settings_field( 'load_wp_css', __( 'Include Default WordPress Login CSS' , 'wp-multisite-sso' ), array( 'WP_MultiSite_SSO_Admin', 'load_wp_css_callback' ), WP_MultiSite_SSO::SETTINGS_SLUG );

		add_settings_field( 'load_custom_css', __( 'Include Custom WordPress Login CSS' , 'wp-multisite-sso' ), array( 'WP_MultiSite_SSO_Admin', 'load_custom_css_callback' ), WP_MultiSite_SSO::SETTINGS_SLUG );

		add_settings_field( 'loginout_css', __( 'Modify Login/Logout Page CSS', 'wp-multisite-sso' ), array( 'WP_MultiSite_SSO_Admin', 'loginout_css_callback' ), WP_MultiSite_SSO::SETTINGS_SLUG );
	}

	public static function admin_menu() {
		add_options_page( __( 'WP Multisite SSO Settings', 'wp-multisite-sso' ), __( 'WP Multisite SSO Settings', 'wp-multisite-sso' ), 'manage_options', WP_MultiSite_SSO::SETTINGS_SLUG, array( 'WP_MultiSite_SSO_Admin', 'admin_page' ) );
	}

	public static function admin_page() {
		include __DIR__ . '/admin_template.php';
	}

	public static function sanitize_settings( $settings ) {
		$new_settings = array();

		if ( isset( $settings['load_wp_css'] ) )
			$new_settings['load_wp_css'] = intval( $settings['load_wp_css'] );
		else
			$new_settings['load_wp_css'] = 0;

		if ( isset( $settings['load_custom_css'] ) )
			$new_settings['load_custom_css'] = intval( $settings['load_custom_css'] );
		else
			$new_settings['load_custom_css'] = 0;

		if ( isset( $settings['loginout_css'] ) )
			$new_settings['loginout_css'] = esc_attr( $settings['loginout_css'] );

		return $new_settings;
	}

	public static function load_wp_css_callback() {
		self::checkbox_callback( 'load_wp_css' );
	}

	public static function load_custom_css_callback() {
		self::checkbox_callback( 'load_custom_css', 1, 'Depending on custom login styles used, may cause issues with SSO presentation.' );
	}

	private static function checkbox_callback( $setting_name, $default_value = 1, $description = '' ) {
		$sso_options = get_option( WP_MultiSite_SSO::SETTINGS_SLUG );
		$value       = isset( $sso_options[$setting_name] ) ? intval( $sso_options[$setting_name] ) : $default_value;

		printf( '<input type="checkbox" id="%1$s" name="wp_multisite_sso_settings[%1$s]" value="1" %2$s />', $setting_name, checked( intval( $value ), 1, false ) );

		if ( !empty( $description ) )
			printf( '<p class="description">%s</p>', esc_html( $description ) );
	}

	public static function loginout_css_callback() {
		$sso_options = get_option( WP_MultiSite_SSO::SETTINGS_SLUG );
		$css         = isset( $sso_options['loginout_css'] ) ? $sso_options['loginout_css'] : '';

		printf( '<textarea id="loginout_css" name="wp_multisite_sso_settings[loginout_css]" cols="50" rows="7">%s</textarea>', esc_attr( $css ) );
	}
}
add_action( 'init', array( 'WP_MultiSite_SSO_Admin', 'init' ) );
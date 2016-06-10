<?php
// failsafe to allow direct calls to the template
if ( is_null( $sso_objects ) || is_null( $action ) )
	die;

$site_args = array(
	'action' => $action
);

$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( esc_url( home_url() ) ) );

// add the site args to each site
$sso_sites = array();
foreach( $network_sites as $blog_id => $blog_url ) {
	$blog_args = $site_args;
	if ( isset( $sso_objects[$blog_id] ) ) {
		$blog_args['sso'] = urlencode( $sso_objects[$blog_id] );
	}

	$sso_sites[] = esc_url_raw( add_query_arg( $blog_args, $blog_url ) );
}

$body_text = __( 'Please wait...', 'wp-multisite-sso' );

if ( WP_MultiSite_SSO::LOGIN_ACTION === $action )
	$body_text = sprintf( __( 'Logging in to network sites. %s', 'wp-multisite-sso' ), $body_text );
else
	$body_text = sprintf( __( 'Logging out of network sites. %s', 'wp-multisite-sso' ), $body_text );

$sso_options     = get_option( WP_MultiSite_SSO::SETTINGS_SLUG );
$load_wp_css     = isset( $sso_options['load_wp_css'] ) ? intval( $sso_options['load_wp_css'] ) : 1;
$load_custom_css = isset( $sso_options['load_custom_css'] ) ? intval( $sso_options['load_custom_css'] ) : 1;
$custom_css      = isset( $sso_options['loginout_css'] ) ? $sso_options['loginout_css'] : '';

$body_classes = array( 'sso-body', 'login', 'login-action-login', 'wp-core-ui', 'locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) ) );
$body_classes = apply_filters( 'sso_login_logout_body_class', $body_classes, $action );

$login_header_url   = network_home_url();
$login_header_title = get_current_site()->site_name;
?>
<html>
	<head>
		<?php
		// include the WordPress login page CSS
		if ( !empty( $load_wp_css ) )
			wp_admin_css( 'login', true );

		// allows the use of wp_enqueue_script
		do_action( 'login_enqueue_scripts' );

		// include jQuery
		wp_print_scripts( array( 'jquery' ) );
		?>
		<script>			
			var sites_list, sites_to_load;

			// callback to perform any logic based on the ajax response
			function loadSitesCB(data) {
				loadSitesHelper(data);
			}

			// helper function that will make the ajax request to log a
			// user in to another one of the network sites
			function loadSitesHelper() {
				window.setTimeout(2000);

				(function($){
					if (sites_to_load.length > 0) {
						var site = sites_to_load.shift();
						$.ajax({
							url: site,
							cache: false,
							timeout: 2000,
							crossDomain: true,
							dataType: 'jsonp',
							jsonpCallback: 'loadSitesCB'
						})
						.success(function(data, textStatus, jqXHR) {
								// handled in the jsonp callback
						})
						.fail(function(jqXHR, textStatus, errorThrown) {
								loadSitesHelper();
						})
						.done(function(data, textStatus, jqXHR) {
								// no logic needed
						});
					} else {
						loadComplete();
					}
				})(jQuery);
			}

			// initial function to aggreigate the sites to authenticate
			function seqLoadSites() {
				sites_to_load = sites_list = <?php echo json_encode( $sso_sites ); ?>;

				loadSitesHelper();
			}

			// send the user back to the main page after SSO login/logout
			function loadComplete(){
			   window.location="<?php echo esc_url( apply_filters('wp-multisite-sso/redirect-to', home_url() ) ); ?>";
			}

			// start the login/logout logic after the sso page has loaded
			window.addEventListener("load", seqLoadSites, false);
		</script>
		<?php
		// include custom WordPress login CSS
		if ( !empty( $load_custom_css ) )
			wp_print_styles();

		// include any CSS specified on SSO settings page
		if ( !empty( $custom_css ) )
			printf( '<style type="text/css">%s</style>', esc_attr( $custom_css ) );

		// do any custom actions for the SSO login/logout page
		do_action( 'sso_head' );
		?>
	</head>
	<body class="<?php echo implode( ' ', $body_classes ); ?>">
		<div id="login">
			<h1><a href="<?php echo esc_url( $login_header_url ); ?>" title="<?php echo esc_attr( $login_header_title ); ?>" tabindex="-1" onclick="return false;"><?php bloginfo( 'name' ); ?></a></h1>
			<p id="nav"><?php echo esc_html( $body_text ); ?></p>
		</div>
	</body>
</html>

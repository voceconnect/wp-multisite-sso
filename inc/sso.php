<?php
if ( is_null( $sso_objects ) || is_null( $action ) )
	die;

$site_args = array(
	'action' => $action
);

$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( home_url() ) );

// add the site args to each site
$sso_sites = array();
foreach( $network_sites as $blog_id => $blog_url ) {
	$blog_args = $site_args;
	if ( isset( $sso_objects[$blog_id] ) ) {
		$blog_args['sso'] = urlencode( $sso_objects[$blog_id] );
	}

	$sso_sites[] = add_query_arg( $blog_args, $blog_url );
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
		if ( !empty( $load_wp_css ) )
			wp_admin_css( 'login', true );

		do_action( 'login_enqueue_scripts' );
		wp_print_scripts( array( 'jquery' ) );
		?>
		<script>
			(function($){
				var sites_list, sites_to_load;

				function loadSitesHelper(sites_to_load) {
					window.setTimeout(2000);
					
					if (sites_to_load.length > 0) {
						var site = sites_to_load.shift();

						$.ajax({
							url: site,
							type: 'POST',
							async: false,
							cache: false,
							timeout: 15000,
							crossDomain: true,
							dataType: 'jsonp',
							error: function() {
								loadSitesHelper(sites_to_load);
							},
							success: function(msg) {
								loadSitesHelper(sites_to_load);
							}
						});
					} else {
						loadComplete();
					}
				}
				function seqLoadSites() {
					sites_to_load = sites_list = <?php echo json_encode( $sso_sites ); ?>;

					loadSitesHelper(sites_to_load);
				}
				function loadComplete(){
				   window.location="<?php echo home_url(); ?>";
				}
				window.addEventListener("load", seqLoadSites, false);
			})(jQuery);
		</script>
		<?php
		if ( !empty( $load_custom_css ) )
			wp_print_styles();

		if ( !empty( $custom_css ) )
			printf( '<style type="text/css">%s</style>', esc_attr( $custom_css ) );

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
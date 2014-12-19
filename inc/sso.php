<?php
if ( is_null( $sso ) || is_null( $action ) )
	die;

$site_args = array(
	'action' => $action
);

if ( !empty( $sso ) )
	$site_args['sso'] = urlencode( $sso );

$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( home_url() ) );

// add the site args to each site
$network_sites = array_map( function( $network_site ) use ($site_args) {
	return add_query_arg( $site_args, $network_site );
}, $network_sites );

?>
<html>
	<head>
		<script>
		function loadSite(site) {
			var ssoImg = document.createElement("img");
			ssoImg.setAttribute('src', site);
		}
		function seqLoadSites() {
			var sites = <?php echo json_encode( array_values( $network_sites ) ); ?>;
			for (site in sites){
				loadSite(sites[site]);
			}
			loadComplete();
		}
		function loadComplete(){
		   window.location="<?php echo home_url(); ?>";
		}
		window.addEventListener("load", seqLoadSites, false);
		</script>
		<?php do_action( 'sso_head' ); ?>
	</head>
	<body>
		<p>Please wait.....</p>
	</body>
</html>
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

?>
<html>
	<head>
		<script>
			function loadSite(site) {
				var ssoImg = document.createElement("img");
				ssoImg.setAttribute('src', site);
			}
			function seqLoadSites() {
				var sites = <?php echo json_encode( $sso_sites ); ?>;
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
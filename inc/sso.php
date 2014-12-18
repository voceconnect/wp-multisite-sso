<?php
if ( is_null( $sso ) || is_null( $action ) )
	die;

$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( home_url() ) );

$site_args = array(
	'action' => $action
);

if ( !empty( $sso ) )
	$site_args['sso'] = urlencode( $sso );

?>
<html>
	<head>
		<script>
		window.addEventListener("load", loadComplete, false);
		function loadComplete(){
		   window.location="<?php echo home_url(); ?>";
		}
		</script>
	</head>
	<body>
		<p>Please wait.....</p>
		<?php foreach( $network_sites as $network_site ) : ?>
			<img src="<?php echo add_query_arg( $site_args, $network_site ); ?>" />
		<?php endforeach; ?>
	</body>
</html>
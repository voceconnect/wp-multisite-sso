<?php
if ( is_null( $sso ) || is_null( $action ) )
	die;

$network_sites = array_diff( WP_MultiSite_SSO::get_network_sites(), array( home_url() ) );
?>
<html>
	<head>
		<script>
		function loadComplete(){
		   window.location="<?php echo home_url(); ?>";
		}
		</script>
	</head>
	<body onload="loadComplete();">
		<p>Please wait.....</p>
		<?php foreach( $network_sites as $network_site ) : ?>
			<img src="<?php echo add_query_arg( array( 'action' => $action, 'sso' => $sso ), $network_site ); ?>" />
		<?php endforeach; ?>
	</body>
</html>
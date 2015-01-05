<div class="wrap">
	<?php screen_icon(); ?>
	<h2>WP Multisite SSO Settings</h2>
	<form method="post" action="options.php">
		<?php
		settings_fields( WP_MultiSite_SSO::SETTINGS_SLUG );
		do_settings_sections( WP_MultiSite_SSO::SETTINGS_SLUG );
		submit_button();
		?>
	</form>
</div>
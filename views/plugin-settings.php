<div class="wrap">
	<?php screen_icon('plugins'); ?> <h2><?php $title; ?></h2>
	<form method="POST" action="options.php">
		<?php
			settings_fields(Base64ImagesSettings::SETTINGS_NAME);
			do_settings_sections(Base64ImagesAdmin::SETTINGS_PAGE);
            submit_button(__('Save Changes', 'base-64-images-plugin-strings'));
		?>
	</form>
</div>

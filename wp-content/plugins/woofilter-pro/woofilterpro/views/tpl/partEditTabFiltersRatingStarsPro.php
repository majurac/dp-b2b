<div class="row-settings-block wpfStarsTypeBlock wpfTypeSwitchable" data-type="linestars liststars">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Stars Settings', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Stars Settings', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Icon size', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Size of stars in pixels.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::text('f_stars_icon_size', array('value' => '20', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Icon color', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select the color of rating stars.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::colorpicker('f_stars_icon_color', array('value' => '#eeee22')); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Unselected icon color', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Choose the color for unselected star\'s.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::colorpicker('f_stars_leer_color', array('value' => '#eeeeee')); ?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width150">
				<?php esc_html_e('Border color', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Choose the color of the star\'s borders.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::colorpicker('f_stars_icon_border', array('value' => '#a3a3a3')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use exact values', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Use exact values instead of range', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_use_exact_values', array()); ?>
		</div>
	</div>
</div>

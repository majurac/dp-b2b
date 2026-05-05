<div class="row-settings-block wpfSwitchTypeBlock wpfTypeSwitchable" data-type="switch">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Switch Settings', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Switch Settings', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width100">
				<?php esc_html_e('Switch Type', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_switch_type', array(
					'options' => array(
						'round' => esc_attr__('Rounded', 'woo-product-filter'),
						'square' => esc_attr__('Square', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width100">
				<?php esc_html_e('Height', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_switch_height', array('value' => 16, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
		<div class="settings-value settings-w100 inner-float">
			<div class="settings-value-label woobewoo-width100 settings-float">
				<?php esc_html_e('Color', 'woo-product-filter'); ?>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_switch_color', array('value' => '#b0bec5')); ?> 
				<div class="settings-label-after">
					<?php esc_html_e('unchecked', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="settings-float">
				<?php HtmlWpf::colorpicker('f_switch_color_checked', array('value' => '#81d742')); ?>
				<div class="settings-label-after">
					<?php esc_html_e('checked', 'woo-product-filter'); ?>
				</div>
			</div>
		</div>
	</div>
</div>

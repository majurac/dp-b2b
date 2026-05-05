<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown mul_dropdown">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Max terms show', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('The maximum number of terms to be shown before Show more button', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
			HtmlWpf::text('f_max_show_more', array(
				'value' => ( isset($this->settings['f_max_show_more']) ? (int) $this->settings['f_max_show_more'] : '' ),
				'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"',
			));
			?>
		</div>
	</div>
</div>

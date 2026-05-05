<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Select default id', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
			HtmlWpf::text('f_select_default_id', array(
				'value' => ( isset($this->settings['f_select_default_id']) ? (int) $this->settings['f_select_default_id'] : '' ),
				'attrs' => 'class="woobewoo-flat-input"',
			));
			?>
		</div>
	</div>
</div>


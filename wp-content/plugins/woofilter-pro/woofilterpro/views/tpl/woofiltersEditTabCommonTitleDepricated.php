<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show title label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show title label', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::selectbox('f_enable_title', array(
					'options' => array(
						'no' => esc_attr__( 'No', 'woo-product-filter' ),
						'yes_close' => esc_attr__( 'Yes, show as close', 'woo-product-filter' ),
						'yes_open' => esc_attr__( 'Yes, show as opened', 'woo-product-filter' ),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>

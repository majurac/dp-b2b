<div class="settings-value settings-w100" data-parent="f_list" data-values="custom_meta_field_check">
	<div class="settings-value-label">
		<?php esc_html_e( 'Custom meta-field', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'You can enter a custom name for the meta field', 'woo-product-filter' ); ?>"></i>
	</div>
	<?php
	HtmlWpf::text( 'f_custom_meta_field', array( 'attrs' => 'class="woobewoo-flat-input"' ) );
	?>
</div>


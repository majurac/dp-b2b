<div class="row-settings-block wpfTypeSwitchable" data-type="list multi mul_dropdown buttons text">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Product selection', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Filter products by different categories using and/or logic.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<?php
				HtmlWpf::selectbox('f_multi_logic', array(
					'options' => array(
						'or'  => esc_attr__('Should be at least in one', 'woo-product-filter'),
						'and' => esc_attr__('Should be in all selected', 'woo-product-filter'),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>

<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown mul_dropdown">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show images', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show element image. Not working with dropdown.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_images', array('checked' => 0)); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_show_images">
			<div class="settings-value-label">
				<?php esc_html_e('Images size', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set images width and height.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php 
				HtmlWpf::text('f_images_width', array(
					'placeholder' => '20',
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
			x
			<?php 
				HtmlWpf::text('f_images_height', array(
					'placeholder' => '20',
					'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"',
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="list">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Alphabetical index', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show Alphabetical index. Works only for parent elements.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_abc_index', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="mul_dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show search for dropdown', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show search field in multiple dropdown box', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_dropdown_search', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_dropdown_search">
			<?php HtmlWpf::text('f_dropdown_search_text', array('placeholder' => esc_attr__('Search', 'woo-product-filter'), 'attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>

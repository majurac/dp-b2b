<?php
$tamplatePath = FrameWpf::_()->getModule('woofilters')->getView()->getPath('woofiltersEditTabCommonTitle');
list( $attrDisplay ) = FrameWpf::_()->getModule('woofilters')->getAttributesDisplay(false);
unset($attrDisplay[0]);

// fallback for depricated optionality
if ( $tamplatePath ) {
	FrameWpf::_()->getModule('woofilters')->getView()->display('woofiltersEditTabCommonTitle');
} else {
	ViewWpf::display('woofiltersEditTabCommonTitleDepricated');
}
?>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use title as placeholder', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Set filter title as search input placeholder.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_title_as_placeholder', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Search by', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Chose searching params.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value wpfTypeSwitchable settings-w100 settings-value-elementor-row-revert">
				<?php HtmlWpf::checkboxToggle('f_search_by_title', array()); ?>
				<div class="settings-label-after">
					<?php esc_html_e('Title', 'woo-product-filter'); ?>
				</div>
			</div>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_content', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('Content', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_excerpt', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('Excerpt', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_tax_categories', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('Categories', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_tax_tags', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('Tags', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_attributes', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('Attributes', 'woo-product-filter'); ?>
			</div>
		</div>
		<div class="settings-value settings-w100 wpf-multi-select" data-parent="f_search_by_attributes">
			<div class="settings-value-label">
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'If you need to search by all attributes, then leave the field empty', 'woo-product-filter' ); ?>"></i>
			</div>
			<?php
			HtmlWpf::selectlist( 'f_search_by_attributes_list', array(
				'options' => $attrDisplay,
			) );
			?>
		</div>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle('f_search_by_meta_sku', array()); ?>
			<div class="settings-label-after">
				<?php esc_html_e('SKU', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Only full matches search by default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
			</div>
		</div>
		<?php if (taxonomy_exists('pwb-brand')) { ?>
			<div class="settings-value settings-w100 settings-value-elementor-row-revert">
				<?php HtmlWpf::checkboxToggle('f_search_by_perfect_brand', array()); ?>
				<div class="settings-label-after">
					<?php esc_html_e('Brand', 'woo-product-filter'); ?>
					<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr('For plugin Perfect Brands for WooCommerce'); ?>"></i>
				</div>
			</div>
		<?php } ?>
		<div class="settings-value settings-w100 settings-value-elementor-row-revert">
			<?php HtmlWpf::checkboxToggle( 'f_search_by_meta_fields', array() ); ?>
			<div class="settings-label-after">
				<?php esc_html_e( 'Meta fields', 'woo-product-filter' ); ?>
			</div>
		</div>
		<div class="settings-value settings-w100" data-parent="f_search_by_meta_fields">
			<div class="settings-value-label">
				<?php esc_html_e( 'If you do not add field names, search by meta fields will not be (List separated by commas)', 'woo-product-filter' ); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'List meta fields to search only in them', 'woo-product-filter' ); ?>"></i>
			</div>
			<?php
			HtmlWpf::text( 'f_search_by_meta_fields_list', array(
				'attrs' => 'class="woobewoo-flat-input"',
			) );
			?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Logic', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_query_logic', array(
					'options' => array(
						'and' => esc_attr__( 'And', 'woo-product-filter' ),
						'or' => esc_attr__( 'Or', 'woo-product-filter' ),
					),
					'value' => 'or',
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Search by full word only', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Search only by full words only.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_search_only_by_full_word', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Exclude from search results', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Exclude from search results selected items or items with selected taxonomies.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value woobewoo-width-full settings-w100">
			<?php HtmlWpf::selectlist('f_mlist', array('size' => 10, 'options' => $this->excludedOptions)); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Autocomplete', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Autocomplete relevant variants. Works only when searching by title, categories and tags.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/search-by-text-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9 settings-value-elementor-row-revert">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_search_autocomplete', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Disable auto filtering when focus out', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('You can disable filtering products if focus out search input', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9 settings-value-elementor-row-revert">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_disable_autofiltering', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Not display the result type', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('will not display the result type (e.g. product title:, product category: etc. )', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9 settings-value-elementor-row-revert">
		<div class="settings-value">
			<?php HtmlWpf::checkboxToggle('f_not_display_result_type', array()); ?>
		</div>
	</div>
</div>

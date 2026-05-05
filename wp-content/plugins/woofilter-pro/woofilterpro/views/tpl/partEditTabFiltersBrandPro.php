<?php
$tamplatePath = FrameWpf::_()->getModule('woofilters')->getView()->getPath('woofiltersEditTabCommonTitle');

// fallback for depricated optionality
if ( $tamplatePath ) {
	FrameWpf::_()->getModule('woofilters')->getView()->display('woofiltersEditTabCommonTitle');
} else {
	ViewWpf::display('woofiltersEditTabCommonTitleDepricated');
}
?>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Depending on whether you need one or several brands to be available at the same time, show your brands list as checkbox or dropdown.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => array(
						'list' => esc_attr__( 'Radiobuttons list (single select)', 'woo-product-filter' ),
						'dropdown' => esc_attr__( 'Dropdown (single select)', 'woo-product-filter' ),
						'mul_dropdown' => esc_attr__( 'Multiple Dropdown', 'woo-product-filter' ),
						'multi' => esc_attr__( 'Checkbox list (multiple select)', 'woo-product-filter' ),
					),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
	</div>
</div>
<?php
DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersMultiSelect');
?>
<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown mul_dropdown">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Dropdown label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Dropdown first option text.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_dropdown_first_option_text', array('placeholder' => esc_attr__('Select all', 'woo-product-filter'), 'attrs' => 'class="woobewoo-flat-input"')); ?>
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

<?php
ViewWpf::setCode('woofilters');
ViewWpf::display('woofiltersEditTabCustomTags');
ViewWpf::setCode('woofilterpro');
?>

<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Sort by', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set categories sorting by ascendance or descendance.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('f_sort_by', array(
					'options' => array('asc' => 'ASC', 'desc' => 'DESC', 'default' => 'Default'),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<?php
			DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSortAsNumbers');
		?>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Order by custom', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Brands are displayed according to the order of their selection in the input fields.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_order_custom', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show count', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show count display the number of products that have the appropriate parameter (attribute, category, tag, brand).', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_count', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide brands without products', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Hide brands without products.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hide_empty', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_hide_empty">
			<div class="settings-value-label">
				<?php esc_html_e('Same behavior for active filter', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('By default if a value is selected in the filter, items without products will still be displayed (this was done on purpose so that you can quickly switch between items).If the option is enabled, empty elements will be hidden regardless of the filter activity (this also applies to loading via ajax and when reloading the page with the parameters specified in the url).', 'woo-product-filter')); ?>"></i>
			</div>
			<?php HtmlWpf::checkboxToggle('f_hide_empty_active', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Product brands', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select product brands to be displayed on your site from the list. If you want to select several brands, hold the "Shift" button and click on category names. Or you can hold "Ctrl" and click on brand names. Press "Ctrl" + "a" for checking all brands.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value woobewoo-width-full">
			<?php
				HtmlWpf::selectlist('f_mlist', array(
					'options' => $this->wpfBrandPro['displays'],
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label">
				<?php esc_html_e( 'Include children', 'woo-product-filter' ); ?>
			</div>
			<?php HtmlWpf::checkboxToggle( 'f_mlist_with_children', array() ); ?>
		</div>
	</div>

</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Make selected brands as default', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Selected brands will be marked as default.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hidden_brands', array()); ?>
		</div>
	</div>
</div>     
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Exclude terms ids', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Exclude brand terms from filter by ids. Example input: 1,2,3.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_exclude_terms', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="list">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show search', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_search_input', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Layout', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select a vertical or horizontal layout and set the count of columns.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php
				HtmlWpf::selectbox('f_layout', array(
					'options' => array('ver' => esc_attr__('Vertical', 'woo-product-filter'), 'hor' => esc_attr__('Horizontal', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w50" data-select="f_layout" data-select-value="ver">
			<div class="settings-value-label">
				<?php esc_html_e('Columns', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_ver_columns', array('value' => 1, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown mul_dropdown">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Maximum height in frontend', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set maximum displayed height in frontend.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_max_height', array('value'=>'200', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
	</div>
</div> 
<?php DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersMaxShowMore'); ?>

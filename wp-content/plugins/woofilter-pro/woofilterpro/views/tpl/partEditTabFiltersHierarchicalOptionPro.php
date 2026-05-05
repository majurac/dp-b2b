<div class="settings-value wpfTypeSwitchable" data-type="multi list radio switch" data-parent-switch="f_show_hierarchical">
	<div class="settings-value-label">
		<?php esc_html_e('Collapsible', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('If enabled, then show only parent elements, if there are children, they are minimized.', 'woo-product-filter'); ?>"></i>
	</div>
	<?php HtmlWpf::checkboxToggle('f_multi_collapsible', array()); ?>
</div>
<div class="settings-value wpfTypeSwitchable wpfDependencyHidden" data-type="multi list radio switch" data-parent-switch="f_multi_collapsible">
	<div class="settings-value-label">
		<?php esc_html_e('Unfolding children', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('When you select a parent filter element, all child nodes will be automatically opened.', 'woo-product-filter'); ?>"></i>
	</div>
	<?php HtmlWpf::checkboxToggle('f_multi_unfold_child', array()); ?>
</div>
<div class="settings-value wpfTypeSwitchable wpfDependencyHidden" data-type="multi list radio switch" data-parent-switch="f_multi_unfold_child">
	<div class="settings-value-label">
		<?php esc_html_e('Unfolding all nesting levels', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Unfold all nesting levels of children, not just the first', 'woo-product-filter'); ?>"></i>
	</div>
	<?php HtmlWpf::checkboxToggle('f_multi_unfold_all_levels', array()); ?>
</div>
<div class="settings-value wpfTypeSwitchable wpfDependencyHidden" data-type="multi list radio switch" data-parent-switch="f_multi_collapsible">
	<div class="settings-value-label">
		<?php esc_html_e('Automatically collapses parent', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Automatically collapses the parent if all child categories collapse', 'woo-product-filter'); ?>"></i>
	</div>
	<?php HtmlWpf::checkboxToggle('f_multi_auto_collapses_parent', array('checked' => 0)); ?>
</div>
<div class="settings-value wpfTypeSwitchable" data-type="<?php echo ( empty($this->settings['attrDisplay']) ? 'multi' : 'list switch' ); ?>" data-parent-switch="f_show_hierarchical">
	<div class="settings-value-label">
		<?php esc_html_e('Extend parent select', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('If parent filter element was selected then extend selection to child elements.', 'woo-product-filter'); ?>"></i>
	</div>
	<?php HtmlWpf::checkboxToggle('f_multi_extend_parent_select', array('checked' => 1)); ?>
</div>

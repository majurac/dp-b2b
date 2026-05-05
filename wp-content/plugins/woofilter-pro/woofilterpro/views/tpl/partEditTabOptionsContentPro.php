<?php
	$settingValue = ( isset($this->settings['settings']['display_view_more']) ? (int) $this->settings['settings']['display_view_more'] : '' );
	$hiddenStyle = $settingValue ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Display "Show more"', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('For long vertical lists, "Show more" will be displayed.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::checkboxToggle('settings[display_view_more]', array(
					'checked' => $settingValue,
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_view_more]">
			<div class="settings-value-label">
				<?php esc_html_e('Full opening', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::checkboxToggle('settings[view_more_full]', array(
					'checked' => ( isset($this->settings['settings']['view_more_full']) ? (int) $this->settings['settings']['view_more_full'] : '' ),
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_view_more]">
			<div class="settings-value-label">
				<?php esc_html_e('Label More', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::text('settings[view_more_label]', array(
					'value' => ( !empty($this->settings['settings']['view_more_label']) ? $this->settings['settings']['view_more_label'] : '' ),
					'attrs' => 'class="woobewoo-flat-input" placeholder="' . __('Show More', 'woo-product-filter') . '"',
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_view_more]">
			<div class="settings-value-label">
				<?php esc_html_e('Label Fewer', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::text('settings[view_more_label2]', array(
					'value' => ( !empty($this->settings['settings']['view_more_label2']) ? $this->settings['settings']['view_more_label2'] : '' ),
					'attrs' => 'class="woobewoo-flat-input" placeholder="' . __('Show Fewer', 'woo-product-filter') . '"',
				));
				?>
		</div>
	</div>
</div>
<?php
	$settingValue = ( isset($this->settings['settings']['display_selected_params']) ? (int) $this->settings['settings']['display_selected_params'] : '' );
	$hiddenStyle = $settingValue ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Display selected parameters of filters', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr('<div class="woobewoo-tooltips-wrapper"><div class="woobewoo-tooltips-text">' . __('Selected parameters will be displayed in the top/bottom of the filter.', 'woo-product-filter') . '</div><img src="' . esc_url(FrameWpf::_()->getModule('woofilters')->getModPath() . 'img/display_selected_parameters_of_filters.png') . '" height="193"></div>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::checkboxToggle('settings[display_selected_params]', array(
					'checked' => $settingValue,
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_selected_params]">
			<?php
				HtmlWpf::selectbox('settings[selected_params_position]', array(
					'options' => array('top' => 'Top', 'bottom' => 'Bottom'),
					'value' => ( isset($this->settings['settings']['selected_params_position']) ? $this->settings['settings']['selected_params_position'] : 'top' ),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<?php
			$settingClear = ( isset($this->settings['settings']['selected_params_clear']) ? (int) $this->settings['settings']['selected_params_clear'] : '' );
			$hiddenStyleSub = $settingValue && $settingClear  ? '' : 'wpfHidden';
		?>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_selected_params]">
			<div class="settings-value-label">
				<?php esc_html_e('Display "Clear All"', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::checkboxToggle('settings[selected_params_clear]', array(
					'checked' => $settingClear,
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyleSub); ?>" data-parent="settings[selected_params_clear]">
			<?php 
				HtmlWpf::text('settings[selected_clean_word]', array(
					'value' => ( isset($this->settings['settings']['selected_clean_word']) ? $this->settings['settings']['selected_clean_word'] : esc_attr__('Clear All', 'woo-product-filter') ),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>"  data-parent="settings[display_selected_params]">
			<div class="settings-value-label">
				<?php esc_html_e('Display child categories', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Display both of child and parent categories.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php
				HtmlWpf::checkboxToggle('settings[expand_selected_to_child]', array(
					'checked' => ( isset($this->settings['settings']['expand_selected_to_child']) ? (int) $this->settings['settings']['expand_selected_to_child'] : 1 ),
				));
				?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[display_selected_params]">
			<?php
			HtmlWpf::text('', array(
				'value' => '[' . WPF_SHORTCODE_SELECTED_FILTERS . " id={$this->filterId}]",
				'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
			));
			?>
		</div>
	</div>
</div>
<?php
$settingValue = ( isset($this->settings['settings']['scroll_after_filtration']) ? (int) $this->settings['settings']['scroll_after_filtration'] : '' );
$hiddenStyle = $settingValue ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Autoscroll to products after filtering', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('After filtration will be scroll to products block', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="sub-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle('settings[scroll_after_filtration]', array(
				'checked' => $settingValue,
			));
			?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>"  data-parent="settings[scroll_after_filtration]">
			<div class="settings-value-label">
				<?php esc_html_e('Animation speed', 'woo-product-filter'); ?>
			</div>
			<?php
			HtmlWpf::input('settings[scroll_after_filtration_speed]', array(
				'type' => 'number',
				'value' => ( isset($this->settings['settings']['scroll_after_filtration_speed']) ? (int) $this->settings['settings']['scroll_after_filtration_speed'] : 1500 ),
				'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width80" min="0"',
			));
			?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>"  data-parent="settings[scroll_after_filtration]">
			<div class="settings-value-label">
				<?php esc_html_e('Retreat from products block (in px)', 'woo-product-filter'); ?>
			</div>
			<?php
			HtmlWpf::input('settings[scroll_after_filtration_retreat]', array(
				'type' => 'number',
				'value' => ( isset($this->settings['settings']['scroll_after_filtration_retreat']) ? (int) $this->settings['settings']['scroll_after_filtration_retreat'] : 30 ),
				'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width80" min="0"',
			));
			?>
		</div>
	</div>
</div>
<?php
$settingValue = ( isset( $this->settings['settings']['only_one_filter_open'] ) ? (int) $this->settings['settings']['only_one_filter_open'] : 0 );
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e( 'If one filter block is open, other blocks are closed', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip"
		   title="<?php echo esc_attr__( 'When you click on the block open icon, all other open blocks will be automatically closed', 'woo-product-filter' ); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle( 'settings[only_one_filter_open]', array(
				'checked' => $settingValue,
			) );
			?>
		</div>
	</div>
</div>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3">
		<?php esc_html_e('Show category slugs in URL instead of IDs', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Turn on only when necessary. Please note that "slug" should only contain lowercase Latin letters, numbers and hyphens.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/content-options/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-lg-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle('settings[use_category_slug]', array(
				'checked' => ( isset($this->settings['settings']['use_category_slug']) ? (int) $this->settings['settings']['use_category_slug'] : 0 ),
			));
			?>
		</div>
	</div>
</div>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3">
		<?php esc_html_e('Filter synchronization', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Enable this setting if there are two or more filters on the page and you want changes to one of them to affect the others, as well as to have products filtered by the parameters selected in all filters. At this stage, only filters of the same type can be synchronized. Please note that this option must be enabled in all filters on the page.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/content-options/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-lg-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle('settings[use_filter_synchro]', array(
				'checked' => ( isset($this->settings['settings']['use_filter_synchro']) ? (int) $this->settings['settings']['use_filter_synchro'] : 0 ),
			));
			?>
		</div>
	</div>
</div>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3">
		<?php esc_html_e('Сlear other filters', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Enable this setting if there are two or more filters on the page and you want clear other filters when filtering by the current one.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/content-options/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-lg-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle('settings[clear_other_filters]', array(
				'checked' => ( isset($this->settings['settings']['clear_other_filters']) ? (int) $this->settings['settings']['clear_other_filters'] : 0 ),
			));
			?>
		</div>
	</div>
</div>

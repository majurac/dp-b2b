<?php
	$settingValue      = $this->getFilterSetting($this->settings['settings'], 'redirect_after_select', '');
	$hiddenStyle       = $settingValue ? '' : 'wpfHidden';
?>
<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3 pr-0">
		<?php esc_html_e('Redirect after filter selection', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('You can select one of the available pages to redirect to it after selecting a filter', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-lg-9">
		<div class="settings-value settings-w100">
		<?php
			HtmlWpf::checkboxToggle('settings[redirect_after_select]', array(
				'checked' => ( isset($this->settings['settings']['redirect_after_select']) ? (int) $this->settings['settings']['redirect_after_select'] : '' ),
			));
			?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr($hiddenStyle); ?>" data-parent="settings[redirect_after_select]">
		<?php
		$redirectAfterSelect = $this->getFilterSetting($this->settings['settings'], 'redirect_page_url', '');
		HtmlWpf::selectbox('settings[redirect_page_url]', array(
			'options' => FrameWpf::_()->getModule('woofilters')->getAllPages(),
			'value' => $redirectAfterSelect,
			'attrs' => 'class="woobewoo-flat-input"',
		));
		?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr( $hiddenStyle ); ?>" data-parent="settings[redirect_after_select]" data-parent-switch="settings[redirect_after_select]">
			<div class="settings-value-label">
				<?php esc_html_e( 'Redirect only if click button', 'woo-product-filter' ); ?>
			</div>
			<?php
			HtmlWpf::checkboxToggle( 'settings[redirect_only_click]', array(
				'checked' => ( isset( $this->settings['settings']['redirect_only_click'] ) ? (int) $this->settings['settings']['redirect_only_click'] : '' ),
			) );
			?>
		</div>
	</div>
</div>

<?php
	$settingValue      = $this->getFilterSetting($this->settings['settings'], 'open_one_by_one', '');
	$hiddenStyle       = $settingValue ? '' : 'wpfHidden';
?>

<div class="row row-settings-block">
	<div class="settings-block-label col-xs-4 col-lg-3 pr-0">
		<?php esc_html_e( 'Open Filters One By One', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr( __( 'Only when a selection is made in the current filter show the next one', 'woo-product-filter' ) ); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-lg-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::checkboxToggle( 'settings[open_one_by_one]', array(
				'checked' => ( isset( $this->settings['settings']['open_one_by_one'] ) ? (int) $this->settings['settings']['open_one_by_one'] : '' ),
			) );
			?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr( $hiddenStyle ); ?>" data-parent="settings[open_one_by_one]" data-parent-switch="settings[open_one_by_one]">
			<div class="settings-value-label">
				<?php esc_html_e( 'Disable Instead Of Hiding Following', 'woo-product-filter' ); ?>
			</div>
			<?php
			HtmlWpf::checkboxToggle( 'settings[disable_following]', array(
				'checked' => ( isset( $this->settings['settings']['disable_following'] ) ? (int) $this->settings['settings']['disable_following'] : '' ),
			) );
			?>
		</div>
		<div class="settings-value settings-w100 <?php echo esc_attr( $hiddenStyle ); ?>" data-parent="settings[open_one_by_one]" data-parent-switch="settings[open_one_by_one]">
			<div class="settings-value-label">
				<?php esc_html_e( 'Сheck category hierarchy', 'woo-product-filter' ); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr( __( 'If enabled the following blocks contain only child categories of the previous ones.', 'woo-product-filter' ) ); ?>"></i>
			</div>
			<?php
			HtmlWpf::checkboxToggle( 'settings[obo_only_children]', array(
				'checked' => ( isset( $this->settings['settings']['obo_only_children'] ) ? (int) $this->settings['settings']['obo_only_children'] : '' ),
			) );
			?>
		</div>
	</div>
</div>

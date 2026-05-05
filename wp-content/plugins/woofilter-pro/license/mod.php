<?php
/**
 * WBW Product Filter PRO - LicenseWpf Class
 *
 * For now this is just dummy mode to identify that we have installed licensed version.
 *
 * @version 3.0.3
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class LicenseWpf extends ModuleWpf {

	/**
	 * Properties.
	 */
	// wc - from WC subscriptions, cc - codecanyon, ''/null - our (default)
	public $licenseType  = '';
	public $isWooLicense = false;
	public $pluginData   = array();

	/**
	 * init.
	 */
	public function init() {
		parent::init();
		$this->_setWCLicenceType();
		DispatcherWpf::addFilter('mainAdminTabs', array($this, 'addAdminTab'));
		add_action('admin_notices', array($this, 'checkActivation'));
		add_action('init', array($this, 'addAfterInit'));
	}

	/**
	 * addAfterInit.
	 */
	public function addAfterInit() {
		if (!function_exists('getProPlugDirWpf')) {
			return;
		}
		$this->_licenseCheck();
		$this->_updateDb();

		$this->_setLicenceType();
		if ($this->getModel()->isActive() && $this->licenseType != $this->getModel()->getLicenseType(false)) {
			$this->getModel()->_removeActive();
			$this->getModel()->setCredentials('', '', $this->licenseType);
		}
		add_action('in_plugin_update_message-' . getProPlugDirWpf() . '/' . getProPlugFileWpf(), array($this, 'checkDisabledMsgOnList'), 1, 2);
	}

	/**
	 * checkDisabledMsgOnList.
	 */
	public function checkDisabledMsgOnList( $plugin_data, $r ) {
		if ($this->getModel()->isExpired()) {
			$licenseTabUrl = FrameWpf::_()->getModule('options')->getTabUrl('license');
			/* translators: 1: license link 2: PRO link for license tab */
			echo '<br />' . sprintf(esc_html__('Your license is expired. Once you extend your license - you will be able to Update PRO version. To extend PRO version license - follow %1$s, then - go to %2$s tab and click on "Re-activate" button to re-activate your PRO version.', 'woo-product-filter'),
				'<a href="' . esc_url($this->getExtendUrl()) . '" target="_blank">' . esc_html__('this link', 'woo-product-filter') . '</a>',
				'<a href="' . esc_url($licenseTabUrl) . '">' . esc_html__('License', 'woo-product-filter') . '</a>');
		}
	}

	/**
	 * checkActivation.
	 */
	public function checkActivation() {
		if ( !$this->getModel()->isActive()) {
			$isDismissable = false;
			$msgClasses = 'error notice is-dismissible';
			if ($isDismissable) {
				$dismiss = (int) FrameWpf::_()->getModule('options')->get('dismiss_pro_opt');
				if ($dismiss) {
					return; // it was already dismissed by user - no need to show it again
				}
				// Those classes required to display close "X" button in message
				$msgClasses .= ' woobewoo-pro-notice wpf-notification';
				// And of course - connect our core scripts (to use core ajax handler), and script with saving "dismiss_pro_opt" option ajax send request
				FrameWpf::_()->getModule('templates')->loadCoreJs();
				FrameWpf::_()->addScript('wpf.admin.license.notices', $this->getModPath() . 'js/admin.license.notices.js');
			}
			$isHideExpiredNotification = FrameWpf::_()->getModule('options')->get('hide_expired_notification');

			$isExpired = $this->getModel()->isExpired();

			if ('wc' === $this->licenseType) {
				$wcLicenseData = $this->getWCLicenseData();
				if (false === $wcLicenseData) {
					if ($isExpired && ( '1' == $isHideExpiredNotification )) {
						return;
					}
					echo '<div class="' . esc_attr($msgClasses) . '"><p>';
					if ($isExpired) {
						/* translators: 1: plugin name, 2: subscriptions path */
						echo sprintf(esc_html__('Your plugin %1$s PRO license is expired and we are unable to verify your subscriptions. Please go to %2$s and make sure you are logged in your WooCommerce.com account.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME), '<b>' . esc_html('WooCommerce > Extensions > WooCommerce.com Subscriptions') . '</b>');
					} else {
						/* translators: 1: plugin name, 2: subscriptions path */
						echo sprintf(esc_html__('To activate your %1$s PRO plugin verification of your subscription is required. Go to %2$s and make sure you are logged in to your WooCommerce.com account.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME), '<b>' . esc_html('WooCommerce > Extensions > WooCommerce.com Subscriptions') . '</b>');
					}
					echo '</p></div>';
				} else {
					$isExpired = ( '1' !== $isHideExpiredNotification ) ? $this->getModel()->isExpiredWC($wcLicenseData) : 0;
					if ($isExpired) {
						echo '<div class="' . esc_attr($msgClasses) . '"><p>';
						/* translators: %s: plugin name */
						echo sprintf(esc_html__('Your plugin %s PRO license is expired. It means your PRO version will work as usual - with all features and options, but you will not be able to update the PRO version and use PRO support.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME));
						echo '</p></div>';
					} else if ($this->getModel()->activate($wcLicenseData)) { //if (true) {
						return;
					} else {
						echo '<div class="' . esc_attr($msgClasses) . '"><p>';
						foreach ($this->getModel()->getErrors() as $error) {
							echo esc_html(WPF_WP_PLUGIN_NAME) . ' PRO: ' . esc_html($error);
						}
						echo '</p></div>';
					}
				}
			} else {
				if ($isExpired && ( '1' == $isHideExpiredNotification )) {
					return;
				}
				echo '<div class="' . esc_attr($msgClasses) . '"><p>';

				if ( $isExpired) {
					/* translators: %s: plugin name */
					echo sprintf(esc_html__('Your plugin %s PRO license is expired. It means your PRO version will work as usual - with all features and options, but you will not be able to update the PRO version and use PRO support.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME));
					if (empty($this->licenseType)) {
						/* translators: %s: PRO version license url */
						echo ' ' . sprintf(esc_html__('To extend PRO version license - follow %s.', 'woo-product-filter'),
							'<a href="' . esc_url($this->getExtendUrl()) . '" target="_blank">' . esc_html__('this link', 'woo-product-filter') . '</a>');
					}
				} else {
					/* translators: 1: plugin name 2: PRO version license url */
					echo sprintf(esc_html__('You need to activate your copy of PRO version %1$s plugin. Go to %2$s tab and finish your software activation process.', 'woo-product-filter'),
						esc_html(WPF_WP_PLUGIN_NAME),
						'<a href="' . esc_url(FrameWpf::_()->getModule('options')->getTabUrl('license')) . '">' . esc_html__('License', 'woo-product-filter') . '</a>');
				}
				echo '</p></div>';
			}
		}
	}

	/**
	 * getExtendUrl.
	 */
	public function getExtendUrl() {
		return $this->getModel()->getExtendUrl();
	}

	/**
	 * addAdminTab.
	 */
	public function addAdminTab( $tabs ) {
		$show = true;
		if ( function_exists('is_multisite') && is_multisite() && get_option( 'wpmuclone_default_blog' ) ) {
			$availableSites = array( SITE_ID_CURRENT_SITE, get_option( 'wpmuclone_default_blog' ) );
			if ( !in_array( get_current_blog_id(), $availableSites ) ) {
				$show = false;
			}
		}
		if ( $show ) {
			$tabs[ $this->getCode() ] = array(
				'label' => esc_html__('License', 'woo-product-filter'),
				'callback' => array($this, 'getTabContent'),
				'fa_icon' => 'fa-hand-o-right',
				'sort_order' => 999,
			);
		}
		return $tabs;
	}

	/**
	 * getTabContent.
	 */
	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	/**
	 * _licenseCheck.
	 */
	private function _licenseCheck() {
		if ($this->getModel()->isActive()) {
			$this->getModel()->check();
			$this->getModel()->checkPreDeactivateNotify();
		}
	}

	/**
	 * _setLicenceType.
	 */
	private function _setLicenceType() {
		$pluginData = get_file_data(getProPlugFullPathWpf(), array('cc_id'=>'CC', 'woo_id'=>'Woo'));
		if (!empty($pluginData['woo_id'])) {
			$this->isWooLicense = true;
			if ($this->isWCHelperExists()) {
				foreach (WC_Helper::get_local_woo_plugins() as $plugin) {
					if ($plugin['Woo'] == $pluginData['woo_id']) {
						$this->licenseType = 'wc';
						$this->pluginData = $plugin;
						return;
					}
				}
			}
		}

		if (!empty($pluginData['cc_id'])) {
			$this->licenseType = 'cc';
			$this->pluginData['product_id'] = $pluginData['cc_id'];
			$this->pluginData['type'] = 'cc-1-12-' . $pluginData['cc_id'];
			$this->pluginData['email'] = 'cc_user@cc.com';
		}
	}

	/**
	 * getWCLicenseData.
	 */
	public function getWCLicenseData() {
		if ($this->isWCHelperExists() && 'wc' == $this->licenseType) {
			$productId =  isset($this->pluginData['_product_id']) ? $this->pluginData['_product_id'] : -1;
			foreach (WC_Helper::get_subscriptions() as $subscription) {
				if ($subscription['product_id'] == $productId) {
					$start = isset($subscription['expires']) ? $subscription['expires'] - 24 * 3600 * 365 : 0;
					$now = time();
					if ($start > $now || $start <= 0) {
						$start = $now;
					}
					$subscription['type'] = 'wc-1-12-' . $start;
					$subscription['email'] = 'wc_user@wc.com';
					$subscription['key'] = $subscription['product_key'];
					return $subscription;
				}
			}
		}
		return false;
	}

	/**
	 * isWCHelperExists.
	 */
	public function isWCHelperExists() {
		return class_exists('WC_Helper');
	}

	/**
	 * _updateDb.
	 */
	private function _updateDb() {
		$this->getModel()->updateDb();
	}

	/**
	 * licenseHttpRequestTimeout.
	 *
	 * @version 3.0.3
	 *
	 */
	public function licenseHttpRequestTimeout( $handle ) {
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $handle, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $handle, CURLOPT_SSLVERSION, 6 );
	}

	/**
	 * _setWCLicenceType.
	 */
	public function _setWCLicenceType() {
		if (function_exists('getProPlugFullPathWpf')) {
			$pluginData = get_file_data(getProPlugFullPathWpf(), array('cc_id'=>'CC', 'woo_id'=>'Woo'));
			if (!empty($pluginData['woo_id'])) {
				$this->isWooLicense = true;
			}
		}
	}

}

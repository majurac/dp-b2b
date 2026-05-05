<?php
/**
 * Plugin Name: Product Filter for WooCommerce by WBW - PRO
 * Description: Product Filter for WooCommerce by WBW - PRO. Best plugins from WBW!
 * Plugin URI: https://woobewoo.com/product/woocommerce-filter/
 * Author: WBW
 * Author URI: https://woobewoo.com/
 * Version: 3.1.7
 * WC requires at least: 3.4.0
 * WC tested up to: 10.7
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPF_FREE_REQUIRES.
 */
define( 'WPF_FREE_REQUIRES', '3.1.7' );

/**
 * We use it as fallback for a cases when we can't parse install.xml fail.
 */
define('WPF_PRO_MODULES',
	serialize(
		array(
			array(
				'code'    => 'license',
				'active'  => '1',
				'type_id' => '6',
				'label'   => 'license',
			),
			array(
				'code'    => 'access',
				'active'  => '1',
				'type_id' => '6',
				'label'   => 'access',
			),
			array(
				'code'    => 'woofilterpro',
				'active'  => '1',
				'type_id' => '6',
				'label'   => 'woofilterpro',
			),
			array(
				'code'    => 'statistics',
				'active'  => '1',
				'type_id' => '6',
				'label'   => 'Statistics',
			),
		)
	)
);

/**
 * WPF_SITE_URL.
 */
if (!defined('WPF_SITE_URL')) {
	define('WPF_SITE_URL', get_bloginfo('wpurl') . '/');
}

/**
 * wpUpdater and dbUpdater.
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wpUpdater.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'dbUpdater.php';

/**
 * Activation, deactivation, uninstall.
 */
register_activation_hook(__FILE__, 'woofilterProActivateCallback');
register_deactivation_hook(__FILE__, 'woofilterProDeactivateCallback');
register_uninstall_hook(__FILE__, 'woofilterProUninstallCallback');

/**
 * HPOS.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

/**
 * pre_set_site_transient_update_plugins and plugins_api.
 */
add_filter('pre_set_site_transient_update_plugins', 'checkForPluginUpdatewoofilterPro');
add_filter('plugins_api', 'myPluginApiCallwoofilterPro', 10, 3);

/**
 * changeViewDetailsWpf.
 */
add_filter('plugin_row_meta', 'changeViewDetailsWpf', 10, 4);
if (!function_exists('changeViewDetailsWpf')) {
	function changeViewDetailsWpf( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if (isset($plugin_data['slug']) && getProPlugCodeWpf() === $plugin_data['slug']) {
			foreach ($plugin_meta as $key => $meta) {
				if (strpos($meta, 'plugin-install.php?tab=plugin-information') !== false && isset($plugin_data['PluginURI'])) {
					$plugin_meta[$key] = '<a href="' . esc_url($plugin_data['PluginURI']) . '" target="_blank">' . __( 'Visit plugin site' ) . '</a>';
				}
			}
		}
		return $plugin_meta;
	}
}

/**
 * getProPlugCodeWpf.
 */
if (!function_exists('getProPlugCodeWpf')) {
	function getProPlugCodeWpf() {
		return 'woofilter_pro';
	}
}

/**
 * getProPlugDirWpf.
 */
if (!function_exists('getProPlugDirWpf')) {
	function getProPlugDirWpf() {
		return basename(dirname(__FILE__));
	}
}

/**
 * getProPlugFileWpf.
 */
if (!function_exists('getProPlugFileWpf')) {
	function getProPlugFileWpf() {
		return basename(__FILE__);
	}
}

/**
 * getProPlugFullPathWpf.
 */
if (!function_exists('getProPlugFullPathWpf')) {
	function getProPlugFullPathWpf() {
		return __FILE__;
	}
}

/**
 * S_YOUR_SECRET_HASH_.
 */
if (!defined('S_YOUR_SECRET_HASH_' . getProPlugCodeWpf())) {
	define('S_YOUR_SECRET_HASH_' . getProPlugCodeWpf(), 'ng93#g3j9g#R#E)@KDPWKOK)Fkvvk#f30f#KF');
}

/**
 * checkForPluginUpdatewoofilterPro.
 */
if (!function_exists('checkForPluginUpdatewoofilterPro')) {
	function checkForPluginUpdatewoofilterPro( $checkedData ) {
		if (class_exists('WpUpdaterWpf')) {
			return WpUpdaterWpf::getInstance(
				getProPlugDirWpf(),
				getProPlugFileWpf(),
				getProPlugCodeWpf(),
				getProPlugFullPathWpf()
			)->checkForPluginUpdate($checkedData);
		}
		return $checkedData;
	}
}

/**
 * myPluginApiCallwoofilterPro.
 */
if (!function_exists('myPluginApiCallwoofilterPro')) {
	function myPluginApiCallwoofilterPro( $def, $action, $args ) {
		if (class_exists('WpUpdaterWpf')) {
			return WpUpdaterWpf::getInstance(
				getProPlugDirWpf(),
				getProPlugFileWpf(),
				getProPlugCodeWpf(),
				getProPlugFullPathWpf()
			)->myPluginApiCall($def, $action, $args);
		}
		return $def;
	}
}

/**
 * Check if there are base (free) version installed.
 */
if (!function_exists('woofilterProActivateCallback')) {
	function woofilterProActivateCallback() {
		if (class_exists('DbUpdaterWpf')) {
			DbUpdaterWpf::runUpdate();
		}
		if (class_exists('FrameWpf')) {
			$arguments = array('extPlugName' => getProPlugDirWpf() . DS . getProPlugFileWpf());
			call_user_func_array(array('ModInstallerWpf', 'check'), $arguments);
		}
	}
}

/**
 * woofilterProDeactivateCallback.
 */
if (!function_exists('woofilterProDeactivateCallback')) {
	function woofilterProDeactivateCallback() {
		if (class_exists('FrameWpf')) {
			call_user_func_array(array('ModInstallerWpf', 'deactivate'), array());
		}
	}
}

/**
 * woofilterProUninstallCallback.
 */
if (!function_exists('woofilterProUninstallCallback')) {
	function woofilterProUninstallCallback() {
		if (class_exists('FrameWpf')) {
			call_user_func_array(array('ModInstallerWpf', 'uninstall'), array());
		}
	}
}

/**
 * woofilterProInstallBaseMsg.
 */
add_action('admin_notices', 'woofilterProInstallBaseMsg');
if (!function_exists('woofilterProInstallBaseMsg')) {
	function woofilterProInstallBaseMsg() {
		if ( !get_option('wpf_full_installed') || !class_exists('FrameWpf') ) {
			$plugName = __('Product Filter by WBW', 'woo-product-filter');
			$plugWpUrl = 'https://wordpress.org/plugins/woo-product-filter/';
			echo '<div class="notice error is-dismissible"><p><strong>';
			/* translators: 1: plugin name 2: plugin url 3: plugin name */
			echo sprintf(esc_html__('Please install Free (Base) version of %1$s plugin, you can get it %2$s or use Wordpress plugins search functionality, activate it, then deactivate and activate again PRO version of %3$s. ', 'woo-product-filter'),
				esc_html($plugName), '<a target="_blank" href="' . esc_url($plugWpUrl) . '">here</a>', esc_html($plugName));
			/* translators: %s: plugin name */
			echo sprintf(esc_html__('In this way you will have full and upgraded PRO version of %s.', 'woo-product-filter'), esc_html($plugName)) .
				'</strong></p></div>';
		} else if (version_compare(WPF_VERSION, WPF_FREE_REQUIRES, '<')) {
			$plugName = __('Product Filter by WBW', 'woo-product-filter');
			$plugWpUrl = 'https://wordpress.org/plugins/woo-product-filter/';
			echo '<div class="notice error is-dismissible"><p><strong>';
			/* translators: 1: plugin name 2: plugin version 3: plugin url 4: plugin name */
			echo sprintf(esc_html__('Please install latest Free (Base) version of %1$s plugin (requires at least %2$s), you can get it %3$s or use Wordpress plugins search functionality, activate it, then deactivate and activate again PRO version of %4$s. ', 'woo-product-filter'),
				esc_html($plugName), esc_html(WPF_FREE_REQUIRES), '<a target="_blank" href="' . esc_url($plugWpUrl) . '">here</a>', esc_html($plugName));
			/* translators: %s: plugin name */
			echo sprintf(esc_html__('In this way you will have full and upgraded PRO version of %s.', 'woo-product-filter'), esc_html($plugName)) .
				'</strong></p></div>';
		}
	}
}

/**
 * Add "Check for updates" row meta in the Plugins list table.
 *
 * @version 2.9.5
 * @since   2.9.5
 */
add_filter(
	'plugin_row_meta',
	function ( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( false !== strpos( $plugin_file, basename( __FILE__ ) ) ) {
			$plugin_meta[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				add_query_arg( 'wbw_woofilter_pro_check_for_updates', true ),
				__( 'Check for updates', 'woo-product-filter' )
			);
		}
		return $plugin_meta;
	},
	10,
	4
);

/**
 * Force check for updates.
 *
 * @version 3.0.6
 * @since   2.9.5
 *
 * @todo    (v2.9.5) add nonce?
 * @todo    (v2.9.5) add admin notice?
 */
add_action(
	'init',
	function () {
		if ( ! isset( $_GET['wbw_woofilter_pro_check_for_updates'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Wrong user.', 'woo-product-filter' ) );
		}

		delete_option( '_wbw_woofilter_pro_request_time_basic_check' );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		wp_safe_redirect( remove_query_arg( 'wbw_woofilter_pro_check_for_updates' ) );
		exit;
	}
);

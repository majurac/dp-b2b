<?php
/**
 * WBW Product Filter PRO - WpUpdaterWpf Class
 *
 * @version 2.9.5
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

if (!class_exists('WpUpdaterWpf')) {

	class WpUpdaterWpf {

		/**
		 * Protected.
		 */
		protected $_plugDir       = '';
		protected $_plugFile      = '';
		protected $_plugSlug      = '';
		protected $_plugFull      = '';
		protected $_userAgentHash = '';
		protected $_apiUrl        = '';

		/**
		 * Constructor.
		 */
		public function __construct( $pluginDir, $pluginFile = '', $pluginSlug = '', $pluginFull = '' ) {
			$this->_plugDir  = $pluginDir;
			$this->_plugFile = $pluginFile;
			$this->_plugSlug = $pluginSlug;
			$this->_plugFull = $pluginFull;
		}

		/**
		 * getInstance.
		 */
		public static function getInstance( $pluginDir, $pluginFile = '', $pluginSlug = '', $pluginFull = '' ) {
			static $instances = array();
			// Instance key
			$instKey = $pluginDir . '/' . $pluginFile;
			if (!isset($instances[ $instKey ])) {
				$instances[ $instKey ] = new WpUpdaterWpf($pluginDir, $pluginFile, $pluginSlug, $pluginFull);
			}
			return $instances[ $instKey ];
		}

		/**
		 * checkForPluginUpdate.
		 *
		 * @version 2.9.4
		 */
		public function checkForPluginUpdate( $checkedData ) {
			if (empty($checkedData->checked)) {
				return $checkedData;
			}
			// For old versions of our addons
			if (empty($this->_plugSlug)) {
				return $checkedData;
			}

			// Check last request time
			if ( ! $this->isRequestTime( 'basic_check' ) ) {
				return $checkedData;
			}

			$version = (
				isset($checkedData->checked[$this->_plugDir . '/' . $this->_plugFile]) ?
				$checkedData->checked[$this->_plugDir . '/' . $this->_plugFile] :
				false
			);
			if (empty($version)) {
				$version = $this->checkPluginVersion();
			}
			$request_args = array(
				'slug' => $this->_plugSlug,
				'hash' => constant('S_YOUR_SECRET_HASH_' . $this->_plugSlug),
				'version' => $version,
			);
			if (
				class_exists('FrameWpf') &&
				FrameWpf::_()->getModule('license') &&
				!FrameWpf::_()->getModule('license')->getModel()->isExpired()
			) {
				$license = FrameWpf::_()->getModule('license')->getModel()->getCredentials();
				if (!empty($license['type'])) {
					return $checkedData;
				}
				$license['key'] = md5($license['key']);
				$request_args['license'] = urlencode(base64_encode(implode('|', $license)));
			}
			$request_string = $this->prepareRequest('basic_check', $request_args);
			// Start checking for an update
			$raw_response = wp_remote_post($this->_getApiUrl(), $request_string);

			if ( !is_wp_error($raw_response) && ( 200 == $raw_response['response']['code'] ) ) {
				$response = unserialize($raw_response['body']);
			}

			if ( isset($response) && is_object($response) && !empty($response) ) {
				// Feed the update data into WP updater
				$checkedData->response[$this->_plugDir . '/' . $this->_plugFile] = $response;
			}

			return $checkedData;
		}

		/**
		 * myPluginApiCall.
		 *
		 * @version 2.9.4
		 */
		public function myPluginApiCall( $def, $action, $args ) {
			if ( !isset($args->slug) || $args->slug != $this->_plugSlug ) {
				return $def;
			}
			// For old versions of our addons
			if (empty($this->_plugSlug)) {
				return $def;
			}

			// Check last request time
			if ( ! $this->isRequestTime( $action ) ) {
				return $def;
			}

			// Get the current version
			$plugin_info = get_site_transient('update_plugins');
			$current_version = (
				isset($plugin_info->checked) ?
				$plugin_info->checked[$this->_plugDir . '/' . $this->_plugFile] :
				false
			);

			if (empty($current_version)) {
				$current_version = $this->checkPluginVersion();
			}
			$args->version = $current_version;

			$request_string = $this->prepareRequest($action, $args);

			$request = wp_remote_post($this->_getApiUrl(), $request_string);
			$error = false;
			$res = $this->controlCallError($request, $error);

			return $res;
		}

		/**
		 * controlCallError.
		 */
		private function controlCallError( $request, &$error ) {
			$error = false;
			if (is_wp_error($request)) {
				$error = true;
				$res = new WP_Error(
					'plugins_api_failed',
					esc_html__('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'),
					$request->get_error_message()
				);
			} else {
				$res = unserialize($request['body']);
				if (false === $res) {
					$error = true;
					$res = new WP_Error(
						'plugins_api_failed',
						esc_html__('An unknown error occurred'),
						$request['body']
					);
				}
			}
			return $res;
		}

		/**
		 * prepareRequest.
		 */
		public function prepareRequest( $action, $args ) {
			global $wp_version;

			return array(
				'body' => array(
					'action' => $action,
					'request' => serialize($args),
					'api-key' => md5(get_bloginfo('url')),
				),
				'user-agent' => $this->_getUserAgentHash() . '/' . $wp_version . '; ' . WPF_SITE_URL . ';' . $this->getIP(),
			);
		}

		/**
		 * checkPluginVersion.
		 */
		public function checkPluginVersion() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_file_data( $this->_plugFull, array('Version' => 'Version') );
			return $plugin_data['Version'];
		}

		/**
		 * getIP.
		 */
		public function getIP() {
			return (
				empty($_SERVER['HTTP_CLIENT_IP']) ?
				(
					empty($_SERVER['HTTP_X_FORWARDED_FOR']) ?
					(
						empty($_SERVER['REMOTE_ADDR']) ?
						'' :
						sanitize_text_field($_SERVER['REMOTE_ADDR'])
					) :
					sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'])
				) :
				sanitize_text_field($_SERVER['HTTP_CLIENT_IP'])
			);
		}

		/**
		 * _getApiUrl.
		 *
		 * @version 2.9.5
		 */
		private function _getApiUrl() {
			if (empty($this->_apiUrl)) {
				$this->_apiUrl = 'https://updates.woobewoo.com/?pl=com&mod=updater&action=requestAction';
			}
			return $this->_apiUrl;
		}

		/**
		 * _getUserAgentHash.
		 */
		private function _getUserAgentHash() {
			if (empty($this->_userAgentHash)) {
				$this->_userAgentHash = 'f323f89F#Ur32424u39842354254(*%5%#($#$OEf9ir3r3d893#$';
			}
			return $this->_userAgentHash;
		}

		/**
		 * isRequestTime.
		 *
		 * @version 2.9.5
		 * @since   2.8.9
		 *
		 * @todo    (v2.9.5) remove deprecated option cleanup
		 */
		function isRequestTime( $action ) {

			$option = '_wbw_woofilter_pro_request_time_' . sanitize_key( $action );
			$time   = time();

			if ( ( $time - (int) get_option( $option, 0 ) ) >= HOUR_IN_SECONDS ) {
				update_option( $option, $time, false );
				delete_option( '_woofilter_pro_request_time_' . sanitize_key( $action ) ); // clean up deprecated option
				return true;
			} else {
				return false;
			}

		}

	}

}

<?php
/**
 * WBW Product Filter PRO - DbUpdaterWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

if (!class_exists('DbUpdaterWpf')) {

	class DbUpdaterWpf {

		/**
		 * versionOption.
		 */
		private static $versionOption = 'wpf_pro_db_version';

		/**
		 * update.
		 */
		public static function update( $path ) {
			global $wpdb;
			$wpPrefix = $wpdb->prefix;
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_file_data( $path, array('Version' => 'Version') );
			$currentVersion = $plugin_data['Version'];
			$saveVersion = get_option($wpPrefix . self::$versionOption, 0);
			if (!$saveVersion || version_compare($currentVersion, $saveVersion, '>')) {
				self::runUpdate();
				update_option($wpPrefix . self::$versionOption, $currentVersion);
			}
		}

		/**
		 * runUpdate.
		 */
		public static function runUpdate() {
			if (!class_exists('DbWpf')) {
				return;
			}
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			if (!DbWpf::existsTableColumn( '@__filters', 'is_stats' ) ) {
				DbWpf::query('ALTER TABLE `@__filters` ADD COLUMN `is_stats` smallint(3) NOT NULL DEFAULT 0 AFTER `setting_data`');
			}
			if (!DbWpf::exist('@__statistics')) {
				dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__statistics` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `filter_id` INT(11) NOT NULL,
				  `page_id` INT(11) NOT NULL,
				  `user_id` INT(11) NOT NULL,
				  `filter_date` DATE NOT NULL,
				  `is_found` smallint(3) NOT NULL,
				  PRIMARY KEY (`id`),
				  INDEX `filter_id` (`filter_id`, `filter_date`)
				) DEFAULT CHARSET=utf8;'));
			}
			if (!DbWpf::exist('@__statistics_det')) {
				dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__statistics_det` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `st_id` INT(11) NOT NULL,
				  `block_id` varchar(9) NOT NULL,
				  `val1` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin,
				  `val2` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin,
				  PRIMARY KEY (`id`),
				  INDEX `st_id` (`st_id`)
				) DEFAULT CHARSET=utf8;'));
			}
			if (!DbWpf::exist('@__statistics_val')) {
				dbDelta(DbWpf::prepareQuery('CREATE TABLE IF NOT EXISTS `@__statistics_val` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `value` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin,
				  PRIMARY KEY (`id`),
				  UNIQUE INDEX `value` (`value`)
				) DEFAULT CHARSET=utf8;'));
			}
			if (!DbWpf::exist('@__statistics_sum')) {
				dbDelta(DbWpf::prepareQuery("CREATE TABLE IF NOT EXISTS `@__statistics_sum` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `filter_date` DATE NOT NULL,
				  `filter_id` INT(11) NOT NULL DEFAULT '0',
				  `page_id` INT(11) NOT NULL DEFAULT '0',
				  `block_id` varchar(9) NOT NULL DEFAULT '',
				  `users` INT(11) NOT NULL DEFAULT '0',
				  `is_max` smallint(11) NOT NULL DEFAULT '0',
				  `val_id` INT(11) NOT NULL DEFAULT '0',
				  `cnt_ok` INT(11) NOT NULL DEFAULT '0',
				  `cnt_no` INT(11) NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`),
				  INDEX `filter_id` (`filter_id`, `filter_date`)
				) DEFAULT CHARSET=utf8;"));
			}
		}

	}

}

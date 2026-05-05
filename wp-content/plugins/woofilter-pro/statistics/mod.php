<?php
/**
 * WBW Product Filter PRO - StatisticsWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class StatisticsWpf extends ModuleWpf {

	/**
	 * init.
	 */
	public function init() {
		parent::init();
		DispatcherWpf::addFilter('mainAdminTabs', array($this, 'addAdminTab'));
		DispatcherWpf::addFilter('prepareFilterListTitle', array($this, 'prepareFilterListTitle'), 10, 2);
		DispatcherWpf::addAction( 'saveStatistics', array( $this, 'saveStatistics' ), 10, 2 );
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
				'label' => esc_html__('Statistics', 'woo-product-filter'),
				'callback' => array($this, 'getTabContent'),
				'fa_icon' => 'fa-line-chart',
				'sort_order' => 990,
			);
		}
		return $tabs;
	}
	public function getTabContent() {
		return $this->getView()->getTabContent();
	}
	public function prepareFilterListTitle( $title, $filter ) {
		if (isset($filter['is_stats'])) {
			$title .= ' <a href="#" data-id="' . $filter['id'] . '" class="wpf-statistics wpf-action-' . ( empty($filter['is_stats']) ? 'off' : 'on' ) . '" title="' . ( empty($filter['is_stats']) ? esc_attr__('Enable statistics collection', 'woo-product-filter') : esc_attr__('Disable statistics collection', 'woo-product-filter') ) . '"><i class="fa fa-line-chart"></i> </a>';
		}
		return $title;
	}

	public function getReportsTypes( $key = false ) {
		$types = array(
			'requests' => esc_html__('Count of filter requests', 'woo-product-filter'),
			'users' => esc_html__('Count of unique users who used filtering', 'woo-product-filter'),
			'blocks' => esc_html__('What blocks were used for filtering', 'woo-product-filter'),
			'values' => esc_html__('What values ​​were chosen for a particular block', 'woo-product-filter'),
			'no_result' => esc_html__('No result', 'woo-product-filter'),
		);
		return $key ? ( isset($types[$key]) ? $types[$key] : '???' ) : $types;
	}
	public function getReportsPeriods() {
		return array(
			'week' => __('Last 7 days', 'woo-product-filter'),
			'month' => __('Last Month', 'woo-product-filter'),
			'cur_month' => __('This Month', 'woo-product-filter'),
			'year' => __('Last Year', 'woo-product-filter'),
			'cur_year' => __('This Year', 'woo-product-filter'),
			'custom' => __('Custom', 'woo-product-filters'),
		);
	}
	public function saveStatistics( $isFound ) {
		$statistics = ReqWpf::getVar('statistics');
		$statistics = UtilsWpf::jsonDecode(stripslashes($statistics));
		if (!empty($statistics)) {
			$statistics['found'] = $isFound;
			$this->getModel()->saveStatistics($statistics);
		}
	}
}

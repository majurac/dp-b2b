<?php
/**
 * WBW Product Filter PRO - StatisticsViewWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class StatisticsViewWpf extends ViewWpf {
	public function getTabContent() {
		$modPath = $this->getModule()->getModPath();
		FrameWpf::_()->getModule('templates')->loadJqGrid();
		FrameWpf::_()->addScript('wpf.admin.statistics', $modPath . 'js/admin.statistics.js');
		FrameWpf::_()->addStyle('wpf.admin.statistics', $modPath . 'css/admin.statistics.css');
		FrameWpf::_()->addScript('wpf.plotly', $modPath . 'js/plotly.min.js');
		FrameWpf::_()->getModule('templates')->loadBootstrap();
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		FrameWpf::_()->getModule('templates')->loadDatePicker();

		$this->assign('reports', $this->getModule()->getReportsTypes());
		$this->assign('filters', $this->getModel()->getFiltersBlocksList());
		$this->assign('pages', array('') + FrameWpf::_()->getModule('woofilters')->getAllPages());
		$this->assign('periods', $this->getModule()->getReportsPeriods());
		$this->assign('typesBlocks', array('pie' => __('Pie', 'woo-product-filter'), 'bar' => __('Bar', 'woo-product-filter'), 'table' => __('Table', 'woo-product-filter')));
		$this->assign('typesValues', array('pie' => __('Pie', 'woo-product-filter'), 'bar' => __('Bar', 'woo-product-filter'), 'bubble' => __('Bubble', 'woo-product-filter'), 'table' => __('Table', 'woo-product-filter')));

		$this->assign('tops', array(10 => 10, 20 => 20, 50 => 50, 1000 => __('All', 'woo-product-filter')));
		$this->assign('from', $this->getModel()->addPeriod(-7));
		$this->assign('to', gmdate('Y-m-d'));

		$langs = array(
			'col-values' => esc_html__('Value', 'woo-product-filter'),
			'col-users' => esc_html__('Unique users', 'woo-product-filter'),
			'col-count' => esc_html__('Count requests', 'woo-product-filter'),
			'col-not' => esc_html__('Not found', 'woo-product-filter'),
			'empty-table' => esc_html__('You have no data for now.', 'woo-product-filter'),
		);
		$this->assign('langs', $langs);

		return parent::getContent('statisticsAdmin');
	}
}

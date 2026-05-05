<?php
/**
 * WBW Product Filter PRO - StatisticsControllerWpf Class
 *
 * @version 3.1.7
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class StatisticsControllerWpf extends ControllerWpf {

	/**
	 * enableStats.
	 */
	public function enableStats() {
		$res = new ResponseWpf();
		$id  = ReqWpf::getVar('id');
		if ($this->getModel()->enableStatistics($id, 1)) {
			$res->addMessage(esc_html__('Done', 'woo-product-tables'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec(true);
	}

	/**
	 * disableStats.
	 */
	public function disableStats() {
		$res = new ResponseWpf();
		$id  = ReqWpf::getVar('id');
		if ($this->getModel()->enableStatistics($id, 0)) {
			$res->addMessage(esc_html__('Done', 'woo-product-tables'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec(true);
	}

	/**
	 * saveStatistics.
	 */
	public function saveStatistics() {
		$res        = new ResponseWpf();
		$statistics = ReqWpf::getVar('statistics');
		$statistics = UtilsWpf::jsonDecode(stripslashes($statistics));

		if ($this->getModel()->saveStatistics($statistics)) {
			$res->addMessage(esc_html__('Done', 'woo-product-tables'));
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		$res->ajaxExec(true);
	}

	/**
	 * getDiagramData.
	 */
	public function getDiagramData() {
		$res = new ResponseWpf();
		$res->ignoreShellData();

		$params = ReqWpf::get('post');
		$result = $this->getModel()->getDiagramData($params);

		if ($result) {
			$res->values = $result;
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		return $res->ajaxExec();
	}

	/**
	 * getTableData.
	 */
	public function getTableData() {
		$res = new ResponseWpf();
		$res->ignoreShellData();

		$params = array();
		parse_str(ReqWpf::getVar('params'), $params);
		if (!is_array($params)) {
			$params = array();
		}
		$params['order'] = ReqWpf::getVar('sidx');
		$params['dir']   = ReqWpf::getVar('sord');
		$result = $this->getModel()->getTableData($params);

		if ($result) {
			$res->addData('page', 1);
			$res->addData('total', $result['total']);
			$res->addData('rows', $result['rows']);
			$res->addData('records', $result['total']);
		} else {
			$res->pushError($this->getModel()->getErrors());
		}
		return $res->ajaxExec();
	}

	/**
	 * getPermissions.
	 *
	 * @version 3.1.7
	 * @since   3.1.3
	 *
	 * @return array
	 */
	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'saveStatistics' => array(
					WPF_ADMIN,
					WPF_LOGGED,
					WPF_GUEST
				),
			),
			WPF_USERLEVELS => array(
				WPF_ADMIN => array(
					'enableStats', 'disableStats', 'getTableData', 'getDiagramData'
				),
			),
		);
	}
}

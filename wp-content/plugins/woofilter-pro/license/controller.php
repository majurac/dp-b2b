<?php
/**
 * WBW Product Filter PRO - LicenseControllerWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class LicenseControllerWpf extends ControllerWpf {

	/**
	 * activate.
	 */
	public function activate() {
		$res = new ResponseWpf();
		if ($this->getModel()->activate(ReqWpf::get('post'))) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError ($this->getModel()->getErrors());
		}
		$res->ajaxExec();
	}

	/**
	 * dismissNotice.
	 */
	public function dismissNotice() {
		$res = new ResponseWpf();
		FrameWpf::_()->getModule('options')->getModel()->save('dismiss_pro_opt', 1);
		$res->ajaxExec();
	}

	/**
	 * getPermissions.
	 */
	public function getPermissions() {
		return array(
			WPF_USERLEVELS => array(
				WPF_ADMIN => array('activate', 'dismissNotice'),
			),
		);
	}

}

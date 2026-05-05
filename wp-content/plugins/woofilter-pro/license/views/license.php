<?php
/**
 * WBW Product Filter PRO - LicenseViewWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class LicenseViewWpf extends ViewWpf {
	public function getTabContent() {
		FrameWpf::_()->addScript('wpf.admin.license', $this->getModule()->getModPath() . 'js/admin.license.js');
		FrameWpf::_()->getModule('templates')->loadJqueryUi();
		$credentials = $this->getModel()->getCredentials();
		if (empty($credentials['key'])) {
			$credentials = array_merge($credentials, $this->getModule()->pluginData);
		}
		$this->assign('credentials', $credentials);
		$this->assign('licenseType', $this->getModule()->licenseType);
		$this->assign('isActive', $this->getModel()->isActive());
		$this->assign('isExpired', $this->getModel()->isExpired());
		$this->assign('extendUrl', $this->getModel()->getExtendUrl());
		return parent::getContent('licenseAdmin');
	}
}

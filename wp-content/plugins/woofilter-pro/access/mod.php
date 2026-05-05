<?php
/**
 * WBW Product Filter PRO - AccessWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class AccessWpf extends ModuleWpf {

	/**
	 * _accessRoles.
	 */
	private $_accessRoles = array();

	/**
	 * init.
	 */
	public function init() {
		parent::init();
		DispatcherWpf::addFilter('adminMenuAccessCap', array($this, 'modifyAccessCap'));
		if (
			class_exists('DbUpdaterWpf') &&
			function_exists('trueRequestWpf') &&
			trueRequestWpf()
		) {
			DbUpdaterWpf::update(getProPlugFullPathWpf());
		}
	}

	/**
	 * modifyAccessCap.
	 */
	public function modifyAccessCap( $mainCap ) {
		if ($this->onlyForAdmin()) {
			return $mainCap;
		}
		$accessRoles = $this->getAccessRolesList();
		$inCaps = array();
		foreach ($accessRoles as $role) {
			$allRoleData = get_role( $role );
			if ( $allRoleData && $allRoleData->capabilities ) {
				$roleInCaps = array();
				foreach ($allRoleData->capabilities as $cKey => $cVal) {
					if ($cVal) {
						$roleInCaps[] = $cKey;
					}
				}
				if (empty($inCaps)) {
					$inCaps = $roleInCaps;
				} else {
					$inCaps = array_intersect ($inCaps, $roleInCaps);
				}
			}
		}
		if (!empty($inCaps)) {
			return array_shift($inCaps);
		}
		return false;
	}

	/**
	 * onlyForAdmin.
	 */
	public function onlyForAdmin() {
		$accessRoles = $this->getAccessRolesList();
		if (
			empty($accessRoles) ||
			(
				count($accessRoles) == 1 &&
				in_array('administrator', $accessRoles)
			)
		) {
			return true;
		}
		return false;
	}

	/**
	 * getAccessRolesList.
	 */
	public function getAccessRolesList() {
		if (empty($this->_accessRoles)) {
			$this->_accessRoles = FrameWpf::_()->getModule('options')->get('access_roles');
			if ( empty($this->_accessRoles) || !is_array($this->_accessRoles) ) {
				$this->_accessRoles = array();
			}
			if (!in_array('administrator', $this->_accessRoles)) {
				$this->_accessRoles[] = 'administrator';
			}
		}
		return $this->_accessRoles;
	}

}

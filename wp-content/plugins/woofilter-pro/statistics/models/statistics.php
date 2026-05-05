<?php
/**
 * WBW Product Filter PRO - StatisticsModelWpf Class
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class StatisticsModelWpf extends ModelWpf {
	public function getFiltersBlocksList( $filterId = 0 ) {
		$query = 'SELECT id, title, is_stats, setting_data FROM `@__filters`' .
			( empty($filterId) ? '' : ' WHERE id=' . ( (int) $filterId ) ) .
			' ORDER BY id';
		$filters = DbWpf::get($query);
		$result = array('filters' => array(), 'blocks' => array());

		foreach ($filters as $filter) {
			$id = $filter['id'];
			$result['filters'][$id] = $filter['title'];
			$settings = unserialize($filter['setting_data']);
			$blocks = array();
			if (!empty($settings['settings']['filters']['order'])) {
				$order = UtilsWpf::jsonDecode($settings['settings']['filters']['order']);
				foreach ($order as $block) {
					$blocks[str_replace('wpf_', '', $block['uniqId'])] = empty($block['settings']['f_title']) ? $block['id'] : $block['settings']['f_title'];
				}
			}
			$result['blocks'][$id] = $blocks;
		}
		return empty($filterId) ? $result : $result['blocks'][$id];
	}

	public function addPeriod( $days, $years = 0 ) {
		$t = mktime(0, 0, 0, gmdate('m'), gmdate('d') + ( (int) $days ), gmdate('Y') + ( (int) $years ));
		return gmdate('Y-m-d', $t);
	}


	public function enableStatistics( $filterId, $isStats ) {
		$query = 'UPDATE `@__filters` SET is_stats=' . ( empty($isStats) ? 0 : 1 ) . ' WHERE id=' . ( ( int ) $filterId );
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}
		return true;
	}
	public function isEnableStatistics( $filterId ) {
		$query = 'SELECT is_stats FROM `@__filters` WHERE id=' . ( ( int ) $filterId );
		$isStats = DbWpf::get($query, 'one');

		return !is_null($isStats) && !empty($isStats) ? 1 : 0;
	}

	public function saveStatistics( $data ) {
		if (!$this->calcSummaryStatistics() && FrameWpf::_()->getModule('options')->getModel()->get('logging') == 1) {
			$logger = wc_get_logger();
			if ($logger) {
				$logger->warning(UtilsWpf::jsonEncode($this->getErrors()), array('source' => 'wpf-statistics'));
			}
		}

		if (!is_array($data)) {
			return false;
		}
		$id = $this->getFilterSetting($data, 'id', 0, 1);
		if (empty($id)) {
			return false;
		}
		$blocks = $this->getFilterSetting($data, 'blocks', array());
		if (empty($blocks)) {
			return false;
		}
		$page = $this->getFilterSetting($data, 'page', 0, 1);
		if (empty($page)) {
			$page = -1;
		}
		$user = $this->getFilterSetting($data, 'user', 0, 1);
		if (empty($user)) {
			$user = -1;
		}
		$query = 'INSERT INTO `@__statistics` (`filter_id`,`page_id`,`user_id`,`filter_date`,`is_found`) VALUES (' .
			$id . ',' . $page . ',' . $user .
			', CURDATE(),' . ( $this->getFilterSetting($data, 'found', 0, 1) == 1 ? 1 : 0 ) . ')';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}
		$stId = DbWpf::lastID();
		if (is_null($stId) || empty($stId)) {
			return false;
		}

		$query = '';
		foreach ($blocks as $bid => $values) {
			if (is_array($values)) {
				foreach ($values as $vals) {
					if (is_array($vals)) {
						$val1 = isset($vals[0]) ? str_replace("'", '', trim($vals[0])) : '';
						$val2 = isset($vals[1]) ? str_replace("'", '', trim($vals[1])) : '';
					} else {
						$val1 = str_replace("'", '', trim($vals));
						$val2 = '';
					}
					if (strlen($val1) > 0 || strlen($val2) > 0) {
						$query .= '(' . $stId . ",'" . str_replace('wpf_', '', $bid) . "'," .
							( strlen($val1) > 0 ? "'" . $val1 . "'" : 'NULL' ) . ',' .
							( strlen($val2) > 0 ? "'" . $val2 . "'" : 'NULL' ) . '),';
					}
				}
			}
		}

		if (empty($query)) {
			if (!DbWpf::query('DELETE FROM `@__statistics` WHERE id=' . $stId)) {
				$this->pushError(DbWpf::getError());
				return false;
			}
			return true;
		}

		$query = 'INSERT INTO `@__statistics_det` (`st_id`,`block_id`,`val1`,`val2`) VALUES ' . substr($query, 0, -1);
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}
		return true;
	}
	public function calcSummaryStatistics() {
		$query = 'SELECT max(filter_date), SUBDATE(CURDATE(),1) FROM `@__statistics_sum` WHERE filter_id=0';
		$result = DbWpf::get($query, 'row', ARRAY_N);
		if (is_null($result) || empty($result) || is_null($result[0])) {
			$maxDate = false;
			$query = 'SELECT min(filter_date), SUBDATE(CURDATE(),1) FROM `@__statistics`';
			$result = DbWpf::get($query, 'row', ARRAY_N);
			if (is_null($result) || empty($result) || is_null($result[0])) {
				return true;
			}
			$minDate = $result[0];
			$yesterday = $result[1];
			if ($minDate > $yesterday) {
				return true;
			}
		} else {
			$maxDate = $result[0];
			$yesterday = $result[1];
			if ($maxDate >= $yesterday) {
				return true;
			}
		}
		$whereDate = ' s.filter_date>' . ( $maxDate ? "'" . $maxDate : "='" . $minDate ) . "' AND s.filter_date<='" . $yesterday . "'";

		$query = 'DELETE s FROM `@__statistics_sum` s WHERE ' . $whereDate;
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT IGNORE INTO `@__statistics_val` (`value`)' .
			' SELECT d.val1 ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' LEFT JOIN `@__statistics_val` v ON (v.value=d.val1)' .
			' WHERE v.id is NULL AND d.val1 is NOT NULL AND ' . $whereDate;
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT IGNORE INTO `@__statistics_val` (`value`)' .
			' SELECT d.val2 ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' LEFT JOIN `@__statistics_val` v ON (v.value=d.val2)' .
			' WHERE v.id is NULL AND d.val2 is NOT NULL AND ' . $whereDate;
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `users`, `cnt_ok`, `cnt_no`)' .
			' SELECT filter_date, filter_id, count(DISTINCT user_id), sum(is_found), sum(IF(is_found=0,1,0)) ' .
			' FROM `@__statistics` s ' .
			' WHERE ' . $whereDate .
			' GROUP BY filter_date, filter_id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `page_id`, `users`, `cnt_ok`, `cnt_no`)' .
			' SELECT filter_date, filter_id, page_id, count(DISTINCT user_id), sum(is_found), sum(IF(is_found=0,1,0)) ' .
			' FROM `@__statistics` s ' .
			' WHERE ' . $whereDate .
			' GROUP BY filter_date, filter_id, page_id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `users`, `block_id`, `cnt_ok`, `cnt_no`)' .
			' SELECT s.filter_date, s.filter_id, count(DISTINCT user_id), d.block_id, count(DISTINCT IF(is_found=1, d.st_id, NULL)), count(DISTINCT IF(is_found=0, d.st_id, NULL)) ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' WHERE d.val1 is NOT NULL AND ' . $whereDate .
			' GROUP BY filter_date, filter_id, d.block_id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `page_id`, `users`, `block_id`, `cnt_ok`, `cnt_no`)' .
			' SELECT s.filter_date, s.filter_id, s.page_id, count(DISTINCT user_id), d.block_id, count(DISTINCT IF(is_found=1, d.st_id, NULL)), count(DISTINCT IF(is_found=0, d.st_id, NULL)) ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' WHERE d.val1 is NOT NULL AND ' . $whereDate .
			' GROUP BY filter_date, filter_id, page_id, d.block_id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `page_id`, `users`, `block_id`, `is_max`, `val_id`, `cnt_ok`, `cnt_no`)' .
			' SELECT s.filter_date, s.filter_id, s.page_id, count(DISTINCT user_id), d.block_id, 0, v.id, count(DISTINCT IF(is_found=1, d.st_id, NULL)), count(DISTINCT IF(is_found=0, d.st_id, NULL)) ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' INNER JOIN `@__statistics_val` v ON (v.value=d.val1)' .
			' WHERE d.val1 is NOT NULL AND ' . $whereDate .
			' GROUP BY filter_date, filter_id, page_id, d.block_id, v.id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}
		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `filter_id`, `page_id`, `users`, `block_id`, `is_max`, `val_id`, `cnt_ok`, `cnt_no`)' .
			' SELECT s.filter_date, s.filter_id, s.page_id, count(DISTINCT user_id), d.block_id, 1, v.id, count(DISTINCT IF(is_found=1, d.st_id, NULL)), count(DISTINCT IF(is_found=0, d.st_id, NULL)) ' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' INNER JOIN `@__statistics_val` v ON (v.value=d.val2)' .
			' WHERE d.val2 is NOT NULL AND ' . $whereDate .
			' GROUP BY filter_date, filter_id, page_id, d.block_id, v.id';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'INSERT INTO `@__statistics_sum` (`filter_date`, `users`, `cnt_ok`, `cnt_no`)' .
			' SELECT filter_date, count(DISTINCT user_id), sum(is_found), sum(IF(is_found=0,1,0))' .
			' FROM `@__statistics` s ' .
			' WHERE ' . $whereDate .
			' GROUP BY filter_date';
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		/*$query = 'DELETE d' .
			' FROM `@__statistics` s ' .
			' INNER JOIN `@__statistics_det` d ON (d.st_id=s.id)' .
			' WHERE ' . $whereDate;
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}

		$query = 'DELETE s FROM `@__statistics` s WHERE ' . $whereDate;
		if (!DbWpf::query($query)) {
			$this->pushError(DbWpf::getError());
			return false;
		}*/

		return true;
	}

	public function getReportPeriod( $params ) {
		$period = $this->getFilterSetting($params, 'period', 'month');
		$from = '';
		$to = '';

		switch ($period) {
			case 'week':
				$from = $this->addPeriod(-7);
				break;
			case 'month':
				$from = $this->addPeriod(-30);
				break;
			case 'cur_month':
				$from = gmdate('Y-m') . '-01';
				break;
			case 'year':
				$from = $this->addPeriod(-365);
				break;
			case 'cur_year':
				$from = gmdate('Y') . '-01-01';
				break;
			case 'custom':
				$from = $this->getFilterSetting($params, 'from');
				$to = $this->getFilterSetting($params, 'to');

				break;
			default:
				break;
		}
		if (empty($from) || empty($to)) {
			$query = 'SELECT min(filter_date), SUBDATE(CURDATE(),1) FROM `@__statistics_sum` WHERE filter_id=0';
			$result = DbWpf::get($query, 'row', ARRAY_N);
			if (!is_null($result) && !empty($result)) {
				$yesterday = $result[1];
				$minDate = $result[0];
			}
			if (empty($from)) {
				$from = is_null($minDate) || empty($minDate) ? $yesterday : $minDate;
			}
			if (empty($to)) {
				$to = $yesterday;
			}
		}
		return array($from, $to);
	}

	public function getDiagramData( $params = array() ) {
		if (!is_array($params)) {
			$this->pushError('There are no parameters of report');
			return false;
		}
		if (!$this->calcSummaryStatistics() && FrameWpf::_()->getModule('options')->getModel()->get('logging') == 1) {
			$logger = wc_get_logger();
			if ($logger) {
				$logger->warning(UtilsWpf::jsonEncode($this->getErrors()), array('source' => 'wpf-statistics'));
			}
		}

		$report = $this->getFilterSetting($params, 'report');
		$filter = $this->getFilterSetting($params, 'filter', 0, 1);
		$page = empty($filter) ? 0 : $this->getFilterSetting($params, 'page', 0, 1);
		$block = empty($filter) ? 0 : $this->getFilterSetting($params, 'block');
		if (empty($block) || is_null($block) || ( 'null' == $block )) {
			$block = '';
		}
		$type = $this->getFilterSetting($params, 'type', 'lines+markers');
		$top = $this->getFilterSetting($params, 'top');
		list($from, $to) = $this->getReportPeriod($params);

		$data = array();
		$isPie = ( 'pie' == $type );
		$isBar = ( 'bar' == $type );

		$title = $this->getModule()->getReportsTypes($report);
		$layout = array(
			'title' => array(
				'text' => $title,
				'xanchor' => 'left',
				'x' => 0,
				'pad' => array('l' => 40),
			),
			'showlegend' => ( 'pie' == $type ),
		);
		if (!$isPie && !$isBar) {
			$layout['xaxis'] = array(
				'type' => 'date',
				'range' => array($from, $to),
			);

			$range = DbWpf::get("SELECT SUBDATE('" . $from . "',1), ADDDATE('" . $to . "',1)", 'row', ARRAY_N);
			if (!is_null($range) && is_array($range) && count($range) == 2 && !empty($range[0]) && !empty($range[1])) {
				$layout['xaxis']['range'] = array($range[0], $range[1]);
			}
		}

		$config = array(
			'responsive' => true,
			'scrollZoom' => true,
			'displaylogo' => false,
			'autosizable' => true,
			'autoexpand' => true,
			'modeBarButtonsToRemove' => array('select2d', 'lasso2d'),
			'toImageButtonOptions' => array(
				'format' => 'png',
				'filename' => preg_replace('/[^a-z0-9]+/', '-', strtolower($title)),
			),
		);
		$all = 0;
		switch ($report) {
			case 'requests':
			case 'users':
			case 'no_result':
				$s = 'sum(cnt_ok+cnt_no)';
				switch ($report) {
					case 'users':
						$s = 'max(users)';
						break;
					case 'no_result':
						$s = 'sum(cnt_no)';
						break;
					default:
						break;
				}
				$select = 'SELECT filter_date as dd, ' . $s . ' as ss' .
					' FROM `@__statistics_sum` s' .
					" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
					' AND s.filter_id=' . $filter .
					' AND s.page_id=' . $page .
					" AND s.block_id='" . $block . "'" .
					' AND s.val_id=0' .
					' GROUP BY s.filter_date';
				$result = DbWpf::get($select);
				$x = array();
				$y = array();
				if ($result) {
					foreach ($result as $d) {
						$s = $d['ss'];
						if (!empty($s)) {
							$x[] = $d['dd'];
							$y[] = $s;
							$all += $s;
						}
					}
				}
				$data[] = array(
					'x' => $x,
					'y' => $y,
					'mode' => 'lines+markers',
					'line' => array('width' => 2, 'shape' => 'spline', 'dash' => 'solid'),
				);

				break;
			case 'blocks':
				if (empty($filter)) {
					$filter = -1;
				}
				$select = 'SELECT block_id as bb, sum(cnt_ok+cnt_no) as ss' .
					' FROM `@__statistics_sum` s' .
					" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
					' AND s.filter_id=' . $filter .
					' AND s.page_id=' . $page .
					' AND s.val_id=0 AND s.is_max=0' .
					" AND s.block_id!=''" .
					' GROUP BY s.block_id' .
					( $top > 0 ? ' ORDER BY ss DESC LIMIT ' . $top : '' );
				$result = DbWpf::get($select);

				$blocks = $this->getFiltersBlocksList( $filter );
				$values = array();
				$labels = array();

				if ($result) {
					foreach ($result as $d) {
						$s = $d['ss'];
						if (!empty($s)) {
							$values[] = $s;
							$labels[] = $this->getFilterSetting($blocks, $d['bb'], '???');
							$all += $s;
						}
					}
					if ('pie' == $type) {
						$data[] = array(
							'values' => $values,
							'labels' => $labels,
							'type' => 'pie',
						);
					} else {
						$data[] = array(
							'x' => $values,
							'y' => $labels,
							'type' => 'bar',
							'orientation' => 'h',
						);
						//$layout['yaxis'] = array('ticklabelposition' => 'inside');
						$layout['yaxis'] = array('automargin' => true);
					}
				}
				break;
			case 'values':
				if (empty($filter)) {
					$filter = -1;
				}
				if ('bubble' == $type) {
					$select = 'SELECT filter_date as dd, s.val_id, s.is_max, v.value, sum(cnt_ok+cnt_no) as ss' .
						' FROM `@__statistics_sum` s' .
						' INNER JOIN `@__statistics_val` v ON (v.id=s.val_id)' .
						" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
						' AND s.filter_id=' . $filter .
						( empty($page) ? '' : ' AND s.page_id=' . $page ) .
						' AND s.val_id!=0' .
						" AND s.block_id='" . $block . "'" .
						' GROUP BY filter_date, s.is_max, s.val_id' .
						( $top > 0 ? ' ORDER BY ss DESC LIMIT ' . $top : '' );
					$result = DbWpf::get($select);

					$blocks = $this->getFiltersBlocksList( $filter );
					$x = array();
					$y = array();
					$size = array();
					$xMax = array();
					$yMax = array();
					$sizeMax = array();
					$maxSize = 0;

					if ($result) {
						foreach ($result as $d) {
							$s = $d['ss'];
							if (!empty($s)) {
								if (empty($d['is_max'])) {
									$x[] = $d['dd'];
									$y[] = $d['value'];
									$size[] = $s;
								} else {
									$xMax[] = $d['dd'];
									$yMax[] = $d['value'];
									$sizeMax[] = $s;
								}
								if ($s > $maxSize) {
									$maxSize = $s;
								}
								if ($s > $maxSize) {
									$maxSize = $s;
								}
								$all += $s;
							}
						}
						$k = 80 / $maxSize;
						foreach ($size as $i => $s) {
							$size[$i] = ceil($s * $k);
						}
						foreach ($sizeMax as $i => $s) {
							$sizeMax[$i] = ceil($s * $k);
						}
						$data[] = array(
							'x' => $x,
							'y' => $y,
							'mode' => 'markers',
							'marker' => array('size' => $size),
							'name' => __('Min values', 'woo-product-filter'),
						);
						if (!empty($sizeMax)) {
							$data[] = array(
								'x' => $xMax,
								'y' => $yMax,
								'mode' => 'markers',
								'marker' => array('size' => $sizeMax),
								'name' => __('Max values', 'woo-product-filter'),
							);
							$layout['showlegend'] = true;

						}
						$layout['yaxis'] = array('automargin' => true);
						//$layout['yaxis'] = array('ticklabelposition' => 'inside', 'ticklabeloverflow' => 'hide past div', 'automargin' => true);
					}

					break;
				}
				$select = 'SELECT s.val_id, s.is_max, v.value, sum(cnt_ok+cnt_no) as ss' .
					' FROM `@__statistics_sum` s' .
					' INNER JOIN `@__statistics_val` v ON (v.id=s.val_id)' .
					" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
					' AND s.filter_id=' . $filter .
					( empty($page) ? '' : ' AND s.page_id=' . $page ) .
					' AND s.val_id!=0' .
					" AND s.block_id='" . $block . "'" .
					' GROUP BY s.is_max, s.val_id' .
					( $top > 0 ? ' ORDER BY ss DESC LIMIT ' . $top : '' );
				$result = DbWpf::get($select);

				$values = array();
				$labels = array();
				$valuesMax = array();
				$labelsMax = array();

				if ($result) {
					foreach ($result as $d) {
						$s = $d['ss'];
						if (!empty($s)) {
							if (empty($d['is_max'])) {
								$values[] = $s;
								$labels[] = $d['value'];
							} else {
								$valuesMax[] = $s;
								$labelsMax[] = $d['value'];
							}
							$all += $s;
						}
					}
					if ('pie' == $type) {
						$data[] = array(
							'values' => $values,
							'labels' => $labels,
							'type' => 'pie',
							'name' => __('Min values', 'woo-product-filter'),
							'domain' => array('row' => 0, 'column' => 0),
						);
						if (!empty($valuesMax)) {
							$data[] = array(
								'values' => $valuesMax,
								'labels' => $labelsMax,
								'type' => 'pie',
								'name' => __('Max values', 'woo-product-filter'),
								'domain' => array('row' => 0, 'column' => 1),
							);
							$layout['grid'] = array('rows' => 1, 'columns' => 2);
						}
						$layout['legend']['orientation'] = 'h';
					} else {
						$data[] = array(
							'x' => $values,
							'y' => $labels,
							'type' => 'bar',
							'orientation' => 'h',
							'name' => __('Min values', 'woo-product-filter'),
						);

						if (!empty($valuesMax)) {
							$data[] = array(
								'x' => $valuesMax,
								'y' => $labelsMax,
								'type' => 'bar',
								'orientation' => 'h',
								'name' => __('Max values', 'woo-product-filter'),
							);
							$layout['barmode'] = 'group';
							$layout['showlegend'] = true;

						}
						$layout['yaxis'] = array('automargin' => true);
					}

				}
				break;
			default:
				break;
		}
		if (!$isPie && !$isBar) {
			if (empty($all)) {
				$layout['yaxis'] = array('range' => array(0, 100));
			}
		}

		return array('data' => $data, 'layout' => $layout, 'config' => $config);
	}

	public function getTableData( $params = array() ) {
		if (!is_array($params)) {
			$this->pushError('There are no parameters of report');
			return false;
		}

		if (!$this->calcSummaryStatistics() && FrameWpf::_()->getModule('options')->getModel()->get('logging') == 1) {
			$logger = wc_get_logger();
			if ($logger) {
				$logger->warning(UtilsWpf::jsonEncode($this->getErrors()), array('source' => 'wpf-statistics'));
			}
		}

		$report = $this->getFilterSetting($params, 'report');
		$filter = $this->getFilterSetting($params, 'filter', 0, 1);
		if (empty($filter)) {
			$filter = -1;
		}
		$page = empty($filter) ? 0 : $this->getFilterSetting($params, 'page', 0, 1);
		$block = empty($filter) ? 0 : $this->getFilterSetting($params, 'block');
		if (empty($block) || is_null($block) || ( 'null' == $block )) {
			$block = '';
		}
		$order = $this->getFilterSetting($params, 'order', 'cnt_all', false, array('cnt_all', 'cnt_not', 'bb'));
		$asc = $this->getFilterSetting($params, 'dir') == 'asc';

		$top = $this->getFilterSetting($params, 'top');
		list($from, $to) = $this->getReportPeriod($params);

		$rows = array();
		$all = 0;
		if ('blocks' == $report) {
			$select = 'SELECT block_id as bb, sum(cnt_ok+cnt_no) as cnt_all, sum(cnt_no) as cnt_not' .
				' FROM `@__statistics_sum` s' .
				" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
				' AND s.filter_id=' . $filter .
				' AND s.page_id=' . $page .
				' AND s.val_id=0 AND s.is_max=0' .
				" AND s.block_id!=''" .
				' GROUP BY s.block_id' .
				' ORDER BY ' . $order . ( $asc ? '' : ' DESC' ) .
				( $top >= 1000 ? ' LIMIT ' . $top : '' );
			$result = DbWpf::get($select);

			$blocks = $this->getFiltersBlocksList( $filter );

			if ($result) {
				foreach ($result as $d) {
					$s = $d['cnt_all'];
					if (!empty($s)) {
						$rows[] = array(
							'value' => $this->getFilterSetting($blocks, $d['bb'], '???'),
							'cnt_all' => $s,
							'cnt_not' => $d['cnt_not'],
						);
						$all++;
					}
				}
			}
		} else {
			$select = 'SELECT s.val_id, s.is_max, v.value, sum(cnt_ok+cnt_no) as cnt_all, sum(cnt_no) as cnt_not' .
				' FROM `@__statistics_sum` s' .
				' INNER JOIN `@__statistics_val` v ON (v.id=s.val_id)' .
				" WHERE s.filter_date BETWEEN '" . $from . "' AND '" . $to . "'" .
				' AND s.filter_id=' . $filter .
				( empty($page) ? '' : ' AND s.page_id=' . $page ) .
				' AND s.val_id!=0' .
				" AND s.block_id='" . $block . "'" .
				' GROUP BY s.is_max, s.val_id' .
				' ORDER BY ' . $order . ( $asc ? '' : ' DESC' ) .
				( $top >= 1000 ? ' LIMIT ' . $top : '' );
			$result = DbWpf::get($select);

			if ($result) {
				foreach ($result as $d) {
					$s = $d['cnt_all'];
					if (!empty($s)) {
						$rows[] = array(
							'value' => $d['value'] . ( empty($d['is_max']) ? '' : ' (max)' ),
							'cnt_all' => $s,
							'cnt_not' => $d['cnt_not'],
						);
						$all++;
					}
				}
			}
		}
		return array('rows' => $rows, 'total' => $all);
	}
}

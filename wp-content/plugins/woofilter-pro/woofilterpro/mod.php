<?php
/**
 * WBW Product Filter PRO - WoofilterProWpf Class
 *
 * @version 3.1.2
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class WoofilterProWpf extends ModuleWpf {

	/**
	 * Properties.
	 */
	public $defaultFont            = 'Default';
	public $acf_prefix             = 'acf-';
	public $ctax_prefix            = 'ctax-';
	public $local_prefix           = 'flocal-';
	public $meta_prefix            = 'fmeta-';
	public $acfFields              = array();
	private $defaultFilterData     = array();
	private $wcAttributes          = null;
	private $productList           = '';
	private static $cacheQuery     = array();
	public $acfEnabledTypes        = array(
		'text',
		'number',
		'radio',
		'select',
		'button_group',
		'checkbox',
		'true_false',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'color_picker',
	);
	public $searchNumberSuffix     = array('_lmin' => '>=', '_lmax' => '<=', '_lmore' => '>', '_lless' => '<', '_lequ' => '=');

	/**
	 * init.
	 */
	public function init() {
		parent::init();
		DispatcherWpf::addAction( 'addScriptsContent', array( $this, 'addScriptsContent' ), 10, 2 );
		DispatcherWpf::addAction( 'addEditTabFilters', array( $this, 'addEditTabFilters' ), 10, 2 );
		DispatcherWpf::addAction( 'addEditTabDesign', array( $this, 'addEditTabDesign' ), 10, 3 );
		DispatcherWpf::addAction( 'addArgsForFilteringBySettings', array( $this, 'addArgsForFilteringBySettings' ), 10, 2 );
		DispatcherWpf::addAction( 'beforeFiltersFrontendArgs', array( $this, 'checkBeforeFiltersFrontendArgs' ), 10, 3 );
		DispatcherWpf::addAction( 'beforeFilterExistsTerms', array( $this, 'checkBeforeFiltersFrontendArgs' ), 10, 3 );
		DispatcherWpf::addAction( 'beforeDisplayProduct', array( $this, 'showVariationAsMineProduct' ), 10, 2 );
		DispatcherWpf::addAction( 'beforeLoopVariations', array( $this, 'beforeLoopVariations' ), 10, 2 );
		DispatcherWpf::addAction( 'addDefaultFilterData', array( $this, 'addDefaultFilterData' ), 10, 2 );
		DispatcherWpf::addAction( 'addAdminButtonsPro', array( $this, 'addAdminButtonsPro' ), 10 );
		DispatcherWpf::addAction( 'afterWoobewooWrap', array( $this, 'appendImportDialog' ) );
		DispatcherWpf::addAction( 'beforeSaveOpts', array( $this, 'translateOptionStrings' ) );

		DispatcherWpf::addFilter( 'addFilterTypes', array( $this, 'addFilterTypes' ) );
		DispatcherWpf::addFilter( 'getSettingOverlayWord', array( $this, 'getSettingOverlayWord' ) );
		DispatcherWpf::addFilter( 'getFrontendFilterTypes', array( $this, 'getFrontendFilterTypes' ), 10, 2 );
		DispatcherWpf::addFilter( 'optionsDefine', array( $this, 'addOptions' ) );
		DispatcherWpf::addFilter( 'addHtmlBeforeFilter', array( $this, 'addHtmlBeforeFilter' ), 10, 3 );
		DispatcherWpf::addFilter( 'addHtmlAfterFilter', array( $this, 'addHtmlAfterFilter' ), 10, 3 );
		DispatcherWpf::addFilter( 'getTaxonomyOptionsHtml', array( $this, 'getTaxonomyOptionsHtml' ), 10, 2 );
		DispatcherWpf::addFilter( 'getOneTaxonomyOptionHtml', array( $this, 'getOneTaxonomyOptionHtml' ), 10, 2 );
		DispatcherWpf::addFilter( 'controlFilterSettings', array( $this, 'controlFilterSettings' ), 10, 2 );
		DispatcherWpf::addFilter( 'addCustomCss', array( $this, 'addCustomCss' ), 10, 3 );
		DispatcherWpf::addFilter( 'getCustomAttributeName', array( $this, 'getCustomAttributeName' ), 10, 2 );
		DispatcherWpf::addFilter( 'addCustomAttributes', array( $this, 'addCustomAttributes' ), 10, 2 );
		DispatcherWpf::addFilter( 'getCustomTerms', array( $this, 'getCustomTerms' ), 10, 4 );
		DispatcherWpf::addFilter( 'addCustomAttributesSql', array( $this, 'addCustomAttributesSql' ), 10, 2 );
		DispatcherWpf::addFilter( 'addAjaxCustomMetaQueryPro', array( $this, 'addAjaxCustomMetaQueryPro' ), 10, 3 );
		DispatcherWpf::addFilter( 'addVariationQueryPro', array( $this, 'addVariationQueryPro' ), 10, 2 );
		DispatcherWpf::addFilter( 'loadProductsFilterPro', array( $this, 'loadProductsFilterPro' ), 10, 2 );
		DispatcherWpf::addFilter( 'loadShortcodeProductsFilterPro', array( $this, 'loadShortcodeProductsFilterPro' ), 10, 2 );
		DispatcherWpf::addFilter( 'getAttrFilterLogic', array( $this, 'getAttrFilterLogicPro' ), 10, 1 );
		DispatcherWpf::addFilter( 'getCustomLoaderHtml', array( $this, 'getCustomLoaderHtml' ), 10, 2 );
		DispatcherWpf::addFilter( 'checkPriceArgs', array( $this, 'checkPriceArgs' ), 10, 1 );
		DispatcherWpf::addFilter( 'checkBeforeFiltersFrontendArgs', array( $this, 'checkBeforeFiltersFrontendArgs' ), 30, 3 );
		DispatcherWpf::addFilter( 'getIconHtml', array( $this, 'getIconHtml' ), 10, 3 );
		DispatcherWpf::addFilter( 'sortAsNumbers', array( $this, 'sortAsNumbers' ), 10, 2 );
		DispatcherWpf::addFilter( 'getFilterDefault', array( $this, 'getFilterDefault' ), 10, 2 );
		DispatcherWpf::addFilter( 'addCustomMetaQueryPro', array( $this, 'addCustomMetaQueryPro' ), 10, 3 );
		DispatcherWpf::addFilter( 'getDefaultFilterParams', array( $this, 'getDefaultFilterParams' ), 10 );
		DispatcherWpf::addFilter( 'getProAttributes', array( $this, 'getProAttributes' ), 10, 2 );
		DispatcherWpf::addFilter( 'getDecimal', array( $this, 'getDecimal' ), 10, 2 );
		DispatcherWpf::addFilter( 'productLoopStart', array( $this, 'productLoopStart' ), 10, 3 );
		DispatcherWpf::addFilter( 'priceTax', array( $this, 'priceTax' ), 10, 3 );
		DispatcherWpf::addFilter( 'getMetaFieldType', array( $this, 'getMetaFieldType' ), 10, 3 );
		DispatcherWpf::addFilter( 'addCustomMetaKeys', array( $this, 'addCustomMetaKeys' ), 10, 3 );
		DispatcherWpf::addFilter( 'getCustomPrefixes', array( $this, 'getCustomPrefixes' ), 10, 2 );
		DispatcherWpf::addFilter( 'getColorGroup', array( $this, 'getColorGroup' ), 10, 4 );
		DispatcherWpf::addFilter( 'excludeColorChildren', array( $this, 'excludeColorChildren' ), 10, 2 );
		DispatcherWpf::addFilter( 'getColorGroupForExistTerms', array( $this, 'getColorGroupForExistTerms' ), 10, 2 );
		DispatcherWpf::addFilter( 'getExistTermsColor', array( $this, 'getExistTermsColor' ), 10, 3 );
		DispatcherWpf::addFilter( 'addCustomTaxQueryPro', array( $this, 'addCustomTaxQueryPro' ), 10, 3 );
		DispatcherWpf::addFilter( 'getOneByOneCategoryHierarchy', array( $this, 'getOneByOneCategoryHierarchy' ), 10, 3 );

	}

	public function translateOptionStrings( $data ) {
		if ( function_exists( 'wpf_translate_string' ) ) {
			if ( ! empty( $data['opt_values']['selected_title'] ) ) {
				wpf_translate_string( $data['opt_values']['selected_title'] );
			}
		}
	}

	public function appendImportDialog() {
		$activeTab = FrameWpf::_()->getModule( 'options' )->getActiveTab();
		if ( 'woofilters' == $activeTab ) {
			HtmlWpf::echoEscapedHtml( $this->getView()->getImportDialog() );
		}
	}

	public function addAdminButtonsPro() {
		$this->getView()->showAdminImortExportButtons();
	}

	public function exportGroup( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! empty( $ids ) ) {
			if ( ob_get_contents() ) {
				ob_end_clean();
			}
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="wtbp_export.sql"' );
			if ( ob_get_contents() ) {
				ob_end_clean();
			}
			$delim    = '/*----------------------------------*/';
			$delimEOL = PHP_EOL . $delim . PHP_EOL;

			$tablesData = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->setWhere( array( 'additionalCondition' => 'id IN (' . implode( ',', $ids ) . ')' ) )->getFromTbl();
			if ( ! empty( $tablesData ) ) {
				$sqlString = 'INSERT INTO `@__filters` ';
				$countData = count( $tablesData ) - 1;
				foreach ( $tablesData as $key => $tableData ) {
					$columns = array_keys( $tableData );
					if ( 0 == $key ) {
						$sqlString .= '(' . implode( ',', $columns ) . ') VALUES' . PHP_EOL;
					}
					unset( $tableData['id'] );
					foreach ( $tableData as $k => $v ) {
						$tableData[$k] = str_replace("'", "\'", $v );
					}
					$sqlString .= "(NULL,'" . implode( "','", $tableData ) . "')";
					if ( $key < $countData ) {
						$sqlString .= ',' . PHP_EOL;
					} else {
						$sqlString .= ';' . $delimEOL;
					}
				}
			}

			return $sqlString;
		} else {
			$this->pushError( esc_html__( 'Invalid ID', 'woo-product-tables' ) );
		}

		return false;
	}

	public function getVariationImageId( $id ) {
		global $product;
		if ( $product->is_type( 'variation' ) ) {
			$varId = $product->get_image_id();
			if (!empty($varId)) {
				return $varId;
			}
		}
		return $id;
	}

	public function showVariationOnPrice( $price, $product ) {
		if ( is_null( $product )) {
			return $price;
		}
		if ( is_int( $product ) ) {
			if ( 'variable' === WC_Product_Factory::get_product_type( $product ) ) {
				$product = new WC_Product_Variable( $product );
			} else {
				return $price;
			}
		}
		if ($product->is_type( 'variable' )) {
			$module   = FrameWpf::_()->getModule( 'woofilters' );
			$varTable = $module->getTempTable( $module->tempVarTable );
			if ( false !== $varTable && !empty($module->clausesByParam['display_one_price'])) {
				$onePrice = DbWpf::get( 'SELECT var_price FROM ' . $varTable . ' as wpf_var_temp WHERE id=' . $product->get_id() . ' AND var_cnt_price=1', 'one' );
				if ( $onePrice ) {
					return wc_price( $onePrice );
				}
			}
		}
		return $price;
	}

	public function showVariationAsMineImage( $image, $product, $size = 'woocommerce_thumbnail', $attr = array() ) {

		if ( is_null( $product ) || isset( $attr['return'] ) ) {
			return $image;
		}

		if ( is_int( $product ) ) {

			if ( 'variable' === WC_Product_Factory::get_product_type( $product ) ) {
				$product = new WC_Product_Variable( $product );
			} else {
				return $image;
			}

		}

		if ( $product->is_type( 'variable' ) ) {
			$module   = FrameWpf::_()->getModule( 'woofilters' );
			$varTable = $module->getTempTable( $module->tempVarTable );

			if ( false !== $varTable ) {
				$isDisplayVariationImage = FrameWpf::_()->getModule( 'options' )->get( 'display_variation_image' );
				$whereAdd                = ( '1' === $isDisplayVariationImage ) ? '' : ' AND wpf_var_temp.var_cnt=1';
				$varId                   = DbWpf::get( 'SELECT var_id FROM ' . $varTable . ' as wpf_var_temp WHERE id=' . $product->get_id() . $whereAdd, 'one' );

				if ( $varId ) {
					$productVar = new WC_Product_Variation( $varId );
					/**
					 * Do custom action.
					 *
					 * @since 1.0
					 */
					$size       = apply_filters( 'single_product_archive_thumbnail_size', $size );

					if ( $productVar->get_image_id() ) {

						if ( ! is_array( $attr ) ) {
							$attr = array();
						}

						$attr['return'] = '';

						return $productVar->get_image( $size, $attr );
					}

				}

			}
		}

		return $image;
	}

	public function addScriptsContent( $adminArea, $settings ) {
		$modPath = $this->getModPath();
		if ( $adminArea ) {
			FrameWpf::_()->addScript( 'admin.filters.pro', $modPath . 'js/admin.woofilters.pro.js', array( 'jquery' ) );
			FrameWpf::_()->addStyle( 'admin.filters.pro', $modPath . 'css/admin.woofilters.pro.css' );
			$jsData = file_exists( $this->getModDir() . 'files/fontAwesomeList.txt' ) ? file( $this->getModDir() . 'files/fontAwesomeList.txt' ) : array();
			if ( ! empty( $jsData ) ) {
				$jsData = array_map( function ( $item ) {
					return 'fa-' . trim( $item );
				}, $jsData );
			}
			FrameWpf::_()->addJSVar( 'admin.filters.pro', 'FONT_AWESOME_DATA', $jsData );
		}
		$order = empty( $settings['settings']['filters']['order'] ) ? '' : $settings['settings']['filters']['order'];
		$dep = array( 'jquery' );

		if ( $adminArea || ( strpos( $order, '"wpfPrice"' ) || strpos( $order, '"slider"' ) ) ) {
			$found = $adminArea;
			if (!$found) {
				$filtersOrder = UtilsWpf::jsonDecode($order);
				foreach ($filtersOrder as $filter) {
					if (!empty($filter['settings']) && !empty($filter['id'])
						&& ( ( $this->getFilterSetting($filter['settings'], 'f_frontend_type') == 'slider' )
							|| ( ( 'wpfPrice' == $filter['id'] ) && $this->getFilterSetting($filter['settings'], 'f_skin_type') != 'default' ) )) {
						$found = true;
						break;
					}
				}
			}
			if ($found) {
				FrameWpf::_()->addScript( 'ion.slider', $modPath . 'js/ion.rangeSlider.min.js', array( 'jquery' ) );
				FrameWpf::_()->addStyle( 'ion.slider', $modPath . 'css/ion.rangeSlider.css' );
				$dep[] = 'ion.slider';
			}
		}

		FrameWpf::_()->addScript( 'frontend.filters.pro', $modPath . 'js/frontend.woofilters.pro.js', $dep );
		FrameWpf::_()->addJSVar( 'frontend.filters.pro', 'wpfTraslate', array(
			'ShowMore'          => empty( $settings['settings']['view_more_label'] ) ? __( 'Show More', 'woo-product-filter' ) : $settings['settings']['view_more_label'],
			'ShowFewer'         => empty( $settings['settings']['view_more_label2'] ) ? __( 'Show Fewer', 'woo-product-filter' ) : $settings['settings']['view_more_label2'],
			'AlphabeticalIndex' => esc_attr__( 'Alphabetical index', 'woo-product-filter' ),
			'ClearAll'          => ! empty( $settings['settings']['selected_clean_word'] ) ? esc_html__( $settings['settings']['selected_clean_word'], 'woo-product-filter' ) : '',
		) );

		FrameWpf::_()->addStyle( 'frontend.filters.pro', $modPath . 'css/frontend.woofilters.pro.css' );

		if ( $adminArea || empty( $settings['settings']['disable_plugin_styles'] ) ) {
			FrameWpf::_()->addStyle( 'custom.filters.pro', $modPath . 'css/custom.woofilters.pro.css' );
		}

		if ( $adminArea || strpos( $order, '"f_search_autocomplete":true' ) ) {

			FrameWpf::_()->addScript( 'jquery-ui-autocomplete', '', array( 'jquery' ), false, true );
			FrameWpf::_()->addStyle( 'jquery-ui-autocomplete', $modPath . 'css/jquery-ui-autocomplete.css' );
		}
	}

	public function getCustomPrefixes( $prefixes, $withTax ) {
		$prefixes[] = $this->acf_prefix;
		$prefixes[] = $this->local_prefix;
		$prefixes[] = $this->meta_prefix;
		if ( $withTax ) {
			$prefixes[] = $this->ctax_prefix;
		}
		foreach ( $prefixes as $value ) {
			$prefixes[] = 'pr_' . $value;
		}

		return $prefixes;
	}

	public function addFilterTypes( $filters ) {
		/**
		 * Plugin compatibility.
		 *
		 * @link https://woocommerce.com/products/brands
		 */
		if ( taxonomy_exists( 'product_brand' ) ) {
			$filters['wpfBrand']['enabled'] = true;
		}

		/**
		 * Plugin compatibility.
		 *
		 * @link https://wordpress.org/plugins/wc-vendors/
		 */
		if ( method_exists( FrameWpf::_()->getModule( 'woofilters' ), 'isWcVendorsPluginActivated' ) && FrameWpf::_()->getModule( 'woofilters' )->isWcVendorsPluginActivated() ) {
			$filters['wpfVendors']['enabled'] = true;
		}

		$filters['wpfSearchText']['enabled'] = true;
		$filters['wpfSearchNumber']['enabled'] = true;

		return $filters;
	}

	public function addEditTabFilters( $part, $settings = array() ) {
		$this->getView()->addEditTabFilters( $part, $settings );
	}

	public function addEditTabDesign( $part, $settings, $filterId = null ) {
		$this->getView()->addEditTabDesign( $part, $settings, $filterId );
	}

	public function getFrontendFilterTypes( $types, $filter ) {
		switch ( $filter ) {
			case 'wpfCategory':
			case 'wpfPerfectBrand':
				$types = array_merge( $types, array( 'mul_dropdown', 'multi', 'buttons', 'text' ) );
				break;
			case 'wpfTags':
				$types = array_merge( $types, array( 'buttons', 'text', 'colors' ) );
				break;
			case 'wpfAttribute':
				$types = array_merge( $types, array( 'buttons', 'text', 'switch' ) );
				break;
			case 'wpfFeatured':
			case 'wpfOnSale':
			case 'wpfInStock':
				$types = array_merge( $types, array( 'switch' ) );
				break;
			case 'wpfSortBy':
				$types = array_merge( $types, array( 'mul_dropdown' ) );
				break;
			default:
				break;
		}

		return $types;
	}

	public function addHtmlBeforeFilter( $html, $settings, $viewId ) {
		$html = $this->getView()->setFloatingMode( $html, $settings, $viewId );
		return $html . $this->getView()->addHideFiltersButton( $settings );
	}

	public function addHtmlAfterFilter( $html, $settings, $viewId ) {
		return $this->getView()->closeFloatingMode( $html, $settings, $viewId );
	}

	public function addCustomCss( $css, $settings, $filterId ) {
		return $this->getView()->addCustomCss( $settings, $filterId ) . $css;
	}

	public function getCustomLoaderHtml( $html, $settings ) {
		return $html . $this->getView()->getCustomLoaderHtml( $settings );
	}

	public function checkPriceArgs( $settings ) {
		return $this->getView()->checkPriceArgs( $settings );
	}

	public function getTaxonomyOptionsHtml( $html, $options ) {
		if ( ! isset( $options['type'] ) ) {
			return $html;
		}

		$type = $options['type'];
		$view = $this->getView();
		switch ( $type ) {
			case 'buttons':
				$html = $view->getButtonsTypeHtml( $options );
				break;
			case 'text':
				$html = $view->getTextTypeHtml( $options );
				break;
			case 'switch':
				$html = $view->getSwitchTypeHtml( $options );
				break;
			case 'colors':
				$html = $view->getColorsTypeHtml( $options );
				break;
			default:
				break;
		}

		return $html;
	}

	public function getOneTaxonomyOptionHtml( $html, $options ) {
		if ( ! isset( $options['type'] ) ) {
			return $html;
		}

		$type = $options['type'];
		$view = $this->getView();
		switch ( $type ) {
			case 'switch':
				$html = $view->generateToggleSwitchHtml( $options['id'], $options['checked'] );
				break;
			default:
				break;
		}

		return $html;
	}

	public function controlFilterSettings( $filter ) {
		return $this->getView()->controlFilterSettings( $filter );
	}

	/**
	 * Get search results for "Search by" filter.
	 *
	 * @param int $attribute Search prefix with some search attributes specific to search options
	 * tcea: t - title, c - content, e - excerpt, a - attributes, k -tax_categories, g - tax_tags s - meta_sku,
	 * o/i - or/and
	 * w/l - word/like
	 * @param int $value Search keyword value
	 * @param int $clauses
	 *
	 * @return array
	 */
	public function getSearchTextSQL( $attribute, $value, $filterSettings = array() ) {
		global $wpdb;
		$clauses = array();
		if ( empty( $value ) ) {
			return $clauses;
		}
		$join  = '';
		$where = '';

		$attrs = str_replace( 'pr_search_', '', $attribute );
		$word  = strpos( $attrs, 'w' ) !== false;
		$like  = $word ? 'REGEXP' : 'LIKE';
		$value = $wpdb->esc_like( $value );

		if ( $word ) {
			if ( version_compare( '7', $wpdb->db_server_info(), '<=' ) ) {
				$searchValue = "\\b{$value}\\b";
			} else {
				$searchValue = "[[:<:]]{$value}[[:>:]]";
			}
		} else {
			$searchValue = '%' . $value . '%';
		}

		$isAnd = strpos( $attrs, 'o' ) === false;
		$orAnd = $isAnd ? ' AND ' : ' OR ';

		$postEntityList = array(
			// for attribute it just alias for a sql request
			't' => 'post_title',
			'c' => 'post_content',
			'e' => 'post_excerpt',
		);

		$i = 0;
		foreach ( $postEntityList as $key => $postEntity ) {
			if ( strpos( $attrs, $key ) !== false ) {
				$where .= ( empty( $where ) || 0 == $i ? '' : $orAnd );
				$field  = $wpdb->posts . '.' . $postEntity;
				if ( $word ) {
					$where .= $field . ' REGEXP ' . $wpdb->prepare( '%s', $searchValue );
				} else {
					$where .= $field . ' LIKE ' . $wpdb->prepare( '%s', $searchValue );
				}
				$i++;
			}
		}

		$taxList = array(
			// for attribute it just alias for a sql request
			'a' => 'pa_attr',
			'k' => 'product_cat',
			'g' => 'product_tag',
			'b' => 'pwb-brand',
		);
		$attrList = array();
		if ( strpos( $attrs, 'a' ) !== false ) {
			preg_match( '/\d+$/', $attrs, $matches );
			if ( isset( $matches[0] ) ) {
				$filterSettings = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'settings' )->getFilterSettings( 'wpfSearchText', array(), array(), $matches[0] );
				if ( isset( $filterSettings[0]['settings'] ) ) {
					$settings = $filterSettings[0]['settings'];
					$attrIds = $this->getFilterSetting( $settings, 'f_search_by_attributes_list[]', false );
					if (!empty($attrIds)) {
						$attIdsArray = explode( ',', $attrIds );
						if (!empty($attIdsArray)) {
							$productAttr = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : array();
							foreach ( $productAttr as $attr ) {
								$attrId = (int) $attr->attribute_id;
								if (empty($attrId)) {
									$attrId = $attr->attribute_slug;
								}
								if (in_array($attrId, $attIdsArray)) {
									$attrList[] = isset($attr->attribute_slug) ? $attr->attribute_slug : 'pa_' . $attr->attribute_name;
								}
							}
						}
					}
				}
			}
		}

		if ( $isAnd ) {
			foreach ( $taxList as $key => $taxName ) {
				if ( strpos( $attrs, $key ) !== false ) {

					$join .= " INNER JOIN {$wpdb->term_relationships} AS wtbp_rel_" . $taxName . ' ON (wtbp_rel_' . $taxName . ".object_id={$wpdb->posts}.ID) ";

					if ( 'pa_attr' == $taxName ) {
						$join .= " INNER JOIN {$wpdb->term_taxonomy} AS " . $taxName .
								 ' ON (' . $taxName . '.term_taxonomy_id=wtbp_rel_' . $taxName . '.term_taxonomy_id AND ' . $taxName . ".taxonomy LIKE 'pa_%'" .
								( empty($attrList) ? '' : ' AND ' . $taxName . ".taxonomy IN ('" . implode("','", $attrList) . "')" ) . ') ';
					} else {
						$join .= " INNER JOIN {$wpdb->term_taxonomy} AS " . $taxName .
								 ' ON (' . $taxName . '.term_taxonomy_id=wtbp_rel_' . $taxName . '.term_taxonomy_id AND ' . $taxName . ".taxonomy='" . $taxName . "') ";
					}

					if ( $word ) {
						$join .= " INNER JOIN {$wpdb->terms} AS wtbp_terms_" . $taxName . ' ON (wtbp_terms_' . $taxName . '.term_id=' . $taxName . '.term_id) ';

						$where .= ( empty( $where ) ? '' : $orAnd );
						$where .= ' wtbp_terms_' . $taxName . '.name REGEXP ' . $wpdb->prepare( '%s', $searchValue ) . ' ';
					} else {
						$join .= " INNER JOIN {$wpdb->terms} AS wtbp_terms_" . $taxName . ' ON (wtbp_terms_' . $taxName . '.term_id=' . $taxName . '.term_id) ';

						$where .= ( empty( $where ) ? '' : $orAnd );
						$where .= ' wtbp_terms_' . $taxName . '.name LIKE ' . $wpdb->prepare( '%s', $searchValue ) . ' ';
					}

					$where .= 'AND (wtbp_terms_' . $taxName . '.term_id is not NULL) ';
				}
			}
		} else {
			$taxSearch = false;
			foreach ( $taxList as $key => $taxName ) {
				if ( strpos( $attrs, $key ) !== false ) {
					$taxSearch = true;
					$where .= ( empty( $where ) ? '' : $orAnd );
					$where .= '( wpf_term_search.name ' . ( $word ? 'REGEXP' : 'LIKE' ) . ' ' . $wpdb->prepare( '%s', $searchValue ) .
								  ' AND wpf_tax_search.taxonomy' .
								  ( 'pa_attr' == $taxName ? ( empty($attrList) ? " LIKE 'pa\_%'" : " IN ('" . implode("','", $attrList) . "')" ) : "='" . $taxName . "'" ) . ') ';
								  ')';
				}
			}
			if ( $taxSearch ) {
				$join .= " LEFT JOIN {$wpdb->term_relationships} AS wpf_rel_tax_search ON (wpf_rel_tax_search.object_id={$wpdb->posts}.ID) ";
				$join .= " LEFT JOIN {$wpdb->term_taxonomy} AS wpf_tax_search ON (wpf_tax_search.term_taxonomy_id=wpf_rel_tax_search.term_taxonomy_id)";
				$join .= " LEFT JOIN {$wpdb->terms} AS wpf_term_search ON (wpf_term_search.term_id=wpf_tax_search.term_id) ";
			}
		}
		$metaDataTable   = DbWpf::getTableName( 'meta_data' );
		$woofitersModule = FrameWpf::_()->getModule( 'woofilters' );
		$metaValuesModel = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' );

		if ( strpos( $attrs, 's' ) !== false ) {
			$where    .= empty( $where ) ? '' : $orAnd;
			$metaKeyId = $woofitersModule->getMetaKeyId( '_sku' );
			if ( $metaKeyId ) {
				$join .= ( $isAnd ? ' INNER' : ' LEFT' ) . ' JOIN ' . $metaDataTable . " AS wpf_meta_sku ON {$wpdb->posts}.ID = wpf_meta_sku.product_id AND wpf_meta_sku.key=" . $metaKeyId . ')';

				if ( $word ) {
					$where .= ' wpf_meta_sku.val_id in (' . implode( ',', $metaValuesModel->getMetaValueIds( $metaKeyId, 0, $wpdb->prepare( ' REGEXP %s', $searchValue ) ) ) . ')';
				} else {
					$where .= ' wpf_meta_sku.val_id=' . $metaValuesModel->getMetaValueId( $metaKeyId, str_replace( '%', '', $searchValue ) );
				}
			} else {
				$join .= " LEFT JOIN {$wpdb->postmeta} AS wpf_meta_sku ON {$wpdb->posts}.ID = wpf_meta_sku.post_id AND wpf_meta_sku.meta_key = '_sku'";

				if ( $word ) {
					$regexp_or_equal = 'REGEXP';
				} else {
					$regexp_or_equal = '=';
					$searchValue     = str_replace( '%', '', $searchValue );
				}

				$sqlValue = $wpdb->prepare( '%s', $searchValue );

				if ( ! empty( $filterSettings['filtering_by_variations'] ) && $filterSettings['filtering_by_variations'] ) {
					// searching in variations by SKU
					$where .= " wpf_meta_sku.meta_value {$regexp_or_equal} {$sqlValue} OR {$wpdb->posts}.ID IN(SELECT post_parent FROM {$wpdb->posts} AS wpf_text_sku_p
																	LEFT JOIN {$wpdb->postmeta} AS wpf_text_sku_m
																	ON wpf_text_sku_p.ID = wpf_text_sku_m.post_id AND wpf_text_sku_m.meta_key = '_sku'
																	WHERE wpf_text_sku_m.meta_value {$regexp_or_equal} {$sqlValue} )";

				} else {
					$where .= " wpf_meta_sku.meta_value {$regexp_or_equal} {$sqlValue}";
				}

			}
		}

		if ( strpos( $attrs, 'm' ) !== false ) {
			$whereAdd = '';
			preg_match( '/\d+$/', $attrs, $matches );
			if ( isset( $matches[0] ) ) {
				$filterSettings = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'settings' )->getFilterSettings( 'wpfSearchText', array(), array(), $matches[0] );
				if ( isset( $filterSettings[0]['settings'] ) ) {
					$settings       = $filterSettings[0]['settings'];
					$metaFieldsList = ( $this->getFilterSetting( $settings, 'f_search_by_meta_fields', false ) )
						? $this->getFilterSetting( $settings, 'f_search_by_meta_fields_list', '' )
						: '';
					if ( '' !== $metaFieldsList ) {
						$metaFieldsList = explode( ',', preg_replace( '/,\s*/', ',', $metaFieldsList ) );
						$placeholder    = implode( ',', array_fill( 0, count( $metaFieldsList ), '%s' ) );
						if ( '' != $placeholder ) {
							$wpdb->wpf_prepared_query = " LEFT JOIN {$wpdb->postmeta} AS wpf_meta_fields ON {$wpdb->posts}.ID = wpf_meta_fields.post_id AND wpf_meta_fields.meta_key IN ( {$placeholder} )";
							$join                    .= $wpdb->prepare( $wpdb->wpf_prepared_query, $metaFieldsList );
							$where                   .= empty( $where ) ? '' : $orAnd;
							$wpdb->wpf_prepared_query = ( $word )
								? "{$whereAdd} wpf_meta_fields.meta_value REGEXP '%s' "
								: "{$whereAdd} wpf_meta_fields.meta_value LIKE '%s' ";

							$where .= $wpdb->prepare( $wpdb->wpf_prepared_query, $searchValue );
						}
					}
				}
			}
		}

		if ( ! empty( $join ) ) {
			$clauses['join'] = array( $join );
		}
		if ( ! empty( $where ) ) {
			$clauses['where'] = array( ' AND (' . $where . ')' );
		}

		return $clauses;
	}

	public function getSearchTextExcludedSQL( $excluded ) {
		global $wpdb;
		$sql = '';
		foreach ( $excluded as $exclude ) {
			$parts = explode( '__', $exclude );
			$attr  = $parts[0];
			switch ( $attr ) {
				case 'products':
					$sql .= $wpdb->prepare( "AND ($wpdb->posts.ID != %d)", $parts[1] );
					break;
				case 'product_cat':
				case 'product_tag':
				default:
					$sql .= $wpdb->prepare( "AND ($wpdb->posts.ID NOT IN (SELECT object_id FROM $wpdb->term_relationships AS wtr WHERE wtr.term_taxonomy_id = %d))", $parts[1] );
					break;
			}
		}

		return $sql;
	}

	public function checkBeforeFiltersFrontendArgs( $args, $filterSettings = array(), $urlQuery = array() ) {
		global $wpdb;
		$vars = ( ! empty( $urlQuery ) ) ? $urlQuery : ReqWpf::get( 'get' );

		$groupId = array();
		foreach ( $vars as $key => $value ) {
			if ( 0 === strpos( $key, 'groupwpf__' ) ) {
				$groupId = DispatcherWpf::applyFilters( 'getColorGroup', $groupId, $key, $value );
			}
		}

		if ( ! empty( $groupId ) ) {
			foreach ( $args['tax_query'] as $key => $tax_query ) {
				if ( ! is_array( $tax_query ) ) {
					continue;
				}
				foreach ( $tax_query as $key2 => $tax_item ) {
					if ( ! is_array( $tax_item ) || empty( $tax_item['taxonomy'] ) ) {
						continue;
					}
					$taxonomy = $tax_item['taxonomy'];
					if ( isset( $groupId[ $taxonomy ] ) ) {
						$isSlug = ( isset( $tax_item['field'] ) && 'slug' === $tax_item['field'] );
						foreach ( $tax_item['terms'] as $termId ) {
							$term = $isSlug ? get_term_by( 'slug', $termId, $taxonomy ) : get_term( $termId );
							if ( $term ) {
								if ( isset( $groupId[ $taxonomy ][ $term->term_id ] ) ) {
									foreach ( $groupId[ $taxonomy ][ $term->term_id ] as $childTermId ) {
										$childTerm                                      = get_term( $childTermId );
										$args['tax_query'][ $key ][ $key2 ]['terms'][]  = ( $isSlug ) ? $childTerm->slug : (string) $childTerm->term_id;
										$args['tax_query'][ $key ][ $key2 ]['operator'] = 'IN';
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ( $vars as $key => $value ) {

			if ( strpos( $key, 'pr_search_' ) === 0 && ! is_null( $value ) && strlen( $value ) > 0 ) {
				$clauses = $this->getSearchTextSQL( $key, $value, $filterSettings );
				if ( empty( $clauses ) ) {
					break;
				}

				$woofilterModule = FrameWpf::_()->getModule( 'woofilters' );
				$woofilterModule->addFilterClauses( $clauses, false );
				$woofilterModule->setFilterClauses();

				break;
			}
		}

		if ( ! empty( $vars['wpf_oistock'] ) ) {
			add_filter( 'posts_clauses', array( $this, 'addOrderByStockFirst' ), 999998 );
		}

		return $args;
	}

	public function showVariationAsMineProduct( $params = false ) {
		global $product, $wp_query;

		if ( $product->is_type( 'variable' ) ) {
			$module   = FrameWpf::_()->getModule( 'woofilters' );
			$varTable = $module->getTempTable( $module->tempVarTable );
			if ( false !== $varTable ) {
				$varId = DbWpf::get( 'SELECT var_id FROM ' . $varTable . ' as wpf_var_temp WHERE id=' . $product->get_id() . ' AND wpf_var_temp.var_cnt=1', 'one' );
				if ( $varId ) {
					$product = new WC_Product_Variation( $varId );
				}
			}
		}

		return $product;
	}

	public function beforeLoopVariations( $settings = array() ) {
		add_filter( 'woocommerce_loop_product_link', array( $this, 'changeProductLink' ), 10, 2 );
		add_filter( 'woocommerce_product_get_image', array( $this, 'showVariationAsMineImage' ), 10, 4 );
		if ('1' === FrameWpf::_()->getModule( 'options' )->get( 'display_one_price' )) {
			add_filter( 'woocommerce_variable_price_html', array( $this, 'showVariationOnPrice' ), 100, 2 );
		}

		$theme = wp_get_theme();

		if ( $theme instanceof WP_Theme ) {
			$themeName = ( '' !== $theme['Parent Theme'] ) ? $theme['Parent Theme'] : $theme['Name'];

			if ( 'Woodmart' === $themeName ) {
				add_filter( 'woodmart_get_product_thumbnail', array( $this, 'showVariationAsMineImage' ), 10, 4 );
			} else if ( 'Total' === $themeName ) {
				add_filter( 'wpex_woocommerce_product_entry_thumbnail_id', array( $this, 'getVariationImageId' ), 10);
			} else if ( 'Betheme' === $themeName ) {
				add_filter( 'the_permalink', function ( $link, $post = null ) {
					global $product;
					return $product ? $this->changeProductLink( $link, $product ) : $link;
				}, 100, 2 );
			}

		}
	}

	public function changeProductLink( $link, $product ) {
		if ( ! is_null( $product ) && $product->is_type( 'variation' ) ) {
			return $product->get_permalink();
		}
		if ('1' === FrameWpf::_()->getModule( 'options' )->get( 'set_variation_link' )) {
			if ( $product->is_type( 'variable' ) ) {
				$module   = FrameWpf::_()->getModule( 'woofilters' );
				$varTable = $module->getTempTable( $module->tempVarTable );
				if ( false !== $varTable) {
					$varId = DbWpf::get( 'SELECT var_id FROM ' . $varTable . ' as wpf_var_temp WHERE id=' . $product->get_id(), 'one' );
					if ( $varId ) {
						$productVar = new WC_Product_Variation( $varId );
						return $productVar->get_permalink();
					}

				}
			}
		}

		return $link;
	}

	/**
	 * addOptions.
	 *
	 * @version 3.1.2
	 */
	public function addOptions( $options ) {
		$opts = array_merge( $options['general']['opts'], array(
			'loader_enable'             => array(
				'label' => esc_html__( 'Enable filter icon on load', 'woo-product-filter' ),
				'desc'  => esc_html__( 'Show filter icon while page is loading.', 'woo-product-filter' ),
				'def'   => '1',
				'html'  => 'checkboxHiddenVal',
			),
			'loader_icon_color'         => array(
				'label' => esc_html__( 'Filter loader color', 'woo-product-filter' ),
				'desc'  => esc_html__( 'Here you may select the color of filter loader animation.', 'woo-product-filter' ),
				'def'   => '#000000',
				'html'  => 'colorpicker',
			),
			'loader_icon'               => array(
				'label'        => esc_html__( 'Filter loader icon', 'woo-product-filter' ),
				'desc'         => esc_html__( 'Here you may select the animated loader, which appears when filter is loading.', 'woo-product-filter' ),
				'html'         => ( method_exists( 'HtmlWpf', 'selectIcon' ) ? 'selectIcon' : 'hidden' ),
				'def'          => 'default|0',
				'add_sub_opts' => array( $this, 'getSettingsLoaderHtml' ),
			),
			'selected_title'            => array(
				'label' => esc_html__( '"Multiple Dropdown" selected title', 'woo-product-filter' ),
				'desc'  => esc_html__( 'Title that is displayed in the "Multiple Dropdown" when multiple list items are selected', 'woo-product-filter' ),
				'html'  => 'input',
				'def'   => 'selected',
			),
			'hide_without_price'        => array(
				'label' => esc_html__( 'Hide products without price', 'woo-product-filter' ),
				'desc'  => esc_html__( 'Do not display products without prices in the filter and in the list of products', 'woo-product-filter' ),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'hide_expired_notification' => array(
				'label' => esc_html__( 'Hide expired license notification', 'woo-product-filter' ),
				'desc'  => esc_html__( 'If this option is enabled, then the notification that licenses have expired will not be displayed.', 'woo-product-filter' ),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'display_variation_image'   => array(
				'label' => esc_html__( 'Display variation image', 'woo-product-filter' ),
				'desc'  => esc_html__( 'If the product has several variations that satisfy the selection condition, then use the variation image with the minimum variation ID', 'woo-product-filter' ),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'display_one_price'   => array(
				'label' => esc_html__( 'Display one variation price', 'woo-product-filter' ),
				'desc'  => esc_html__( 'If the product has several variations that satisfy the selection condition and the prices of these variations are the same, then display exactly this price, and not a range of values.', 'woo-product-filter' ) . ' <br>' . esc_html__('Works only if the option Display Variations Instead Of Variable Product is enabled.', 'woo-product-filter'),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'set_variation_link'   => array(
				'label' => esc_html__( 'Display preselected variation', 'woo-product-filter' ),
				'desc'  => esc_html__( 'When filtering, the link of the variable product always will be substituted with the link of the variation. If the product has several variations that satisfy the selection condition, then use the variation with the minimum variation ID. Thus, when you click on a product and go to the product page, it will already have default options.', 'woo-product-filter' ) . ' <br><br>' . esc_html__('Works only if the option Display Variations Instead Of Variable Product is enabled.', 'woo-product-filter'),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'use_max_price'   => array(
				'label' => esc_html__( 'Use max variations price for sorting', 'woo-product-filter' ),
				'desc'  => esc_html__( 'By default, when sorting by price for variable products use the minimum price. Enable this option if you want to sort variable products by maximum price.', 'woo-product-filter' ),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
			'unlimited_color_filter'   => array(
				'label' => esc_html__( 'Unlimited colors/buttons filter', 'woo-product-filter' ),
				'desc'  => esc_html__( 'By default, the colors and buttons filters (for attributes and tags) are not available if the number of elements exceeds 300 pieces. Enable this option if you wish to remove this limit.', 'woo-product-filter' ) . '<br><br>' . esc_html__( 'Attention! This option can lead to significant delays in the admin panel by setting filter parameters.', 'woo-product-filter' ),
				'def'   => '0',
				'html'  => 'checkboxHiddenVal',
			),
		) );

		$options['general']['opts'] = $opts;

		return $options;
	}

	public function getSettingsLoaderHtml( $options ) {
		$opts  = $options['general']['opts'];
		$icon  = ! empty( $opts['loader_icon']['value'] ) ? $opts['loader_icon']['value'] : 'default|0';
		$parts = explode( '|', $icon );
		if ( count( $parts ) == 2 ) {
			$iconName   = $parts[0];
			$iconNumber = $parts[1];
		} else {
			$iconName   = 'default';
			$iconNumber = '0';
		}

		if ( 'default' === $iconName ) {
			$htmlPreview = '<div class="woobewoo-filter-loader"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>';
		} else {
			$htmlPreview = '<div class="woobewoo-filter-loader la-' . $iconName . ' la-2x">';
			for ( $i = 1; $i <= $iconNumber; $i++ ) {
				$htmlPreview .= '<div></div>';
			}
			$htmlPreview .= '</div>';
		}
		$loaderSkins = array(
			'timer'                             => 1,
			'ball-beat'                         => 3,
			'ball-circus'                       => 5,
			'ball-atom'                         => 4,
			'ball-spin-clockwise-fade-rotating' => 8,
			'line-scale'                        => 5,
			'ball-climbing-dot'                 => 4,
			'square-jelly-box'                  => 2,
			'ball-rotate'                       => 1,
			'ball-clip-rotate-multiple'         => 2,
			'cube-transition'                   => 2,
			'square-loader'                     => 1,
			'ball-8bits'                        => 16,
			'ball-newton-cradle'                => 4,
			'ball-pulse-rise'                   => 5,
			'triangle-skew-spin'                => 1,
			'fire'                              => 3,
			'ball-zig-zag-deflect'              => 2,
		);

		$html = HtmlWpf::hidden( 'opt_values[loader_icon]', array( 'value' => $iconName . '|' . $iconNumber ) ) .
				'<div class="wpfIconPreview">' . $htmlPreview . '</div>
			<div class="wpfLoaderIconTemplate wpfHidden">
				<div class="items items-list">
					<div class="item">
						<div class="item-inner">
							<div class="item-loader-container">
								<div class="preicon_img" data-name="default" data-items="0">
									<div class="woobewoo-filter-loader"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>
								</div>
							</div>
						</div>
						<div class="item-title">default</div>
					</div>';
		foreach ( $loaderSkins as $name => $number ) {
			$html .= '<div class="item">
				<div class="item-inner">
					<div class="item-loader-container">
						<div class="woobewoo-filter-loader la-' . $name . ' la-2x preicon_img" data-name="' . $name . '" data-items="' . $number . '">';
			for ( $i = 0; $i < $number; $i++ ) {
				$html .= '<div></div>';
			}
			$html .= '</div>
					</div>
				</div>
				<div class="item-title">' . $name . '</div>
				</div>';
		}
		$html .= '</div></div>';

		return $html;
	}

	public function addCustomAttributes( $attributes ) {
		$exclude = array( 'product_type', 'product_visibility', 'product_cat', 'product_tag', 'product_shipping_class' );
		foreach ( $attributes as $attr ) {
			$exclude[] = 'pa_' . $attr->attribute_name;
		}
		$metaKeyId = FrameWpf::_()->getModule( 'woofilters' )->getMetaKeyId( '_product_attributes' );
		if ( $metaKeyId ) {
			$localAttrs = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getFieldValuesList( $metaKeyId, 'key3', array( 'key2' => 'local' ), true );
			foreach ( $localAttrs as $local ) {
				$attr                  = new stdClass();
				$attr->attribute_id    = 0;
				$attr->attribute_name  = $local;
				$attr->attribute_slug  = $this->local_prefix . $local;
				$attr->attribute_label = $local . ' *';
				$attr->filter_name     = $this->local_prefix . $local;
				$attr->custom_type     = 'text';
				$attributes[]          = $attr;
			}
		}

		foreach ( get_object_taxonomies( 'product', 'objects' ) as $slug => $tax ) {
			if ( ! in_array( $slug, $exclude ) ) {
				$attr                  = new stdClass();
				$attr->attribute_id    = 0;
				$attr->attribute_name  = $slug;
				$attr->attribute_slug  = $this->ctax_prefix . $slug;
				$attr->attribute_label = $tax->label;
				$attr->filter_name     = 'wpf_filter_' . $slug;
				$attr->custom_type     = 'text';
				$attributes[]          = $attr;
			}
		}
		if ( $this->isACFPluginActivated() ) {
			$enabledTypes = $this->acfEnabledTypes;

			$groups = acf_get_field_groups();
			if ( is_array( $groups ) ) {
				foreach ( $groups as $group ) {
					$forProduct = false;
					if ( ! is_array( $group['location'] ) ) {
						continue;
					}
					foreach ( $group['location'] as $location ) {
						if ( ! is_array( $location ) ) {
							continue;
						}
						foreach ( $location as $obj ) {
							if ( 'post_type' == $obj['param'] && '==' == $obj['operator'] && 'product' == $obj['value'] ) {
								$forProduct = true;
								break;
							}
						}
					}
					if ( ! $forProduct ) {
						continue;
					}
					$fields = acf_get_fields( $group['ID'] );
					foreach ( $fields as $field ) {
						if ( empty( $field['name'] ) || empty( $field['label'] ) ) {
							continue;
						}
						$type = $field['type'];
						if ( ! in_array( $type, $enabledTypes ) ) {
							continue;
						}
						$slug = $this->acf_prefix . $field['name'];

						$attr                  = new stdClass();
						$attr->attribute_id    = 0;
						$attr->attribute_name  = $field['name'];
						$attr->attribute_slug  = $slug;
						$attr->attribute_label = $field['label'];
						$attr->filter_name     = $slug;

						$attr->custom_type = $type;
						$attributes[]      = $attr;
					}
				}
			}
		}

		return $attributes;
	}

	public function getCustomAttributeName( $slug, $filter ) {
		if ( strpos( $slug, $this->ctax_prefix ) === 0 ) {
			$slug = str_replace( $this->ctax_prefix, '', $slug );
		} elseif ( 'custom_meta_field_check' === $slug ) {
			if ( ! empty( $filter['settings']['f_custom_meta_field'] ) ) {
				$slug = $this->meta_prefix . $filter['settings']['f_custom_meta_field'];
			}
		}

		return $slug;
	}

	public function getMetaFieldType( $type, $key ) {
		if ( ! $this->isACFPluginActivated() ) {
			return $type;
		}
		if ( ! isset( $this->acfFields[ $key ] ) ) {
			$this->acfFields[ $key ] = acf_get_field( $key );
		}
		if ( empty( $this->acfFields[ $key ] ) ) {
			return $type;
		}

		$acfSetting = $this->acfFields[ $key ];
		$acfType    = $acfSetting['type'];
		if ( ! in_array( $acfType, $this->acfEnabledTypes ) ) {
			return $type;
		}

		if ( ( 'select' == $acfType && ! empty( $acfSetting['multiple'] ) ) || 'checkbox' == $acfType ) {
			$type = 9;
		} elseif ( 'true_false' == $acfType ) {
			$type = 2;
		} elseif ( 'number' == $acfType ) {
			$type = 1;
		} else {
			$type = 0;
		}

		return $type;
	}

	public function getCustomTerms( $terms, $slug, $args ) {
		$order           = ( isset( $args['order'] ) ) ? $args['order'] : 'asc';
		$sort_as_numbers = ( isset( $args['sort_as_numbers'] ) ) ? $args['sort_as_numbers'] : false;
		$isCustomOrder   = false;
		if ( isset( $args['orderby'] ) ) {
			$orderby = $args['orderby'];
			$isCustomOrder = ( 'include' == $orderby && !empty($args['include']) );
		}
		$woofitersModule = FrameWpf::_()->getModule( 'woofilters' );

		if ( strpos( $slug, $this->ctax_prefix ) === 0 ) {
			return $woofitersModule->getView()->getTaxonomyHierarchy( str_replace( $this->ctax_prefix, '', $slug ), $args );

		} elseif ( strpos( $slug, $this->local_prefix ) === 0 ) {
			$metaKeyId = $woofitersModule->getMetaKeyId( '_product_attributes' );
			if ( $metaKeyId ) {
				$terms = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueTerms( $metaKeyId, array_merge( $args, array(
					'key3' => str_replace( $this->local_prefix, '', $slug ),
					'key2' => 'local',
					'key4' => '',
				) ) );
			}

		} elseif ( strpos( $slug, $this->meta_prefix ) === 0 ) {
			$key       = str_replace( $this->meta_prefix, '', $slug );
			$metaKeyId = $woofitersModule->getMetaKeyId( $key );
			if ( $metaKeyId ) {
				$metaField = $woofitersModule->getMetaKeyId( $key, 'field' );
				$keys = array(
					'field' => $metaField,
					'fbv'   => ! empty( $args['wpf_fbv'] ),
					'order' => isset( $args['order'] ) ? $args['order'] : 'asc',
				);
				return FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueTerms( $metaKeyId, $keys );
			}

		} elseif ( strpos( $slug, $this->acf_prefix ) === 0 ) {

			$key = str_replace( $this->acf_prefix, '', $slug );
			$terms = array();
			$metaKeyId = $woofitersModule->getMetaKeyId( $key );
			if ( $metaKeyId ) {
				$metaField = $woofitersModule->getMetaKeyId( $key, 'field' );
				$terms     = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueTerms( $metaKeyId, array_merge( $args, array( 'field' => $metaField ) ) );
			}
			if ( ! isset( $this->acfFields[ $key ] ) ) {
				$this->acfFields[ $key ] = acf_get_field( $key );
			}
			if ( !empty( $this->acfFields[ $key ] ) ) {
				$acfSetting = $this->acfFields[ $key ];
				$acfType = $acfSetting['type'];
				if ( in_array($acfType, array('select', 'checkbox', 'radio', 'button_group')) && !empty($acfSetting['choices']) ) {
					$choices = $acfSetting['choices'];
					foreach ($terms as $id => $term) {
						if (!isset($choices[$term->name])) {
							unset($terms[$id]);
						} else {
							$terms[$id]->name_label = $choices[$term->name];
						}
					}

					if ( ! empty( $args['all_attrs']) ) {
						$id = -1;
						foreach ($acfSetting['choices'] as $value => $l) {
							$found = false;
							foreach ($terms as $i => $term) {
								if ($value == $term->name) {
									$found = true;
									$terms[$i]->name_label = $l;
									break;
								}
							}
							if (!$found) {
								$term = new stdClass();
								$term->term_id = $id;
								$term->name    = $value;
								$term->name_label = $l;
								$term->slug    = $id;
								$term->count   = 0;
								$terms[]       = $term;
								$id--;
							}
						}
					}
				} else if ( 'date_picker' == $acfType ) {
					$dateFormat = empty($acfSetting['display_format']) ? false : $acfSetting['display_format'];
					if ($dateFormat && function_exists('acf_format_date')) {
						foreach ($terms as $id => $term) {
							$term->name = acf_format_date($term->name, $dateFormat);
						}
					}
				}

			}
		}
		if (!empty($terms)) {
			if ($isCustomOrder) {
				if (is_array($terms)) {
					$temp = array();
					foreach ($terms as $key => $value) {
						$temp[$key] = ( is_object($value) ) ? $value->term_id : $value;
					}
					$_terms = array();
					foreach ($args['include'] as $id) {
						$key = array_search($id, $temp);
						if (false !== $key) {
							$_terms[$key] = $terms[$key];
						}
					}
					$terms = $_terms;
				}
			} else {
				$terms = ( $sort_as_numbers )
					? $this->sortAsNumbers( $terms, $order )
					: $this->sortAsStrings( $terms, $order );
			}
		}

		return isset($terms) ? $terms : array();
	}

	public function addCustomAttributesSql( $termProducts, $args ) {
		global $wpdb;
		$withCount       = $args['withCount'];
		$generalSettings = $args['generalSettings'];
		$currentSettings = $args['currentSettings'];
		$listTable       = $args['listTable'];
		$woofilterModule = FrameWpf::_()->getModule( 'woofilters' );
		$metaValuesModel = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' );
		$metaFields      = array();
		$oldCalc         = array();

		$localId    = false;
		$metaVarSuf = FrameWpf::_()->getModule( 'meta' )->getModel()->metaVarSuf;

		foreach ( $args['taxonomies'] as $slug ) {
			if ( strpos( $slug, $this->acf_prefix ) === 0 ) {
				$key       = str_replace( $this->acf_prefix, '', $slug );
				$metaKeyId = $woofilterModule->getMetaKeyId( $key );
				if ( $metaKeyId ) {
					$field = $woofilterModule->getMetaKeyId( $key, 'field' );
					if ( empty( $metaFields[ $field ] ) || ! in_array( $metaKeyId, $metaFields[ $field ] ) ) {
						$metaFields[ $field ][] = $metaKeyId;
					}
				} else {
					$oldCalc[] = $slug;
				}
			} elseif ( strpos( $slug, $this->local_prefix ) === 0 ) {
				$key = str_replace( $this->local_prefix, '', $slug );
				if ( false === $localId ) {
					$localId = $woofilterModule->getMetaKeyId( '_product_attributes' );
				}
				if ( $localId ) {
					if ( empty( $metaFields['local'] ) || ! in_array( $key, $metaFields['local'] ) ) {
						$metaFields['local'][] = $key;
					}
				} else {
					$oldCalc[] = $slug;
				}
			} elseif ( strpos( $slug, $this->meta_prefix ) === 0 ) {
				$key       = str_replace( $this->meta_prefix, '', $slug );
				$metaKeyId = $woofilterModule->getMetaKeyId( $key );
				if ( $metaKeyId ) {
					$field = $woofilterModule->getMetaKeyId( $key, 'field' );
					if ( empty( $metaFields[ $field ] ) || ! in_array( $metaKeyId, $metaFields[ $field ] ) ) {
						$metaFields[ $field ][] = $metaKeyId;
						$keyIdVar               = $woofilterModule->getMetaKeyId( $key . $metaVarSuf );
						if ( $keyIdVar ) {
							$metaFields[ $field ][] = $keyIdVar;
						}
					}
				} else {
					$oldCalc[] = $slug;
				}
			}
		}

		foreach ( $metaFields as $field => $metaKeyIds ) {
			$isLocal = ( 'local' === $field );

			if ( $isLocal ) {
				$field = 'id';
			}

			$taxonomy = ( $isLocal ? 'pmv.key3' : 'pmk.taxonomy' );
			$sql      = 'SELECT ' . ( $withCount ? '' : 'DISTINCT ' ) . ' 0 as term_taxonomy_id,' .
						'pm.val_' . $field . ' as term_id, ' . $taxonomy . ' as taxonomy, 0 as parent' . ( $withCount ? ', COUNT(DISTINCT pm.product_id) as cnt' : '' ) .
						' FROM `' . $listTable . '` AS wpf_temp ' .
						' INNER JOIN @__meta_data pm ON (pm.product_id=wpf_temp.ID)' .
						( $isLocal ? ' INNER JOIN @__meta_values pmv ON (pmv.id=pm.val_id)' : ' INNER JOIN @__meta_keys pmk ON (pmk.id=pm.key_id)' );

			if ( $isLocal ) {
				$sql .= ' WHERE pm.key_id=' . $localId . " AND pmv.key2='local' AND pmv.key3 in ('" . implode( "','", $metaKeyIds ) . "')";
			} else {
				$sql .= ' WHERE pm.key_id in (' . implode( ',', $metaKeyIds ) . ')';
			}

			if ( $withCount ) {
				$sql .= ' GROUP BY pm.val_' . $field . ', ' . $taxonomy;
			}

			$result = DbWpf::get( $sql );

			if ( false !== $result ) {
				$termProducts = array_merge( $termProducts, $result );
			}

		}

		foreach ( $oldCalc as $slug ) {
			if ( strpos( $slug, $this->acf_prefix ) === 0 ) {
				$key        = str_replace( $this->acf_prefix, '', $slug );
				$acfSetting = acf_get_field( $key );

				$metaValue = 'meta_value';
				if ( isset( $acfSetting['choices'] ) && is_array( $acfSetting['choices'] ) && is_array( $acfSetting['default_value'] ) ) {
					foreach ( $acfSetting['choices'] as $value => $name ) {
						$value = $this->clearIdString( $value );
						$sql   = 'SELECT ' . ( $withCount ? '' : 'DISTINCT ' ) . " 0 as term_taxonomy_id, '$value' as term_id, meta_key as taxonomy, 0 as parent" . ( $withCount ? ', COUNT(*) as cnt' : '' ) .
								 ' FROM `' . $listTable . '` AS wpf_temp ' .
								 " INNER JOIN $wpdb->postmeta pm ON (pm.post_id=wpf_temp.ID)
							WHERE pm.meta_key='$key' AND meta_value LIKE '%:\"" . $value . "\";%'";
						if ( $withCount ) {
							$sql .= " GROUP BY '$value'";
						}
						$result = DbWpf::get( $sql );
						if ( false !== $result ) {
							$termProducts = array_merge( $termProducts, $result );
						}
					}
				} else {
					$sql = 'SELECT ' . ( $withCount ? '' : 'DISTINCT ' ) . ' 0 as term_taxonomy_id, meta_value as term_id, meta_key as taxonomy, 0 as parent' . ( $withCount ? ', COUNT(*) as cnt' : '' ) .
						   ' FROM `' . $listTable . '` AS wpf_temp ' .
						   " INNER JOIN $wpdb->postmeta pm ON (pm.post_id=wpf_temp.ID)
						WHERE pm.meta_key='" . $key . "' AND pm.meta_value != ''";
					if ( $withCount ) {
						$sql .= ' GROUP BY meta_value';
					}
					$result = DbWpf::get( $sql );
					if ( false !== $result ) {
						$termProducts = array_merge( $termProducts, $result );
					}
				}
			}
		}

		return $termProducts;
	}

	public function clearIdString( $id ) {
		return str_replace( "'", '', str_replace( '"', '', $id ) );
	}

	public function getWcAttributeTaxonomies() {
		if ( is_null( $this->wcAttributes ) ) {
			$allAttributes = wc_get_attribute_taxonomies();
			if ( ! empty( $allAttributes ) ) {
				$allAttributes = array_column( $allAttributes, 'attribute_name' );
				$allAttributes = array_map( function ( $attribute ) {
					return 'pa_' . $attribute;
				}, $allAttributes );
			} else {
				$allAttributes = array();
			}
			$this->wcAttributes = $allAttributes;
		}

		return $this->wcAttributes;
	}

	public function loadProductsFilterPro( $q ) {
		$module      = FrameWpf::_()->getModule( 'woofilters' );
		$getGet      = array_merge( $module->getPreselectedValue(), ReqWpf::get( 'get' ) );
		$defaults    = $this->getDefaultFilterParams( $getGet );
		$orderFields = false;

		if ( ! empty( $defaults['pr_sortby'] ) ) {
			$orderFields = $this->getCustomSortByFields( false, $defaults['pr_sortby'] );
		}
		if ( is_array( $orderFields ) ) {
			foreach ( $orderFields as $field => $value ) {
				$q->set( $field, $value );
			}
		}
		if ( ! empty( $defaults['pr_oistock'] ) || isset( $getGet['wpf_oistock'] ) ) {
			add_filter( 'posts_clauses', array( $this, 'addOrderByStockFirst' ), 999999 );
		}

		if ( isset( $getGet['wpf_dpv'] ) ) {
			$this->beforeLoopVariations();

			$theme = wp_get_theme();
			$themeName = '';
			if ( $theme instanceof WP_Theme ) {
				$themeName = ( '' !== $theme['Parent Theme'] ) ? $theme['Parent Theme'] : $theme['Name'];
			}

			if ( 'Total' === $themeName ) {
				add_filter( 'woocommerce_product_loop_start', array( $this, 'addActionsByLoopStartForShortcode' ), 10 );
				add_filter( 'woocommerce_product_loop_end', array( $this, 'removeActionsByLoopStartForShortcode' ), 10 );
			} else {
				add_action( 'woocommerce_shop_loop', array( $this, 'showVariationAsMineProduct' ), 10 );
			}

		}

		return $q;
	}

	public function loadShortcodeProductsFilterPro( $arg ) {

		$module      = FrameWpf::_()->getModule( 'woofilters' );
		$defaults    = $this->getDefaultFilterParams( $module->getPreselectedValue() );
		$orderFields = false;

		$params = ReqWpf::get( 'get' );

		if ( ! empty( $defaults['pr_sortby'] ) ) {
			$orderFields = $this->getCustomSortByFields( false, $defaults['pr_sortby'] );
		} else if ( isset( $params['orderby'] ) ) {
			$orderFields = $this->getCustomSortByFields( false, $params['orderby'] );
		}

		if ( is_array( $orderFields ) ) {
			$arg = array_merge( $arg, $orderFields );
		}

		if ( ! empty( $defaults['pr_oistock'] ) || isset( $params['wpf_oistock'] ) ) {
			add_filter( 'posts_clauses', array( $this, 'addOrderByStockFirst' ), 999999 );
		}

		if ( isset( $params['wpf_dpv'] ) ) {
			$this->beforeLoopVariations();
			add_filter( 'woocommerce_product_loop_start', array( $this, 'addActionsByLoopStartForShortcode' ), 10 );
			add_filter( 'woocommerce_product_loop_end', array( $this, 'removeActionsByLoopStartForShortcode' ), 10 );
		}

		return $arg;
	}

	public function addActionsByLoopStartForShortcode( $s ) {
		add_action( 'woocommerce_product_is_visible', array( $this, 'showVariationAsMineProductShortcode' ), 10 );
		return $s;
	}

	public function showVariationAsMineProductShortcode( $flag = false ) {
		$this->showVariationAsMineProduct();
		return $flag;
	}

	public function removeActionsByLoopStartForShortcode( $s ) {
		remove_action( 'woocommerce_product_is_visible', array( $this, 'showVariationAsMineProductShortcode' ), 10 );
		return $s;
	}

	public function getCustomSortByFields( $fields, $sortBy ) {
		if ( ! empty( $sortBy ) ) {
			$WC_Query = new WC_Query();
			remove_all_filters( 'woocommerce_get_catalog_ordering_args' );
			$values  = explode( '-', $sortBy );
			$orderBy = esc_attr( $values[0] );
			$order   = ! empty( $values[1] ) ? $values[1] : ( ( 'date' === $orderBy ) ? 'DESC' : 'ASC' );

			$fields = $WC_Query->get_catalog_ordering_args( $orderBy, $order );
			$WC_Query->remove_product_query();
		}

		return $fields;
	}

	public function addOrderByStockFirst( $args ) {
		global $wpdb;
		$args['join']   .= " LEFT JOIN $wpdb->postmeta wpf_ois ON ($wpdb->posts.ID = wpf_ois.post_id AND wpf_ois.meta_key='_stock_status' AND wpf_ois.meta_value<>'') ";
		$args['orderby'] = ' wpf_ois.meta_value ASC, ' . $args['orderby'];

		remove_filter( 'posts_clauses', array( $this, 'addOrderByStockFirst' ), 999999 );

		return $args;
	}

	public function getCustomMetaFieldTerms( $multiValue = array() ) {
		$metaQuery = array();
		if ( ! empty( $multiValue ) ) {
			foreach ( $multiValue as $value ) {
				$metaData    = get_metadata_by_mid( 'post', $value );
				$metaQuery[] = array( 'key' => $metaData->meta_key, 'value' => $metaData->meta_value );
			}
			$metaQuery['relation'] = 'OR';
		}

		return $metaQuery;
	}


	public function isACFPluginActivated() {
		return class_exists( 'acf' );
	}

	public function getFontsList() {
		return array(
			'ABeeZee',
			'Abel',
			'Abril Fatface',
			'Aclonica',
			'Acme',
			'Actor',
			'Adamina',
			'Advent Pro',
			'Aguafina Script',
			'Akronim',
			'Aladin',
			'Aldrich',
			'Alef',
			'Alegreya',
			'Alegreya SC',
			'Alegreya Sans',
			'Alegreya Sans SC',
			'Alex Brush',
			'Alfa Slab One',
			'Alice',
			'Alike',
			'Alike Angular',
			'Allan',
			'Allerta',
			'Allerta Stencil',
			'Allura',
			'Almendra',
			'Almendra Display',
			'Almendra SC',
			'Amarante',
			'Amaranth',
			'Amatic SC',
			'Amethysta',
			'Amiri',
			'Anaheim',
			'Andada',
			'Andika',
			'Angkor',
			'Annie Use Your Telescope',
			'Anonymous Pro',
			'Antic',
			'Antic Didone',
			'Antic Slab',
			'Anton',
			'Arapey',
			'Arbutus',
			'Arbutus Slab',
			'Architects Daughter',
			'Archivo Black',
			'Archivo Narrow',
			'Arimo',
			'Arizonia',
			'Armata',
			'Artifika',
			'Arvo',
			'Asap',
			'Asset',
			'Astloch',
			'Asul',
			'Atomic Age',
			'Aubrey',
			'Audiowide',
			'Autour One',
			'Average',
			'Average Sans',
			'Averia Gruesa Libre',
			'Averia Libre',
			'Averia Sans Libre',
			'Averia Serif Libre',
			'Bad Script',
			'Balthazar',
			'Bangers',
			'Basic',
			'Battambang',
			'Baumans',
			'Bayon',
			'Belgrano',
			'Belleza',
			'BenchNine',
			'Bentham',
			'Berkshire Swash',
			'Bevan',
			'Bigelow Rules',
			'Bigshot One',
			'Bilbo',
			'Bilbo Swash Caps',
			'Biryani',
			'Bitter',
			'Black Ops One',
			'Bokor',
			'Bonbon',
			'Boogaloo',
			'Bowlby One',
			'Bowlby One SC',
			'Brawler',
			'Bree Serif',
			'Bubblegum Sans',
			'Bubbler One',
			'Buenard',
			'Butcherman',
			'Butterfly Kids',
			'Cabin',
			'Cabin Condensed',
			'Cabin Sketch',
			'Caesar Dressing',
			'Cagliostro',
			'Calligraffitti',
			'Cambay',
			'Cambo',
			'Candal',
			'Cantarell',
			'Cantata One',
			'Cantora One',
			'Capriola',
			'Cardo',
			'Carme',
			'Carrois Gothic',
			'Carrois Gothic SC',
			'Carter One',
			'Caudex',
			'Cedarville Cursive',
			'Ceviche One',
			'Changa One',
			'Chango',
			'Chau Philomene One',
			'Chela One',
			'Chelsea Market',
			'Chenla',
			'Cherry Cream Soda',
			'Cherry Swash',
			'Chewy',
			'Chicle',
			'Chivo',
			'Cinzel',
			'Cinzel Decorative',
			'Clicker Script',
			'Coda',
			'Codystar',
			'Combo',
			'Comfortaa',
			'Coming Soon',
			'Concert One',
			'Condiment',
			'Content',
			'Contrail One',
			'Convergence',
			'Cookie',
			'Copse',
			'Corben',
			'Courgette',
			'Cousine',
			'Coustard',
			'Covered By Your Grace',
			'Crafty Girls',
			'Creepster',
			'Crete Round',
			'Crimson Text',
			'Croissant One',
			'Crushed',
			'Cuprum',
			'Cutive',
			'Cutive Mono',
			'Damion',
			'Dancing Script',
			'Dangrek',
			'Dawning of a New Day',
			'Days One',
			'Dekko',
			'Delius',
			'Delius Swash Caps',
			'Delius Unicase',
			'Della Respira',
			'Denk One',
			'Devonshire',
			'Dhurjati',
			'Didact Gothic',
			'Diplomata',
			'Diplomata SC',
			'Domine',
			'Donegal One',
			'Doppio One',
			'Dorsa',
			'Dosis',
			'Dr Sugiyama',
			'Droid Sans',
			'Droid Sans Mono',
			'Droid Serif',
			'Duru Sans',
			'Dynalight',
			'EB Garamond',
			'Eagle Lake',
			'Eater',
			'Economica',
			'Ek Mukta',
			'Electrolize',
			'Elsie',
			'Elsie Swash Caps',
			'Emblema One',
			'Emilys Candy',
			'Engagement',
			'Englebert',
			'Enriqueta',
			'Erica One',
			'Esteban',
			'Euphoria Script',
			'Ewert',
			'Exo',
			'Exo 2',
			'Expletus Sans',
			'Fanwood Text',
			'Fascinate',
			'Fascinate Inline',
			'Faster One',
			'Fasthand',
			'Fauna One',
			'Federant',
			'Federo',
			'Felipa',
			'Fenix',
			'Finger Paint',
			'Fira Mono',
			'Fira Sans',
			'Fjalla One',
			'Fjord One',
			'Flamenco',
			'Flavors',
			'Fondamento',
			'Fontdiner Swanky',
			'Forum',
			'Francois One',
			'Freckle Face',
			'Fredericka the Great',
			'Fredoka One',
			'Freehand',
			'Fresca',
			'Frijole',
			'Fruktur',
			'Fugaz One',
			'GFS Didot',
			'GFS Neohellenic',
			'Gabriela',
			'Gafata',
			'Galdeano',
			'Galindo',
			'Gentium Basic',
			'Gentium Book Basic',
			'Geo',
			'Geostar',
			'Geostar Fill',
			'Germania One',
			'Gidugu',
			'Gilda Display',
			'Give You Glory',
			'Glass Antiqua',
			'Glegoo',
			'Gloria Hallelujah',
			'Goblin One',
			'Gochi Hand',
			'Gorditas',
			'Goudy Bookletter 1911',
			'Graduate',
			'Grand Hotel',
			'Gravitas One',
			'Great Vibes',
			'Griffy',
			'Gruppo',
			'Gudea',
			'Gurajada',
			'Habibi',
			'Halant',
			'Hammersmith One',
			'Hanalei',
			'Hanalei Fill',
			'Handlee',
			'Hanuman',
			'Happy Monkey',
			'Headland One',
			'Henny Penny',
			'Herr Von Muellerhoff',
			'Hind',
			'Holtwood One SC',
			'Homemade Apple',
			'Homenaje',
			'IM Fell DW Pica',
			'IM Fell DW Pica SC',
			'IM Fell Double Pica',
			'IM Fell Double Pica SC',
			'IM Fell English',
			'IM Fell English SC',
			'IM Fell French Canon',
			'IM Fell French Canon SC',
			'IM Fell Great Primer',
			'IM Fell Great Primer SC',
			'Iceberg',
			'Iceland',
			'Imprima',
			'Inconsolata',
			'Inder',
			'Indie Flower',
			'Inika',
			'Irish Grover',
			'Istok Web',
			'Italiana',
			'Italianno',
			'Jacques Francois',
			'Jacques Francois Shadow',
			'Jaldi',
			'Jim Nightshade',
			'Jockey One',
			'Jolly Lodger',
			'Josefin Sans',
			'Josefin Slab',
			'Joti One',
			'Judson',
			'Julee',
			'Julius Sans One',
			'Junge',
			'Jura',
			'Just Another Hand',
			'Just Me Again Down Here',
			'Kalam',
			'Kameron',
			'Kantumruy',
			'Karla',
			'Karma',
			'Kaushan Script',
			'Kavoon',
			'Kdam Thmor',
			'Keania One',
			'Kelly Slab',
			'Kenia',
			'Khand',
			'Khmer',
			'Khula',
			'Kite One',
			'Knewave',
			'Kotta One',
			'Koulen',
			'Kranky',
			'Kreon',
			'Kristi',
			'Krona One',
			'Kurale',
			'La Belle Aurore',
			'Laila',
			'Lakki Reddy',
			'Lancelot',
			'Lateef',
			'Lato',
			'League Script',
			'Leckerli One',
			'Ledger',
			'Lekton',
			'Lemon',
			'Libre Baskerville',
			'Life Savers',
			'Lilita One',
			'Lily Script One',
			'Limelight',
			'Linden Hill',
			'Lobster',
			'Lobster Two',
			'Londrina Outline',
			'Londrina Shadow',
			'Londrina Sketch',
			'Londrina Solid',
			'Lora',
			'Love Ya Like A Sister',
			'Loved by the King',
			'Lovers Quarrel',
			'Luckiest Guy',
			'Lusitana',
			'Lustria',
			'Macondo',
			'Macondo Swash Caps',
			'Magra',
			'Maiden Orange',
			'Mako',
			'Mallanna',
			'Mandali',
			'Marcellus',
			'Marcellus SC',
			'Marck Script',
			'Margarine',
			'Marko One',
			'Marmelad',
			'Martel',
			'Martel Sans',
			'Marvel',
			'Mate',
			'Mate SC',
			'Maven Pro',
			'McLaren',
			'Meddon',
			'MedievalSharp',
			'Medula One',
			'Megrim',
			'Meie Script',
			'Merienda',
			'Merienda One',
			'Merriweather',
			'Merriweather Sans',
			'Metal',
			'Metal Mania',
			'Metamorphous',
			'Metrophobic',
			'Michroma',
			'Milonga',
			'Miltonian',
			'Miltonian Tattoo',
			'Miniver',
			'Miss Fajardose',
			'Modak',
			'Modern Antiqua',
			'Molengo',
			'Monda',
			'Monofett',
			'Monoton',
			'Monsieur La Doulaise',
			'Montaga',
			'Montez',
			'Montserrat',
			'Montserrat Alternates',
			'Montserrat Subrayada',
			'Moul',
			'Moulpali',
			'Mountains of Christmas',
			'Mouse Memoirs',
			'Mr Bedfort',
			'Mr Dafoe',
			'Mr De Haviland',
			'Mrs Saint Delafield',
			'Mrs Sheppards',
			'Muli',
			'Mystery Quest',
			'NTR',
			'Neucha',
			'Neuton',
			'New Rocker',
			'News Cycle',
			'Niconne',
			'Nixie One',
			'Nobile',
			'Nokora',
			'Norican',
			'Nosifer',
			'Nothing You Could Do',
			'Noticia Text',
			'Noto Sans',
			'Noto Serif',
			'Nova Cut',
			'Nova Flat',
			'Nova Mono',
			'Nova Oval',
			'Nova Round',
			'Nova Script',
			'Nova Slim',
			'Nova Square',
			'Numans',
			'Nunito',
			'Odor Mean Chey',
			'Offside',
			'Old Standard TT',
			'Oldenburg',
			'Oleo Script',
			'Oleo Script Swash Caps',
			'Open Sans',
			'Oranienbaum',
			'Orbitron',
			'Oregano',
			'Orienta',
			'Original Surfer',
			'Oswald',
			'Over the Rainbow',
			'Overlock',
			'Overlock SC',
			'Ovo',
			'Oxygen',
			'Oxygen Mono',
			'PT Mono',
			'PT Sans',
			'PT Sans Caption',
			'PT Sans Narrow',
			'PT Serif',
			'PT Serif Caption',
			'Pacifico',
			'Palanquin',
			'Palanquin Dark',
			'Paprika',
			'Parisienne',
			'Passero One',
			'Passion One',
			'Pathway Gothic One',
			'Patrick Hand',
			'Patrick Hand SC',
			'Patua One',
			'Paytone One',
			'Peddana',
			'Peralta',
			'Permanent Marker',
			'Petit Formal Script',
			'Petrona',
			'Philosopher',
			'Piedra',
			'Pinyon Script',
			'Pirata One',
			'Plaster',
			'Play',
			'Playball',
			'Playfair Display',
			'Playfair Display SC',
			'Podkova',
			'Poiret One',
			'Poller One',
			'Poly',
			'Pompiere',
			'Pontano Sans',
			'Port Lligat Sans',
			'Port Lligat Slab',
			'Pragati Narrow',
			'Prata',
			'Preahvihear',
			'Press Start 2P',
			'Princess Sofia',
			'Prociono',
			'Prosto One',
			'Puritan',
			'Purple Purse',
			'Quando',
			'Quantico',
			'Quattrocento',
			'Quattrocento Sans',
			'Questrial',
			'Quicksand',
			'Quintessential',
			'Qwigley',
			'Racing Sans One',
			'Radley',
			'Rajdhani',
			'Raleway',
			'Raleway Dots',
			'Ramabhadra',
			'Ramaraja',
			'Rambla',
			'Rammetto One',
			'Ranchers',
			'Rancho',
			'Ranga',
			'Rationale',
			'Ravi Prakash',
			'Redressed',
			'Reenie Beanie',
			'Revalia',
			'Ribeye',
			'Ribeye Marrow',
			'Righteous',
			'Risque',
			'Roboto',
			'Roboto Condensed',
			'Roboto Slab',
			'Rochester',
			'Rock Salt',
			'Rokkitt',
			'Romanesco',
			'Ropa Sans',
			'Rosario',
			'Rosarivo',
			'Rouge Script',
			'Rozha One',
			'Rubik Mono One',
			'Rubik One',
			'Ruda',
			'Rufina',
			'Ruge Boogie',
			'Ruluko',
			'Rum Raisin',
			'Ruslan Display',
			'Russo One',
			'Ruthie',
			'Rye',
			'Sacramento',
			'Sail',
			'Salsa',
			'Sanchez',
			'Sancreek',
			'Sansita One',
			'Sarina',
			'Sarpanch',
			'Satisfy',
			'Scada',
			'Scheherazade',
			'Schoolbell',
			'Seaweed Script',
			'Sevillana',
			'Seymour One',
			'Shadows Into Light',
			'Shadows Into Light Two',
			'Shanti',
			'Share',
			'Share Tech',
			'Share Tech Mono',
			'Shojumaru',
			'Short Stack',
			'Siemreap',
			'Sigmar One',
			'Signika',
			'Signika Negative',
			'Simonetta',
			'Sintony',
			'Sirin Stencil',
			'Six Caps',
			'Skranji',
			'Slabo 13px',
			'Slabo 27px',
			'Slackey',
			'Smokum',
			'Smythe',
			'Sniglet',
			'Snippet',
			'Snowburst One',
			'Sofadi One',
			'Sofia',
			'Sonsie One',
			'Sorts Mill Goudy',
			'Source Code Pro',
			'Source Sans Pro',
			'Source Serif Pro',
			'Special Elite',
			'Spicy Rice',
			'Spinnaker',
			'Spirax',
			'Squada One',
			'Sree Krushnadevaraya',
			'Stalemate',
			'Stalinist One',
			'Stardos Stencil',
			'Stint Ultra Condensed',
			'Stint Ultra Expanded',
			'Stoke',
			'Strait',
			'Sue Ellen Francisco',
			'Sumana',
			'Sunshiney',
			'Supermercado One',
			'Suranna',
			'Suravaram',
			'Suwannaphum',
			'Swanky and Moo Moo',
			'Syncopate',
			'Tangerine',
			'Taprom',
			'Tauri',
			'Teko',
			'Telex',
			'Tenali Ramakrishna',
			'Tenor Sans',
			'Text Me One',
			'The Girl Next Door',
			'Tienne',
			'Timmana',
			'Tinos',
			'Titan One',
			'Titillium Web',
			'Trade Winds',
			'Trocchi',
			'Trochut',
			'Trykker',
			'Tulpen One',
			'Ubuntu',
			'Ubuntu Condensed',
			'Ubuntu Mono',
			'Ultra',
			'Uncial Antiqua',
			'Underdog',
			'Unica One',
			'UnifrakturMaguntia',
			'Unkempt',
			'Unlock',
			'Unna',
			'VT323',
			'Vampiro One',
			'Varela',
			'Varela Round',
			'Vast Shadow',
			'Vesper Libre',
			'Vibur',
			'Vidaloka',
			'Viga',
			'Voces',
			'Volkhov',
			'Vollkorn',
			'Voltaire',
			'Waiting for the Sunrise',
			'Wallpoet',
			'Walter Turncoat',
			'Warnes',
			'Wellfleet',
			'Wendy One',
			'Wire One',
			'Yanone Kaffeesatz',
			'Yellowtail',
			'Yeseva One',
			'Yesteryear',
			'Zeyada',
		);
	}

	public function getStandardFontsList() {
		return array(
			'Georgia',
			'Palatino Linotype',
			'Times New Roman',
			'Arial',
			'Helvetica',
			'Arial Black',
			'Gadget',
			'Comic Sans MS',
			'Impact',
			'Charcoal',
			'Lucida Sans Unicode',
			'Lucida Grande',
			'Tahoma',
			'Geneva',
			'Trebuchet MS',
			'Verdana',
			'Geneva',
			'Courier New',
			'Courier',
			'Lucida Console',
			'Monaco',
		);
	}

	public function getAllFontsList() {
		$fontsList = array_merge( $this->getFontsList(), $this->getStandardFontsList() );
		natsort( $fontsList );
		array_unshift( $fontsList, $this->defaultFont );
		$options = array();
		foreach ( $fontsList as $font ) {
			$options[ $font ] = $font;
		}

		return $options;
	}

	public function getFontStyles() {
		return array( '' => '', 'n' => 'normal', 'b' => 'bold', 'i' => 'italic', 'bi' => 'bold + italic' );
	}

	public function getBorderStyles() {
		return array( '' => '', 'solid' => 'solid', 'dashed' => 'dashed', 'dotted' => 'dotted', 'double' => 'double' );
	}

	public function getDefaultFloatingIcon() {
		return 'background-image:url(' . $this->getModPath() . 'img/filter.jpg);width:32px;height:27px;';
	}

	/**
	 * Extend free version logic for filter.
	 *
	 * @return array
	 */
	public function getAttrFilterLogicPro( $logic ) {
		$logic['display']['not_in']  = 'Not In';
		$logic['loop']['not_in']     = 'NOT IN';
		$logic['delimetr']['not_in'] = ';';

		return $logic;
	}

	/**
	 * Get options filter variant list with collapsible option activated.
	 *
	 * @return array
	 */
	public function getCollapsibleFiltreOptions( $obj = '' ) {

		$collapsible = array(
			'multi',
			'list',
		);
		if ('wpfAttribute' == $obj) {
			$collapsible = array_merge($collapsible, array('radio', 'switch'));
		}

		return $collapsible;
	}

	public function getIconHtml( $html, $styles, $settings ) {
		$sign  = ( isset( $settings['settings']['styles'] ) ) ? $this->getFilterSetting( $settings['settings']['styles'], $styles ) : '';
		$class = ( '' === $sign ) ? 'plus' : $sign . '-down';
		if ( '+' === $html ) {
			$html = '<i class="fa fa-' . $class . '"></i>';
		} else {
			if ( 'fa-minus' === $html ) {
				$class = ( '' === $sign ) ? 'minus' : $sign . '-up';
			}
			$html = 'fa-' . $class;
		}

		return $html;
	}

	private function sortAs( $terms, $order, $numbers = false ) {
		$_terms = array();
		if ( is_array( $terms ) ) {
			$temp = array();
			foreach ( $terms as $key => $value ) {
				$temp[ $key ] = ( is_object( $value ) ) ? $value->name : $value;
			}
			if ( $numbers ) {
				natcasesort( $temp );
			} else {
				asort( $temp );
			}
			if ( 'desc' === $order ) {
				$temp = array_reverse( $temp, true );
			}
			foreach ( $temp as $key => $name ) {
				$_terms[ $key ] = $terms[ $key ];
			}
		}

		return $_terms;
	}

	public function sortAsNumbers( $terms, $order ) {
		return $this->sortAs( $terms, $order, true );
	}

	public function sortAsStrings( $terms, $order ) {
		return $this->sortAs( $terms, $order );
	}

	public function addAjaxCustomMetaQueryPro( $metaQuery, $setting, $filterSettings = array() ) {
		if ( empty( $setting['settings'] ) ) {
			return $metaQuery;
		}
		$woofilterModule = FrameWpf::_()->getModule( 'woofilters' );
		$clauses         = array( 'join' => array(), 'where' => array() );

		if ( 'wpfAttribute' === $setting['id'] || 'wpfSearchNumber' === $setting['id'] ) {
			$terms = $setting['settings'];
			if ( empty( $terms ) ) {
				return $metaQuery;
			}
			$urlParam = $setting['name'];
			$searchNumberLogic = false;
			if ('wpfSearchNumber' === $setting['id']) {
				foreach ($this->searchNumberSuffix as $logic => $str) {
					if ( 0 < strpos( $urlParam, $logic )) {
						$clearKey = str_replace( $logic, '', $urlParam );
						if ($clearKey . $logic == $urlParam) {
							if ('' === $terms['value']) {
								return $metaQuery;
							}
							$searchNumberLogic = $str;
							$urlParam = $clearKey;
							$setting['name'] = $urlParam;
							break;
						}
					}
				}
			}

			$keyId    = false;
			if ( strpos( $setting['name'], $this->acf_prefix ) === 0 ) {
				$key   = str_replace( $this->acf_prefix, '', $setting['name'] );
				$keyId = $woofilterModule->getMetaKeyId( $key );
				if ( empty($keyId) ) {
					$key = preg_replace( '/_\d+$/', '', $key );
					$keyId = $woofilterModule->getMetaKeyId( $key );
				}
			} elseif ( strpos( $setting['name'], $this->meta_prefix ) === 0 ) {
				$key   = str_replace( $this->meta_prefix, '', $setting['name'] );
				$keyId = $woofilterModule->getMetaKeyId( $key );
				if ( ! empty( $filterSettings['filtering_by_variations'] ) ) {
					$keyIdVar = $woofilterModule->getMetaKeyId( $key . FrameWpf::_()->getModule( 'meta' )->getModel()->metaVarSuf );
					if ( $keyIdVar ) {
						$keyId = array( $keyId, $keyIdVar );
					}
				}
			} elseif ( strpos( $setting['name'], $this->local_prefix ) === 0 ) {
				$key   = str_replace( $this->local_prefix, '', $setting['name'] );
				$keyId = $woofilterModule->getMetaKeyId( '_product_attributes' );
			}
			if ( $keyId ) {
				if ($searchNumberLogic) {
					$isAnd = true;
					$terms = array((int) $terms['value']);
				} else {
					$isAnd   = ! isset( $setting['logic'] ) || ( 'or' != $setting['logic'] );
				}
				$clauses = $woofilterModule->addWpfMetaClauses( array(
					'keyId'    => $keyId,
					'isAnd'    => $isAnd,
					'values'   => $terms,
					'field'    => $woofilterModule->getMetaKeyId( $key, 'field' ),
					'isLight'  => false,
					'urlParam' => $urlParam,
					'searchLogic' => $searchNumberLogic,
					'keyName' => $key,
				) );
			}

		}

		if ( 'wpfSearchText' == $setting['id'] ) {
			$text     = $setting['settings']['value'];
			$attr     = $setting['settings']['attribute'];
			$excluded = $setting['settings']['excluded'];

			if ( $excluded ) {
				FrameWpf::_()->getModule( 'woofilters' )->addFilterClauses( array( 'where' => array( $this->getSearchTextExcludedSQL( $excluded ) ) ), false );
			}
		}

		$woofilterModule->setFilterClauses();

		return $metaQuery;
	}

	public function addCustomMetaQueryPro( $metaQuery, $data, $mode ) {
		global $wpdb;
		$woofilterModule = FrameWpf::_()->getModule( 'woofilters' );
		$i = 0;

		foreach ( $data as $key => $param ) {
			$urlParam   = $key;
			$meta       = array();
			$changeToId = false;

			if ( '' === $param ) {
				continue;
			}

			$searchNumberLogic = false;
			foreach ($this->searchNumberSuffix as $logic => $str) {
				if ( 0 < strpos( $key, $logic )) {
					$clearKey = str_replace( $logic, '', $key );
					if ($clearKey . $logic == $key) {
						$searchNumberLogic = $str;
						$key = $clearKey;
						break;
					}
				}
			}


			$keyId = false;
			if ( 0 === strpos( $key, $this->acf_prefix ) ) {
				$key        = str_replace( $this->acf_prefix, '', $key );
				$keyId      = $woofilterModule->getMetaKeyId( $key );
				if ( empty($keyId) ) {
					$key = preg_replace( '/_\d+$/', '', $key );
					$keyId = $woofilterModule->getMetaKeyId( $key );
				}
				$changeToId = true;
			} elseif ( 0 === strpos( $key, $this->meta_prefix ) ) {
				$key   = str_replace( $this->meta_prefix, '', $key );
				$keyId = $woofilterModule->getMetaKeyId( $key );
				if ( key_exists( 'wpf_fbv', $data ) ) {
					$keyIdVar = $woofilterModule->getMetaKeyId( $key . FrameWpf::_()->getModule( 'meta' )->getModel()->metaVarSuf );
					if ( $keyIdVar ) {
						$keyId = array( $keyId, $keyIdVar );
					}
				}

			} elseif ( 0 === strpos( $key, $this->local_prefix ) ) {
				$key   = str_replace( $this->local_prefix, '', $key );
				$key   = preg_replace( '/_\d{1,}/', '', $key );
				$keyId = $woofilterModule->getMetaKeyId( '_product_attributes' );
			}

			if ( $keyId ) {
				if ($searchNumberLogic) {
					$isAnd = true;
					$idsAnd = array((int) $param);
				} else {

					$idsAnd = explode( ',', $param );
					$idsOr  = explode( '|', $param );
					$isAnd  = count( $idsAnd ) > count( $idsOr );

					if ( $changeToId ) {
						$ids = ( $isAnd ) ? $isAnd : $idsOr;

						foreach ( $ids as $index => $value ) {
							if ( ! is_numeric( $value ) ) {
								$ids[ $index ] = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueId( $keyId, $value );
							}
						}

						if ( $isAnd ) {
							$isAnd = $ids;
						} else {
							$idsOr = $ids;
						}
					}
				}

				$woofilterModule->addWpfMetaClauses( array(
					'keyId'    => $keyId,
					'isAnd'    => $isAnd,
					'values'   => $isAnd ? $idsAnd : $idsOr,
					'field'    => $woofilterModule->getMetaKeyId( $key, 'field' ),
					'isLight'  => 'preselect' === $mode,
					'urlParam' => $urlParam,
					'searchLogic'  => $searchNumberLogic,
					'keyName' => $key,
				) );
			}

			if ( count( $meta ) > 0 ) {
				$metaQuery = array_merge( $metaQuery, $meta );
			}

		}

		$woofilterModule->setFilterClauses();

		return $metaQuery;
	}

	public function addVariationQueryPro( $clauses, $urlQuery ) {
		$vars = ( ! empty( $urlQuery ) ) ? $urlQuery : ReqWpf::get( 'get' );

		$i = empty( $clauses['i'] ) ? 0 : $clauses['i'];

		if ( ! empty( $vars ) ) {
			global $wpdb;
			$woofilterModule = FrameWpf::_()->getModule( 'woofilters' );
			$whAnd           = empty( $clauses['whAnd'] ) ? ' AND ' : $clauses['whAnd'];
			$modelMetaValues = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' );
			$metaDataTable   = DbWpf::getTableName( 'meta_data' );
			$woofilters      = FrameWpf::_()->getModule( 'woofilters' );

			foreach ( $vars as $key => $param ) {

				if ( empty( $param ) ) {
					continue;
				}

				$taxonomy = $woofilters->getTaxonomyByUrl( $key );

				if ( is_null( $taxonomy ) ) {
					continue;
				}

				$where[ $taxonomy ]  = array();
				$join[ $taxonomy ]   = array();
				$having[ $taxonomy ] = array();

				if ( 0 === strpos( $key, $this->meta_prefix ) ) {
					$key   = str_replace( $this->meta_prefix, '', $key );
					$keyId = $woofilterModule->getMetaKeyId( $key );
					if ( $keyId ) {
						$field  = $woofilterModule->getMetaKeyId( $key, 'field' );
						$idsAnd = explode( ',', $param );
						$idsOr  = explode( '|', $param );
						$isAnd  = count( $idsAnd ) > count( $idsOr );
						$values = UtilsWpf::controlNumericValues( $isAnd ? $idsAnd : $idsOr, $field );

						foreach ( $values as $val ) {
							$i++;
							$join[ $taxonomy ][]  = ' LEFT JOIN ' . DbWpf::getTableName( 'meta_data' ) . ' AS md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $keyId . ')';
							$where[ $taxonomy ][] = ( empty( $where[ $taxonomy ] ) ? '' : ' AND ' ) . ' md' . $i . '.val_' . $field . ( $isAnd ? '=' . $val : ' IN (' . implode( ',', $values ) . ')' );

							if ( ! $isAnd ) {
								break;
							}

						}

					}
				} elseif ( false !== strpos( $key, $this->local_prefix ) ) {
					$pos       = strpos( $key, $this->local_prefix );
					$notPrefix = 'pr_' . $this->local_prefix;
					$prefix    = ( 0 === $pos ? $this->local_prefix : ( 0 === strpos( $key, $notPrefix ) ? $notPrefix : '' ) );

					if ( empty( $prefix ) ) {
						continue;
					}

					$key     = str_replace( $prefix, '', $key );
					$key     = preg_replace( '/_\d{1,}/', '', $key );
					$keyId   = $woofilterModule->getMetaKeyId( '_product_attributes' );
					$localId = $woofilterModule->getMetaKeyId( 'attribute_' . $key );

					if ( $keyId && $localId ) {
						$isNot = $notPrefix === $prefix;

						if ( $isNot ) {
							$vals  = explode( ';', $param );
							$isAnd = false;
						} else {
							$idsAnd = explode( ',', $param );
							$idsOr  = explode( '|', $param );
							$isAnd  = count( $idsAnd ) > count( $idsOr );
							$vals   = $isAnd ? $idsAnd : $idsOr;
						}

						$values   = $modelMetaValues->getFieldValuesList( $keyId, 'value', array( 'ids' => $vals ), true );
						$valueIds = $modelMetaValues->getMetaValueIds( $localId, array_merge( $values, array( '' ) ) );

						if ( ! empty( $valueIds ) ) {
							$leerId = $modelMetaValues->getMetaValueId( $localId, '' );
							$i++;

							if ( $isAnd ) {
								$join[ $taxonomy ][]   = ' LEFT JOIN ' . $metaDataTable . ' md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $localId . ' AND md' . $i . '.val_id=' . $leerId . ')';
								$having[ $taxonomy ][] = ' (count(DISTINCT md' . $i . '.val_id) > 0';

								$i++;
								$join[ $taxonomy ][]   = ' LEFT JOIN ' . $metaDataTable . ' md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $localId . ' AND md' . $i . '.val_id IN (' . implode( ',', $valueIds ) . '))';
								$having[ $taxonomy ][] = ' OR count(DISTINCT md' . $i . '.val_id)>=' . count( $valueIds ) . ')';

							} else {
								$valueIds[]          = $leerId;
								$join[ $taxonomy ][] = ' LEFT JOIN ' . $metaDataTable . ' md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $localId . ')';

								$where[ $taxonomy ][] = ' md' . $i . '.val_id' . ( $isNot ? ' NOT' : '' ) . ' IN (' . implode( ',', $valueIds ) . ')';
							}
						}

						if ( $isNot ) {
							$clauses['whereNot'] .= ( empty( $clauses['$whereNot'] ) ? '' : $whAnd ) .
													$wpdb->posts . '.ID NOT IN (SELECT product_id FROM ' . DbWpf::getTableName( 'meta_data' ) . ' WHERE key_id=' . $localId . ' AND val_id IN (' . implode( ',', $values ) . '))';
						}
					}
				}

				if ( ! empty( $join[ $taxonomy ] ) ) {
					$sql              = implode( '', $join[ $taxonomy ] );
					$clauses['join'] .= $sql;
					$woofilters->clausesByParam['variation']['conditions'][ $taxonomy ]['join'][] = $sql;
				}

				if ( ! empty( $where[ $taxonomy ] ) ) {
					$sql               = implode( '', $where[ $taxonomy ] );
					$clauses['where'] .= ( empty( $clauses['where'] ) ? '' : $whAnd ) . $sql;
					$woofilters->clausesByParam['variation']['conditions'][ $taxonomy ]['where'][] = $sql;
				}

				if ( ! empty( $having[ $taxonomy ] ) ) {
					$sql                = ( empty( $clauses['having'] ) ? '' : $whAnd ) . implode( '', $having[ $taxonomy ] );
					$clauses['having'] .= $sql;
					$woofilters->clausesByParam['variation']['conditions'][ $taxonomy ]['having'][] = $sql;
				}

			}
		}
		$clauses['i'] = $i;

		return $clauses;
	}

	public function getFilterDefault( $data, $fieldName ) {
		$filterId = FrameWpf::_()->getModule( 'woofilters' )->currentFilterId;
		if ( ! is_null( $filterId ) && isset( $this->defaultFilterData[ $filterId ] ) ) {
			return $this->getFilterSetting( $this->defaultFilterData[ $filterId ], $fieldName );
		}

		return $data;
	}

	public function getDefaultFilterParams( $params ) {
		$defaults = array();
		$filterId = FrameWpf::_()->getModule( 'woofilters' )->currentFilterId;
		if ( is_null( $filterId ) ) {
			foreach ( $this->defaultFilterData as $data ) {
				$defaults = array_merge( $defaults, $data );
			}
		} elseif ( isset( $this->defaultFilterData[ $filterId ] ) ) {
			$defaults = $this->defaultFilterData[ $filterId ];
		}

		return array_merge( $params, $defaults );
	}

	public function addDefaultFilterData( $filterId, $settings ) {
		if ( empty( $settings ) ) {
			$filter = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( $filterId );
			if ( $filter ) {
				$settings = unserialize( $filter['setting_data'] );
			}
		}
		if ( ! empty( $settings ) && ! isset( $this->defaultFilterData[ $filterId ] ) ) {
			$defaults = array();
			$getGet   = ReqWpf::get( 'get' );
			if ( count( $getGet ) === 0 || !FrameWpf::_()->getModule( 'woofilters' )->isFiltered(false) ) {
				$def = ! empty( $settings['settings']['filters']['defaults'] ) ? $settings['settings']['filters']['defaults'] : '';
				if ( ! empty( $def ) ) {
					$def = explode( ';', $def );
					foreach ( $def as $value ) {
						if ( ! empty( $value ) ) {
							$paar = explode( '=', $value );
							if ( count( $paar ) === 2 ) {
								$defaults[ $paar[0] ] = $paar[1];
							}
						}
					}
				}
			}
			$this->defaultFilterData[ $filterId ] = $defaults;
		}
	}

	/**
	 * Returns a string with a pro attribute.
	 *
	 * @param string attribute string
	 * @param array $settings array of settings
	 *
	 * @return string attribute string
	 */
	public function getProAttributes( $proAttributes, $settings ) {

		// key = name of the option that depends on, value = option name
		$options = array(
			'redirect_after_select' => 'redirect_page_url',
		);
		foreach ( $options as $key => $option ) {
			$value = null;
			switch ( $option ) {
				case 'redirect_page_url':
					$dependenceAllow = $this->getFilterSetting( $settings, $key, false );
					if ( $dependenceAllow ) {
						$value = get_permalink( $this->getFilterSetting( $settings, $option, '' ) );
					}
					break;
				default:
					$value = $this->getFilterSetting( $settings, $option, '' );
					break;
			}
			if ( ! is_null( $value ) ) {
				$attrName       = ' data-' . str_replace( '_', '-', $option );
				$proAttributes .= $attrName . ' = "' . $value . '"';
			}
		}
		$filterModule = FrameWpf::_()->getModule('woofilters');
		$id = $filterModule->currentFilterId;
		$statisticsModule = FrameWpf::_()->getModule('statistics');

		if (!is_null($id) && $statisticsModule && !is_null($statisticsModule) && $statisticsModule->getModel()->isEnableStatistics($id)) {
			$proAttributes .= ' data-is-stats="' . 1 . '" data-page="' . $filterModule->getView()->wpfGetPageId() . '" data-user-id="' . get_current_user_id() . '"';
		}

		return $proAttributes;
	}

	public function getDecimal( $args, $settings ) {
		$dataStep = $this->getFilterSetting( $settings, 'f_skin_step', 1, true );
		if ( $dataStep <= 0 ) {
			$dataStep = 1;
		}

		if ( class_exists( 'frameWcu' ) ) {
			$currencySwitcher = frameWcu::_()->getModule( 'currency' );
			if ( isset( $currencySwitcher ) ) {
				$currentCurrency    = $currencySwitcher->getCurrentCurrency();
				$cryptoCurrencyList = $currencySwitcher->getCryptoCurrencyList();
				if ( array_key_exists( $currentCurrency, $cryptoCurrencyList ) ) {
					$dataStep = '0.001';
				}
			}
		}


		$punkt = strpos( $dataStep, '.' );
		$args  = array( ( false === $punkt ? 0 : strlen( $dataStep ) - 1 - $punkt ), $dataStep );

		return $args;
	}

	public function productLoopStart( $html, $settings = array(), $getGet = array() ) {
		$displayDescription = false;

		if ( isset( $settings[0] ) ) {
			foreach ( $settings as $order ) {
				if ( ! $displayDescription && 'wpfPerfectBrand' === $order['id'] ) {
					$displayDescription = $this->getFilterSetting( $order['settings'], 'f_display_description', false );
				}
			}
		} else {
			$displayDescription = $this->getFilterSetting( $settings, 'f_display_description', false );
		}

		if ( $displayDescription ) {
			$id = null;

			if ( empty( $getGet ) ) {
				$getGet = ReqWpf::get( 'get' );
			}

			foreach ( $getGet as $key => $value ) {
				if ( strpos( $key, 'wpf_filter_pwb' ) !== false && strpos( $value, '|' ) === false ) {
					$id = ( ! is_null( $id ) && $id !== $value ) ? null : $value;
				}
			}

			if ( ! is_null( $id ) ) {
				$brand = get_term( $id, 'pwb-brand' );
				if ( isset( $brand->description ) && '' !== $brand->description ) {
					$html = $brand->description;
				}
			}
		}

		return $html;
	}

	public function priceTax( $value, $mode, $settings = array() ) {
		switch ( $mode ) {
			case 'subtract':
				$tax = (float) ReqWpf::getVar( 'tax' );
				if ( ! $tax ) {
					$params = ReqWpf::get( 'post' );
					if ( isset( $params['currenturl'] ) ) {
						$parts = parse_url( $params['currenturl'] );
						if ( isset( $parts['query'] ) ) {
							parse_str( $parts['query'], $urlQuery );
							if ( isset( $urlQuery['tax'] ) ) {
								$tax = (float) $urlQuery['tax'];
							}
						}
					}
				}
				if ( $tax ) {
					foreach ( $value as $key => $item ) {
						$value[ $key ] = round( $item * 100 / ( 100 + $tax ), 3 );
					}
				}
				break;
			case 'add':
				$tax = (float) $this->getFilterSetting( $settings, 'f_set_tax_rates', '' );
				if ( $tax ) {
					$value[0] = $tax;
					$value[1] = '*' . ( 1 + $tax / 100 );
				}
				break;
		}

		return $value;
	}

	public function addCustomMetaKeys( $metaKeys, $filters ) {
		foreach ( $filters as $filter ) {
			$filterId = $this->getFilterSetting( $filter, 'id' );
			if ( 'wpfAttribute' == $filterId || 'wpfSearchNumber' == $filterId ) {
				$settings = $this->getFilterSetting( $filter, 'settings' );
				$fList    = $this->getFilterSetting( $settings, 'f_list' );
				if ( 'custom_meta_field_check' == $fList ) {
					$key = $this->getFilterSetting( $settings, 'f_custom_meta_field' );
					if ( ! empty( $key ) && !in_array($key, $metaKeys)) {
						$metaKeys[] = $key;
						$metaKeys[] = $key . FrameWpf::_()->getModule( 'meta' )->getModel()->metaVarSuf;
					}
				} elseif ( strpos( $fList, 'acf-' ) === 0 ) {
					$metaKeys[] = substr( $fList, 4 );
				}
			}
		}

		return $metaKeys;
	}

	public function getColorGroup( $ids, $param, $filterId ) {

		if ( ! is_array( $ids ) || ! is_string( $param ) || ! is_numeric( $filterId ) ) {
			return $ids;
		}

		$parentId = ( ! empty( $ids ) ) ? $ids[0] : null;


		preg_match( '/groupwpf__(.*?)(?:_\d+)?_([^_]+?)$/', $param, $matches );
		if ( ! isset( $matches[2] ) ) {
			return $ids;
		}

		$filters = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'settings' )->getFilterSettings( 'wpfAttribute', array(), array(), $filterId );
		if ( ! is_array( $filters ) ) {
			return $ids;
		}

		$taxonomy = 'pa_' . $matches[1];
		$uniqId   = 'wpf_' . $matches[2];

		foreach ( $filters as $filter ) {
			if ( $uniqId === $filter['uniqId'] ) {
				if ( ! is_null( $parentId ) ) {
					if ( ! isset( $filter['settings']["f_cglist[{$parentId}][]"] ) ) {
						return $ids;
					}

					$value = $filter['settings']["f_cglist[{$parentId}][]"];
					if ( '' !== $value ) {
						$children = explode( ',', $value );

						return array_merge( $ids, $children );
					}
				} else {
					$keysAll        = array_keys( $filter['settings'] );
					$keysColorGroup = preg_grep( '/^f_cglist\[\d+\]\[\]$/', $keysAll );
					foreach ( $keysColorGroup as $key ) {
						$value = $filter['settings'][ $key ];
						if ( '' !== $value ) {
							preg_match( '/^f_cglist\[(\d+)\]/', $key, $parent );
							if ( isset( $parent[1] ) ) {
								$ids[ $taxonomy ][ $parent[1] ] = explode( ',', $value );
							}
						}
					}
				}
			}
		}

		return $ids;
	}

	public function excludeColorChildren( $excludeIds, $settings ) {
		$keysAll        = array_keys( $settings );
		$keysColorGroup = preg_grep( '/^f_cglist\[\d+\]\[\]$/', $keysAll );

		foreach ( $keysColorGroup as $key ) {
			$value = $settings[ $key ];
			if ( '' !== $value ) {
				$children = explode( ',', $value );
				foreach ( $children as $id ) {
					if (!is_array($excludeIds)) {
						$excludeIds = array();
					}
					if ( ! in_array( $id, $excludeIds, true ) ) {
						$excludeIds[] = $id;
					}
				}
			}
		}

		return $excludeIds;
	}

	public function getColorGroupForExistTerms( $colorGroup, $param ) {

		if ( !empty( $param['generalSettings'] ) ) {
			$filters = $param['generalSettings'];

			foreach ( $filters as $filter ) {
				$settings = $filter['settings'];

				if ( isset( $settings['f_frontend_type'] ) && 'colors' === $settings['f_frontend_type'] ) {
					$keysAll        = array_keys( $settings );
					$keysColorGroup = preg_grep( '/^f_cglist\[\d+\]\[\]$/', $keysAll );
					$taxonomy       = str_replace( 'wpf_filter_', 'pa_', $filter['name'] );

					foreach ( $keysColorGroup as $key ) {
						$value = $settings[ $key ];

						if ( '' !== $value ) {
							preg_match( '/^f_cglist\[(\d+)\]/', $key, $parent );

							if ( isset( $parent[1] ) ) {
								$colorGroup[ $taxonomy ][ $parent[1] ] = explode( ',', $value );
							}

						}

					}

				}

			}
		}

		return $colorGroup;
	}

	public function getExistTermsColor( $existTerms, $colorGroup, $termProducts ) {

		if ( ! empty( $colorGroup ) && ! empty( $termProducts ) ) {

			foreach ( $colorGroup as $taxonomy => $ids ) {

				foreach ( $termProducts as $product ) {
					$temp[ $product['taxonomy'] ][ $product['term_id'] ][] = $product['ID'];
				}

				foreach ( $ids as $parent => $children ) {
					$products = ( isset( $temp[ $taxonomy ][ $parent ] ) ) ? $temp[ $taxonomy ][ $parent ] : array();

					foreach ( $children as $child ) {

						if ( isset( $temp[ $taxonomy ][ $child ] ) ) {
							$products = array_merge( $products, $temp[ $taxonomy ][ $child ] );
						}

					}
					$cnt = count( array_unique( $products ) );
					if ($cnt > 0) {
						$existTerms[ $taxonomy ][ $parent ] = $cnt;
					}
				}
			}
		}

		return $existTerms;
	}

	public function getOneByOneCategoryHierarchy( $onlyCatChilds, $urlQuery, $filterSettings ) {
		if ( $this->getFilterSetting( $filterSettings, 'open_one_by_one', '0' ) == 1 && $this->getFilterSetting( $filterSettings, 'obo_only_children', '0' ) == 1 ) {
			$urlIds = array();
			foreach ( $urlQuery as $key => $value ) {
				if ( 0 === strpos( $key, 'wpf_filter_cat_' ) ) {
					$idsAnd = explode( ',', $value );
					$idsOr = explode( '|', $value );
					$ids = count( $idsAnd ) > count( $idsOr ) ? $idsAnd : $idsOr;
					$field = ( substr( $key, - 1 ) == 's' ? 'slug' : 'id' );
					$only = array();
					foreach ($ids as $id) {
						$term = get_term_by( $field, $id, 'product_cat' );
						if ( $term ) {
							$only = array_merge($only, get_term_children( $term->term_id, 'product_cat' ));
							$urlIds[] = $term->term_id;
						}
					}
					$onlyCatChilds[] = $only;
				}
			}
			if (!empty($urlIds)) {
				foreach ($onlyCatChilds as $k => $ids) {
					$onlyCatChilds[$k] = array_merge($onlyCatChilds[$k], $urlIds);
				}
			}
		}
		return $onlyCatChilds;
	}

	public function addCustomTaxQueryPro( $taxQuery, $data, $mode ) {
		foreach ( $data as $key => $param ) {
			if ( strpos( $key, 'wpf_filter_' ) === 0 ) {
				if ( ! empty( $param ) && is_numeric($param) ) {
					$searchNumberLogic = false;
					foreach ($this->searchNumberSuffix as $logic => $str) {
						$pos = strpos( $key, $logic );
						if ( 0 < $pos ) {
							$clearKey = str_replace( $logic, '', $key );
							$withAddAttrs = false;
							$clearKey = str_replace( $logic, '', $key );
							if ($clearKey . $logic == $key) {
								$searchNumberLogic = $str;
								$key = $clearKey;
								break;
							} else {
								$end = substr($key, $pos + strlen($logic));
								$ids = explode('_', $end);
								if (count($ids) == 3 && empty($ids[0]) && is_numeric($ids[1])  && is_numeric($ids[2])) {
									$searchNumberLogic = $str;
									$key = substr($key, 0, $pos);
									$withAddAttrs = true;
									$fid = (int) $ids[1];
									$oid = (int) $ids[2];
									break;
								}
							}
						}
					}
					if ($searchNumberLogic) {
						$taxonomies = array();
						$taxonomy = str_replace( 'wpf_filter_', '', $key );
						$taxonomies[] = $taxonomy;
						$taxonomies[] = 'pa_' . $taxonomy;
						$taxExists = false;
						$taxs = array();

						foreach ( $taxonomies as $taxonomy ) {
							$taxExists = taxonomy_exists( $taxonomy );
							if ( $taxExists ) {
								$taxs[] = $taxonomy;
								break;
							}
						}
						if ( $taxExists ) {
							if ($withAddAttrs) {
								$filterSettings = FrameWpf::_()->getModule( 'woofilters' )->getModel( 'settings' )->getFilterSettings( 'wpfSearchNumber', array(), array(), $fid, $oid);
								if ( is_array( $filterSettings ) && isset( $filterSettings[0]['settings'] ) ) {
									$settings = $filterSettings[0]['settings'];
									if ($this->getFilterSetting($settings, 'f_multi_attributes', 0) == '1') {
										$addAttsIds = !empty($settings['f_additional_attributes_list[]']) ? explode(',', $settings['f_additional_attributes_list[]']) : false;
										if (is_array($addAttsIds) && count($addAttsIds) > 0) {
											foreach ($addAttsIds as $id) {
												$attrName = wc_attribute_taxonomy_name_by_id((int) $id);
												if ($attrName) {
													$taxs[] = $attrName;
												}
											}
										}
									}
								}
							}
							$value = (int) $param;
							$taxQ = array('relation' => 'OR');
							$first = true;
							foreach ($taxs as $taxonomy) {
								$attrIds = array();
								$terms = get_terms(array('taxonomy' => $taxonomy, 'fields' => 'all', 'hide_empty' => true));
								if (is_array($terms)) {
									foreach ($terms as $term) {
										$v = (int) $term->name;
										switch ($searchNumberLogic) {
											case '>=':
												if ($v >= $value) {
													$attrIds[] = $term->term_id;
												}
												break;
											case '<=':
												if ($v <= $value) {
													$attrIds[] = $term->term_id;
												}
												break;
											case '>':
												if ($v > $value) {
													$attrIds[] = $term->term_id;
												}
												break;
											case '<':
												if ($v < $value) {
													$attrIds[] = $term->term_id;
												}
												break;
											case '=':
												if ($v == $value) {
													$attrIds[] = $term->term_id;
												}
												break;
										}
									}
									$found = false;
									if (count($attrIds) == 0) {
										$attrIds[] = 0;
									} else {
										$found = true;
									}
									if ($first || $found) {
										$taxQ[] = array(
											'taxonomy' => $taxonomy,
											'field'    => 'id',
											'terms'    => $attrIds,
											'operator' => 'IN',
										);
									}
								}
								$first = false;
							}
							$taxQuery[] = $taxQ;
						}
					}
				}
			}
		}
		return $taxQuery;
	}

}

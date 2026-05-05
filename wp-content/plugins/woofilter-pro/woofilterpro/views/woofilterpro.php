<?php
/**
 * WBW Product Filter PRO - WoofilterProViewWpf Class
 *
 * @version 2.8.6
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class WoofilterProViewWpf extends WoofiltersViewWpf {

	public $lineRatingStyle = array(
		'leer' => '.wpfStarsRatingLine{color: #eee;}',
		'leerHover' => '.wpfLineStarsRating .wpfStarItem:hover ~ .wpfStarItem{color: #eee;}',
		'fillHover' => '.wpfLineStarsRating .wpfStarsRatingLine:hover,
			.wpfStarInput:nth-of-type(1):checked ~ .active:nth-of-type(1),
			.wpfStarInput:nth-of-type(2):checked ~ .active:nth-of-type(-n+2),
			.wpfStarInput:nth-of-type(3):checked ~ .active:nth-of-type(-n+3),
			.wpfStarInput:nth-of-type(4):checked ~ .active:nth-of-type(-n+4),
			.wpfStarInput:nth-of-type(5):checked ~ .active:nth-of-type(-n+5){
			color: #eeee22; };',
	);

	private $_taxonomyList = array();

	/**
	 * Decimals custom range option value for range price filter.
	 *
	 * @var int
	 */
	public $customDecimalsRange;

	/**
	 * Class constructor.
	 *
	 * @param int $filterId
	 */
	public function __construct( $filterId = 0 ) {
		$this->filterId = $filterId;
	}

	public function getImportDialog() {
		return parent::getContent('importDialog');
	}

	public function showAdminImortExportButtons() {
		parent::display('partAdminButtonsPro');
	}

	public function setLeerFilter( $leer ) {
		if (isset(parent::$isLeerFilter)) {
			parent::$isLeerFilter = $leer;
		}
	}

	public function getLeerFilter() {
		return isset(parent::$isLeerFilter) ? parent::$isLeerFilter : false;
	}

	public function generatePriceFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {

		$settings = $this->getFilterSetting($filter, 'settings', array());
		$skin = $this->getFilterSetting($settings, 'f_skin_type');

		if ('default' == $skin) {
			return parent::generatePriceFilterHtml($filter, $filterSettings, $blockStyle);
		}
		$prices = self::$filterExistsPrices;

		$isShowCurrencySlider = $this->getFilterSetting($settings, 'f_price_show_currency_slider', false);

		$classShowCurrencySlider = ( $isShowCurrencySlider ) ? ' js-show-currency-slider' : '';

		$settings['minPrice'] = $prices->wpfMinPrice;
		// ion range slider cannot display value greater than 2000000 (fixed to 100000000000)
		$settings['maxPrice'] = ( $prices->wpfMaxPrice < 100000000001 ) ? $prices->wpfMaxPrice : 100000000000;
		$settings['minValue'] = ReqWpf::getVar('wpf_min_price', 'all', -1);
		$settings['maxValue'] = ReqWpf::getVar('wpf_max_price', 'all', -1);
		$settings['decimal'] = $prices->decimal;

		$isSetMinMaxPrices = $this->getFilterSetting($settings, 'f_set_min_max_price', false);
		$isPreselect = $isSetMinMaxPrices && $this->getFilterSetting($settings, 'f_use_as_preselect', false);
		$settings = $this->checkPriceArgs($settings);
		$currencyShowAs = '';
		$currencySymbolBefore = '';
		$currencySymbolAfter = '';
		$attrCurrency = '';

		if ($classShowCurrencySlider) {
			if ($this->getFilterSetting($settings, 'f_currency_show_as', '') === 'symbol') {
				$currencyShowAs = get_woocommerce_currency_symbol();

				preg_match('/<span(.*?)>(.*?)<\/span>/ui', $currencyShowAs, $matches);
				if (isset($matches[0])) {
					$currencyShowAs = str_replace($matches[0], '', $currencyShowAs);
				}
				unset($matches);

			} else {
				$currencyShowAs = get_woocommerce_currency();
			}

			if ($this->getFilterSetting($settings, 'f_currency_position', '') === 'before') {
				$currencySymbolBefore = $currencyShowAs . ' ';
				$currencySymbolAfter  = '';
			} else {
				$currencySymbolAfter  = ' ' . $currencyShowAs;
				$currencySymbolBefore = '';
			}
		}
		$attrCurrency = ' data-slider-currency-before="' . $currencySymbolBefore . '"';
		$attrCurrency .=  ' data-slider-currency-after="' . $currencySymbolAfter . '"';
		$attrCurrency .=  ' data-slider-currency="' . $currencyShowAs . '"';

		$noActive = ( ( $settings['minValue'] >= 0 && $settings['maxValue'] >= 0 ) || $isPreselect ) ? '' : 'wpfNotActive';

		if ( $this->getFilterSetting($settings, 'f_hide_if_no_prices', false) && empty($settings['minPrice']) && empty($settings['maxPrice']) ) {
			$this->setFilterCss('#' . self::$blockId . ' {display:none;}');
		}

		$html =
			'<div id="' . self::$blockId . '"' .
				( empty($filter['uniqId']) ? '' : ' data-uniq-id="' . $filter['uniqId'] . '"' ) .
				' class="wpfFilterWrapper ' . $noActive . $classShowCurrencySlider .
				'" data-filter-type="' . $filter['id'] .
				'" data-price-skin="' . $skin .
				'" data-get-attribute="wpf_min_price,wpf_max_price,tax" data-decimal="' . $prices->decimal .
				'" data-step="' . $prices->dataStep .
				'" data-minvalue="' . $settings['minPrice'] .
				'" data-maxvalue="' . $settings['maxPrice'] .
			( ( isset( $prices->tax ) ) ? '" data-tax="' . $prices->tax : '' ) .
				'" data-skin-css="' . $this->getFilterSetting($settings, 'f_skin_css') .
				'" data-slug="' . esc_attr__('price', 'woo-product-filter') . '"' . $this->getFilterSetting($filter, 'blockAttributes') . $attrCurrency .
			'>' .
				$this->generateFilterHeaderHtml($filter, $filterSettings, $noActive) .
				$this->generateDescriptionHtml($filter) .
				'<input type="text" class="ion-range-slider wpfHidden passiveFilter" value=""
					data-skin="' . $skin .
					'" data-step="' . $prices->dataStep .
					'" data-type="double"' .
					'" data-prettify-separator="' . wc_get_price_thousand_separator() . '"' .
					' data-hide-from-to="' . ( $this->getFilterSetting($settings, 'f_skin_labels_fromto') == '1' ? 'false' : 'true' ) .
					'" data-hide-min-max="' . ( $this->getFilterSetting($settings, 'f_skin_labels_minmax') == '1' ? 'false' : 'true' ) .
					'" data-min="' . $settings['minPrice'] . '"
					data-max="' . $settings['maxPrice'] . '"' .
					( $settings['minValue'] >= 0 ? ' data-from="' . $settings['minValue'] . '"' : '' ) .
					( $settings['maxValue'] >= 0 ? ' data-to="' . $settings['maxValue'] . '"' : '' ) .
					' data-grid="' . ( $this->getFilterSetting($settings, 'f_skin_grid') == '1' ? 'true' : 'false' ) . '"
				/>' . $this->generatePriceInputsHtml($settings) .
				'<style id="' . self::$blockId . '_style"></style>' .
			'</div></div>';
		return $html;
	}

	public function checkPriceArgs( $settings ) {
		$isSetMinMaxPrices = $this->getFilterSetting($settings, 'f_set_min_max_price', false);
		if ($isSetMinMaxPrices) {
			$preDefinedMinPrice = $this->getFilterSetting($settings, 'f_min_price', false);
			if (false !== $preDefinedMinPrice && $settings['minPrice'] < $preDefinedMinPrice) {
				$settings['minPrice'] = floatval($preDefinedMinPrice);
			}
			$preDefinedMaxPrice = $this->getFilterSetting($settings, 'f_max_price', false);
			if (false !== $preDefinedMaxPrice && $settings['maxPrice'] > $preDefinedMaxPrice) {
				$settings['maxPrice'] = floatval($preDefinedMaxPrice);
			}
		}

		if ($settings['minPrice'] > $settings['maxPrice']) {
			$settings['minPrice'] = $settings['maxPrice'];
		}
		if ($settings['minValue'] < $settings['minPrice']) {
			$settings['minValue'] = $settings['minPrice'];
		}
		if ($settings['maxValue'] > $settings['maxPrice']) {
			$settings['maxValue'] = $settings['maxPrice'];
		}

		return $settings;
	}

	public function generateSearchTextFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$fullWord = $this->getFilterSetting($settings, 'f_search_only_by_full_word', 0);
		$useTitleAsPlaceholder = $this->getFilterSetting($settings, 'f_title_as_placeholder', 0);
		$excludedItems = !empty($settings['f_mlist[]']) ? explode(',', $settings['f_mlist[]']) : false;
		$fields = array();
		$logic = $this->getFilterSetting($settings, 'f_query_logic', 'or');

		/**
		* Deprecated functionality
		*
		* @deprecated 1.3.4
		* @deprecated No longer used by internal code and not recommended.
		*/
		$name = $this->getFilterSetting($settings, 'f_search_by', '');
		switch ($name) {
			case 'title':
				$fields['title'] = $variables['title'];
				break;
			case 'content':
				$fields['content'] = $variables['content'];
				break;
			case 'excerpt':
				$fields['excerpt'] = $variables['excerpt'];
				break;
			case 'coe':
				$fields['content'] = $variables['content'];
				$fields['excerpt'] = $variables['excerpt'];
				break;
			case 'toc':
				$fields['title'] = $variables['title'];
				$fields['content'] = $variables['content'];
				break;
			case 'tac':
				$fields['title'] = $variables['title'];
				$fields['content'] = $variables['content'];
				$logic = 'and';
				break;
		}

		$variables = array(
			'title'          => 't',
			'content'        => 'c',
			'excerpt'        => 'e',
			'attributes'     => 'a',
			'tax_categories' => 'k',
			'tax_tags'       => 'g',
			'meta_sku'       => 's',
			'meta_fields'    => 'm',
		);
		if (taxonomy_exists('pwb-brand')) {
			$variables['perfect_brand'] = 'b';
		}
		foreach ($variables as $field => $key) {
			if ($this->getFilterSetting($settings, 'f_search_by_' . $field, false)) {
				$fields[$field] = $key;
			}
		}

		if (count($fields) == 0) {
			$fields['title'] = $variables['title'];
		}
		if (count($fields) == 1) {
			$logic = 'or';
		}

		$filterName = 'pr_search_';
		$id = '';
		foreach ($fields as $field => $key) {
			$filterName .= $key;
			if ( 'meta_fields' === $field || 'attributes' === $field ) {
				$part = explode( '_', $viewId );
				$id   = '_' . $part[0];
			}
		}
		$filterName .= ( 'or' == $logic ? 'o' : 'i' );
		$filterName .= ( $fullWord ? 'w' : 'l' );

		$filterName .= $id;

		$text = ReqWpf::getVar($filterName);
		$placeholder = $useTitleAsPlaceholder ? $settings['f_title'] : esc_attr__('Search text', 'woo-product-filter');
		$placeholderClass = $useTitleAsPlaceholder ? ' usePlaceholder' : '';

		$wrapperStart = '';
		$wrapperEnd = '';
		$htmlOpt = '<div class="wpfSingleInputSearch">
						<input name="textFilter" class="passiveFilter js-passiveFilterSearch' . $placeholderClass . '" placeholder="' . esc_attr($placeholder) . '" type="text" value="' . esc_attr($text) . '"> <button class="js-wpfFilterButtonSearch"></button>
					</div>';

		$noActive = $text ? '' : 'wpfNotActive';

		//$autocomplete = $this->getFilterSetting($settings, 'f_search_by_title', false) ? $this->getFilterSetting($settings, 'f_search_autocomplete') : 0;
		$cnt = $this->getFilterSetting($settings, 'f_search_by_title', 0) + $this->getFilterSetting($settings, 'f_search_by_tax_categories', 0) + $this->getFilterSetting($settings, 'f_search_by_tax_tags', 0);

		$autocomplete = $cnt > 0 && $this->getFilterSetting($settings, 'f_search_autocomplete', false) ? $cnt : 0;

		$notDisplayResultType = ( $this->getFilterSetting( $settings, 'f_not_display_result_type' ) ) ? ' data-not-display-result-type="1"' : '';

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper ' . $noActive . '"' .

					$this->setFitlerId() .
					$this->setCommonFitlerDataAttr($filter, $filterName, 'text') .

					' data-autocomplete="' . $autocomplete .
					'" data-excluded="' . htmlspecialchars(json_encode($excludedItems), ENT_QUOTES, 'UTF-8') . '"' .

					$this->getFilterSetting($filter, 'blockAttributes') .
					$notDisplayResultType .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId .
					'" class="wpfFilterWrapper ' . $noActive .
					'" data-filter-type="' . $filter['id'] .
					'" data-display-type="text' .
					'" data-get-attribute="' . $filterName .
					'" data-autocomplete="' . $autocomplete .
					'" data-excluded="' . htmlspecialchars(json_encode($excludedItems), ENT_QUOTES, 'UTF-8') .
					'" data-slug="' . esc_attr__('text', 'woo-product-filter') .
					'"' . $this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function generateSearchNumberFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$useTitleAsPlaceholder = $this->getFilterSetting($settings, 'f_title_as_placeholder', 0);
		$logic = $this->getFilterSetting($settings, 'f_search_logic', 'min');
		$labelBefore = $this->getFilterSetting($settings, 'f_label_before');
		$labelAfter = $this->getFilterSetting($settings, 'f_label_after');
		$singleInput = empty($labelBefore) && empty($labelAfter);
		$controlProducts = $this->getFilterSetting($settings, 'f_conrol_products') == 1;

		$attrId = $this->getFilterSetting($settings, 'f_list', 0, false);
		$addIds = '';

		if (!is_numeric($attrId)) {
			$filter['custom_taxonomy'] = $this->getCustomTaxonomy($attrId, $settings);
		} else if ($this->getFilterSetting($settings, 'f_multi_attributes', 0) == '1') {
			$addAttsId = !empty($settings['f_additional_attributes_list[]']) ? explode(',', $settings['f_additional_attributes_list[]']) : false;
			if (is_array($addAttsId) && count($addAttsId) > 0) {
				foreach ($addAttsId as $id) {
					if ($id != $attrId) {
						$part = explode( '_', $viewId );
						$addIds = '_' . $part[0] . '_' . $key;
						break;
					}
				}
			}
		}

		list($excludeIds, $termArr, $filterName, $attrName, $attrLabel, $filterNameSlug) = $this->getProductAttrFilterData($filter, $filterSettings, $key);

		$filterName .= '_l' . $logic . $addIds;

		$text = ReqWpf::getVar($filterName);
		$placeholder = $useTitleAsPlaceholder ? $settings['f_title'] : '';
		$placeholderClass = $useTitleAsPlaceholder ? ' usePlaceholder' : '';

		$wrapperStart = '';
		$wrapperEnd = '';
		if ($singleInput) {
			$htmlOpt = '<div class="wpfSingleInputSearch">';
		} else {
			$htmlOpt = '<div class="wpfLabelInputSearch">';

			if (!empty($labelBefore)) {
				$htmlOpt .= '<div class="wpfBeforeInputSearch">' . esc_html__($labelBefore, 'woo-product-filter') . '</div>';
			}
			$htmlOpt .= '<div class="wpfSingleInputSearch">';
		}
		$htmlOpt .= '<input name="wpfNumberFilter" class="passiveFilter js-passiveFilterSearch' . $placeholderClass . '" placeholder="' . esc_attr($placeholder) . '" type="text" value="' . esc_attr($text) . '"> <button class="js-wpfFilterButtonSearch"></button>';
		if (!$singleInput) {
			$htmlOpt .= '</div>';
		}
		if (!empty($labelAfter)) {
			$htmlOpt .= '<div class="wpfAfterInputSearch">' . esc_html__($labelAfter, 'woo-product-filter') . '</div>';
		}
		$htmlOpt .= '</div>';

		$noActive = $text ? '' : 'wpfNotActive';

		if ($controlProducts) {
			list($showedTerms, $countsTerms, $showFilter, $showFilter) = $this->getShowedTerms($attrName, false, $filterName);
			if (count($showedTerms) == 0) {
				$this->setFilterCss('#' . self::$blockId . ' {display:none;}');
			}
		}
		$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .
			$this->setFitlerId() .
			$this->setCommonFitlerDataAttr($filter, $filterName, 'text') .
			'" data-query-logic="' . $logic .
			'" data-taxonomy="' . $attrName .
			'" data-label="' . $attrLabel .
			'" data-control-products="' . ( $controlProducts ? 1 : 0 ) .
			'"' . $this->getFilterSetting($filter, 'blockAttributes') .
		'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function generateRatingFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$type = $this->getFilterSetting($settings, 'f_frontend_type');

		if ( 'linestars' != $type && 'liststars' != $type ) {
			return parent::generateRatingFilterHtml($filter, $filterSettings, $blockStyle);
		}

		$filterName = 'pr_rating';
		$ratingSelected = ReqWpf::getVar($filterName);

		$line = ( 'linestars' == $type );
		$size = $this->getFilterSetting($settings, 'f_stars_icon_size', 20, true);
		$activColor = $this->getFilterSetting($settings, 'f_stars_icon_color');
		$leerColor = $this->getFilterSetting($settings, 'f_stars_leer_color');
		$borderColor = $this->getFilterSetting($settings, 'f_stars_icon_border');
		$addText = __($this->getFilterSetting($settings, 'f_add_text', 'and up'), 'woo-product-filter');
		$addText5 = __($this->getFilterSetting($settings, 'f_add_text5', '5 only'), 'woo-product-filter');
		$useExactValues = $this->getFilterSetting($settings, 'f_use_exact_values', false);

		$ratingStyle = 'font-size:' . $size . 'px;line-height:' . $size . 'px;height:' . $size . 'px;';
		$starStyle = 'stroke:' . $borderColor . ';font-size:' . $size . 'px;line-height:' . $size . 'px;';
		$wrapperStart = '<div class="wpfStarsRating" data-star-color="' . esc_attr($activColor) . '" data-leer-color="' . esc_attr($leerColor) .
			'" data-display-type="' . $type . '"' . ( $line ? ' data-exact-values="' . esc_attr($useExactValues) . '" data-add-text="' . esc_attr($addText) . '" data-add-text5="' . esc_attr($addText5) . '"' : '' ) . '>';
		$wrapperEnd = '</div>';

		$htmlOpt = '';

		$this->setFilterCss('#' . self::$blockId . ' .wpfStarsRating {display:none;}');
		$this->setFilterCss('#' . self::$blockId . ' .wpfStarsRatingBlock {' . $ratingStyle . '}');
		$this->setFilterCss('#' . self::$blockId . ' .wpfRatingStar {' . $starStyle . '}');

		if ($line) {
			$htmlOpt = '<style type="text/css">' .
				str_replace('#eee', $leerColor, $this->lineRatingStyle['leer']) .
				str_replace('#eee', $leerColor, $this->lineRatingStyle['leerHover']) .
				str_replace('#eeee22', $activColor, $this->lineRatingStyle['fillHover']) .
				'</style><div class="wpfStarsRatingBlock wpfLineStarsRating"><div class="wpfStarsRatingLine active">';

			$inputs = '';
			$labels = '';
			for ($i = 1; $i <= 5; $i++) {
				$value = $i . ( !$useExactValues ? '-5' : '' );
				$inputs .= '<input type="radio" name="ratingStar" class="wpfStarInput" id="wpfLineStar' . $i . '" value="' . $value . '"' .
					( $value == $ratingSelected ? ' checked' : '' ) . ' data-label="' . ( $useExactValues ? $i : ( 5 == $i ? $addText5 : $i . ' ' . $addText ) ) . '">';
				$labels .= '<label class="wpfStarItem active" for="wpfLineStar' . $i . '"><svg class="wpfRatingStar"><use xlink:href="#wpfStar"></use></svg></label>';
			}
			$htmlOpt .= $inputs . $labels . '</div><div class="wpfStarsAdditional">' . ( $useExactValues ? $ratingSelected : ( '5-5' == $ratingSelected ? $addText5 : $addText ) ) . '</div></div>';
		} else {
			$this->setFilterCss('#' . self::$blockId . ' .wpfStarsRatingLine {color:' . $leerColor . ';}');
			$this->setFilterCss('#' . self::$blockId . ' .wpfStarItem.checked {color:' . $activColor . ';}');
			$begin = '<div class="wpfStarsRatingBlock"><div class="wpfStarsRatingLine">';
			for ($j = 5; $j >= 1; $j--) {
				$id = 'wpfListStar' . $j;
				$value = $j . ( !$useExactValues ? '-5' : '' );
				$checked = ( $value == $ratingSelected ? ' checked' : '' );
				$htmlOpt .= '<div class="wpfStarsRatingBlock' . ( $value == $ratingSelected ? ' wpfLineChecked' : '' ) . '">' .
					'<div class="wpfStarsRatingLine"><input type="radio" name="ratingStar" class="wpfStarInput" id="' . $id .
					'" value="' . $value . '"' . $checked . ' data-label="' . ( $useExactValues ? $j : ( 5 == $j ? $addText5 : $j . ' ' . $addText ) ) . '">';
				for ($i = 1; $i <= 5; $i++) {
					$htmlOpt .= '<label class="wpfStarItem' . ( $i <= $j ? ' checked' : '' ) .
						'" for="' . $id . '"><svg class="wpfRatingStar"><use xlink:href="#wpfStar"></use></svg></label>';
				}
				$htmlOpt .= '</div><div class="wpfStarsAdditional' . $checked . '">' . ( $useExactValues ? $j : ( 5 == $j ? $addText5 : $addText ) ) . '</div></div>';
			}
		}
		$this->setFilterCss('#' . self::$blockId . ' .wpfStarDefault {display:none;}');

		$noActive = ReqWpf::getVar($filterName) ? '' : 'wpfNotActive';

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper ' . $noActive . '"' .

					$this->setFitlerId() .
					$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

					$this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId .
					'" class="wpfFilterWrapper ' . $noActive .
					'" data-filter-type="' . $filter['id'] .
					'" data-display-type="' . $type .
					'" data-get-attribute="' . $filterName .
					'" data-slug="' . esc_attr__('rating', 'woo-product-filter') . '"' .
					$this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive) .
		$this->generateDescriptionHtml($filter) .
		'<div class="wpfCheckboxHier">' . $wrapperStart . $htmlOpt . $wrapperEnd .
		'<svg class="wpfStarDefault" xmlns="http://www.w3.org/2000/svg">
			<symbol id="wpfStar" viewBox="0 0 26 28">
				<path d="M26 10.109c0 .281-.203.547-.406.75l-5.672 5.531 1.344 7.812c.016.109.016.203.016.313 0 .406-.187.781-.641.781a1.27 1.27 0 0 1-.625-.187L13 21.422l-7.016 3.687c-.203.109-.406.187-.625.187-.453 0-.656-.375-.656-.781 0-.109.016-.203.031-.313l1.344-7.812L.39 10.859c-.187-.203-.391-.469-.391-.75 0-.469.484-.656.875-.719l7.844-1.141 3.516-7.109c.141-.297.406-.641.766-.641s.625.344.766.641l3.516 7.109 7.844 1.141c.375.063.875.25.875.719z"></path>
			</symbol>
		</svg></div></div></div>';

		return $html;
	}

	public function getCustomTaxonomy( $slug, $settings = array() ) {
		$module = $this->getModule();
		if (strpos($slug, $module->ctax_prefix) === 0) {
			$key = str_replace($module->ctax_prefix, '', $slug);
			foreach (get_object_taxonomies('product', 'objects') as $name => $tax) {
				if ($name == $key) {
					$attr = new stdClass();
					$attr->attribute_name = $name;
					$attr->attribute_slug = $slug;
					$attr->attribute_label = $tax->label;
					$attr->custom_type = 'text';
					$attr->filter_name = 'wpf_filter_' . $name;
					return $attr;
				}
			}
		} else if (strpos($slug, $module->acf_prefix) === 0 && $module->isACFPluginActivated()) {
			$key = str_replace($module->acf_prefix, '', $slug);
			$field = acf_get_field($key);
			if (!empty($field)) {
				$attr = new stdClass();
				$attr->attribute_name = strtolower($field['name']);
				$attr->attribute_slug = $slug;
				$attr->attribute_label = $field['label'];
				$attr->custom_type = $field['type'];
				$attr->filter_name = $slug;
				return $attr;
			}
		} else if (strpos($slug, $module->local_prefix) === 0) {
			$metaKeyId = FrameWpf::_()->getModule('woofilters')->getMetaKeyId('_product_attributes');
			if ($metaKeyId) {
				$key = str_replace($module->local_prefix, '', $slug);
				$localAttrs = FrameWpf::_()->getModule('meta')->getModel('meta_values')->getFieldValuesList($metaKeyId, 'key3', array('key3' => $key, 'key2' => 'local'), true);
				foreach ($localAttrs as $name) {
					if ($name == $key) {
						$attr = new stdClass();
						$attr->attribute_name = $name;
						$attr->attribute_slug = $slug;
						$attr->attribute_label = $name . ' *';
						$attr->custom_type = 'text';
						$attr->filter_name = $slug;
						return $attr;
					}
				}
			}
		} else if ('custom_meta_field_check' == $slug) {
			$key = $this->getFilterSetting($settings, 'f_custom_meta_field');
			if (!empty($key)) {
				$key = strtolower($key);
				$metaKeyId = FrameWpf::_()->getModule('woofilters')->getMetaKeyId($key);
				if ($metaKeyId) {
					$slug = $module->meta_prefix . $key;
					$attr = new stdClass();
					$attr->attribute_name = $key;
					$attr->attribute_slug = $slug;
					$attr->attribute_label = $key;
					$attr->custom_type = 'text';
					$attr->filter_name = $slug;
					return $attr;
				}
			}
		}
		return false;
	}

	/**
	 * generateAttributeFilterHtml.
	 *
	 * @version 2.8.6
	 */
	public function generateAttributeFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$hiddenAtts = $this->getFilterSetting($settings, 'f_hidden_attributes', false);
		$type = $hiddenAtts ? 'list' : $this->getFilterSetting($settings, 'f_frontend_type');
		$attrId = $this->getFilterSetting($settings, 'f_list', 0, false);

		if (!is_numeric($attrId)) {
			$filter['custom_taxonomy'] = $this->getCustomTaxonomy($attrId, $settings);
		}

		if ($hiddenAtts) {
			$filter['settings']['f_frontend_type'] = $type;
		}

		if (!in_array($type, array( 'colors', 'slider' ))) {
			return parent::generateAttributeFilterHtml($filter, $filterSettings, $blockStyle, $key);
		}
		if ( 'slider' === $type ) {
			return $this->generateAttributeSliderFilterHtml($filter, $filterSettings, $blockStyle, $key, $viewId);
		}

		list($excludeIds, $productAttr, $filterName, $attrName, $attrLabel, $filterNameSlug) = $this->getProductAttrFilterData($filter, $filterSettings, $key);

		$excludeIds = DispatcherWpf::applyFilters( 'excludeColorChildren', $excludeIds, $settings );

		if (!$productAttr) {
			return '';
		}

		$logic = FrameWpf::_()->getModule('woofilters')->getAttrFilterLogic();
		$logicSlug = $this->getFilterSetting($settings, 'f_query_logic', 'or', false, array_keys($logic['loop']));
		$hideEmpty = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);
		$hideBySingle = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_by_single', false);

		$showAllAtts = $this->getFilterSetting($settings, 'f_show_all_attributes', false);
		list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($attrName, $showAllAtts, $filterName);

		// add additional slug for filter name if logic is out of standard woocmmerce filter
		if ('not_in' == $logicSlug) {
			$filterName = 'pr_' . $filterName;
			$showAllAtts = true;
		}

		$defSelected = $this->getFilterUrlData($filterName);
		$attrSelected = $defSelected;
		if ($attrSelected) {
			if ($attrSelected == $this->getFilterSetting($settings, 'f_select_default_id')) {
				$attrSelected = explode( '|', $attrSelected );
				$filter['is_ids'] = true;
			} else {
				$delimetrList = array_values( $logic['delimetr'] );
				foreach ( $delimetrList as $delimetr ) {
					$slugs = explode( $delimetr, $attrSelected );
					if ( count( $slugs ) > 1 ) {
						break;
					}
				}
			}
		}
		$isACF = ( 0 === strpos( $filter['name'], 'acf-' ) );
		if ( ! empty( $slugs ) ) {
			$attrSelected = $slugs;
		}

		if ($defSelected && !$hideEmptyActive) {
			$showedTerms = $allTerms;
			$showFilter = true;
		}

		$search = $this->getFilterSetting($settings, 'f_show_search_input', false);

		$layoutHor = $this->getFilterSetting($settings, 'f_colors_layout') == 'hor';
		$disableTooltip = $this->getFilterSetting($settings, 'f_colors_disable_tooltip', false);
		$wrapperStart = '<div class="wpfColorsFilter"><div class="wpfColorsFilter' . ( $layoutHor ? 'Hor' : 'Ver' ) . '">';
		$wrapperEnd = '</div></div>';

		$size = $this->getFilterSetting($settings, 'f_colors_size', 16, true);
		$icon = $this->getFilterSetting($settings, 'f_colors_type', 'square', false, array('circle', 'square', 'round'));
		$showLabels = $this->getFilterSetting($settings, 'f_colors_labels', false);
		$lineHeight = $size;

		$style = 'height:' . $size . 'px; width:' . $size . 'px; max-height:' . $size . 'px; max-width:' . $size . 'px; font-size:' . round($size / 2) . 'px; ';

		if ('square' != $icon) {
			$style .= 'border-radius:' . ( ( 'circle' == $icon ) ? '50%' : '3px' ) . '; ';
		}
		$border = false;
		if ($this->getFilterSetting($settings, 'f_colors_border', false)) {
			$width = $this->getFilterSetting($settings, 'f_colors_border_width', 0, true);
			$color = $this->getFilterSetting($settings, 'f_colors_border_color');
			if ($width >= 1 && !empty($color)) {
				$width = round($width);
				$style .= 'border:' . $width . 'px solid ' . $color . '; ';
				$border = true;
				$lineHeight -= $width * 2;
			}
		}

		$style .= 'line-height:' . $lineHeight . 'px;';

		$showCount = $this->getFilterSetting($settings, 'f_show_count', false);

		if ($layoutHor) {
			$cntProBlock = $this->getFilterSetting($settings, 'f_colors_hor_row', 0, true);
			$margin = $this->getFilterSetting($settings, 'f_colors_hor_spacing', 0, true);

			$style .= 'margin-right:' . $margin . 'px; margin-bottom:' . $margin . 'px;';
			$styleBlock = ' class="wpfColorsRow"';
			$this->setFilterCss('#' . self::$blockId . ' .wpfColorsRow {line-height:' . $size . 'px;}');

			if ($this->getFilterSetting($settings, 'f_colors_rotate_checked', false)) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter input:checked + label.icon{transform: rotate(15deg);}');
			}
			if ($showCount && $this->getFilterSetting($settings, 'f_colors_label_count')) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter input:checked + label.icon:before {display: none;}');
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter label.icon {text-align: center;}');
			}
		} else {
			$cntColunms = round($this->getFilterSetting($settings, 'f_colors_ver_columns', 1, true));
			$cntProBlock = ceil(count($productAttr) / $cntColunms);
			$styleBlock = ' class="wpfColorsCol wpfFilterVerScroll"';
			$this->setFilterCss('#' . self::$blockId . ' .wpfColorsCol {line-height:' . $size . 'px;}');
			$maxHeight = $this->getFilterSetting( $settings, 'f_max_height', 0, true );
			if ( $maxHeight > 0 ) {
				$this->setFilterCss( '#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}' );
			}
		}
		$htmlOpt = '<ul' . $styleBlock . '>';

		$i = 0;
		if (!empty($viewId)) {
			$viewId = '_' . $viewId;
		}

		$this->setFilterCss('#' . self::$blockId . ' label.icon {' . $style . '}');
		$leer = true;
		foreach ($productAttr as $term) {
			$id = $term->term_id;
			if ( !empty($excludeIds) && in_array($id, $excludeIds) ) {
				continue;
			}
			$label = $this->getFilterSetting($settings, 'f_colors_text_term' . $id, $term->name);
			if ($showCount) {
				$count = ( false !== $countsTerms ) ? ( isset($countsTerms[$id]) ? $countsTerms[$id] : 0 ) : ( isset($term->count) ? $term->count : '0' );
				$label .= $layoutHor ? ' (' . $count . ')' : '<span class="wpfCount">(' . $count . ')</span>';
			}
			$slug = $term->slug;
			$iconStyle = $this->getFilterSetting($settings, 'f_colors_icon_term' . $id, '');

			$color = $this->getFilterSetting($settings, 'f_colors_term' . $id, '');
			$bicolor = $this->getFilterSetting($settings, 'f_colors_bicolor_term' . $id, '');
			if ($color && $bicolor) {
				$backgroundColor = ' background: linear-gradient(45deg, ' . $color . ' 50%, ' . $bicolor . ' 50%); ';
			} else {
				$m = '';
				if ($color) {
					$backgroundColor = ' background-color: ' . $color . '; ';
				} elseif ($bicolor) {
					$backgroundColor = ' background-color: ' . $bicolor . '; ';
				} else {
					$backgroundColor = ' background-color: #ffffff; ';
				}
			}

			if ( $cntProBlock > 0 && $i == $cntProBlock ) {
				$htmlOpt .= '</ul><ul' . $styleBlock . '>';
				$i = 0;
			}
			$i++;
			$termSlug = urldecode($slug);
			$selector = '#' . self::$blockId . ' li[data-term-slug="' . $termSlug . '"]';
			if ( !$showAllAtts && is_array($showedTerms) && ( empty($showedTerms) || !in_array($term->term_id, $showedTerms) ) ) {
				$this->setFilterCss($selector . ' {display:none;}');
			} else {
				$leer = false;
			}
			$htmlOpt .= '<li data-term-slug="' . $termSlug . '">';
			if (!$layoutHor) {
				$htmlOpt .= '<div class="wpfColorsColBlock">';
			}

			if ($iconStyle) {
				$this->setFilterCss($selector . ' label.icon {' . $iconStyle . '; background-color: transparent;}');
			} else {
				$this->setFilterCss($selector . ' label.icon {' . $backgroundColor . '}');
				if (!$border) {
					if ( '#ffffff' == $color || '#ffffff' == $bicolor || ( !$bicolor && !$color ) ) {
						// set default border
						$this->setFilterCss($selector . ' label.icon { border:1px solid #cccccc;}');
					}
				}
			}

			$labelColor = $this->getFilterSetting($settings, 'f_colors_label_term' . $id, '');
			if (!empty($labelColor)) {
				$this->setFilterCss($selector . ' label.wpfAttrLabel {color:' . $labelColor . ';}');
			}

			$label_count = '';
			$labelCount = $this->getFilterSetting($settings, 'f_colors_label_count');

			if ($showCount && $labelCount && $layoutHor) {
				$label_count = $count;
			}

			$value = ( $isACF ) ? $id : $slug;

			$htmlOpt .= '<input id="filter-term' . $id . $viewId . '" type="checkbox" data-term-id="' . $id . '" data-term-slug="' . urldecode($slug) . '"' .
				( $attrSelected && in_array($value, $attrSelected) ? ' checked' : '' ) . '>' .
				'<label class="icon"' .
					( $layoutHor && !$disableTooltip ? ' title="' . $label . '"' : '' ) .
					' data-term-name="' . $term->name .
					'" data-color="' . ( $color ? $color : $bicolor ) .
					'" data-show-count="' . $label_count .
					'" for="filter-term' . $id . $viewId .
				'">' . $label_count . '</label>';
			if (!$layoutHor) {
				if ($showLabels) {
					$htmlOpt .= '<label class="wpfAttrLabel" for="filter-term' . $id . $viewId . '" >' . $label . '</label>';
				}
				$htmlOpt .= '</div>';
			}
			$htmlOpt .= '</li>';
		}
		$htmlOpt .= '</ul>';
		$this->setLeerFilter($leer);

		$blockStyle = ( !$showFilter || ( !$showAllAtts && $this->getLeerFilter() ) ? 'display:none;' : '' ) . $blockStyle;
		if (!empty($blockStyle)) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		$noActive = $defSelected ? '' : 'wpfNotActive';
		$showCount = $this->getFilterSetting($settings, 'f_show_count') ? ' wpfShowCount' : '';
		//$iniqId       = empty($filter['uniqId']) ? '' : $filter['uniqId'];
		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper ' . $noActive . $showCount . '"' .
				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr( $filter, $filterName, $type, esc_attr( $filterNameSlug ) ) .
				//' data-uniq-id="' . $iniqId .
				'" data-query-logic="' . $logicSlug .
				'" data-taxonomy="' . $attrName .
				'" data-label="' . $attrLabel .
				'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
				'" data-show-all="' . ( (int) $showAllAtts ) .
				'" data-hide-single="' . ( (int) $hideBySingle ) . '"' .
				$this->getFilterSetting( $filter, 'blockAttributes' ) .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId . '" class="wpfFilterWrapper ' . $noActive . $showCount . '" data-filter-type="' . $filter['id'] .
					'" data-display-type="' . $type . '" data-get-attribute="' . $filterName . '" data-query-logic="' . $logicSlug .
					'" data-slug="' . esc_attr__($filterNameSlug, 'woo-product-filter') . '" data-taxonomy="' . $attrName . '" data-label="' . $attrLabel .
					'" data-show-all="' . ( (int) $showAllAtts ) . '"' . $this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( $search && ( 'list' == $type ) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_attr__('Search ...', 'woo-product-filter') . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart . $htmlOpt . $wrapperEnd;
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function generateAttributeSliderFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$type = $this->getFilterSetting($settings, 'f_frontend_type');
		$attrId = $this->getFilterSetting($settings, 'f_list', 0, false);

		if (!is_numeric($attrId) && empty($filter['custom_taxonomy'])) {
			$filter['custom_taxonomy'] = $this->getCustomTaxonomy($attrId);
		}
		$isCustom = !empty($filter['custom_taxonomy']);

		$attrName = ( ! empty( $filter['custom_taxonomy'] ) ) ? $filter['custom_taxonomy']->attribute_name : wc_attribute_taxonomy_name_by_id( (int) $attrId );

		$showAllAtts = $this->getFilterSetting($settings, 'f_show_all_attributes', false);

		$filterName = ( isset( $filter['custom_taxonomy']->filter_name ) ) ? $filter['custom_taxonomy']->filter_name : 'wpf_filter_' . preg_replace( '/^pa_/', '', $attrName );
		$needIndex = FrameWpf::_()->getModule( 'woofilters' )->getView()->needIndex;
		$index     = ( in_array( $filter['name'], $needIndex, true ) ) ? "_{$key}" : '';

		list( $showedTerms, $countsTerms, $showFilter, $showedTermsWithotFiltering ) = $this->getShowedTerms( $attrName, $showAllAtts, $filterName . $index );

		list($excludeIds, $termArr, $filterName, $attrName, $attrLabel, $filterNameSlug) = $this->getProductAttrFilterData($filter, $filterSettings, $key, $showedTerms);

		if (!$termArr) {
			return '';
		}

		$logic = FrameWpf::_()->getModule('woofilters')->getAttrFilterLogic();
		$logicSlug = $this->getFilterSetting($settings, 'f_query_logic', 'or', false, array_keys($logic['loop']));
		$hideEmpty = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);
		$hideBySingle = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_by_single', false);

		$predefindValues = $this->getFilterSetting($filter['settings'], 'f_mlist[]', '');
		if ($predefindValues) {
			$predefindValues = explode(',', $predefindValues);
			$showedTermsWithotFiltering = $predefindValues;
		}
		if (!$showedTermsWithotFiltering) {
			$showedTermsWithotFiltering = empty(parent::$filterExistsTermsWithotFiltering[$attrName]) ? array() : array_keys(parent::$filterExistsTermsWithotFiltering[$attrName]);
		}
		$isCustomOrder = $this->getFilterSetting($filter['settings'], 'f_order_custom', false);
		$sortBy = $this->getFilterSetting($filter['settings'], 'f_sort_by', 'default');
		$isCustomOrder = $predefindValues ? $isCustomOrder : false;

		$termIdNameRelation = array();

		if ( ! $isCustomOrder || 'desc' === $sortBy ) {
			// we need some additional sorting to arrange array with and without filtering according to sorting setting
			foreach ($showedTermsWithotFiltering as $k=> $termId) {
				//if ( 'custom_meta_field_check' === $attrId ) {
				if ($isCustom) {
					//$term                                 = get_metadata_by_mid( 'post', $termId );
					$termIdNameRelation[$termId] = $termId;

				} else {
					$term = get_term_by( 'id', $termId, $attrName );
					if ( $term ) {
						$termIdNameRelation[ $term->term_id ] = $term->name;
						//$termList[ $termId ]                  = $term;
					}
				}
			}
			if ('desc' == $sortBy) {
				arsort($termIdNameRelation);
			} else {
				asort($termIdNameRelation);
			}
			$showedTermsWithotFiltering = array();
			foreach ($termIdNameRelation as $termId => $value) {
				//$showedTermsWithotFiltering[$termId] = $termList[$termId];
				$showedTermsWithotFiltering[] = $termId;
			}
		}

		// add additional slug for filter name if logic is out of standard woocommerce filter
		if ('not_in' == $logicSlug) {
			$filterName = 'pr_' . $filterName;
			$showAllAtts = true;
		}

		$attrSelected = ReqWpf::getVar($filterName);
		if ($attrSelected) {
			$delimetrList = array_values( $logic['delimetr'] );
			foreach ( $delimetrList as $delimetr ) {
				$slugs = explode( $delimetr, $attrSelected );
				if ( count( $slugs ) > 1 ) {
					break;
				}
			}
		}

		if ( ! empty( $slugs ) ) {
			$attrSelected = $slugs;
		}

		$skin = $this->getFilterSetting($settings, 'f_skin_type', 'round');
		$isForceNumeric = $this->getFilterSetting($settings, 'f_force_numeric', false);
		$grid = $this->getFilterSetting($settings, 'f_skin_grid') == '1' ? 'true' : 'false';

		$dataStep = $this->getFilterSetting($settings, 'f_skin_step', 1, true);
		if ($dataStep <= 0) {
			$dataStep = 1;
		}

		$punkt = strpos($dataStep, '.');
		$decimal = ( false === $punkt ? 0 : strlen($dataStep) - 1 - $punkt );

		$attributes = array();
		$attributeIds = array();

		$temp = array();
		foreach ($termArr as $term) {
			$temp[$term->term_id] = $term;
		}
		$termArr = $temp;
		$showAllSliderAttributes = $this->getFilterSetting( $settings, 'f_show_all_slider_attributes', false );

		foreach ($showedTermsWithotFiltering as $id) {
			if (isset($termArr[$id])) {
				$term = $termArr[ $id ];

				if ( $showAllSliderAttributes || $showAllAtts || ( is_array( $showedTerms ) && in_array( $id, $showedTerms ) ) ) {
					if ( ! empty( $excludeIds ) && in_array( $id, $excludeIds ) ) {
						continue;
					}
					if ( $isForceNumeric ) {
						$name = floatval( preg_replace( '/[^0-9\.]/', '', str_replace(',', '.', $term->name) ) );
						if ( is_numeric( $name ) ) {
							$attributes[ $term->slug ]      = $name;
							$attributeIds[ $term->term_id ] = $name;
						}
					} else {
						$attributes[ $term->slug ]      = $term->name;
						$attributeIds[ $term->term_id ] = $term->name;
					}
				}
			}
		}

		if ( empty($attributes) ) {
			return '';
		}

		$blockStyle = ( !$showFilter || ( !$showAllAtts && $this->getLeerFilter() ) ? 'display:none;' : '' ) . $blockStyle;
		if (!empty($blockStyle)) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}

		if ($isForceNumeric) {
			asort($attributes);
			asort($attributeIds);
			$showedTermsWithotFiltering = array_keys($attributeIds);
		}

		$filteredData = array();
		if ( ! is_null( $attrSelected ) ) {
			foreach ( (array) $attrSelected as $item ) {
				if ( isset( $attributes[ $item ] ) ) {
					array_push( $filteredData, $item );
				} else {
					array_push( $filteredData, null );
				}
			}
		} else {
			$filteredData = array( null, null );
		}

		$attributeKeys = array_map('strval', array_keys($attributes));
		$attributeKeys = array_map('urldecode', $attributeKeys);

		if ($isForceNumeric) {
			$settings['minAttrNum'] = min($attributes);
			$settings['maxAttrNum'] = max($attributes);
		} else {
			$settings['minAttrNum'] = reset($attributes);
			$settings['maxAttrNum'] = end($attributes);
		}

		$settings['minValue'] = array_search( $filteredData[0], $attributeKeys );
		$settings['maxValue'] = array_search( end( $filteredData ), $attributeKeys );

		if ( 'default' === $skin ) {
			$skin = 'round';
		}

		$noActive = ( ( 0 === $settings['minValue'] ) && ( count($attributeKeys) - 1 ) === $settings['maxValue'] ) || !$attrSelected ? 'wpfNotActive' : '';

		// exeptional case with with single value that produce console error
		/*if ( is_array( $showedTerms ) && $isForceNumeric && 1 == count( $showedTerms ) && false === $settings['minValue'] && false === $settings['maxValue'] ) {
			return '';
		}*/
		if ( count( $attributes ) == 1 ) {
			$attributes[] = current( $attributes );
		}

		$termIdWithoutFilteringList = array();

		if ('acf-' == substr($filter['name'], 0, 4)) {
			$isAcf = true;
		} else {
			$isAcf = false;
		}
		foreach ($showedTermsWithotFiltering as $termId) {
			if ($isCustom) {
				$termIdWithoutFilteringList['slug'][] = $termId;
				$termIdWithoutFilteringList['id'][] = $termId;

				foreach ($termArr as $index => $termData) {
					if ($termData->term_id == $termId) {
						$termIdWithoutFilteringList['name'][] = ( $isForceNumeric ) ? $attributes[ $termData->slug ] : $termData->name;
					}
				}
			} else {
				$term = get_term($termId, $attrName);
				if ( ! empty( $term->name ) && ! empty( $term->slug ) ) {
					$termIdWithoutFilteringList['id'][]   = $termId;
					$termIdWithoutFilteringList['name'][] = ( $isForceNumeric && ( isset( $attributes[ $term->slug ] ) ) ) ? $attributes[ $term->slug ] : $term->name;
					$termIdWithoutFilteringList['slug'][] = urldecode( $term->slug );
				}
			}
		}

		// we use it for clear all button

		$minTermNameWithoutFiltering = ( isset( $settings['minAttrNum'] ) ) ? $settings['minAttrNum'] : '';
		$maxTermNameWithoutFiltering = ( isset( $settings['maxAttrNum'] ) ) ? $settings['maxAttrNum'] : '';

		$disNumberFormatting = $this->getFilterSetting($settings, 'f_disable_number_formatting', false);

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper ' . $noActive . '"' .

					$this->setFitlerId() .
					$this->setCommonFitlerDataAttr($filter, $filterName, $type, esc_attr($filterNameSlug)) .

					' data-price-skin="' . $skin .
					'" data-show-all="' . ( $showAllAtts ? 1 : 0 ) .
					'" data-taxonomy="' . $attrName . //( $isAcf ? $attrLabel : $attrName ) .
					'" data-decimal="' . $decimal .
					'" data-step="' . $dataStep .
					'" data-decimal-formating="' . ( $isForceNumeric && !$disNumberFormatting ? 1 : 0 ) .
					'" data-minvalue="' . $settings['minAttrNum'] .
					'" data-maxvalue="' . $settings['maxAttrNum'] .
					'" data-minvalue-without-filtering="' . $minTermNameWithoutFiltering .
					'" data-maxvalue-without-filtering="' . $maxTermNameWithoutFiltering .
					'" data-values-without-filtering="' . ( empty($termIdWithoutFilteringList['name']) ? '' : implode(', ', $termIdWithoutFilteringList['name']) ) .
					'" data-slugs-without-filtering="' . ( empty($termIdWithoutFilteringList['slug']) ? '' : implode(', ', $termIdWithoutFilteringList['slug']) ) .
					'" data-ids-without-filtering="' . ( empty($termIdWithoutFilteringList['id']) ? '' : implode(', ', $termIdWithoutFilteringList['id']) ) .
					'" data-query-logic="' . $logicSlug .
					'" data-skin-css="' . $this->getFilterSetting($settings, 'f_skin_css') .
					'" data-hide-single="' . ( (int) $hideBySingle ) . '"' .

					$this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId .
					'" class="wpfFilterWrapper ' . $noActive .
					'" data-filter-type="' . $filter['id'] .
					'" data-price-skin="' . $skin .
					'" data-display-type="' . $type .
					'" data-get-attribute="' . $filterName .
					'" data-show-all="' . ( $showAllAtts ? 1 : 0 ) .
					'" data-taxonomy="' . ( $isAcf ? '' : 'pa_' ) . $attrLabel .
					'" data-decimal="' . $decimal .
					'" data-step="' . $dataStep .
					'" data-decimal-formating="' . ( $isForceNumeric && !$disNumberFormatting ? 1 : 0 ) .
					'" data-minvalue="' . $settings['minAttrNum'] .
					'" data-maxvalue="' . $settings['maxAttrNum'] .
					'" data-maxvalue-without-filtering="' . $minTermNameWithoutFiltering .
					'" data-minvalue-without-filtering="' . $maxTermNameWithoutFiltering .
					'" data-values-without-filtering="' . ( empty($termIdWithoutFilteringList['name']) ? '' : implode(', ', $termIdWithoutFilteringList['name']) ) .
					'" data-slugs-without-filtering="' . ( empty($termIdWithoutFilteringList['slug']) ? '' : implode(', ', $termIdWithoutFilteringList['slug']) ) .
					'" data-ids-without-filtering="' . ( empty($termIdWithoutFilteringList['id']) ? '' : implode(', ', $termIdWithoutFilteringList['id']) ) .
					'" data-query-logic="' . $logicSlug .
					'" data-skin-css="' . $this->getFilterSetting($settings, 'f_skin_css') .
					'" data-slug="' . esc_attr__($filterNameSlug, 'woo-product-filter') . '"' . $this->getFilterSetting($filter, 'blockAttributes') .
				'>';
		}
		$disableNumberFormatting = $disNumberFormatting ? 'data-prettify-enabled = "false"' : '';
		$html .=
			$this->generateFilterHeaderHtml($filter, $filterSettings, $noActive) .
			$this->generateDescriptionHtml($filter) .
			'<input type="text" class="ion-range-slider wpfAttrNumFilterRange wpfHidden passiveFilter" value=""
			    ' . $disableNumberFormatting . '
				data-skin="' . $skin . '"
				data-force-numeric="' . ( $isForceNumeric ? 1 : 0 ) . '"
				data-step="' . $dataStep . '"
				data-prettify-separator="' . wc_get_price_thousand_separator() . '"
				data-values="' . implode(',', $attributes) . '"
				data-slugs="' . implode(',', $attributeKeys) . '"
				data-term-ids="' . implode(',', array_keys($attributeIds)) . '"
				data-type="double"
				data-hide-from-to="' . ( $this->getFilterSetting($settings, 'f_skin_labels_fromto') == '1' ? 'false' : 'true' ) . '"
				data-hide-min-max="' . ( $this->getFilterSetting($settings, 'f_skin_labels_minmax') == '1' ? 'false' : 'true' ) . '"
				data-min="' . $settings['minAttrNum'] . '"
				data-max="' . $settings['maxAttrNum'] . '"
				data-from="' . $settings['minValue'] . '"
				data-to="' . $settings['maxValue'] . '"
				data-grid="' . $grid . '"
			/>' .
			$this->generateAttrInputsHtml($settings) .
			'<style id="' . self::$blockId . '_style"></style>' .
		'</div></div>';

		return $html;
	}

	public function generateAttrInputsHtml( $settings ) {
		$skin = $this->getFilterSetting($settings, 'f_skin_type');
		if ( !isset($settings['minValue']) || is_null($settings['minValue']) ) {
			$settings['minValue'] = $settings['minAttrNum'];
		}
		if ( !isset($settings['maxValue']) || is_null($settings['maxValue']) ) {
			$settings['maxValue'] = $settings['maxAttrNum'];
		}

		if ( !empty($settings['f_attribute_tooltip_show_as']) ) {
			$attrTooltip['class']    = 'wpfPriceTooltipShowAsText';
			$attrTooltip['readonly'] = 'readonly';
		}

		$attrTooltip['class']    = isset($attrTooltip['class']) ? $attrTooltip['class'] : '';
		$attrTooltip['readonly'] = isset($attrTooltip['readonly']) ? $attrTooltip['readonly'] : '';
		$hideInputs = $this->getFilterSetting($settings, 'f_show_inputs_slider_attr') ? '' : ' wpfHidden';

		$isAttrNumNumber = false;
		if (is_numeric($settings['minAttrNum']) && is_numeric($settings['maxAttrNum'])) {
			$isAttrNumNumber = true;
		}

		if (is_numeric($settings['minAttrNum']) && is_numeric($settings['maxAttrNum'])) {
			$isAttrNumNumber = true;
		}
		return '<div class="wpfPriceInputs' . $hideInputs . '">' .

				'<input ' .
					$attrTooltip['readonly'] .
					' type="' . ( $isAttrNumNumber ? 'number' : 'text' ) .
					'" min="' . ( $isAttrNumNumber ? $settings['minAttrNum'] : $settings['minAttrNum'] ) .
					'" max="' . ( $isAttrNumNumber ? $settings['maxAttrNum'] - 1 : $settings['maxAttrNum'] ) .
					'" id="wpfMinAttrNum" class="wpfAttrNumField ' .
					$attrTooltip['class'] .
					'" value="' . $settings['minValue'] .
					'" data-min-numeric-value="' . $settings['minValue'] .
				'" />' .
				'<span class="wpfFilterDelimeter"> - </span>' .

				'<input ' . $attrTooltip['readonly'] .
					' type="' . ( $isAttrNumNumber ? 'number' : 'text' ) .
					'" min="' . ( $isAttrNumNumber ? $settings['minAttrNum'] : $settings['minAttrNum'] ) .
					'" max="' . ( $isAttrNumNumber ? $settings['maxAttrNum'] : $settings['maxAttrNum'] ) .
					'" id="wpfMaxAttrNum" class="wpfAttrNumField ' . $attrTooltip['class'] .
					'" value="' . $settings['maxValue'] .
					'" data-max-numeric-value="' . $settings['maxValue'] .
				'" /> ' .

				'<input readonly type="hidden" id="wpfDataStep" value="1" />' .
			'</div>';
	}

	public function generateInStockFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$defaultStock = $this->getFilterSetting($settings, 'f_default_stock', false);
		$hiddenStock = $defaultStock && $this->getFilterSetting($settings, 'f_hidden_stock', false);

		if (!$hiddenStock) {
			return parent::generateInStockFilterHtml($filter, $filterSettings, $blockStyle, $key, $viewId);
		}

		$optionsAll = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('InStock');
		$defaultStockStatus = $this->getFilterSetting($settings, 'f_hidden_stock_status', 'instock');
		$htmlOpt = '<option value="' . esc_html($defaultStockStatus) . '" data-slug="' . esc_html($defaultStockStatus) . '" selected>' . ( isset($optionsAll[$defaultStockStatus]) ? esc_attr($optionsAll[$defaultStockStatus]) : '' ) . '</option>';
		$htmlOpt = '<select>' . $htmlOpt . '</select>';

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper wpfHidden wpfPreselected" ' .
					$this->setFitlerId() .
					$this->setCommonFitlerDataAttr($filter, 'pr_stock', 'dropdown') .

					$filter['blockAttributes'] .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId .
					'" class="wpfFilterWrapper wpfHidden wpfPreselected" data-filter-type="' . $filter['id'] .
					'" data-display-type="dropdown" data-get-attribute="pr_stock" data-slug="' . esc_attr__('stock status', 'woo-product-filter') . '"' . $filter['blockAttributes'] .
				'>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= $htmlOpt;
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function generateSortByFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$defaultSort = $this->getFilterSetting($settings, 'f_default_sortby', false);
		$hiddenSort = $defaultSort && $this->getFilterSetting($settings, 'f_hidden_sort', false);

		if (!$hiddenSort) {
			return parent::generateSortByFilterHtml($filter, $filterSettings, $blockStyle, $key, $viewId);
		}

		$optionsAll = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('SortBy');
		$defaultSortBy = $this->getFilterSetting($settings, 'f_hidden_sortby', 'default');
		$htmlOpt = '<option value="' . esc_html($defaultSortBy) . '" data-term-slug="' . esc_html($defaultSortBy) . '" selected>' . esc_attr($optionsAll[$defaultSortBy]) . '</option>';
		$htmlOpt = '<select>' . $htmlOpt . '</select>';

		$html =
			'<div class="wpfFilterWrapper wpfHidden wpfPreselected" ' .
				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, 'orderby', 'dropdown') .
				' data-first-instock="' . ( $this->getFilterSetting($settings, 'f_first_instock', false) ? 1 : 0 ) . '"' .
				$filter['blockAttributes'] .
			'>';

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings);
		$html .= $this->generateDescriptionHtml($filter);
		$html .= $htmlOpt;
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function getProductAttrFilterData( $filter, $filterSettings, $key, $showedTerms = array() ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$attrId = $this->getFilterSetting($settings, 'f_list', 0, false);
		$excludeIds = $this->getFilterSetting($settings, 'f_exclude_terms', false);
		$includeAttsId = !empty($settings['f_mlist[]']) ? explode(',', $settings['f_mlist[]']) : false;
		$hideEmpty = $this->getFilterSetting($settings, 'f_hide_empty', false);

		$args = array(
			'parent' => 0,
			'hide_empty' => $hideEmpty,
			'include' => $includeAttsId,
		);

		$isCustomOrder = $includeAttsId && !empty($settings['f_order_custom']);
		$order = !$isCustomOrder && !empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ($isCustomOrder || !$isDefaultOrder) {
			$args = array_merge($args, array(
				'orderby' => ( $isCustomOrder ? 'include' : 'name' ),
				'order' => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		$needIndex = FrameWpf::_()->getModule( 'woofilters' )->getView()->needIndex;
		$index     = ( in_array( $filter['name'], $needIndex, true ) ) ? "_{$key}" : '';

		$isCustom = !empty($filter['custom_taxonomy']);
		if ($isCustom) {
			$customTaxonomy = $filter['custom_taxonomy'];
			$taxonomyName = $customTaxonomy->attribute_name;
			$attrSlug = $customTaxonomy->attribute_slug;
			if (function_exists('mb_strtolower')) {
				$attrLabel = mb_strtolower( $customTaxonomy->attribute_label, 'UTF-8' );
			} else {
				$attrLabel = strtolower( $customTaxonomy->attribute_label );
			}
			$filterNameSlug = $attrSlug;
			$filterName = "{$customTaxonomy->filter_name}{$index}";
		} else {
			$taxonomyName = wc_attribute_taxonomy_name_by_id((int) $attrId);
			if (function_exists('mb_strtolower')) {
				$attrLabel = mb_strtolower( wc_attribute_label( $taxonomyName ), 'UTF-8' );
			} else {
				$attrLabel = strtolower( wc_attribute_label( $taxonomyName ));
			}
			$filterNameSlug = str_replace('pa_', '', $taxonomyName);
			$filterName = "wpf_filter_{$filterNameSlug}{$index}";
		}
		$attrLabel = strip_tags($attrLabel);

		if ($isCustomOrder) {
			$args['wpf_orderby'] = implode(',', $includeAttsId);
			if (method_exists($this, 'wpfAddTermsClauses')) {
				add_filter('terms_clauses', array($this, 'wpfAddTermsClauses'), 99, 3);
			} else {
				add_filter('get_terms_orderby', array($this, 'wpfGetTermsOrderby'), 99, 2);
			}
		}
		if ($isCustom) {
			$custArgs = array(
				'wpf_fbv' => $this->getFilterSetting($filterSettings['settings'], 'filtering_by_variations'),
				'all_attrs' => $this->getFilterSetting($settings, 'f_show_all_attributes', false),
			);
		}
		$productAttr = $isCustom ? DispatcherWpf::applyFilters('getCustomTerms', array(), $attrSlug, array_merge($args, $custArgs)) : $this->getTaxonomyHierarchy($taxonomyName, $args);

		list( $productAttr, $filterNameSlug, $filterName ) = DispatcherWpf::applyFilters( 'getCustomMetaField', array(
			$productAttr,
			$filterNameSlug,
			$filterName,
		), $settings, $filterSettings, $showedTerms );
		remove_filter('get_terms_orderby', array($this, 'wpfGetTermsOrderby'), 99, 2);
		remove_filter('terms_clauses', array($this, 'wpfAddTermsClauses'), 99, 3);

		return array($excludeIds, $productAttr, $filterName, $taxonomyName, $attrLabel, $filterNameSlug);
	}

	public function getButtonsTypeHtml( $options ) {
		if ( !isset($options['settings']) || !isset($options['terms']) ) {
			return '';
		}
		$settings = $options['settings'];
		$settings = $this->getFilterSetting($settings, 'settings', array());

		$terms = $options['terms'];
		$selected = isset($options['selected']) ? $options['selected'] : false;
		$showed = isset($options['showed']) ? $options['showed'] : false;
		$counts = isset($options['counts']) ? $options['counts'] : false;

		$excludes = isset($options['excludes']) ? $options['excludes'] : false;
		if ( false !== $excludes && !is_array($excludes) ) {
			$excludes = explode(',', $excludes);
		}

		$includes = isset($options['includes']) ? $options['includes'] : false;
		if ( false !== $includes && !is_array($includes) ) {
			$includes = explode(',', $includes);
		}

		$layout = isset($options['display']) ? $options['display'] : 0;

		$blockId = self::$blockId;
		$selector = '#' . $blockId . ' .wpfButtonsFilter';
		$style = '';

		// normal styles
		$style .= $selector . ' .wpfTermWrapper {line-height:normal;';

		$padding = $this->getFilterSetting($settings, 'f_buttons_inner_spacing', 5, true, false, true);
		$style .= 'padding:' . $padding . 'px;';

		$margin = $this->getFilterSetting($settings, 'f_buttons_outer_spacing', 5, true, false, true);
		$style .= 'margin: 0 ' . $margin . 'px ' . $margin . 'px 0;';

		$bgColor = $this->getFilterSetting($settings, 'f_buttons_bg_color', '#ffffff');
		$style .= 'background-color:' . $bgColor . ';';

		$size = $this->getFilterSetting($settings, 'f_buttons_font_size', 15, true);
		$style .= 'font-size:' . $size . 'px;';

		$color = $this->getFilterSetting($settings, 'f_buttons_font_color', '#000000');
		$style .= 'color:' . $color . ';';

		$buttonsType = $this->getFilterSetting($settings, 'f_buttons_type', 'square', false, array('circle', 'square', 'corners', 'edges'));
		$style .= 'border-radius:';
		switch ($buttonsType) {
			case 'circle':
				$style .= '50%;';
				break;
			case 'corners':
				$style .= '5px;';
				break;
			case 'edges':
				$style .= ( $padding * 2 + $size ) . 'px;';
				break;
			default:
				$style .= '0;';
				break;
		}

		$border = $this->getFilterSetting($settings, 'f_buttons_border_width', 1, true, false, true);
		$style .= 'border:' . $border . 'px solid ';

		$color = $this->getFilterSetting($settings, 'f_buttons_border_color', '#000000');
		$style .= $color . ' !important;';

		$width = $this->getFilterSetting($settings, 'f_buttons_width', '', true);
		if (!empty($width)) {
			$style .= 'width:' . $width . 'px;text-align:center;';
		}
		$height = $this->getFilterSetting($settings, 'f_buttons_height', '', true);
		if (!empty($height)) {
			$style .= 'height:' . $height . 'px;line-height:' . ( $height - $padding * 2 - $border * 2 ) . 'px;';
		}

		if ( !empty($width) || !empty($height) ) {
			$style .= 'overflow:hidden;';
		}
		$style .= '}';

		// checked styles
		$style .= $selector . ' .wpfTermChecked{';

		$color = $this->getFilterSetting($settings, 'f_buttons_font_color_checked', '#000000');
		$style .= 'color:' . $color . ' ;';

		$color = $this->getFilterSetting($settings, 'f_buttons_border_color_checked', '#000000');
		$style .= 'border-color:' . $color . ' !important;';

		$bgColor = $this->getFilterSetting($settings, 'f_buttons_bg_color_checked', '#ffffff');
		$style .= 'background-color:' . $bgColor . ';}';

		// wrapper styles

		$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
		if ($maxHeight > 0) {
			$style .= $selector . '{max-height:' . $maxHeight . 'px;}';
		}

		if (is_array($layout)) {
			if ($layout['is_ver']) {
				if ($layout['cnt'] >= 1) {
					$width = number_format(100 / $layout['cnt'], 4, '.', '');
					$style .= $selector . '>li {width:' . ( $margin > 0 ? 'calc(' . $width . '% - ' . $margin . 'px)' : $width . '%' ) . ';}';
				}
			}
		}

		$this->setFilterCss($style);

		$htmlOpt = '<ul class="wpfButtonsFilter wpfFilterVerScroll' . ( isset($options['class']) ? ' ' . $options['class'] : '' ) . '">';
		$htmlOpt .= $this->generateButtonsOptionHtml($settings, $terms, $selected, $showed, $counts, $excludes, $includes);

		$htmlOpt .= '</ul>';
		return $htmlOpt;
	}

	public function generateButtonsOptionHtml( $settings, $terms, $selected, $showed, $counts, $excludes, $includes, $leer = true ) {
		$showImage = $this->getFilterSetting($settings, 'f_show_images', false);
		if ($showImage) {
			$imgSize = array($this->getFilterSetting($settings, 'f_images_width', 20), $this->getFilterSetting($settings, 'f_images_height', 20));
		}
		$showCount = $this->getFilterSetting($settings, 'f_show_count', false);
		$existsSelected = is_array($selected) && !empty($selected);
		$isShowed = is_array($showed);

		$blockId = '#' . self::$blockId;
		$perButton = $this->getFilterSetting($settings, 'f_buttons_per_button', false);
		$menuMode = $this->getFilterSetting($settings, 'f_menu_mode', false);

		$html = '';
		foreach ($terms as $term) {
			$id = $term->term_id;
			if ( !empty($excludes) && in_array($id, $excludes) ) {
				continue;
			}
			if ( !empty($includes) && !in_array($id, $includes) ) {
				continue;
			}
			if ($perButton) {
				$bgColor = $this->getFilterSetting($settings, 'f_buttons_term' . $id, '');
				if (!empty($bgColor)) {
					$this->setFilterCss($blockId . ' .wpfTermWrapper[data-term-id="' . $id . '"] {background-color:' . $bgColor . ';}');
				}
				$bgColor = $this->getFilterSetting($settings, 'f_buttons_check_term' . $id, '');
				if (!empty($bgColor)) {
					$this->setFilterCss($blockId . ' .wpfTermChecked[data-term-id="' . $id . '"] {background-color:' . $bgColor . ';}');
				}
			}

			$slug = $term->slug;
			$name = $this->getFilterSetting($settings, 'f_buttons_text_term' . $id, $term->name);
			$checked = $existsSelected && ( ( in_array($slug, $selected) || in_array($id, $selected) ) );

			if ( $isShowed && ( empty($showed) || !in_array($term->term_id, $showed) ) ) {
				$this->setFilterCss($blockId . ' .wpfTermWrapper[data-term-id="' . $id . '"] {display:none;}');
			} else {
				$leer = false;
			}
			$img = '';
			if ($showImage) {
				$thumbnail_id = get_term_meta($id, 'thumbnail_id', true);
				$img = wp_get_attachment_image($thumbnail_id, $imgSize, false, array('alt' => $name));
			}

			$html .= '<li class="wpfTermWrapper' . ( $checked ? ' wpfTermChecked' : '' ) . '" data-term-id="' . $id . '" data-term-slug="' . urldecode($slug) . '"' .
				( $menuMode ? ' data-link="' . get_term_link($id, 'product_cat') . '"' : '' ) . '>';
			$html .= '<input type="checkbox"' . ( $checked ? ' checked' : '' ) . '><span class="wpfValue">' . $img . $name . '</span>';
			if ($showCount) {
				$count = ( false !== $counts ) ? ( isset($counts[$id]) ? $counts[$id] : 0 ) : ( isset($term->count) ? $term->count : '0' );
				$html .= '<span class="wpfCount">(' . $count . ')</span>';
			}
			$html .= '</li>';

			if (!empty($term->children)) {
				$html .= $this->generateButtonsOptionHtml($settings, $term->children, $selected, $showed, $counts, $excludes, $includes, $leer);
			}
		}
		$this->setLeerFilter($leer);

		return $html;
	}

	public function generatePriceRangeFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$isCustomDecimals = $this->getFilterSetting($filter['settings'], 'f_custom_decimals', false);
		if ($isCustomDecimals) {
			$this->customDecimalsRange = $this->getFilterSetting($filter['settings'], 'f_custom_decimals_range');
		}

		if (isset($this->customDecimalsRange)) {
			if (!$this->customDecimalsRange) {
				$this->customDecimalsRange = 0;
			}
			add_filter('wc_get_price_decimals', array($this, 'addWcPriceDecimals'), 10, 1);
		}

		$html = parent::generatePriceRangeFilterHtml($filter, $filterSettings, $blockStyle, $key, $viewId);

		if (isset($this->customDecimalsRange)) {
			remove_filter( 'wc_get_price_decimals', array($this, 'addWcPriceDecimals'));
		}

		return $html;
	}

	public function getSwitchTypeHtml( $options ) {
		if ( !isset($options['settings']) || !isset($options['terms']) ) {
			return '';
		}
		$settings = $this->getFilterSetting($options['settings'], 'settings', array());

		$terms = $options['terms'];
		$selected = isset($options['selected']) ? $options['selected'] : false;
		$showed = isset($options['showed']) ? $options['showed'] : false;
		$counts = isset($options['counts']) ? $options['counts'] : false;

		$excludes = isset($options['excludes']) ? $options['excludes'] : false;
		if ( false !== $excludes && !is_array($excludes) ) {
			$excludes = explode(',', $excludes);
		}

		$includes = isset($options['includes']) ? $options['includes'] : false;
		if ( false !== $includes && !is_array($includes) ) {
			$includes = explode(',', $includes);
		}

		$layout = isset($options['display']) ? $options['display'] : 0;

		$blockId = self::$blockId;
		$selector = '#' . $blockId . ' .wpfSwitchFilter';
		$style = '';
		$label = '';
		$after = '';

		$swithType = $this->getFilterSetting($settings, 'f_switch_type', 'round', false, array('round', 'square'));
		if ('square' == $swithType) {
			$label .= 'border-radius: 0;';
			$after .= 'border-radius: 0;';
		}
		$swithHeight = $this->getFilterSetting($settings, 'f_switch_height', 16, true);
		if (16 != $swithHeight) {
			$h = $swithHeight - 2;
			$label .= 'width:' . ( $swithHeight * 2 - 2 ) . 'px;height:' . $swithHeight . 'px;';
			$after .= 'width:' . $h . 'px;height:' . $h . 'px;';
		}

		// normal styles
		$setColor = false;
		$defColor = '#b0bec5';
		$color = $this->getFilterSetting($settings, 'f_switch_color', $defColor);
		if ($color != $defColor) {
			$label .= 'background:' . $color . ';';
			$setColor = true;
		}
		if (!empty($label)) {
			$style .= $selector . ' label.wpfSwitch {' . $label . '}';
		}
		if (!empty($after)) {
			$style .= $selector . ' label.wpfSwitch:after {' . $after . '}';
		}

		// checked styles
		$defColor = '#81d742';
		$color = $this->getFilterSetting($settings, 'f_switch_color_checked', $defColor);
		if ( $setColor || $color != $defColor ) {
			$style .= $selector . ' input.wpfSwitch:checked + label.wpfSwitch {background:' . $color . ';}';
		}

		$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
		if ($maxHeight > 0) {
			$style .= $selector . '{max-height:' . $maxHeight . 'px;}';
		}
		$this->setFilterCss($style);

		$htmlOpt = '<ul class="wpfSwitchFilter wpfFilterVerScroll' . ( isset($options['class']) ? ' ' . $options['class'] : '' ) . '">';
		$htmlOpt .= $this->generateTaxonomyOptionsHtmlFromPro($terms, $selected, $options['settings'], $excludes, '', $layout, $includes, $showed, $counts);

		$htmlOpt .= '</ul>';
		return $htmlOpt;
	}

	public function generateToggleSwitchHtml( $id, $checked ) {
		return '<span class="wpfToggleSwitch"><input type="checkbox" id="' . $id . '" ' . $checked . ' class="wpfSwitch"><label for="' . $id . '" class="wpfSwitch"></label></span>';
	}

	public function getTextTypeHtml( $options ) {
		if ( !isset($options['settings']) || !isset($options['terms']) ) {
			return '';
		}

		$settings = $options['settings'];
		$terms = $options['terms'];
		$selected = isset($options['selected']) ? $options['selected'] : false;
		$showed = isset($options['showed']) ? $options['showed'] : false;
		$counts = isset($options['counts']) ? $options['counts'] : false;

		$layout = isset($options['display']) ? $options['display'] : 0;

		$excludes = isset($options['excludes']) ? $options['excludes'] : false;
		if ( false !== $excludes && !is_array($excludes) ) {
			$excludes = explode(',', $excludes);
		}

		$includes = isset($options['includes']) ? $options['includes'] : false;
		if ( false !== $includes && !is_array($includes) ) {
			$includes = explode(',', $includes);
		}

		$maxHeight = $this->getFilterSetting($settings['settings'], 'f_max_height', 0, true);
		if ($maxHeight > 0) {
			$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
		}
		$htmlOpt = '<ul class="wpfTextFilter wpfFilterVerScroll ' . ( isset($options['class']) ? $options['class'] : '' ) . '">';
		$htmlOpt .= $this->generateTaxonomyOptionsHtmlFromPro($terms, $selected, $settings, $excludes, '', $layout, $includes, $showed, $counts);

		$htmlOpt .= '</ul>';
		return $htmlOpt;
	}

	/**
	 * Generate custom taxonomy filter for a specific plugin.
	 *
	 * @version 2.8.6
	 *
	 * @link https://woocommerce.com/products/brands/
	 *
	 * @param array $filter
	 * @param array $filterSettingss
	 * @param string $blockStyles
	 * @param int $keys
	 * @param int $viewIds
	 *
	 * @return string
	 */
	public function generateBrandFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());

		$hiddenBrands = $this->getFilterSetting($settings, 'f_hidden_brands', false);
		$includeBrandId = ( !empty($settings['f_mlist[]']) ) ? explode(',', $settings['f_mlist[]']) : false;
		$includeBrandChildren = $this->getFilterSetting($settings, 'f_mlist_with_children', false);
		$excludeIds = !empty($settings['f_exclude_terms']) ? $settings['f_exclude_terms'] : false;
		$hideEmpty = $this->getFilterSetting($settings, 'f_hide_empty', false);
		$hideEmptyActive = $hideEmpty && $this->getFilterSetting($settings, 'f_hide_empty_active', false);

		$taxonomy = 'product_brand';
		if (!empty($includeBrandId) && $includeBrandChildren) {
			$includeBrandId = $this->getChildrenOfIncludedCategories($taxonomy, $includeBrandId);
		}
		$args = array(
			'parent' => 0,
			'hide_empty' => $hideEmpty,
			'include' => $includeBrandId,
		);

		$isCustomOrder = $includeBrandId && !empty($settings['f_order_custom']);
		$order = !$isCustomOrder && !empty($settings['f_sort_by']) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );

		if ($isCustomOrder || !$isDefaultOrder) {
			$args = array_merge($args, array(
				'orderby' => ( $isCustomOrder ? 'include' : 'name' ),
				'order' => ( $isDefaultOrder ? 'asc' : $order ),
				'sort_as_numbers' => ( $isDefaultOrder ? false : $this->getFilterSetting($settings, 'f_sort_as_numbers', false) ),
			));
		}

		$showAllBrands = $this->getFilterSetting($settings, 'f_show_all_brands', false);
		list($showedTerms, $countsTerms, $showFilter, $allTerms) = $this->getShowedTerms($taxonomy, $showAllBrands);

		$productBrand = $this->getTaxonomyHierarchy($taxonomy, $args);
		if (!$productBrand) {
			return '';
		}

		$type       = $filter['settings']['f_frontend_type'];
		$filterName = ( 'multi' === $type ) ? 'product_brand_list' : 'wpf_filter_brand';

		$defSelected = ReqWpf::getVar( $filterName );
		$brandSelected = $defSelected;
		if ($brandSelected) {
			$brandSelected = explode(',', $brandSelected);
		} elseif ( $hiddenBrands && $includeBrandId ) {
			$brandSelected = $includeBrandId;
			$filter['is_ids'] = true;
		}

		if ( empty( $brandSelected ) ) {
			global $wp_query;
			$obj = $wp_query->get_queried_object();
			if ( is_a( $obj, 'WP_Term' ) && 'product_brand' === $obj->taxonomy) {
				$brandSelected[] = $obj->term_id;
			}
		}

		$layout = $this->getFilterLayout($settings, $filterSettings);
		if ($defSelected && !$hideEmptyActive) {
			$showedTerms = $allTerms;
			$showFilter = true;
		}

		$logic = $this->getFilterSetting($settings, 'f_query_logic', 'or');
		$notIds = '';
		$notValues = '';
		if ($hiddenBrands) {
			$type = 'list';
			if (!$includeBrandId && $excludeIds) {
				$logic = 'not';
				$notValues = '" data-not-ids="' . esc_attr($excludeIds);
			}
		}

		$filter['settings']['f_frontend_type'] = $type;

		$htmlOpt = $this->generateTaxonomyOptionsHtmlFromPro($productBrand, $brandSelected, $filter, $excludeIds, '', $layout, $includeBrandId, $showedTerms, $countsTerms);

		if ( 'list' === $type || 'multi' === $type ) {
			$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
			if ($maxHeight > 0) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
			}
			$wrapperStart = '<ul class="wpfFilterVerScroll' . $layout['class'] . '">';
			$wrapperEnd = '</ul>';
		} else if ('dropdown' === $type) {
			$wrapperStart = '<select aria-label="' . esc_html__($this->getRealDropdownLabel($settings, 'Product Brands'), 'woo-product-filter') . '">';
			if (!empty($filter['settings']['f_dropdown_first_option_text'])) {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__($filter['settings']['f_dropdown_first_option_text'], 'woo-product-filter') . '</option>' . $htmlOpt;
			} else {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>' . $htmlOpt;
			}
			$wrapperEnd = '</select>';
		} else if ('mul_dropdown' === $type) {
			$wrapperStart = '';
			$wrapperEnd = '';
			$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
		}

		$noActive = $defSelected ? '' : 'wpfNotActive';
		$noActive = $hiddenBrands ? 'wpfHidden' : $noActive;
		$preselected = $hiddenBrands ? ' wpfPreselected' : '';

		$blockStyle = ( !$showFilter || ( !$showAllBrands && $this->getLeerFilter() ) ? 'display:none;' : '' ) . $blockStyle;
		if (!empty($blockStyle)) {
			$this->setFilterCss('#' . self::$blockId . ' {' . $blockStyle . '}');
		}
		$showCount = $this->getFilterSetting($settings, 'f_show_count', false) ? ' wpfShowCount' : '';

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
				'<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .

					$this->setFitlerId() .
					$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

					' data-taxonomy="product_brand" ' .
					' data-show-all="' . ( (int) $showAllBrands ) . '"' . $filter['blockAttributes'] .
				'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html =
				'<div id="' . self::$blockId .
					'" class="wpfFilterWrapper ' . $noActive . $showCount . $preselected .
					'" data-filter-type="' . $filter['id'] .
					'" data-query-logic="' . $logic . $notValues .
					'" data-display-type="' . $type .
					'" data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) .
					'" data-get-attribute="product_brand" data-slug="' . esc_attr__('brand', 'woo-product-filter') .
					'" data-taxonomy="product_brand" data-show-all="' . ( (int) $showAllBrands ) . '"' . $filter['blockAttributes'] .
				'>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_attr__('Search ...', 'woo-product-filter') . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	/**
	 * Add plugin compatibility filter.
	 *
	 * @link https://wordpress.org/support/plugin/wc-vendors/
	 *
	 * @param array $filter
	 * @param array $filterSettings
	 * @param array $blockStyle
	 * @param int $key
	 * @param int $viewId
	 *
	 * @return string
	 */
	public function generateVendorsFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$labels = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getFilterLabels('Author');

		$roleNames = array('vendor');
		$filterName = 'vendors';

		$args = array(
			'role__in' => $roleNames,
			'fields' => array('ID', 'display_name', 'user_nicename'),
		);
		$usersMain = get_users( $args );

		$userExistList = array();
		if (property_exists($this, 'filterExistsUsers')) {
			foreach (self::$filterExistsUsers as $userData) {
				$userExistList[] = $userData['ID'];
			}
		}

		$users = array();
		$userSelectedSlugs = array();
		$userShowedIds = array();
		$layout = $this->getFilterLayout($settings, $filterSettings);

		foreach ($usersMain as $key => $user) {
			if (property_exists($this, 'filterExistsUsers')) {
				$u = new stdClass();
				$u->term_id = $user->ID;
				$shop_name = get_user_meta( $user->ID, 'pv_shop_name', true );
				$u->name = $shop_name ? $shop_name : $user->display_name;
				$u->slug = $user->user_nicename;
				$users[] = $u;

				if ($userExistList && in_array($user->ID, $userExistList)) {
					$userShowedIds[] = $user->ID;
				}

				if (strpos(ReqWpf::getVar('vendors'), $user->user_nicename) !== false || strpos(ReqWpf::getVar('pr_author'), $user->user_nicename) !== false) {
					$userSelectedSlugs[] = $user->user_nicename;
				}

				$htmlOpt = $this->generateTaxonomyOptionsHtmlFromPro($users, $userSelectedSlugs, $filter, false, '', $layout, false, $userShowedIds);

			} else {
				$u = new stdClass();
				$u->term_id = $user->ID;
				$shop_name = get_user_meta( $user->ID, 'pv_shop_name', true );
				$u->name = $shop_name ? $shop_name : $user->display_name;
				$u->slug = $user->user_nicename;
				$users[] = $u;

				$vendorSelected = ReqWpf::getVar('vendors');
				$htmlOpt = $this->generateTaxonomyOptionsHtmlFromPro($users, array($vendorSelected), $filter, false, '', $layout);
			}
		}

		if ($layout['is_ver']) {
			$this->setFilterCss('#' . self::$blockId . ' {display: inline-block; min-width: auto;}');
		}

		$type = $filter['settings']['f_frontend_type'];

		if ('list' === $type) {
			$maxHeight = $this->getFilterSetting($settings, 'f_max_height', 0, true);
			if ($maxHeight > 0) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}');
			}
			$wrapperStart = '<ul class="wpfFilterVerScroll' . $layout['class'] . '">';
			$wrapperEnd = '</ul>';
		} else if ('dropdown' === $type) {
			$wrapperStart = '<select>';
			if (!empty($filter['settings']['f_dropdown_first_option_text'])) {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__($filter['settings']['f_dropdown_first_option_text'], 'woo-product-filter') . '</option>' . $htmlOpt;
			} else {
				$htmlOpt = '<option value="" data-slug="">' . esc_html__('Select all', 'woo-product-filter') . '</option>' . $htmlOpt;
			}
			$wrapperEnd = '</select>';
		} elseif ( 'mul_dropdown' === $type ) {
			$wrapperStart = '';
			$wrapperEnd = '';
			$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
		}

		$noActive = ReqWpf::getVar('vendors') ? '' : 'wpfNotActive';

		if (-1 === version_compare('1.3.7', WPF_VERSION)) {
			$html =
			'<div class="wpfFilterWrapper ' . $noActive . '"' .

				$this->setFitlerId() .
				$this->setCommonFitlerDataAttr($filter, $filterName, $type) .

				// $filter['blockAttributes'];
			'>';
		} else {
			/**
			* Deprecated functionality
			*
			* @deprecated 1.3.8
			* @deprecated No longer used by internal code and not recommended.
			*/
			$html = '<div id="' . self::$blockId;
			$html .= '" class="wpfFilterWrapper ' . $noActive;
			$html .= '" data-filter-type="' . $filter['id'];
			$html .= '" data-display-type="' . $type;
			$html .= '" data-get-attribute="' . $filterName;
			$html .= '" data-slug="' . esc_attr__('vendors', 'woo-product-filter');
			$html .= '"';
			$html .= $filter['blockAttributes'];
			$html .= '>';
		}

		$html .= $this->generateFilterHeaderHtml($filter, $filterSettings, $noActive);
		$html .= $this->generateDescriptionHtml($filter);
		if ( 'list' === $type && $this->getFilterSetting($settings, 'f_show_search_input', false) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_html__($this->getFilterSetting($settings, 'f_search_label', $labels['search']), 'woo-product-filter') . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">';
		$html .= $wrapperStart;
		$html .= $htmlOpt;
		$html .= $wrapperEnd;
		$html .= '</div>';//end wpfCheckboxHier
		$html .= '</div>';//end wpfFilterContent
		$html .= '</div>';//end wpfFilterWrapper

		return $html;
	}

	public function getColorsTypeHtml( $options ) {
		if ( !isset($options['settings']) || !isset($options['terms']) ) {
			return '';
		}
		$settings = $options['settings'];
		$settings = $this->getFilterSetting($settings, 'settings', array());

		$terms = $options['terms'];
		if (!$terms) {
			return '';
		}
		$termsSelected = isset($options['selected']) ? $options['selected'] : false;
		$showedTerms = isset($options['showed']) ? $options['showed'] : false;
		$countsTerms = isset($options['counts']) ? $options['counts'] : false;
		$showAll = isset($options['show_all']) ? $options['show_all'] : false;

		$excludes = isset($options['excludes']) ? $options['excludes'] : false;
		if ( false !== $excludes && !is_array($excludes) ) {
			$excludes = explode(',', $excludes);
		}

		$includes = isset($options['includes']) ? $options['includes'] : false;
		if ( false !== $includes && !is_array($includes) ) {
			$includes = explode(',', $includes);
		}
		$viewId = isset($options['view_id']) ? $options['view_id'] : false;

		$layoutHor = $this->getFilterSetting($settings, 'f_colors_layout') == 'hor';
		$wrapperStart = '<div class="wpfColorsFilter"><div class="wpfColorsFilter' . ( $layoutHor ? 'Hor' : 'Ver' ) . '">';
		$wrapperEnd = '</div></div>';

		$size = $this->getFilterSetting($settings, 'f_colors_size', 16, true);
		$icon = $this->getFilterSetting($settings, 'f_colors_type', 'square', false, array('circle', 'square', 'round'));
		$showLabels = $this->getFilterSetting($settings, 'f_colors_labels', false);
		$lineHeight = $size;

		$style = 'height:' . $size . 'px; width:' . $size . 'px; max-height:' . $size . 'px; max-width:' . $size . 'px; font-size:' . round($size / 2) . 'px; ';

		if ('square' != $icon) {
			$style .= 'border-radius:' . ( ( 'circle' == $icon ) ? '50%' : '3px' ) . '; ';
		}
		$border = false;
		if ($this->getFilterSetting($settings, 'f_colors_border', false)) {
			$width = $this->getFilterSetting($settings, 'f_colors_border_width', 0, true);
			$color = $this->getFilterSetting($settings, 'f_colors_border_color');
			if ($width >= 1 && !empty($color)) {
				$width = round($width);
				$style .= 'border:' . $width . 'px solid ' . $color . '; ';
				$border = true;
				$lineHeight -= $width * 2;
			}
		}

		$style .= 'line-height:' . $lineHeight . 'px;';

		$showCount = $this->getFilterSetting($settings, 'f_show_count', false);

		if ($layoutHor) {
			$cntProBlock = $this->getFilterSetting($settings, 'f_colors_hor_row', 0, true);
			$margin = $this->getFilterSetting($settings, 'f_colors_hor_spacing', 0, true);

			$style .= 'margin-right:' . $margin . 'px; margin-bottom:' . $margin . 'px;';
			$styleBlock = ' class="wpfColorsRow"';
			$this->setFilterCss('#' . self::$blockId . ' .wpfColorsRow {line-height:' . $size . 'px;}');

			if ($this->getFilterSetting($settings, 'f_colors_rotate_checked', false)) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter input:checked + label.icon{transform: rotate(15deg);}');
			}
			if ($showCount && $this->getFilterSetting($settings, 'f_colors_label_count')) {
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter input:checked + label.icon:before {display: none;}');
				$this->setFilterCss('#' . self::$blockId . ' .wpfColorsFilter label.icon {text-align: center;}');
			}
		} else {
			$cntColunms = round($this->getFilterSetting($settings, 'f_colors_ver_columns', 1, true));
			$cntProBlock = ceil(count($terms) / $cntColunms);
			$styleBlock = ' class="wpfColorsCol wpfFilterVerScroll"';
			$this->setFilterCss('#' . self::$blockId . ' .wpfColorsCol {line-height:' . $size . 'px;}');
			$maxHeight = $this->getFilterSetting( $settings, 'f_max_height', 0, true );
			if ( $maxHeight > 0 ) {
				$this->setFilterCss( '#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . $maxHeight . 'px;}' );
			}
		}
		$htmlOpt = '<ul' . $styleBlock . '>';

		$i = 0;
		if (!empty($viewId)) {
			$viewId = '_' . $viewId;
		}

		$this->setFilterCss('#' . self::$blockId . ' label.icon {' . $style . '}');
		$leer = true;
		foreach ($terms as $term) {
			$id = $term->term_id;
			if ( !empty($excludeIds) && in_array($id, $excludeIds) ) {
				continue;
			}
			$label = $this->getFilterSetting($settings, 'f_colors_text_term' . $id, $term->name);
			if ($showCount) {
				$count = ( false !== $countsTerms ) ? ( isset($countsTerms[$id]) ? $countsTerms[$id] : 0 ) : ( isset($term->count) ? $term->count : '0' );
				$label .= $layoutHor ? ' (' . $count . ')' : '<span class="wpfCount">(' . $count . ')</span>';
			}
			$slug = $term->slug;
			$iconStyle = $this->getFilterSetting($settings, 'f_colors_icon_term' . $id, '');

			$color = $this->getFilterSetting($settings, 'f_colors_term' . $id, '');
			$bicolor = $this->getFilterSetting($settings, 'f_colors_bicolor_term' . $id, '');
			if ($color && $bicolor) {
				$backgroundColor = ' background: linear-gradient(45deg, ' . $color . ' 50%, ' . $bicolor . ' 50%); ';
			} else {
				$m = '';
				if ($color) {
					$backgroundColor = ' background-color: ' . $color . '; ';
				} elseif ($bicolor) {
					$backgroundColor = ' background-color: ' . $bicolor . '; ';
				} else {
					$backgroundColor = ' background-color: #ffffff; ';
				}
			}

			if ( $cntProBlock > 0 && $i == $cntProBlock ) {
				$htmlOpt .= '</ul><ul' . $styleBlock . '>';
				$i = 0;
			}
			$i++;
			$termSlug = urldecode($slug);
			$selector = '#' . self::$blockId . ' li[data-term-slug="' . $termSlug . '"]';
			if ( !$showAll && is_array($showedTerms) && ( empty($showedTerms) || !in_array($term->term_id, $showedTerms) ) ) {
				$this->setFilterCss($selector . ' {display:none;}');
			} else {
				$leer = false;
			}
			$htmlOpt .= '<li data-term-slug="' . $termSlug . '">';
			if (!$layoutHor) {
				$htmlOpt .= '<div class="wpfColorsColBlock">';
			}

			if ($iconStyle) {
				$this->setFilterCss($selector . ' label.icon {' . $iconStyle . '; background-color: transparent;}');
			} else {
				$this->setFilterCss($selector . ' label.icon {' . $backgroundColor . '}');
				if (!$border) {
					if ( '#ffffff' == $color || '#ffffff' == $bicolor || ( !$bicolor && !$color ) ) {
						// set default border
						$this->setFilterCss($selector . ' label.icon { border:1px solid #cccccc;}');
					}
				}
			}

			$labelColor = $this->getFilterSetting($settings, 'f_colors_label_term' . $id, '');
			if (!empty($labelColor)) {
				$this->setFilterCss($selector . ' label.wpfAttrLabel {color:' . $labelColor . ';}');
			}

			$label_count = '';
			$labelCount = $this->getFilterSetting($settings, 'f_colors_label_count');

			if ($showCount && $labelCount && $layoutHor) {
				$label_count = $count;
			}

			$value = $slug;

			$htmlOpt .= '<input id="filter-term' . $id . $viewId . '" type="checkbox" data-term-id="' . $id . '" data-term-slug="' . urldecode($slug) . '"' .
				( $termsSelected && in_array($value, $termsSelected) ? ' checked' : '' ) . '>' .
				'<label class="icon"' .
					( $layoutHor ? ' title="' . $label . '"' : '' ) .
					' data-term-name="' . $term->name .
					'" data-color="' . ( $color ? $color : $bicolor ) .
					'" data-show-count="' . $label_count .
					'" for="filter-term' . $id . $viewId .
				'">' . $label_count . '</label>';
			if (!$layoutHor) {
				if ($showLabels) {
					$htmlOpt .= '<label class="wpfAttrLabel" for="filter-term' . $id . $viewId . '" >' . $label . '</label>';
				}
				$htmlOpt .= '</div>';
			}
			$htmlOpt .= '</li>';
		}
		$htmlOpt .= '</ul>';
		$this->setLeerFilter($leer);

		return $wrapperStart . $htmlOpt . $wrapperEnd;
	}

	public function addEditTabFilters( $part, $settings ) {
		switch ($part) {
			case 'partEditTabFiltersRatingStars':
				$this->assign('style', $this->lineRatingStyle);
				break;

			case 'partEditTabFiltersBrand':
				$wpfBrand = array(
					'exist' => taxonomy_exists('product_brand'),
					'displays' => array(),
				);
				if ($wpfBrand['exist']) {
					$brandsArgs = array(
						'taxonomy'   => 'product_brand',
						'orderby'    => 'name',
						'order'      => 'asc',
						'hide_empty' => false,
						'parent'     => 0,
					);

					$productBrands = get_terms($brandsArgs);
					foreach ($productBrands as $tag) {
						$wpfBrand['displays'][$tag->term_id] = $tag->name;
					}
				}
				$this->assign('wpfBrandPro', $wpfBrand);
				break;

			case 'partEditTabFiltersSearchText':
				$args = array('order' => 'asc', 'orderby' => 'name', 'parent' => 0, 'hide_empty' => true);
				$cnt = get_terms(array('taxonomy' => 'product_cat', 'fields' => 'count', 'hide_empty' => false));
				if ($cnt && $cnt < 1000) {
					$productCategory = parent::getTaxonomyHierarchy('product_cat', $args);
					if ($productCategory) {
						$this->generateTaxonomyList($productCategory);
					}
				}
				$productTag = parent::getTaxonomyHierarchy('product_tag', $args);
				if ($productTag) {
					$this->generateTaxonomyList($productTag);
				}
				$productAttr = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : false;
				if ($productAttr) {
					foreach ($productAttr as $attr) {
						$productAttrValues = parent::getTaxonomyHierarchy(wc_attribute_taxonomy_name($attr->attribute_name), $args);
						if ($productAttrValues) {
							$this->generateTaxonomyList($productAttrValues);
						}
					}
				}

				$this->assign('excludedOptions', $this->_taxonomyList);
				break;

			case 'partEditTabFiltersCustomAttribute':
				$this->assign('style', $this->lineRatingStyle);
				break;

			default:
				break;
		}
		$this->assign('settings', $settings);
		return parent::display($part . 'Pro');
	}

	public function addEditTabDesign( $part, $settings, $filterId = null ) {
		$this->assign('filterId', $filterId);
		$this->assign('settings', $settings);
		return parent::display($part . 'Pro');
	}

	public function generateTaxonomyList( $taxonomyHierarchy, $pre = '' ) {
		if (!empty($taxonomyHierarchy)) {
			foreach ($taxonomyHierarchy as $term_id => $term) {
				$this->_taxonomyList[urldecode($term->taxonomy) . '__' . $term_id] = $pre . urldecode($term->name) . ' (' . urldecode($term->taxonomy) . ')';
				if (!empty($term->children)) {
					$this->generateTaxonomyList($term->children, $pre . '&nbsp;&nbsp;');
				}
			}
		}
	}

	public function generateLoaderLayoutHtml( $options ) {
		if ( isset($options['loader_enable']) && empty($options['loader_enable']['value']) ) {
			return '';
		}
		$this->setFilterCss('.wpfLoaderLayout {position:absolute;top:0;bottom:0;left:0;right:0;background-color: rgba(255, 255, 255, 0.9);z-index: 999;}');

		$html = '<div class="wpfLoaderLayout">';
		$color = $this->getFilterSetting($this->getFilterSetting($options, 'loader_icon_color', array()), 'value', 'black');
		$icon = $this->getFilterSetting($this->getFilterSetting($options, 'loader_icon', array()), 'value', 'default|0');

		$parts = explode('|', $icon);
		if (count($parts) == 2) {
			$iconName = $parts[0];
			$iconNumber = $parts[1];
		} else {
			$iconName = 'default';
			$iconNumber = '0';
		}
		$this->setFilterCss('.wpfLoaderLayout .woobewoo-filter-loader {position:absolute;z-index:9;top:50%;left:50%;margin-top:-30px;margin-left:-30px;color: ' . $color . ';}');

		if ('default' === $iconName) {
			$html .= '<div class="woobewoo-filter-loader"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>';
		} else {
			$html .= '<div class="woobewoo-filter-loader la-' . $iconName . ' la-2x">';
			for ($i = 1; $i <= $iconNumber; $i++) {
				$html .= '<div></div>';
			}
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	public function closeFloatingMode( $html, $settings, $viewId ) {
		if ( $this->getFilterSetting( $settings, 'floating_mode' ) !== '1' ) {
			return $html;
		}
		$isMobile = UtilsWpf::isMobile();
		$devices = $this->getFilterSetting( $settings, 'floating_devices' );
		if ( ( $isMobile && ( 'desktop' == $devices ) ) || ( !$isMobile && ( 'mobile' == $devices ) ) ) {
			return $html;
		}
		return $html . '</div>';
	}

	public function setFloatingMode( $html, $settings, $viewId ) {
		if ( $this->getFilterSetting( $settings, 'floating_mode' ) !== '1' ) {
			return $html;
		}
		$isMobile = UtilsWpf::isMobile();
		$devices = $this->getFilterSetting( $settings, 'floating_devices' );
		if ( ( $isMobile && ( 'desktop' == $devices ) ) || ( !$isMobile && ( 'mobile' == $devices ) ) ) {
			return $html;
		}
		$styles = $this->getFilterSetting($this->getFilterSetting($settings, 'styles', array()), $isMobile ? 'fmobile' : 'fdesktop', array());
		$h = '';

		if ($this->getFilterSetting( $settings, 'floating_call_button' ) != 'custom') {
			$isButtonFloating = $this->getFilterSetting($styles, 'button_fixed') == 'float';
			$isPrVisible = $isButtonFloating && $this->getFilterSetting($styles, 'button_product_visible', '', true) == 1;
			$h .= '<button class="wpfFloatingSwitcher' . ( $isButtonFloating ? ' wpfSwitcherRealFloat' : '' ) . '" id="wpfFloatingSwitcher-' . $viewId .
			'" data-product-visible="' . ( $isPrVisible ? '1" style="display:none;' : 0 ) .
			'">';
			if ($this->getFilterSetting($styles, 'button_type') != 'icon') {
				$h .= esc_html($this->getFilterSetting($styles, 'button_text', esc_attr__('Filter', 'woo-product-filter')));
			}
			$h .= '</button>';
		}
		if ($this->getFilterSetting( $styles, 'popup_overlay' ) != 'none') {
			$h .= '<div class="wpfFloatingOverlay" id="wpfFloatingOverlay-' . $viewId . '"></div>';
		}
		$h .= '<div class="wpfFloatingWrapper" id="wpfFloatingWrapper-' . $viewId .
			'" data-side="' . $this->getFilterSetting($styles, 'popup_arrival_side', 'right' ) .
			'" data-position-top="' . $this->getFilterSetting($styles, 'popup_position_top', '', true, false, true) .
			'" data-position-right="' . $this->getFilterSetting($styles, 'popup_position_right', '', true, false, true) .
			'" data-position-bottom="' . $this->getFilterSetting($styles, 'popup_position_bottom', '', true, false, true) .
			'" data-position-left="' . $this->getFilterSetting($styles, 'popup_position_left', '', true, false, true) .
			'" data-animation-speed="' . $this->getFilterSetting($styles, 'popup_animation_speed', 0, true ) .
			'" data-close-after="' . ( $this->getFilterSetting($styles, 'popup_close_after' ) == 1 ? '1' : '0' ) .
			'" style="display:none;"><div class="wpfFloatingTitle">';
		if ($this->getFilterSetting( $styles, 'use_title', '0', true ) == '1') {
			$h .= esc_html($this->getFilterSetting( $styles, 'title_text', __('Filter', 'woo-product-filter')));
		}
		$h .= '<i class="wpfFloatingClose fa fa-' . esc_attr($this->getFilterSetting( $styles, 'popup_close_icon', 'times' )) . '"></i></div>' .
			str_replace('class="wpfMainWrapper"', 'class="wpfMainWrapper wpfFloating"', $html);
		return $h;
	}

	public function addHideFiltersButton( $settings ) {
		$enableButton = $this->getFilterSetting($settings, 'display_hide_button' . ( UtilsWpf::isMobile() ? '_mobile' : '' ), 'no');

		$breakpointData = $this->getMobileBreakpointOptionDataPro($settings);

		if ($breakpointData) {
			$isOpen = true;
		} else {
			if ('no' == $enableButton) {
				return '';
			}
			$isOpen = ( 'yes_open' === $enableButton );
		}

		$showText = $this->getFilterSetting($settings, 'hide_button_show_text', 'SHOW FILTERS');
		$hideText = $this->getFilterSetting($settings, 'hide_button_hide_text', 'HIDE FILTERS');

		$html = '<button class="wfpHideButton wfpButton wfpClickable" data-is-open="' . ( $isOpen ? 1 : 0 ) . '" data-closed="' . ( $isOpen ? 0 : 1 ) .
			'" data-show-text="' . esc_attr__($showText, 'woo-product-filter') .
			'" data-hide-text="' . esc_attr__($hideText, 'woo-product-filter') .
			'" data-filtered-open="' . ( $this->getFilterSetting($settings, 'display_hide_button_filtered_open', false, 1) == 1 ? 1 : 0 ) .
			'" data-filtered-open-mobile="' . ( $this->getFilterSetting($settings, 'display_hide_button_filtered_open_mobile', false, 1) == 1 ? 1 : 0 ) .
			'"' . $breakpointData .
			'><span class="wfpHideText">' .
				esc_html( $isOpen ? $hideText : $showText ) .
			'</span><i class="fa fa-chevron-' . ( $isOpen ? 'up' : 'down' ) . '"></i></button>';
		return $html;
	}

	public function getMobileBreakpointOptionDataPro( $settings ) {
		$titleMobileBreakpointData = '';

		if (method_exists(FrameWpf::_()->getModule('woofilters')->getView(), 'getMobileBreakpointValue')) {
			$mobileBreakpointWidth = FrameWpf::_()->getModule('woofilters')->getView()->getMobileBreakpointValue($settings);

			if ($mobileBreakpointWidth) {
				$showTitleDesctop =
					$this->getFilterSetting(
						$settings,
						'display_hide_button',
						'no'
				);
				$showTitleMobile =
					$this->getFilterSetting(
						$settings,
						'display_hide_button_mobile',
						'no'
				);
				$isBtnFloatingOnMobile =
					$this->getFilterSetting(
						$settings,
						'display_hide_button_floating',
						false
					);
				$floatingBtnParams = $isBtnFloatingOnMobile
					? array(
						'left' => array(
							'value' => $this->getFilterSetting($settings, 'display_hide_button_floating_left', '50'),
							'in' => $this->getFilterSetting($settings, 'display_hide_button_floating_left_in', '%'),
						),
						'bottom' => array(
							'value' => $this->getFilterSetting($settings, 'display_hide_button_floating_bottom', '20'),
							'in' => $this->getFilterSetting($settings, 'display_hide_button_floating_bottom_in', 'px'),
						),
					)
					: array();

				if ($isBtnFloatingOnMobile) {
					$this->setFilterCss('.wfpHideButton.wpfHideButtonMobile {position:fixed;z-index:99;max-width:90%;}');
					if ('%' == $floatingBtnParams['left']['in']) {
						$this->setFilterCss('.wfpHideButton.wpfHideButtonMobile {left:' . esc_html($floatingBtnParams['left']['value']) . '%;transform:translateX(-50%);}');
					} else {
						$this->setFilterCss('.wfpHideButton.wpfHideButtonMobile {left:' . esc_html($floatingBtnParams['left']['value']) . 'px;}');
					}
					$this->setFilterCss('.wfpHideButton.wpfHideButtonMobile {bottom:' . esc_html($floatingBtnParams['bottom']['value']) . esc_html($floatingBtnParams['bottom']['in']) . ';}');
				}

				if ('no' != $showTitleDesctop || 'no' != $showTitleMobile) {
					$titleMobileBreakpointData =
						' data-show-on-mobile="' . esc_html($showTitleMobile)
						. '" data-show-on-desctop="' . esc_html($showTitleDesctop)
						. '" data-button-mobile-floating="' . esc_html($isBtnFloatingOnMobile) . '"';
				}
			}
		}

		return $titleMobileBreakpointData;
	}

	public function controlFilterSettings( $filter ) {
		$id = $filter['id'];
		$settings = $this->getFilterSetting($filter, 'settings', array());
		$type = $this->getFilterSetting($settings, 'f_frontend_type', 'list');
		$blockAttributes = '';

		if ( ( 'list' == $type ) && $this->getFilterSetting($settings, 'f_abc_index', false) ) {
			$filter['settings']['f_sort_by'] = 'asc';
			unset($filter['settings']['f_order_custom']);
			$blockAttributes .= ' data-abc="1" ';
		}

		$collapsibleList = FrameWpf::_()->getModule('woofilterpro')->getCollapsibleFiltreOptions($id);
		if ( ( in_array( $type, $collapsibleList ) ) && $this->getFilterSetting( $settings, 'f_multi_collapsible', false ) ) {
			$blockAttributes .= ' data-collapsible="1" ';
			if ( $this->getFilterSetting( $settings, 'f_multi_unfold_child', false ) ) {
				$blockAttributes .= ' data-autounfold="1" ';
				if ( $this->getFilterSetting( $settings, 'f_multi_unfold_all_levels', false ) ) {
					$blockAttributes .= ' data-autounfold-all-levels="1" ';
				}
			}
		}
		$maxShowMore = $this->getFilterSetting($settings, 'f_max_show_more', 0, 1);

		if (!empty($maxShowMore) && 'dropdown' != $type && 'mul_dropdown' != $type ) {
			$blockAttributes .= ' data-max-showmore="' . $maxShowMore . '" ';
		}
		if ($this->getFilterSetting($settings, 'f_default_stock', 0, 1)) {
			$blockAttributes .= ' data-filter-default="' . $this->getFilterSetting($settings, 'f_hidden_stock_status') . '" ';
		}

		if (!empty($blockAttributes)) {
			$filter['blockAttributes'] = ( empty($filter['blockAttributes']) ? '' : $filter['blockAttributes'] ) . $blockAttributes;
		}


		return $filter;
	}

	public function addCustomCss( $settings, $filterId ) {
		$disabled = $this->getFilterSetting($settings, 'disable_plugin_styles', false);

		$useBlockStyles = !$disabled && $this->getFilterSetting($settings, 'use_block_styles', false);
		$useTitleStyles = !$disabled && $this->getFilterSetting($settings, 'use_title_styles', false);
		$useButtonStyles = !$disabled && $this->getFilterSetting($settings, 'use_button_styles', false);
		$useHideButtonStyles = !$disabled && $this->getFilterSetting($settings, 'use_hide_button_styles', false);
		$useFloatingMode = $this->getFilterSetting($settings, 'floating_mode', false);
		if ($useFloatingMode) {
			$isMobile = UtilsWpf::isMobile();
			$devices = $this->getFilterSetting( $settings, 'floating_devices' );
			if ( ( $isMobile && ( 'desktop' == $devices ) ) || ( !$isMobile && ( 'mobile' == $devices ) ) ) {
				$useFloatingMode = false;
			}
		}

		if ( !$useBlockStyles && !$useTitleStyles && !$useButtonStyles && !$useHideButtonStyles && !$useFloatingMode) {
			return '';
		}

		$styles = $this->getFilterSetting($settings, 'styles', array());
		if (empty($styles)) {
			return '';
		}

		$module = $this->getModule();

		$standartFonts   = $module->getStandardFontsList();
		$defaultFont     = $module->defaultFont;
		$stylesCss       = array();
		$fonts           = array();
		$wrapperSelector = '#' . $filterId;
		$important       = ' !important';
		$sides           = array('top', 'right', 'bottom', 'left');
		//$contents        = array('chevron' => array('\f078', '\f077'), 'angle-double' => array('\f103', '\f102'));

		// custom blocks styles
		if ($useBlockStyles) {
			$blockSelector = $wrapperSelector . ' .wpfFilterWrapper';

			$categoriesSizeIcon = $this->getFilterSetting($styles, 'categories_size_icon', 14);
			$categoriesBoldIcon = $this->getFilterSetting($styles, 'categories_bold_icon', 0);
			$blockCategories    = $blockSelector . ' .wpfFilterVerScroll';
			$stylesCss[$blockCategories . ' i, ' . $blockCategories . ' svg']['float']             = 'none' . $important;
			$stylesCss[$blockCategories . ' i, ' . $blockCategories . ' svg']['font-size'] = $categoriesSizeIcon . 'px' . $important;
			if ('1' === $categoriesBoldIcon) {
				$stylesCss[$blockCategories . ' i, ' . $blockCategories . ' svg']['font-weight'] = 'bold' . $important;
			}

			$font = $this->getFilterSetting($styles, 'block_font_family', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$blockSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}

			$weight = $this->getFilterSetting($styles, 'block_font_style', '');
			if ('n' == $weight) {
				$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
			} else if ( 'b' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-weight'] = 'bold' . $important;
			}
			if ( 'i' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-style'] = 'italic' . $important;
				if ('i' == $weight) {
					$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
				}
			}

			$color = $this->getFilterSetting($styles, 'block_font_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['color'] = $color . $important;
			}
			$size = $this->getFilterSetting($styles, 'block_font_size', '');
			if (!empty($size)) {
				$stylesCss[$blockSelector]['font-size'] = $size . 'px' . $important;
			}

			// styles for the selected element
			$selectSelector = $blockSelector . ' .wpfDisplay.selected .wpfFilterTaxNameWrapper';

			$font = $this->getFilterSetting($styles, 'block_font_family_selected', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$selectSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}

			$weight = $this->getFilterSetting($styles, 'block_font_style_selected', '');
			if ('n' == $weight) {
				$stylesCss[$selectSelector]['font-weight'] = 'normal' . $important;
			} else if ( 'b' == $weight || 'bi' == $weight ) {
				$stylesCss[$selectSelector]['font-weight'] = 'bold' . $important;
			}
			if ( 'i' == $weight || 'bi' == $weight ) {
				$stylesCss[$selectSelector]['font-style'] = 'italic' . $important;
				if ('i' == $weight) {
					$stylesCss[$selectSelector]['font-weight'] = 'normal' . $important;
				}
			}

			$color = $this->getFilterSetting($styles, 'block_font_color_selected', '');
			if (!empty($color)) {
				$stylesCss[$selectSelector]['color'] = $color . $important;
			}
			$size = $this->getFilterSetting($styles, 'block_font_size_selected', '');
			if (!empty($size)) {
				$stylesCss[$selectSelector]['font-size'] = $size . 'px' . $important;
			}


			$color = $this->getFilterSetting($styles, 'block_bg_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['background-color'] = $color . $important;
			}

			$style = $this->getFilterSetting($styles, 'block_border_style', '');
			if (!empty($style)) {
				$stylesCss[$blockSelector]['border-style'] = $style . $important;
			}
			$color = $this->getFilterSetting($styles, 'block_border_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['border-color'] = $color . $important;
			}
			$color = $this->getFilterSetting($styles, 'block_selected_item_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector . ' .selected']['color'] = $color . $important;
			}
			$height = $this->getFilterSetting($styles, 'block_line_height', '', true, false, true);
			if ('' != $height) {
				$stylesCss[$blockSelector . ' .wpfFilterVerScroll li']['line-height'] = $height . 'px' . $important;
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'block_border_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['border-' . $side . '-width'] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'block_padding_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['padding-' . $side] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'block_margin_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['margin-' . $side] = $width . 'px' . $important;
				}
			}
			$checkbox = $this->getFilterSetting($styles, 'block_checkbox_type', '');
			if (!empty($checkbox)) {
				$blockSelector = $wrapperSelector . ' .wpfFilterWrapper .wpfCheckbox input';
				$stylesCss[$blockSelector]['display'] = 'none' . $important;

				$blockSelector = $wrapperSelector . ' .wpfFilterWrapper .wpfCheckbox label';
				$size = $this->getFilterSetting($styles, 'block_checkbox_size', 16, true, false, true);
				if (!empty($size)) {
					$stylesCss[$blockSelector]['width'] = $size . 'px' . $important;
					$stylesCss[$blockSelector]['height'] = $size . 'px' . $important;
				}

				$blockSelector = $wrapperSelector . ' .wpfFilterWrapper .wpfCheckbox label::before';

				if ('circle' == $checkbox) {
					$stylesCss[$blockSelector]['border-radius'] = '50%' . $important;
				} else if ('square' == $checkbox) {
					$stylesCss[$blockSelector]['border-radius'] = '0' . $important;
				} else if ('round' == $checkbox) {
					$stylesCss[$blockSelector]['border-radius'] = '5px' . $important;
				}

				$color = $this->getFilterSetting($styles, 'block_checkbox_border', '');
				if (!empty($color)) {
					$stylesCss[$blockSelector]['border-color'] = $color . $important;
				}
				$color = $this->getFilterSetting($styles, 'block_checkbox_color', '');
				if (!empty($color)) {
					$stylesCss[$blockSelector]['background-color'] = $color . $important;
				}

				$blockSelector = $wrapperSelector . ' .wpfFilterWrapper .wpfCheckbox input[type="checkbox"]:checked + label::after';
				$mark = $this->getFilterSetting($styles, 'block_checkbox_mark_color', '');
				if (!empty($mark)) {
					$stylesCss[$blockSelector]['color'] = $mark . $important;
					$stylesCss[$blockSelector]['font-family'] = 'FontAwesome' . $important;
					$stylesCss[$blockSelector]['content'] = '"\f00c"' . $important;
					$stylesCss[$blockSelector]['width'] = $size . 'px' . $important;
					$stylesCss[$blockSelector]['height'] = $size . 'px' . $important;
					$stylesCss[$blockSelector]['top'] = '0px' . $important;
					$stylesCss[$blockSelector]['left'] = '0px' . $important;
					$stylesCss[$blockSelector]['text-align'] = 'center' . $important;
					$stylesCss[$blockSelector]['transform'] = 'none' . $important;
					$stylesCss[$blockSelector]['font-size'] = ( $size * 0.7 ) . 'px' . $important;
					$stylesCss[$blockSelector]['line-height'] = $size . 'px' . $important;
					$stylesCss[$blockSelector]['margin'] = '0 1px' . $important;
				}

				$blockSelector = $wrapperSelector . ' .wpfFilterWrapper .wpfCheckbox input[type="checkbox"]:checked + label::before';
				if (!empty($mark)) {
					$stylesCss[$blockSelector]['background'] = 'none' . $important;
				}
				$color = $this->getFilterSetting($styles, 'block_checkbox_checked_color', '');
				if (!empty($color)) {
					$stylesCss[$blockSelector]['background-color'] = $color . $important;
				}
				$borderColor = $this->getFilterSetting( $styles, 'block_checkbox_checked_border_color', '' );
				if ( ! empty( $borderColor ) ) {
					$stylesCss[ $blockSelector ]['border-color'] = $borderColor . $important;
				}

			}


		}

		// custom title styles
		if ($useTitleStyles) {
			$blockSelector = $wrapperSelector . ' .wpfFilterTitle';

			$font = $this->getFilterSetting($styles, 'title_font_family', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$blockSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}

			$weight = $this->getFilterSetting($styles, 'title_font_style', '');
			if ('n' == $weight) {
				$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
			} else if ( 'b' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-weight'] = 'bold' . $important;
			}
			if ( 'i' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-style'] = 'italic' . $important;
				if ('i' == $weight) {
					$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
				}
			}

			$color = $this->getFilterSetting($styles, 'title_font_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['color'] = $color . $important;
			}
			$size = $this->getFilterSetting($styles, 'title_font_size', '');
			if (!empty($size)) {
				$titleSelector = $blockSelector . ' .wfpTitle, ' . $blockSelector . ' i';
				$stylesCss[$titleSelector]['font-size'] = $size . 'px' . $important;
				$stylesCss[$titleSelector]['line-height'] = $size . 'px' . $important;
				$stylesCss[$titleSelector]['height'] = 'auto' . $important;
			}

			$color = $this->getFilterSetting($styles, 'title_bg_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['background-color'] = $color . $important;
			}

			$style = $this->getFilterSetting($styles, 'title_border_style', '');
			if (!empty($style)) {
				$stylesCss[$blockSelector]['border-style'] = $style . $important;
			}
			$color = $this->getFilterSetting($styles, 'title_border_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['border-color'] = $color . $important;
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'title_border_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['border-' . $side . '-width'] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'title_padding_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['padding-' . $side] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'title_margin_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['margin-' . $side] = $width . 'px' . $important;
				}
			}

			$iconsPosition = $this->getFilterSetting($styles, 'title_icons_position', 'after-right');
			if ('after' == $iconsPosition) {
				$stylesCss[$blockSelector . ' .wpfTitleToggle']['float'] = 'none' . $important;
				$stylesCss[$blockSelector . ' .wpfTitleToggle']['margin-left'] = '3px';
			} elseif ('before' == $iconsPosition) {
				$stylesCss[$blockSelector . ' .wpfTitleToggle']['float'] = 'left' . $important;
				$stylesCss[$blockSelector . ' .wpfTitleToggle']['margin-right'] = '3px';
			} elseif ('before-left' == $iconsPosition) {
				$stylesCss[$blockSelector . ' .wpfTitleToggle']['float'] = 'left' . $important;
				$stylesCss[$blockSelector . ' .wfpTitle']['float'] = 'right' . $important;
			}

			$minHeight = $this->getFilterSetting($styles, 'title_min_height', '');
			if ($minHeight) {
				$stylesCss[$blockSelector]['min-height'] = $minHeight . 'px' . $important;
			}
		}

		// custom buttons styles
		if ($useButtonStyles) {
			$blockSelector = $wrapperSelector . ' .wpfFilterButtons';
			$buttonSelector = $blockSelector . ' .wpfButton';
			$buttonHover = $buttonSelector . ':hover';
			$effects = array('' => $buttonSelector, '_hover' => $buttonHover);
			$stylesCss[$buttonSelector]['overflow'] = 'hidden' . $important;

			$align = $this->getFilterSetting($styles, 'button_block_align', '', false, array('center', 'left', 'right'));
			if (!empty($align)) {
				$stylesCss[$blockSelector]['text-align'] = $align . $important;
			}

			$float = $this->getFilterSetting($styles, 'button_block_float', '', false, array('left', 'right'));
			if (!empty($float)) {
				$stylesCss[$blockSelector]['float'] = $float . $important;
				$stylesCss[$blockSelector]['clear'] = 'none' . $important;
			}

			$font = $this->getFilterSetting($styles, 'button_font_family', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$buttonSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}

			$size = $this->getFilterSetting($styles, 'button_font_size', '');
			if (!empty($size)) {
				$stylesCss[$buttonSelector]['font-size'] = $size . 'px' . $important;
				$stylesCss[$buttonSelector]['line-height'] = $size . 'px' . $important;
			}

			foreach ($effects as $effect => $selector) {
				$color = $this->getFilterSetting($styles, 'button_font_color' . $effect, '');
				if (!empty($color)) {
					$stylesCss[$selector]['color'] = $color . $important;
				}
				$weight = $this->getFilterSetting($styles, 'button_font_style' . $effect, '');
				if ('n' == $weight) {
					$stylesCss[$selector]['font-weight'] = 'normal' . $important;
				} else if ( 'b' == $weight || 'bi' == $weight ) {
					$stylesCss[$selector]['font-weight'] = 'bold' . $important;
				}
				if ( 'i' == $weight || 'bi' == $weight ) {
					$stylesCss[$selector]['font-style'] = 'italic' . $important;
					if ('i' == $weight) {
						$stylesCss[$selector]['font-weight'] = 'normal' . $important;
					}
				}
			}

			// text shadow
			$x = $this->getFilterSetting($styles, 'button_text_shadow_x', '', true, false, true);
			$y = $this->getFilterSetting($styles, 'button_text_shadow_y', '', true, false, true);
			if ( '' !== $x && '' !== $y ) {
				$value = $x . 'px ' . $y . 'px';
				$blur = $this->getFilterSetting($styles, 'button_text_shadow_blur', '', true, false, true);
				if ('' !== $blur) {
					$value .= ' ' . $blur . 'px';
				}
				$color = $this->getFilterSetting($styles, 'button_text_shadow_color', '');
				if (!empty($color)) {
					$value .= ' ' . $color;
				}
				$stylesCss[$buttonSelector]['text-shadow'] = $value . $important;
			}

			// button size
			$bWidth = $this->getFilterSetting($styles, 'button_width', '', true);
			if (!empty($bWidth)) {
				$stylesCss[$buttonSelector]['width'] = $bWidth . $this->getFilterSetting($styles, 'button_width_unit', '%') . $important;
				$stylesCss[$buttonSelector]['overflow'] = 'hidden' . $important;
			}
			$bHeight = $this->getFilterSetting($styles, 'button_height', '', true);
			if (!empty($bHeight)) {
				$stylesCss[$buttonSelector]['height'] = $bHeight . 'px' . $important;
				$stylesCss[$buttonSelector]['min-height'] = '0' . $important;
			}

			// radius coners
			$radius = $this->getFilterSetting($styles, 'button_radius', '', true, false, true);
			if ('' !== $radius) {
				$stylesCss[$buttonSelector]['border-radius'] = $radius . $this->getFilterSetting($styles, 'button_radius_unit', 'px') . $important;
			}

			foreach ($effects as $effect => $selector) {
				// borders
				$color = $this->getFilterSetting($styles, 'button_border_color' . $effect, '');
				foreach ($sides as $side) {
					$width = $this->getFilterSetting($styles, 'button_border_' . $side . $effect, '', true, false, true);
					if ('' != $width) {
						$stylesCss[$selector]['border-' . $side] = $width . 'px solid ' . $color . $important;
					}
				}

				// button shadow
				$x = $this->getFilterSetting($styles, 'button_shadow_x' . $effect, '', true, false, true);
				$y = $this->getFilterSetting($styles, 'button_shadow_y' . $effect, '', true, false, true);
				if ( '' !== $x && '' !== $y ) {
					$value = $x . 'px ' . $y . 'px';
					$blur = $this->getFilterSetting($styles, 'button_shadow_blur' . $effect, '', true, false, true);
					if ('' !== $blur) {
						$value .= ' ' . $blur . 'px';
					}
					$spread = $this->getFilterSetting($styles, 'button_shadow_spread' . $effect, '', true, false, true);
					if ('' !== $spread) {
						$value .= ' ' . $spread . 'px';
					}
					$color = $this->getFilterSetting($styles, 'button_shadow_color' . $effect, '');
					if (!empty($color)) {
						$value .= ' ' . $color;
					}
					$stylesCss[$selector]['box-shadow'] = $value . $important;
				}

				// button background
				$bgType = $this->getFilterSetting($styles, 'button_bg_type' . $effect, '');
				if (!empty($bgType)) {
					if ('unicolored' == $bgType) {
						$color = $this->getFilterSetting($styles, 'button_bg_color' . $effect, '');
						if (!empty($color)) {
							$stylesCss[$selector]['background'] = $color . $important;
						}
					} else {
						$color1 = $this->getFilterSetting($styles, 'button_bg_color1' . $effect, '');
						$color2 = $this->getFilterSetting($styles, 'button_bg_color2' . $effect, '');
						if (!empty($color1)) {
							$stylesCss[$selector]['background'] = $color1; // for Old browsers
							if (!empty($color2)) {
								switch ($bgType) {
									case 'bicolored':
										$value = 'linear-gradient( to bottom, ' . $color1 . ' 50%, ' . $color2 . ' 50% )';
										break;
									case 'gradient':
										$value = 'linear-gradient( to bottom, ' . $color1 . ', ' . $color2 . ')';
										break;
									case 'pyramid':
										$value = 'linear-gradient( to bottom, ' . $color1 . ' 0%, ' . $color2 . ' 50%, ' . $color1 . ' 100% )';
										break;
									default:
										$value = '';
										break;
								}
								if (!empty($value)) {
									$stylesCss[$selector]['background'] = '-webkit-' . $value . $important;
									$stylesCss[$selector]['background'] = '-moz-' . $value . $important;
									$stylesCss[$selector]['background'] = '-o-' . $value . $important;
									$stylesCss[$selector]['background'] = $value . $important;
								}
							}
						}
					}
				}
			}

			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'button_padding_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$buttonSelector]['padding-' . $side] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'button_margin_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$buttonSelector]['margin-' . $side] = $width . 'px' . $important;
				}
			}
		}

		// hide filter button custom css
		$hb_styles = $this->getFilterSetting($settings, 'hide_button', array());
		if ($useHideButtonStyles && $hb_styles) {

			$blockSelector = $wrapperSelector;
			$hideButtonSelector = $blockSelector . ' .wfpHideButton';
			$hideButtonHover = $hideButtonSelector . ':hover';
			$hb_effects = array('' => $hideButtonSelector, '_hover' => $hideButtonHover);
			$stylesCss[$hideButtonSelector]['overflow'] = 'hidden' . $important;

			//$align = $this->getFilterSetting($hb_styles, 'button_block_align', '', false, array('center', 'left', 'right'));
			//if (!empty($align)) {
			//$stylesCss[$blockSelector]['text-align'] = $align . $important;
			//}

			$float = $this->getFilterSetting($hb_styles, 'button_block_float', '', false, array('left', 'right'));
			if (!empty($float)) {
				$stylesCss[$blockSelector]['float'] = $float . $important;
				$stylesCss[$blockSelector]['clear'] = 'none' . $important;
			}



			$font = $this->getFilterSetting($hb_styles, 'button_font_family', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$hideButtonSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}

			$size = $this->getFilterSetting($hb_styles, 'button_font_size', '');
			if (!empty($size)) {
				$stylesCss[$hideButtonSelector]['font-size'] = $size . 'px' . $important;
				$stylesCss[$hideButtonSelector]['line-height'] = $size . 'px' . $important;
			}

			foreach ($hb_effects as $effect => $selector) {
				$color = $this->getFilterSetting($hb_styles, 'button_font_color' . $effect, '');
				if (!empty($color)) {
					$stylesCss[$selector]['color'] = $color . $important;
				}
				$weight = $this->getFilterSetting($hb_styles, 'button_font_style' . $effect, '');
				if ('n' == $weight) {
					$stylesCss[$selector]['font-weight'] = 'normal' . $important;
				} else if ( 'b' == $weight || 'bi' == $weight ) {
					$stylesCss[$selector]['font-weight'] = 'bold' . $important;
				}
				if ( 'i' == $weight || 'bi' == $weight ) {
					$stylesCss[$selector]['font-style'] = 'italic' . $important;
					if ('i' == $weight) {
						$stylesCss[$selector]['font-weight'] = 'normal' . $important;
					}
				}
			}

			// text shadow
			$x = $this->getFilterSetting($hb_styles, 'button_text_shadow_x', '', true, false, true);
			$y = $this->getFilterSetting($hb_styles, 'button_text_shadow_y', '', true, false, true);
			if ( '' !== $x && '' !== $y ) {
				$value = $x . 'px ' . $y . 'px';
				$blur = $this->getFilterSetting($hb_styles, 'button_text_shadow_blur', '', true, false, true);
				if ('' !== $blur) {
					$value .= ' ' . $blur . 'px';
				}
				$color = $this->getFilterSetting($hb_styles, 'button_text_shadow_color', '');
				if (!empty($color)) {
					$value .= ' ' . $color;
				}
				$stylesCss[$hideButtonSelector]['text-shadow'] = $value . $important;
			}

			// button size
			$bWidth = $this->getFilterSetting($hb_styles, 'button_width', '', true);
			if (!empty($bWidth)) {
				$stylesCss[$hideButtonSelector]['width'] = $bWidth . $this->getFilterSetting($hb_styles, 'button_width_unit', '%') . $important;
				$stylesCss[$hideButtonSelector]['overflow'] = 'hidden' . $important;
			}
			$bMaxWidth = $this->getFilterSetting($hb_styles, 'button_max_width', '', true);
			if (!empty($bMaxWidth)) {
				$stylesCss[$hideButtonSelector]['max-width'] = $bMaxWidth . $this->getFilterSetting($hb_styles, 'button_max_width_unit', 'px') . $important;
			}
			$bHeight = $this->getFilterSetting($hb_styles, 'button_height', '', true);
			if (!empty($bHeight)) {
				$stylesCss[$hideButtonSelector]['height'] = $bHeight . 'px' . $important;
				$stylesCss[$hideButtonSelector]['min-height'] = '0' . $important;
			}

			// radius coners
			$radius = $this->getFilterSetting($hb_styles, 'button_radius', '', true, false, true);
			if ('' !== $radius) {
				$stylesCss[$hideButtonSelector]['border-radius'] = $radius . $this->getFilterSetting($hb_styles, 'button_radius_unit', 'px') . $important;
			}

			foreach ($hb_effects as $effect => $selector) {
				// borders
				$color = $this->getFilterSetting($hb_styles, 'button_border_color' . $effect, '');
				foreach ($sides as $side) {
					$width = $this->getFilterSetting($hb_styles, 'button_border_' . $side . $effect, '', true, false, true);
					if ('' != $width) {
						$stylesCss[$selector]['border-' . $side] = $width . 'px solid ' . $color . $important;
					}
				}

				// button shadow
				$x = $this->getFilterSetting($hb_styles, 'button_shadow_x' . $effect, '', true, false, true);
				$y = $this->getFilterSetting($hb_styles, 'button_shadow_y' . $effect, '', true, false, true);
				if ( '' !== $x && '' !== $y ) {
					$value = $x . 'px ' . $y . 'px';
					$blur = $this->getFilterSetting($hb_styles, 'button_shadow_blur' . $effect, '', true, false, true);
					if ('' !== $blur) {
						$value .= ' ' . $blur . 'px';
					}
					$spread = $this->getFilterSetting($hb_styles, 'button_shadow_spread' . $effect, '', true, false, true);
					if ('' !== $spread) {
						$value .= ' ' . $spread . 'px';
					}
					$color = $this->getFilterSetting($hb_styles, 'button_shadow_color' . $effect, '');
					if (!empty($color)) {
						$value .= ' ' . $color;
					}
					$stylesCss[$selector]['box-shadow'] = $value . $important;
				}

				// button background
				$bgType = $this->getFilterSetting($hb_styles, 'button_bg_type' . $effect, '');
				if (!empty($bgType)) {
					if ('unicolored' == $bgType) {
						$color = $this->getFilterSetting($hb_styles, 'button_bg_color' . $effect, '');
						if (!empty($color)) {
							$stylesCss[$selector]['background'] = $color . $important;
						}
					} else {
						$color1 = $this->getFilterSetting($hb_styles, 'button_bg_color1' . $effect, '');
						$color2 = $this->getFilterSetting($hb_styles, 'button_bg_color2' . $effect, '');
						if (!empty($color1)) {
							$stylesCss[$selector]['background'] = $color1; // for Old browsers
							if (!empty($color2)) {
								switch ($bgType) {
									case 'bicolored':
										$value = 'linear-gradient( to bottom, ' . $color1 . ' 50%, ' . $color2 . ' 50% )';
										break;
									case 'gradient':
										$value = 'linear-gradient( to bottom, ' . $color1 . ', ' . $color2 . ')';
										break;
									case 'pyramid':
										$value = 'linear-gradient( to bottom, ' . $color1 . ' 0%, ' . $color2 . ' 50%, ' . $color1 . ' 100% )';
										break;
									default:
										$value = '';
										break;
								}
								if (!empty($value)) {
									$stylesCss[$selector]['background'] = '-webkit-' . $value . $important;
									$stylesCss[$selector]['background'] = '-moz-' . $value . $important;
									$stylesCss[$selector]['background'] = '-o-' . $value . $important;
									$stylesCss[$selector]['background'] = $value . $important;
								}
							}
						}
					}
				}
			}

			foreach ($sides as $side) {
				$width = $this->getFilterSetting($hb_styles, 'button_padding_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$hideButtonSelector]['padding-' . $side] = $width . 'px' . $important;
				}
			}
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($hb_styles, 'button_margin_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$hideButtonSelector]['margin-' . $side] = $width . 'px' . $important;
				}
			}
		}

		// floating mode styles
		if ($useFloatingMode) {
			$styles = $this->getFilterSetting($styles, $isMobile ? 'fmobile' : 'fdesktop', array());
			$viewId = explode('-', $filterId)[1];
			$wrapperSelector = '#wpfFloatingWrapper-' . $viewId;

			//popup title
			$blockSelector = $wrapperSelector . ' .wpfFloatingTitle';
			$titleSelector = $blockSelector;

			$font = $this->getFilterSetting($styles, 'title_font_family', '');
			if ( !empty($font) && $font != $defaultFont ) {
				$stylesCss[$blockSelector]['font-family'] = '"' . $font . '"';
				if (!in_array($font, $standartFonts)) {
					$fonts[str_replace(' ', '+', $font)] = $font;
				}
			}
			$size = $this->getFilterSetting($styles, 'title_font_size', '');
			if (!empty($size)) {
				$stylesCss[$blockSelector]['font-size'] = $size . 'px' . $important;
				$stylesCss[$blockSelector]['line-height'] = $size . 'px' . $important;
			}
			$titlePadding = 0;
			$rightPadding = 10;
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'title_padding_' . $side, '', true, false, true);
				if ('' != $width && $width >= 0) {
					if ('right' == $side) {
						$rightPadding = $width;
						$width += 20;
					}
					$stylesCss[$blockSelector]['padding-' . $side] = $width . 'px' . $important;
				}

				if ( 'top' === $side || 'bottom' === $side ) {
					$titlePadding += ( '' !== $width && $width >= 0 ) ? $width : 10;
				}

			}
			$titleSize = ( empty($size) ? 16 : $size ) + $titlePadding; // with padding
			$stylesCss[$blockSelector]['min-height'] = $titleSize . 'px' . $important;
			$color = $this->getFilterSetting($styles, 'title_font_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['color'] = $color . $important;
			}
			$weight = $this->getFilterSetting($styles, 'title_font_style', '');
			if ('n' == $weight) {
				$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
			} else if ( 'b' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-weight'] = 'bold' . $important;
			}
			if ( 'i' == $weight || 'bi' == $weight ) {
				$stylesCss[$blockSelector]['font-style'] = 'italic' . $important;
				if ('i' == $weight) {
					$stylesCss[$blockSelector]['font-weight'] = 'normal' . $important;
				}
			}
			$color = $this->getFilterSetting($styles, 'title_bg_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['background'] = $color . $important;
			}
			$color = $this->getFilterSetting($styles, 'title_border_color', '');
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'title_border_' . $side, '', true, false, true);
				if ('' != $width) {
					$stylesCss[$blockSelector]['border-' . $side] = $width . 'px solid ' . $color . $important;
				}
			}

			//popup close icon
			$blockSelector = $wrapperSelector . ' .wpfFloatingClose';
			$size = $this->getFilterSetting($styles, 'popup_close_size', '');
			if (!empty($size)) {
				$stylesCss[$blockSelector]['font-size'] = $size . 'px' . $important;
				$stylesCss[$blockSelector]['line-height'] = $size . 'px' . $important;
			}
			$color = $this->getFilterSetting($styles, 'popup_close_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['color'] = $color . $important;
			}
			//if ($titleSize < $size)
			$position = round(( $titleSize - (int) $size ) / 2);
			$stylesCss[$blockSelector]['top'] = $position . 'px' . $important;
			$stylesCss[$blockSelector]['right'] = $rightPadding . 'px' . $important;

			//$blockSelector = $wrapperSelector . '.wpfFloating';
			$blockSelector = $wrapperSelector;

			$width = $this->getFilterSetting($styles, 'popup_width', '', true, false, true);
			$units = $this->getFilterSetting($styles, 'popup_width_unit', '%', false, array('%', 'px'));
			$stylesCss[$blockSelector]['width'] = ( empty($width) ? 'auto' : $width . $units ) . $important;

			$height = $this->getFilterSetting($styles, 'popup_height', '', true, false, true);
			$units = $this->getFilterSetting($styles, 'popup_height_unit', '%', false, array('%', 'px'));
			$stylesCss[$blockSelector]['height'] = ( empty($height) ? 'auto' : $height . $units ) . $important;

			$color = $this->getFilterSetting($styles, 'popup_bg_color', '');
			if (!empty($color)) {
				$stylesCss[$blockSelector]['background'] = $color . $important;
			}
			// borders
			$color = $this->getFilterSetting($styles, 'popup_border_color', '');
			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'popup_border_' . $side, '', true, false, true);
				if ('' !== $width && $width >= 0) {
					$stylesCss[$blockSelector]['border-' . $side] = $width . 'px solid ' . $color . $important;
					if ('top' == $side || 'bottom' == $side) {
						$titleSize += $width;
					}
				}
			}
			// shadow
			$x = $this->getFilterSetting($styles, 'popup_shadow_x', '', true, false, true);
			$y = $this->getFilterSetting($styles, 'popup_shadow_y', '', true, false, true);
			if ( '' !== $x && '' !== $y ) {
				$value = $x . 'px ' . $y . 'px';
				$blur = $this->getFilterSetting($styles, 'popupn_shadow_blur', '', true, false, true);
				if ('' !== $blur) {
					$value .= ' ' . $blur . 'px';
				}
				$spread = $this->getFilterSetting($styles, 'popup_shadow_spread', '', true, false, true);
				if ('' !== $spread) {
					$value .= ' ' . $spread . 'px';
				}
				$color = $this->getFilterSetting($styles, 'popup_shadow_color', '');
				if (!empty($color)) {
					$value .= ' ' . $color;
				}
				$stylesCss[$blockSelector]['box-shadow'] = $value . $important;
			}
			// radius coners
			$radius = $this->getFilterSetting($styles, 'popup_radius', '', true, false, true);
			$unit = $this->getFilterSetting($styles, 'popup_radius_unit', 'px');
			if ('' !== $radius) {
				$stylesCss[$blockSelector]['border-radius'] = $radius . $unit . $important;
			}

			//position
			$side = $this->getFilterSetting($styles, 'popup_arrival_side', 'right');
			$top = 0;
			if ('right' == $side || 'left' == $side) {
				$t = $this->getFilterSetting($styles, 'popup_position_top', '', true, false, true);
				$b = $this->getFilterSetting($styles, 'popup_position_bottom', '', true, false, true);
				$stylesCss[$blockSelector][( '' == $b ? 'top' : 'bottom' )] = ( '' == $b ? ( '' == $t ? '0' : $t . 'px' ) : $b . 'px' ) . $important;
				if ('' == $b && '' != $t) {
					$top = $t;
				}
			} else {
				$l = $this->getFilterSetting($styles, 'popup_position_left', '', true, false, true);
				$r = $this->getFilterSetting($styles, 'popup_position_right', '', true, false, true);
				$stylesCss[$blockSelector][( '' == $l ? 'right' : 'left' )] = ( '' == $l ? ( '' == $r ? '0' : $r . 'px' ) : $l . 'px' ) . $important;
			}

			// scrollbar
			$bodySelector = $wrapperSelector . ' .wpfFloating';

			$stylesCss[$bodySelector]['max-height'] = 'calc(100% - ' . ( $titleSize + $top ) . 'px)' . $important;
			$width = $this->getFilterSetting($styles, 'popup_scrollbar_width', 'auto');
			if ('auto' != $width) {
				$stylesCss[$bodySelector]['scrollbar-width'] = $width . $important;
				$stylesCss[$bodySelector . '::-webkit-scrollbar']['width'] = ( 'none' == $width ? 0 : 8 ) . 'px' . $important;
			}
			$thumb = $this->getFilterSetting($styles, 'popup_scrollbar_thumb', '');
			$track = $this->getFilterSetting($styles, 'popup_scrollbar_track', '');
			if ('' != $thumb && '' != $track) {
				$stylesCss[$bodySelector]['scrollbar-color'] = $thumb . ' ' . $track . $important;
				$stylesCss[$bodySelector . '::-webkit-scrollbar-thumb']['background-color'] = $thumb . $important;
				$stylesCss[$bodySelector . '::-webkit-scrollbar-track']['background'] = $track . $important;
				if ('auto' == $width) {
					$stylesCss[$bodySelector . '::-webkit-scrollbar']['width'] = '12px' . $important;
				}
			}

			foreach ($sides as $side) {
				$width = $this->getFilterSetting($styles, 'popup_padding_' . $side, 0, true, false, true);
				if ('' != $width && $width >= 0) {
					$stylesCss[$bodySelector]['padding-' . $side] = $width . 'px' . $important;
				}
			}

			// overlay
			$blockSelector = '#wpfFloatingOverlay-' . $viewId;
			$overlay = $this->getFilterSetting($styles, 'popup_overlay', '');
			$percent = $this->getFilterSetting($styles, 'popup_overlay_percent', '', true, false, true);
			if ('blur' == $overlay) {
				$stylesCss[$blockSelector]['backdrop-filter'] = 'blur(' . round($percent * 20 / 100) . 'px)';
			} elseif ('blackout' == $overlay) {
				$stylesCss[$blockSelector]['background-color'] = '#000000' . $important;
				$stylesCss[$blockSelector]['opacity'] = round($percent / 100, 2) . $important;
			}

			//button
			if ($this->getFilterSetting($settings, 'floating_call_button') == 'plugin') {
				$buttonSelector = '#wpfFloatingSwitcher-' . $viewId;
				$buttonHover = $buttonSelector . ':hover';
				$effects = array('' => $buttonSelector, '_hover' => $buttonHover);

				if ($this->getFilterSetting($styles, 'button_fixed', 'fixed') == 'float') {
					$stylesCss[$buttonSelector]['position'] = 'fixed' . $important;
					$stylesCss[$buttonSelector]['z-index'] = '999998';
					$t = $this->getFilterSetting($styles, 'button_position_top', '', true, false, true);
					$b = $this->getFilterSetting($styles, 'button_position_bottom', '', true, false, true);
					$stylesCss[$buttonSelector][( '' == $b ? 'top' : 'bottom' )] = ( '' == $b ? ( '' == $t ? '10%' : $t . 'px' ) : $b . 'px' ) . $important;
					$l = $this->getFilterSetting($styles, 'button_position_left', '', true, false, true);
					$r = $this->getFilterSetting($styles, 'button_position_right', '', true, false, true);
					$stylesCss[$buttonSelector][( '' == $l ? 'right' : 'left' )] = ( '' == $l ? ( '' == $r ? '0' : $r . 'px' ) : $l . 'px' ) . $important;
				}

				$isIcon = $this->getFilterSetting($styles, 'button_type', 'text') == 'icon';
				if ($isIcon) {
					$icon = $this->getFilterSetting($styles, 'button_icon', $module->getDefaultFloatingIcon());
					$parts = explode(';', $icon);
					foreach ($parts as $part) {
						$paar = explode(':', $part);
						if ('background-image' == $paar[0]) {
							$stylesCss[$buttonSelector]['background'] = str_replace('background-image:', '', $part) . $important;
						} elseif (count($paar) == 2) {
							$stylesCss[$buttonSelector][$paar[0]] = $paar[1];
						}
					}
					$stylesCss[$buttonSelector]['background-repeat'] = 'no-repeat' . $important;
					$stylesCss[$buttonSelector]['background-position'] = 'center' . $important;
				} else {
					$font = $this->getFilterSetting($styles, 'button_font_family', '');
					if ( !empty($font) && $font != $defaultFont ) {
						$stylesCss[$buttonSelector]['font-family'] = '"' . $font . '"';
						if (!in_array($font, $standartFonts)) {
							$fonts[str_replace(' ', '+', $font)] = $font;
						}
					}

					$size = $this->getFilterSetting($styles, 'button_font_size', '');
					if (!empty($size)) {
						$stylesCss[$buttonSelector]['font-size'] = $size . 'px' . $important;
						$stylesCss[$buttonSelector]['line-height'] = $size . 'px' . $important;
					}

					foreach ($effects as $effect => $selector) {
						$color = $this->getFilterSetting($styles, 'button_font_color' . $effect, '');
						if (!empty($color)) {
							$stylesCss[$selector]['color'] = $color . $important;
						}
						$weight = $this->getFilterSetting($styles, 'button_font_style' . $effect, '');
						if ('n' == $weight) {
							$stylesCss[$selector]['font-weight'] = 'normal' . $important;
						} else if ( 'b' == $weight || 'bi' == $weight ) {
							$stylesCss[$selector]['font-weight'] = 'bold' . $important;
						}
						if ( 'i' == $weight || 'bi' == $weight ) {
							$stylesCss[$selector]['font-style'] = 'italic' . $important;
							if ('i' == $weight) {
								$stylesCss[$selector]['font-weight'] = 'normal' . $important;
							}
						}
					}

					// text shadow
					$x = $this->getFilterSetting($styles, 'button_text_shadow_x', '', true, false, true);
					$y = $this->getFilterSetting($styles, 'button_text_shadow_y', '', true, false, true);
					if ( '' !== $x && '' !== $y ) {
						$value = $x . 'px ' . $y . 'px';
						$blur = $this->getFilterSetting($styles, 'button_text_shadow_blur', '', true, false, true);
						if ('' !== $blur) {
							$value .= ' ' . $blur . 'px';
						}
						$color = $this->getFilterSetting($styles, 'button_text_shadow_color', '');
						if (!empty($color)) {
							$value .= ' ' . $color;
						}
						$stylesCss[$buttonSelector]['text-shadow'] = $value . $important;
					}
				}

				// button size
				$bWidth = $this->getFilterSetting($styles, 'button_width', '', true);
				if (!empty($bWidth)) {
					$stylesCss[$buttonSelector]['width'] = $bWidth . $this->getFilterSetting($styles, 'button_width_unit', '%') . $important;
					$stylesCss[$buttonSelector]['overflow'] = 'hidden' . $important;
				}
				$bHeight = $this->getFilterSetting($styles, 'button_height', '', true);
				if (!empty($bHeight)) {
					$stylesCss[$buttonSelector]['height'] = $bHeight . 'px' . $important;
					$stylesCss[$buttonSelector]['min-height'] = '0' . $important;
				}

				// radius coners
				$radius = $this->getFilterSetting($styles, 'button_radius', '', true, false, true);
				if ('' !== $radius) {
					$stylesCss[$buttonSelector]['border-radius'] = $radius . $this->getFilterSetting($styles, 'button_radius_unit', 'px') . $important;
				}

				foreach ($effects as $effect => $selector) {
					// borders
					$color = $this->getFilterSetting($styles, 'button_border_color' . $effect, '');
					foreach ($sides as $side) {
						$width = $this->getFilterSetting($styles, 'button_border_' . $side . $effect, '', true, false, true);
						if ('' != $width) {
							$stylesCss[$selector]['border-' . $side] = $width . 'px solid ' . $color . $important;
						}
					}

					// button shadow
					$x = $this->getFilterSetting($styles, 'button_shadow_x' . $effect, '', true, false, true);
					$y = $this->getFilterSetting($styles, 'button_shadow_y' . $effect, '', true, false, true);
					if ( '' !== $x && '' !== $y ) {
						$value = $x . 'px ' . $y . 'px';
						$blur = $this->getFilterSetting($styles, 'button_shadow_blur' . $effect, '', true, false, true);
						if ('' !== $blur) {
							$value .= ' ' . $blur . 'px';
						}
						$spread = $this->getFilterSetting($styles, 'button_shadow_spread' . $effect, '', true, false, true);
						if ('' !== $spread) {
							$value .= ' ' . $spread . 'px';
						}
						$color = $this->getFilterSetting($styles, 'button_shadow_color' . $effect, '');
						if (!empty($color)) {
							$value .= ' ' . $color;
						}
						$stylesCss[$selector]['box-shadow'] = $value . $important;
					}

					// button background
					if (!$isIcon) {
						$bgType = $this->getFilterSetting($styles, 'button_bg_type' . $effect, '');
						if (!empty($bgType)) {
							if ('unicolored' == $bgType) {
								$color = $this->getFilterSetting($styles, 'button_bg_color' . $effect, '');
								if (!empty($color)) {
									$stylesCss[$selector]['background'] = $color . $important;
								}
							} else {
								$color1 = $this->getFilterSetting($styles, 'button_bg_color1' . $effect, '');
								$color2 = $this->getFilterSetting($styles, 'button_bg_color2' . $effect, '');
								if (!empty($color1)) {
									$stylesCss[$selector]['background'] = $color1; // for Old browsers
									if (!empty($color2)) {
										switch ($bgType) {
											case 'bicolored':
												$value = 'linear-gradient( to bottom, ' . $color1 . ' 50%, ' . $color2 . ' 50% )';
												break;
											case 'gradient':
												$value = 'linear-gradient( to bottom, ' . $color1 . ', ' . $color2 . ')';
												break;
											case 'pyramid':
												$value = 'linear-gradient( to bottom, ' . $color1 . ' 0%, ' . $color2 . ' 50%, ' . $color1 . ' 100% )';
												break;
											default:
												$value = '';
												break;
										}
										if (!empty($value)) {
											$stylesCss[$selector]['background'] = '-webkit-' . $value . $important;
											$stylesCss[$selector]['background'] = '-moz-' . $value . $important;
											$stylesCss[$selector]['background'] = '-o-' . $value . $important;
											$stylesCss[$selector]['background'] = $value . $important;
										}
									}
								}
							}
						}
					}
				}

				foreach ($sides as $side) {
					$width = $this->getFilterSetting($styles, 'button_padding_' . $side, '', true, false, true);
					if ('' != $width) {
						$stylesCss[$buttonSelector]['padding-' . $side] = $width . 'px' . $important;
					}
				}
				foreach ($sides as $side) {
					$width = $this->getFilterSetting($styles, 'button_margin_' . $side, '', true, false, true);
					if ('' != $width) {
						$stylesCss[$buttonSelector]['margin-' . $side] = $width . 'px' . $important;
					}
				}

			}
		}

		$customCSS = '';
		foreach ($fonts as $key => $value) {
			$customCSS .= '@import url("//fonts.googleapis.com/css?family=' . $key . '");';
		}

		foreach ($stylesCss as $selector => $rules) {
			$customCSS .= $selector . ' {';
			foreach ($rules as $key => $value) {
				$customCSS .= $key . ': ' . $value . ';';
			}
			$customCSS .= '} ';
		}

		return $customCSS;
	}

	/**
	 * Inject wc_get_price_decimals due to plugin settings.
	 */
	public function addWcPriceDecimals( $wcPriceNumDecimals ) {
		return $this->customDecimalsRange;
	}

	public function getCustomLoaderHtml( $settings ) {
		$block = $this->getFilterSetting($settings, 'is_overlay', false) ? '#wpfOverlay' : '.wpfPreviewLoader';
		$this->setFilterCss($block . ' .woobewoo-filter-loader {' . $this->getFilterSetting($settings, 'filter_loader_custom_icon', '') . '}');
		$animate = $this->getFilterSetting($settings, 'filter_loader_custom_animation', '');
		if (!empty($animate)) {
			$animate = ' animate-' . $animate;
		}
		return '<div class="woobewoo-filter-loader wpfCustomLoader' . $animate . '"></div>';
	}

}

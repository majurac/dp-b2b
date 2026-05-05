<?php
/**
 * WBW Product Filter PRO - AutocompleteModelWpf Class
 *
 * Class contain autocomplete functionality.
 * We use it in "Search By Text" filter block
 *
 * You can use it in any part of your code with construction
 * FrameWpf::_()->getModule('woofilterpro')->getModel('autocomplete');
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

class AutocompleteModelWpf extends ModelWpf {

	/**
	 * Autocomplete keywords finding limit per option.
	 */
	const LIMIT = 5;

	/**
	 * Searching word.
	 *
	 * @var string
	 */
	public $keyword;

	/**
	 * Autocomplete result data.
	 *
	 * @var array
	 */
	public $resultData;

	/**
	 * Autocomplete active options based on a filter settings.
	 *
	 * @var array
	 */
	public $atcmplActive;

	/**
	 * Init autocomplete.
	 *
	 * @param string $keyword
	 *
	 * @return object
	 */
	public function init( $keyword, $filterId ) {
		$this->keyword = $keyword;
		$this->atcmplActive = $this->setActive($filterId);

		return $this;
	}

	/**
	 * Collect autocomplete data result base on a keyword and filter settings.
	 *
	 * @return array
	 */
	public function getData() {
		$this->resultData = array();
		foreach ($this->atcmplActive as $atcmplOption) {
			$method = 'setAtcpl' . $atcmplOption['wp_instance'];

			if (method_exists($this, $method)) {
				// collect autocomplete data depending on options to $resultData property
				$this->$method($atcmplOption);
			}
		}

		return $this->resultData;
	}

	/**
	 * Set autocomplete active settings base on a filter settings.
	 *
	 * @param int $filterId
	 */
	public function setActive( $filterId ) {
		if (FrameWpf::_()->getModule('woofilters')->getModel('settings')) {
			$filterSettings = FrameWpf::_()->getModule('woofilters')->getModel('settings')->getFilterSettings(
				'wpfSearchText',
				array(),
				array(),
				$filterId
			);
		} else {
			$filterSettings = array();
		}

		if (!empty($filterSettings[0])) {
			$filterSettings = $filterSettings[0];
		}

		$atcmplActive = array();
		$suportList = $this->getAtcplSupportList();
		foreach ($suportList as $index => $atcmplElement) {
			// fund if autocomplete option for a autocomplete search block is activated in admin area
			if (!empty($filterSettings['settings'][$atcmplElement['option_slug']])) {
				$atcmplActive[] = $atcmplElement;
			}
		}


		if ($filterSettings['settings']['f_search_by_attributes']) {
			// get all attribute taxonomies list
			$attributesTaxSlugs = array();
			$attributesTaxSlugs = array_keys( wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' ) );
			$attributesTaxSlugs = array_map(
				function ( $tax ) {
					return 'pa_' . $tax;
				},
				$attributesTaxSlugs
			);
			//add all product attribute taxonomies to activate autocomplete settings
			foreach ($attributesTaxSlugs as $attrTaxSlug) {
				$atcmplActive[] = array(
					'nice_name' => __('product attribute', 'woo-product-filter'),
					'slug' => $attrTaxSlug,
					'option_slug' => 'f_search_by_attributes',
					'wp_instance' => 'Taxonomy',
				);
			}
		}

		return $atcmplActive;
	}

	/**
	 * Get autocomplete data for post element wp instance.
	 *
	 * @param array $atcmplOption
	 *
	 * @return array
	 */
	public function setAtcplPostElemtnt( $atcmplOption ) {
		$args = array(
			'post_status' => 'publish',
			'post_type' => 'product',
			'posts_per_page' => 100,
			'ignore_sticky_posts' => true,
		);

		$keyword = $this->keyword;

		add_filter('posts_where', function ( $where ) use ( $keyword ) {
			global $wpdb;
			$term = '%' . $wpdb->esc_like($keyword) . '%';
			$where .= ' AND ' . $wpdb->prepare("($wpdb->posts.post_title LIKE %s)", $term);
			return $where;
		});
		$query = new WP_Query($args);

		$regex = '~(' . $this->keyword . '\w+)~i';
		$i = 0;
		foreach ($query->posts as $term) {
			if (!empty($term->post_title)) {
				$title = $term->post_title;
				if (preg_match_all($regex, $title, $matches, PREG_PATTERN_ORDER)) {
					foreach ($matches[1] as $word) {
						if (!in_array($word, $this->resultData)) {
							if ($i < self::LIMIT) {
								$isDublicate = $this->checkDublicateAtclpResult($word, $atcmplOption['nice_name']);
								$isDublicate ? '' : $this->resultData[][$atcmplOption['nice_name']] = $word;
								$i++;
							} else {
								break;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Get autocomplete data for taxonomy wp instance.
	 *
	 * @param array $atcmplOption
	 *
	 * @return array
	 */
	public function setAtcplTaxonomy( $atcmplOption ) {
		$results = array();
		$args = array(
			'taxonomy' => $atcmplOption['slug'],
		);
		$terms = get_terms($args);
		// Compare keyword and term name
		$i = 0;
		foreach ( $terms as $term ) {

			if ( $i < self::LIMIT ) {
				$termName = html_entity_decode( $term->name, ENT_COMPAT );
				if (function_exists('mb_strtolower')) {
					$pos = strpos( mb_strtolower( $termName ), mb_strtolower( $this->keyword ) );
				} else {
					$pos = strpos( strtolower( $termName ), strtolower( $this->keyword ) );
				}

				if (false !== $pos) {
					$word = preg_replace( sprintf( '/(%s)/', $this->keyword ), '$1', $termName );
					$isDublicate = $this->checkDublicateAtclpResult($word, $atcmplOption['nice_name']);
					$isDublicate ? '' : $this->resultData[][$atcmplOption['nice_name']] = $word;
					$i++;
				}
			} else {
				break;
			}
		}
	}

	/**
	 * Check if world already exist in resulting autocomplete response before add new item in it.
	 *
	 * @param string $world
	 * @param string $niceName
	 *
	 * @return bool
	 */
	public function checkDublicateAtclpResult( $world, $niceName ) {
		$key = array_search($world, array_column($this->resultData, $niceName));

		$responce = true;
		if ( false === $key ) {
			$responce = false;
		}

		return $responce;
	}

	/**
	 * Get autocomplete support options list.
	 *
	 * @return array
	 */
	public function getAtcplSupportList() {
		return array(
			array(
				'nice_name'   => __('product category', 'woo-product-filter'),
				'slug'        => 'product_cat',
				'option_slug' => 'f_search_by_tax_categories',
				'wp_instance' => 'Taxonomy',
			),
			array(
				'nice_name'   => __('product tag', 'woo-product-filter'),
				'slug'        => 'product_tag',
				'option_slug' => 'f_search_by_tax_tags',
				'wp_instance' => 'Taxonomy',
			),
			array(
				'nice_name'   => __('product title', 'woo-product-filter'),
				'slug'        => 'post_title',
				'option_slug' => 'f_search_by_title',
				'wp_instance' => 'PostElemtnt',
			),
			array(
				'nice_name'   => __('product brand', 'woo-product-filter'),
				'slug'        => 'pwb-brand',
				'option_slug' => 'f_search_by_perfect_brand',
				'wp_instance' => 'Taxonomy',
			),
		);
	}

}

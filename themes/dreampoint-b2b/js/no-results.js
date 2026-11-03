/**
 * Storefront no-results component (CAT-05): reflects whether a WBW filter is active.
 * The markup is rendered once by template-parts/no-results.php; this script only toggles
 * `hidden` on elements tagged data-dp-nr-show="plain|filtered" and routes "Očisti sve filtre"
 * through WBW's own clear button (native AJAX pipeline) when it exists.
 */
(function () {
	'use strict';

	const ROOT = '[data-dp-no-results]';

	// WBW's own selected-filters node, or any checked non-sort filter input.
	function hasActiveFilters() {
		return !!(
			document.querySelector('.wpfSelectedParameters .wpfSelectedParameter') ||
			document.querySelector('.wpfFilterWrapper:not([data-filter-type="wpfSortBy"]) input:checked')
		);
	}

	function sync() {
		const filtered = hasActiveFilters();
		document.querySelectorAll(ROOT).forEach(function (root) {
			root.querySelectorAll('[data-dp-nr-show]').forEach(function (el) {
				el.hidden = (el.dataset.dpNrShow === 'filtered') !== filtered;
			});
		});
	}

	document.addEventListener('click', function (e) {
		const btn = e.target.closest('[data-dp-nr-action="clear"]');
		if (!btn) {
			return;
		}
		const wbwClear = document.querySelector('.wpfClearButton');
		if (wbwClear) {
			e.preventDefault();
			wbwClear.click();
		}
		// Otherwise the link itself (same search/archive without WBW params) is followed.
	});

	document.addEventListener('DOMContentLoaded', sync);
	document.addEventListener('wpfAjaxSuccess', sync);
	if (document.readyState !== 'loading') {
		sync();
	}
})();

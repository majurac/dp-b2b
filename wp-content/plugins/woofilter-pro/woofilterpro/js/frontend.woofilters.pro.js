(function ($, app) {
"use strict";
	function doProIntialise() {
		var WpfFrontendPage = window.wpfFrontendPage;
		WpfFrontendPage.statistics = {};

		function wpfEventsFrontendPro() {
			//Mark selected
			jQuery('.wpfFilterWrapper input').on('change', function(e) {
				var $input = jQuery(this);
				WpfFrontendPage.styleCheckboxSelected($input.closest('.wpfFilterWrapper'));

				if (!$input.is('input:checked')) {
					WpfFrontendPage.autoUnfoldByCheck($input);
				}
			});
			//Price Filter
			jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').each(function () {
				WpfFrontendPage.initIonSlider(jQuery(this));
			});
			jQuery('.wpfPriceRangeCustom input').on('change', function(e) {
				e.preventDefault();
				var li = jQuery(this).closest('li');
				li.attr('data-range', li.find('input[name="wpf_custom_min"]').val() + ',' + li.find('input[name="wpf_custom_max"]').val());
			});
			jQuery('.wpfPriceRangeCustom i').on('click', function(e) {
				e.preventDefault();
				var $this = jQuery(this),
					wrapper = $this.closest('.wpfFilterWrapper'),
					input = wrapper.find('.wpfFilterContent .wpfCheckbox input');
				
				if (typeof wpfFrontendPage.setCurrentLocation == 'function') wpfFrontendPage.setCurrentLocation();

				input.prop('checked', false);
				$this.closest('li').find('.wpfCheckbox input').prop('checked', true);
				if (typeof WpfFrontendPage.moveCheckedToTop != 'undefined') {
					WpfFrontendPage.moveCheckedToTop($this.closest('li').find('.wpfCheckbox input'));
				}
				wrapper.removeClass('wpfNotActive');
				WpfFrontendPage.setSelectedParamsPro(wrapper);

				WpfFrontendPage.filtering($this.closest('.wpfMainWrapper'));
			});
			jQuery('.wpfFilterWrapper .wpfSearchWrapper button').on('click', function (e) {
				e.preventDefault();
				var wrapper = jQuery(this).closest('.wpfFilterWrapper');
				if (typeof wpfFrontendPage.setCurrentLocation == 'function') wpfFrontendPage.setCurrentLocation();

				if (wrapper.find('.wpfSearchFieldsFilter').val() == '') {
					WpfFrontendPage.clearFilters(wrapper);
				} else {
					wrapper.removeClass('wpfNotActive');
					wrapper.find('.wpfFilterContent li:not(.wpfSearchHidden) .wpfCheckbox input').prop('checked', true);
					WpfFrontendPage.setSelectedParamsPro(wrapper);
				}
				WpfFrontendPage.filtering(wrapper.closest('.wpfMainWrapper'));
			});

			//Category Filter
			jQuery('.wpfFilterWrapper[data-collapsible="1"]').each(function() {
				var categoryFilter = jQuery(this);
				categoryFilter.find('.wpfCollapsible').off('click').on('click', function (e) {
					e.preventDefault();
					let $this = $(this),
						parentLi = $this.closest('li');

					setTimeout(function () {
						let $icon = $this.find('i.fa, svg');

						if ($icon.length) {
							WpfFrontendPage.collapsibleToggle($icon, WpfFrontendPage.getIcons($icon), parentLi);
						}
					}, 100);

					return;
				});
				categoryFilter.find('.wpfCollapsible').each(function() {

					var $this = jQuery(this),
					    li = $this.closest('li');

					if (li.find('ul input:checked').length) {
						$this.trigger('click');
					}

					var iconVisible = false;
					li.find('ul li').each(function () {
						if  (jQuery(this).css('display') === 'block') {
							iconVisible = true;
						}
					});

					if (!iconVisible) {
						$this.hide();
					} else {
						$this.show();
					}

				});
			});

			//Rating Filter
			if(jQuery('.wpfFilterWrapper .wpfStarsRating').length) {
				var starFilter = jQuery('.wpfFilterWrapper .wpfStarsRating');
				if(starFilter.attr('data-display-type') == 'linestars') {
					var	starColor = starFilter.attr('data-star-color'),
						leerColor = starFilter.attr('data-leer-color'),
						addText = starFilter.attr('data-add-text'),
						addText5 = starFilter.attr('data-add-text5'),
						exactValues = starFilter.attr('data-exact-values') || 0;
					starFilter.find('input.wpfStarInput').on('change',function() {
						var checkedItem = starFilter.find('input.wpfStarInput:checked');
						starFilter.find('.wpfStarsAdditional').text(!exactValues ? ( checkedItem.length && checkedItem.attr('id') == 'wpfLineStar5' ? addText5 : addText ) : checkedItem.data('label'));
					});

				} else {
					starFilter.find('input.wpfStarInput').on('change',function() {
						starFilter.find('.wpfStarsRatingBlock').removeClass('wpfLineChecked');
						starFilter.find('input.wpfStarInput:checked').closest('.wpfStarsRatingBlock').addClass('wpfLineChecked');
					});
				}
				starFilter.show();
			}

			//SearchText Filter
			jQuery('.wpfFilterWrapper[data-filter-type="wpfSearchText"]').each(function(){
				var searchTextFilter = jQuery(this),
					autoComplete = parseInt(searchTextFilter.attr('data-autocomplete'));
				if(!isNaN(autoComplete) && autoComplete > 0) {
					var input = searchTextFilter.find('input'),
						filterId = searchTextFilter.closest('.wpfMainWrapper').data('filter'),
						oneField = autoComplete == 1;

					if (searchTextFilter.data('not-display-result-type') === 1) {
						oneField = true;
					}

					input.autocomplete({
						source: function (request, response) {
							var autocomleate = [];
							jQuery.sendFormWpf({
								data: {
									mod: 'woofilterpro',
									action: 'autocompliteSearchText',
									keyword: input.val(),
									filterId: filterId
								},
								onSuccess: function(result) {
									if(!result.error && result.data && result.data.autocompleteData) {
										var data = result.data.autocompleteData;

										for(var option in data) {
											for(var optionName in data[option]) {
												var keyword = data[option][optionName];
												autocomleate.push({label: (oneField ? keyword : optionName + ': ' + keyword), value: keyword});
											}
										}
									}
									response(autocomleate);
								}
							});
						},
						select: function(event, ui) {
							input.val(ui.item.value);
							input.blur();
							WpfFrontendPage.eventChangeFilter(event);
						},
						minLength: 2,
						delay: 10
					});

				}

				searchTextFilter.find('input').on('focusout', function (e) {
					var _this = jQuery(e.target),
						mainWrapper = _this.closest('.wpfMainWrapper'),
						filterWrapper = _this.closest('.wpfFilterWrapper'),
						disable_autofiltering = WpfFrontendPage.getFilterParam('f_disable_autofiltering', mainWrapper, filterWrapper);
					if (!disable_autofiltering) {
						WpfFrontendPage.eventChangeFilter(e);
					}
				});
			});
			
			//SearchNumber Filter
			jQuery('.wpfFilterWrapper[data-filter-type="wpfSearchNumber"]').each(function(){
				jQuery(this).find('input').on('focusout', function (e) {
					var _this = jQuery(e.target),
						mainWrapper = _this.closest('.wpfMainWrapper'),
						filterWrapper = _this.closest('.wpfFilterWrapper'),
						disable_autofiltering = WpfFrontendPage.getFilterParam('f_disable_autofiltering', mainWrapper, filterWrapper);
					if (!disable_autofiltering) {
						WpfFrontendPage.eventChangeFilter(e);
					}
				});
			});

			//Attribute Filter
			jQuery('.wpfFilterWrapper .wpfColorsFilterHor label.icon').tooltipster().attr('title', '');
			jQuery('.wpfFilterWrapper .wpfColorsFilter label.icon').each(function(){
					jQuery(this).css('color', wpfGetColorText(jQuery(this).data('color')));
			});
			jQuery('.wpfFilterWrapper[data-filter-type="wpfAttribute"][data-display-type="slider"]').each(function () {
				var skin = jQuery(this).attr('data-price-skin');
				if (skin === 'default') {
					// deprecated functionality
					WpfFrontendPage.initDefaultSlider(jQuery(this), 'attr');
				} else {
					WpfFrontendPage.initIonSlider(jQuery(this), 'attr');
				}
			});
			jQuery('.wpfColorsFilter li').on('click', function(e){
				if(jQuery(this).hasClass('wpfOptionDisabled')) return false;
				var input = jQuery(this).find('input'),
					mainWrapper = input.closest('.wpfMainWrapper'),
					filterWrapper = input.closest('.wpfFilterWrapper'),
					isSingle = WpfFrontendPage.getFilterParam('f_colors_singleselect', mainWrapper, filterWrapper);

				if (isSingle) {
					var isChecked = input.is(':checked');
					filterWrapper.find('input').each(function () {
						if (jQuery(this).is(':checked')) {
							jQuery(this).prop('checked', false).trigger('wpf-synchro');
						}
					});
					input.prop('checked', isChecked);
				}
			});

			// Buttons/text-type filters
			jQuery('.wpfButtonsFilter input, .wpfTextFilter input').on('change wpf-synchro', function(e){
				var input = jQuery(this),
					wrapper = input.closest('li'),
					filterWrapper = input.closest('.wpfFilterWrapper'),
					type = filterWrapper.data('display-type');
				if(input.is(':checked')) {
					if ( type == 'buttons' ) {
						input.closest('li').addClass('wpfTermChecked');
					}
					input.closest('label').find('.wpfValue').addClass('wpfTermChecked');
				} else {
					if ( type == 'buttons' ) {
						input.closest('li').removeClass('wpfTermChecked');
					}
					input.closest('label').find('.wpfValue').removeClass('wpfTermChecked');
				}
			});
			jQuery('.wpfMainWrapper input').on('wpf-synchro', function(e){
				WpfFrontendPage.setSelectedParamsPro(jQuery(this).closest('.wpfFilterWrapper'));
			});
			jQuery('.wpfButtonsFilter li').on('click', function(e){
				if(jQuery(this).hasClass('wpfOptionDisabled')) return false;
				var input = jQuery(this).find('input'),
					mainWrapper = input.closest('.wpfMainWrapper'),
					filterWrapper = input.closest('.wpfFilterWrapper'),
					isMuulti = WpfFrontendPage.getFilterParam('f_buttons_multiselect', mainWrapper, filterWrapper),
					isChecked = input.is(':checked');

				if (!isMuulti) {
					filterWrapper.find('input').each(function () {
						if (jQuery(this).is(':checked')) {
							jQuery(this).prop('checked', false).trigger('wpf-synchro');
						}
					});
				}
				input.prop('checked', !isChecked).trigger('change');
			});
			jQuery('.wpfTextFilter li').on('click', function(e){
				if(jQuery(this).hasClass('wpfOptionDisabled')) return false;
				var input = jQuery(this).find('input'),
					mainWrapper = input.closest('.wpfMainWrapper'),
					filterWrapper = input.closest('.wpfFilterWrapper'),
					isSingle = WpfFrontendPage.getFilterParam('f_text_singleselection', mainWrapper, filterWrapper),
					isChecked = input.is(':checked');

				if (isSingle) {
					filterWrapper.find('input').each(function () {
						if (jQuery(this).is(':checked')) {
							jQuery(this).prop('checked', false).trigger('wpf-synchro');
						}
					});
				}
				input.prop('checked', !isChecked).trigger('change');
			});
			jQuery('.wpfTextFilter input:checked').each(function(){
				jQuery(this).closest('label').find('.wpfValue').addClass('wpfTermChecked');
			});

			jQuery('.wpfMainWrapper').each(function () {
				var mainWrapper = jQuery(this),
					settings = WpfFrontendPage.getFilterMainSettings(mainWrapper),
					hideButton = mainWrapper.find('.wfpHideButton');
				// Selected Parameters
				if(settings && settings.settings.display_selected_params === '1') {
					var id = mainWrapper.data('viewid').split('_')[0],
						wrapper = jQuery('<div class="wpfSelectedParameters"></div>'),
						wrapperExternal = jQuery('.wpfSelectedParameters[data-filter="' + id + '"]');
					if (wrapperExternal.length) {
						wrapper = wrapperExternal;
					}

					WpfFrontendPage.selectedParamsColor = wpfLightenDarkenColor(mainWrapper.css('background-color'), -10);
					mainWrapper.find('.wpfFilterWrapper:not(.wpfNotActive):not(.wpfHidden)').each(function () {
						var filter = jQuery(this),
							filterType = filter.attr('data-filter-type'),
							filterName = filter.attr('data-get-attribute'),
							filterLogic = filter.attr('data-query-logic'),
							params = WpfFrontendPage.getFilterOptionsByType(filter, filterType);
							params = WpfFrontendPage.filterParamByParentPro(settings, params);
						WpfFrontendPage.addSelectedParamsPro(wrapper, params, filterType, filterName, filterLogic);
					});

					if (settings.settings.selected_params_clear === '1') {
						var word = wpfTraslate.ClearAll ? wpfTraslate.ClearAll : settings.settings.selected_clean_word;
						wrapper.append(jQuery('<div class="wpfSelectedParametersClear">' + (word ? word : 'Clear All') + '</div>'));
					}

					if (wrapper.find('.wpfSelectedParameter').length == 0) {
						wrapper.addClass('wpfHidden');
					} else {
						wrapper.removeClass('wpfHidden');
					}

					if (!wrapperExternal.length) {
						if (settings.settings.selected_params_position === 'bottom') mainWrapper.append(wrapper);
						else if (hideButton.length) wrapper.insertAfter(hideButton);
						else mainWrapper.prepend(wrapper);
					}
				}
				var isActiveFilter = false;
				mainWrapper.find('.wpfFilterWrapper').each(function() {
					var wrapper = jQuery(this);
					if (!wrapper.hasClass('wpfNotActive')) isActiveFilter = true;

					//ABC index
					if(wrapper.attr('data-abc') == '1') {
						wrapper.find('.wpfFilterVerScroll').each(function() {
							var abc = '',
								verBlock = jQuery(this);
							verBlock.children('li[data-parent="0"]').each(function(){
								var li = jQuery(this),
									name = li.find('.wpfValue .wpfFilterTaxNameWrapper').html(),
									letter = name.substr((name.substring(0, 1) == '<' ? name.indexOf('>') + 1 : 0), 1).toUpperCase();

								if(abc.indexOf(">"+letter+"<") == -1) {
									jQuery('<li class="wpfAbcLetter" data-letter="'+letter+'">'+letter+'</li>').insertBefore(li);
									abc += '<div class="wpfAbcLink" data-letter="'+letter+'">'+letter+'</div>';
								}
								li.attr('data-letter', letter);
							});
							if(abc != '') jQuery('<div class="wpfAbcToggle">'+(wpfTraslate.AlphabeticalIndex || 'Alphabetical index')+'</div><div class="wpfAbcLinks wpfHidden">'+abc+'</div>').insertBefore(verBlock);
						});
						WpfFrontendPage.wpfShowHideFiltersAttsPro(wrapper);
					}

					WpfFrontendPage.styleCheckboxSelected(wrapper);

					// Show More 
					setTimeout(function() {
						wrapper.find('.wpfFilterVerScroll').each(function () {
							WpfFrontendPage.initShowMore(jQuery(this));
						});
					}, 100);
				});
				if(hideButton.length && hideButton.attr('data-is-open') === '0') {
					hideButton.siblings('div:not(.wpfPreviewLoader):not(.wpfLoaderLayout)').addClass('wpfHideFilter');
				} 

				WpfFrontendPage.getHideButtons(mainWrapper, settings, isActiveFilter);
				var width = $( window ).width(),
					height = $( window ).height();
				jQuery( window ).on('resize', function() {
					if ( width !== $( window ).width()) {
						WpfFrontendPage.getHideButtons(mainWrapper, settings);
					}
				});
			});
			jQuery('.wpfMainWrapper .wpfAbcToggle').on('click', function(e){
				e.preventDefault();
				var wrapper = jQuery(this).closest('.wpfFilterWrapper').find('.wpfAbcLinks');
				if(wrapper.hasClass('wpfHidden')) wrapper.removeClass('wpfHidden');
				else wrapper.addClass('wpfHidden');
			});
			jQuery('.wpfMainWrapper .wpfAbcLink').on('click', function(e){
				e.preventDefault();
				var link = jQuery(this),
					wrapper = link.closest('.wpfFilterWrapper').find('.wpfFilterVerScroll'),
					letter = wrapper.find('.wpfAbcLetter[data-letter="'+link.attr('data-letter')+'"]:first');
				if(letter.hasClass('wpfMoreHidden')) wrapper.find('.wpfShowMoreWrapper').trigger('click');
				//wrapper.find('.wpfAbcLetter[data-letter="'+link.attr('data-letter')+'"]').get(0).scrollIntoView(true);
				//wrapper.scrollTo(wrapper.find('.wpfAbcLetter[data-letter="'+link.attr('data-letter')+'"]'));
				wrapper.stop().animate({
					scrollTop: wrapper.find('.wpfAbcLetter[data-letter="'+link.attr('data-letter')+'"]:first').offset().top-wrapper.find('li:not(.wpfHidden):not([style*="display: none"]):first').offset().top				
				}, 500);
				//wrapper.scrollTo('.wpfAbcLetter[data-letter="'+link.attr('data-letter')+'"]', 1000);
			});

			jQuery('body').off('click', '.wpfShowMoreWrapper').on('click', '.wpfShowMoreWrapper', function(e) {
				e.preventDefault();
				var more = jQuery(this),
					block = more.closest('.wpfFilterVerScroll').attr('data-open-more', 1);
				if (more.attr('data-full-opening') == '1') block.css({'maxHeight': 'none'});

				block.find('li').removeClass('wpfMoreHidden');
				more.remove();
				block.append(jQuery('<li class="wpfShowFewerWrapper"> - ' + wpfTraslate.ShowFewer + ' </li>'));
			});
			jQuery('body').off('click', '.wpfShowFewerWrapper').on('click', '.wpfShowFewerWrapper', function(e) {
				e.preventDefault();
				WpfFrontendPage.initShowMore(jQuery(this).closest('.wpfFilterVerScroll').attr('data-open-more', 0));
			});

			jQuery('body').off('click', '.wpfSelectedParameters .wpfSelectedDelete').on('click', '.wpfSelectedParameters .wpfSelectedDelete',  function(e){
				e.preventDefault();
				var param = jQuery(this).closest('.wpfSelectedParameter'),
					filterId = param.closest('.wpfSelectedParameters').data('filter'),
					paramName = param.attr('data-filter-name'),
					paramType = param.attr('data-filter-type'),
					mainWrapper = filterId ? jQuery('.wpfMainWrapper[data-filter="' + filterId + '"]') : param.closest('.wpfMainWrapper');
				if (typeof wpfFrontendPage.setCurrentLocation == 'function') wpfFrontendPage.setCurrentLocation();
				if(param.attr('data-is-one') == '1') {
					WpfFrontendPage.clearFilters(mainWrapper.find('.wpfFilterWrapper[data-filter-type="'+paramType+'"]'));
					WpfFrontendPage.filtering(mainWrapper);
				} else {
					mainWrapper.find('.wpfFilterWrapper[data-filter-type="'+paramType+'"][data-get-attribute="'+paramName+'"]').each(function() {
						var filter = jQuery(this);
						if(filter.attr('data-display-type') == 'mul_dropdown') {
							filter.find('option[data-term-id="'+param.attr('data-key')+'"]').removeAttr('selected').trigger('change');
							filter.find('select.jqmsLoaded').multiselect('reload');
						} else {
							var input = filter.find('[data-term-id="'+param.attr('data-key')+'"]');
							if(input.length && !input.is('input')) input = input.find('input');
							if(input.length) input.prop('checked', false).trigger('change');
						}
					});
				}
			});
			jQuery('body').off('click', '.wpfSelectedParameters .wpfSelectedParametersClear').on('click', '.wpfSelectedParameters .wpfSelectedParametersClear',  function(e){
				e.preventDefault();
				var filterId = jQuery(this).closest('.wpfSelectedParameters').data('filter'),
					$filterWrapper = filterId ? jQuery('.wpfMainWrapper[data-filter="' + filterId + '"]') : jQuery(this).closest('.wpfMainWrapper');
				if (typeof wpfFrontendPage.setCurrentLocation == 'function') wpfFrontendPage.setCurrentLocation();
				WpfFrontendPage.clearFilters($filterWrapper.find('.wpfFilterWrapper'), true);
				WpfFrontendPage.filtering($filterWrapper);
				WpfFrontendPage.initOneByOne($filterWrapper);
			});

			// Hide button
			var hideButtonEvent = document.createEvent('Event');
			hideButtonEvent.initEvent('wpfHideButtonClick', false, true); 
			jQuery('body').off('click', '.wfpHideButton').on('click', '.wfpHideButton',  function(e) {
				e.preventDefault();
				var button = jQuery(this),
					icon = button.find('i.fa, svg'),
					up = 'fa-chevron-up',
					down = 'fa-chevron-down',
					mainWrapper = button.closest('.wpfMainWrapper');

				if (mainWrapper.length) {
					$('html,body').animate({'scrollTop': mainWrapper.offset().top - 30}, 500);
				}

				if (icon.length) {
					if (icon.hasClass(up)) {
						var toggle = 'up';
					} else {
						var toggle = 'down';
					}
					WpfFrontendPage.toggleHideFiltersButton(button, toggle);
				}
			});

			// add skin css to attribute and price slider type filter
			jQuery('.wpfFilterWrapper[data-skin-css]').each(function() {
				var css = jQuery(this).attr('data-skin-css'),
					filterBlockId = jQuery(this).closest('.wpfMainWrapper').attr('data-filter'),
					filterId = jQuery(this).attr('id'),
					styleBlockId = '#' + filterId + '_style';

					if (filterBlockId && filterId) {
						var selector = '.wpfMainWrapper[data-filter="' + filterBlockId + '"] #' + filterId;
						// we user it for override after action
						var selectorAction = 'div.wpfMainWrapper[data-filter="' + filterBlockId + '"] #' + filterId;

						css = css.split('filter_admin_area_id_placeholder').join(selectorAction);

						jQuery(styleBlockId).html(css);
					}
			});

			document.dispatchEvent(hideButtonEvent);

			//change slider filters
			jQuery('.wpfFilterWrapper[data-filter-type="wpfAttribute"][data-display-type="slider"]').on('wpfAttrSliderChange', function(event, sliderData) {
				var filter = jQuery(this),
					wrapper = sliderData.input.closest('.wpfFilterWrapper');

				filter.removeClass('wpfNotActive');
				wrapper.find('#wpfMinAttrNum').val(sliderData.from_value);
				wrapper.find('#wpfMaxAttrNum').val(sliderData.to_value);
				wrapper.find('#wpfMinAttrNum').attr('data-min-numeric-value', sliderData.from);
				wrapper.find('#wpfMaxAttrNum').attr('data-max-numeric-value', sliderData.to);
			});

			jQuery('body').on('click', '.wpfFilterTitle', function(e){
				var curTitle = jQuery(this);
				setTimeout(function () {
					let $icon = curTitle.find('i.fa, svg');
					if ($icon.length) {
						let icons = WpfFrontendPage.getIcons($icon);
						if (!icons.collapsed) {
							var wrapper = curTitle.closest('.wpfMainWrapper'),
								settings = WpfFrontendPage.getFilterMainSettings(wrapper);
							if (settings.settings.only_one_filter_open === '1') {
								var curBlockId = curTitle.closest('.wpfFilterWrapper').attr('id');
								wrapper.find('.wpfFilterTitle .' + icons.minusIcon).each(function () {
									var _this = jQuery(this),
										block = _this.closest('.wpfFilterWrapper');
									if (block.attr('id') != curBlockId) {
										WpfFrontendPage.closeFilterToggle(_this, block.find('.wpfFilterContent'), true, icons);
									}
								});
							}
						}
					}
				}, 100);
			});

			// display perfect brand description
			var brandDescription = jQuery('.wpfFilterWrapper .data-brand-description').html();
			if (typeof brandDescription !== 'undefined') {
				var loopContainer = jQuery('ul.products');
				loopContainer.before('<div class="brand-description">' + brandDescription + '</div>');
			}

			jQuery('.wpfMainWrapper').each(function () {
				WpfFrontendPage.initOneByOne(this);
			});

            jQuery('.wpfFilterWrapper[data-filter-type="wpfPrice"]').on('wpfPriceChange', function () {                
				WpfFrontendPage.setCurrentOrderKey(jQuery(this));
			});

            jQuery('.wpfFilterWrapper').off('change').on('change', function () {                
				WpfFrontendPage.setCurrentOrderKey(jQuery(this));
			});

			document.addEventListener('wpfAjaxSuccess', function () {

				jQuery('.wpfMainWrapper').each(function () {
					var mainWrapper = jQuery(this),
						settings = WpfFrontendPage.getFilterMainSettings(mainWrapper).settings;

					if (settings.open_one_by_one === '1') {

						var currentOrderKey = mainWrapper.data('current-order-key');

						if (typeof currentOrderKey !== 'undefined') {

							var filterWrapper = {};

							if (settings.disable_following === '1') {
								filterWrapper = WpfFrontendPage.findNextFilterWrapper(currentOrderKey,'.wpfFilterWrapper',mainWrapper);
								if (!jQuery.isEmptyObject(filterWrapper)) {
									filterWrapper.removeClass('wpfDisable').find('select').removeAttr('disabled');
								}
							} else {
								filterWrapper = WpfFrontendPage.findNextFilterWrapper(currentOrderKey,'.hideUntilPrevSelected',mainWrapper);
								if (!jQuery.isEmptyObject(filterWrapper)) {
									filterWrapper.removeClass('hideUntilPrevSelected');
								}

								mainWrapper.removeData('current-order-key');
							}
						}

					}

				});

			});
			
			if (!WpfFrontendPage.isAdminPreview) {
				jQuery('.wpfFloatingSwitcher').each(function() {
					var btn = jQuery(this);
					if (btn.attr('data-product-visible') == '1') {
						var	btnId = btn.attr('id'),
							$filterWrapper = btn.parent().find('#'+btnId.replace('wpfFloatingSwitcher', 'wpfFloatingWrapper')+' .wpfMainWrapper');
						if ($filterWrapper.length) {
							var $generalSettings = WpfFrontendPage.getFilterMainSettings($filterWrapper),
								$target = false,
								prContainer = WpfFrontendPage.fixSelector($generalSettings['settings']['product_container_selector'] ? $generalSettings['settings']['product_container_selector'] : '', '');
							if (prContainer.length) $target = jQuery(prContainer);
							if (!$target || $target.length == 0) {
								prContainer = WpfFrontendPage.fixSelector($generalSettings['settings']['product_list_selector'] ? $generalSettings['settings']['product_list_selector'] : '', WpfFrontendPage.defaultProductSelector);
								if (prContainer.length) $target = jQuery(prContainer).parent();
							}
							if ($target && $target.length) {
								var btns = $target.attr('data-wpf-buttons'),
									btnsAr = btns && btns.length ? btns.split(',') : [];
								if (btnsAr.length == 0) {
									var observer = new IntersectionObserver(function(entries) {
										entries.forEach(function(entry) {
											var btns = $target.attr('data-wpf-buttons'),
												btnsAr = btns && btns.length ? btns.split(',') : [];
											btnsAr.forEach(function(element){
												jQuery('#'+element).css('display', entry.isIntersecting ? 'block' : 'none');
											});
										});
									});
									observer.observe($target.get(0));
								}
								if (btnsAr.indexOf(btnId) == -1) {
									btnsAr.push(btnId);
									$target.attr('data-wpf-buttons', btnsAr.join(','));
								}
							}
						}
					}
				});
			}
			
			var openFloatingEvent = document.createEvent('Event');
			openFloatingEvent.initEvent('wpfOpenFloatingClick', false, true);
			jQuery('.wpfFloatingSwitcher').off('click').on('click', function(e) {
				e.preventDefault();
				var floating = jQuery(this).parent().find('.wpfFloatingWrapper');
				if (floating.length == 0) {
					floating = jQuery('#wpfFloatingWrapper-'+jQuery(this).attr('id').replace('wpfFloatingSwitcher-',''));
				}
				if (floating.length) WpfFrontendPage.showFloatingPopup(0, floating);
				document.dispatchEvent(openFloatingEvent);
			});
			var closeFloatingEvent = document.createEvent('Event');
			closeFloatingEvent.initEvent('wpfCloseFloatingClick', false, true);
			jQuery('.wpfFloatingClose').off('click').on('click', function () {
				var popup = jQuery(this).closest('.wpfFloatingWrapper');
				if (popup.attr('data-hide-css')) {
					popup.animate(JSON.parse(popup.attr('data-hide-css')), parseInt(popup.attr('data-hide-speed')), function(){
						popup.hide();
						popup.removeClass('wpfFloatingShow');
					});
				}
				if (popup.attr('data-auto-side')) popup.css(popup.attr('data-auto-side'), 'auto');
				popup.parent().find('.wpfFloatingOverlay').hide();
				
				document.dispatchEvent(closeFloatingEvent);
			});
			jQuery('.wpfFloatingOverlay').off('click').on('click', function () {
				var elem = jQuery(this);
				jQuery(this).parent().find('#' + elem.attr('id').replace('wpfFloatingOverlay', 'wpfFloatingWrapper')+' .wpfFloatingClose').trigger('click');
			});
			if (jQuery('.wpfFloatingWrapper').length) {
				jQuery(document).on('keydown', function(e){
					if (e.keyCode === 27) {
						jQuery('.wpfFloatingShow .wpfFloatingClose').trigger('click');
					}
				});
			}

		}
		
		WpfFrontendPage.constructor.prototype.showFloatingPopup = (function (filterId, floating) {
			if (typeof(floating) == 'undefined') {
				var floating = jQuery('.wpfMainWrapper[data-filter="'+filterId+'"]').closest('.wpfFloatingWrapper');
			}
			if (floating.length == 0) return;
			var popup = floating.eq(0);
			if (popup.hasClass('wpfFloatingShow')) return;
			
			var _thisObj = this.$obj,
				viewId = popup.attr('data-viewid'),
				overlay = popup.parent().find('.wpfFloatingOverlay'),
				width = popup.width(),
				height = popup.height(),
				side = popup.attr('data-side'),
				horiz = side == 'left' || side == 'right',
				speed = parseInt(popup.attr('data-animation-speed')) || 200,
				t = popup.attr('data-position-top') || '',
				r = popup.attr('data-position-right') || '',
				b = popup.attr('data-position-bottom') || '',
				l = popup.attr('data-position-left') || '',
				beginP = horiz ? {left: 'auto', right: 'auto'} : {top: 'auto', bottom: 'auto'},
				endP = {},
				saveP = {};
			beginP[side] = '-'+((horiz ? width : height)+50)+'px';
			if (horiz) {
				if (l.length) endP['left'] = l+'px';
				else if (r.length) endP['right'] = r+'px';
				else if (side == 'left') endP['left'] = '0';
				else endP['right'] = '0';
			} else {
				if (t.length) endP['top'] = t+'px';
				else if (b.length) endP['bottom'] = b+'px';
				else if (side == 'top') endP['top'] = '0';
				else endP['bottom'] = '0';
			}
			saveP[side] = beginP[side];
			overlay.show();
			
			popup.attr('data-hide-css', JSON.stringify(saveP)).attr('data-hide-speed', speed).css(beginP).show().animate(endP, speed);
			popup.addClass('wpfFloatingShow');
			if (!(side in endP)) {
				popup.css(side, 'auto');
				popup.attr('data-auto-side', Object.keys(endP)[0]);
			}
			_thisObj.chageRangeFieldWidth();
			popup.find('.wpfFilterVerScroll').each(function () {
				_thisObj.initShowMore(jQuery(this));
			});
		});
		
		WpfFrontendPage.constructor.prototype.initOneByOne = (function (_this) {
			var mainWrapper = jQuery(_this),
				settings = WpfFrontendPage.getFilterMainSettings(mainWrapper).settings;
			if (settings.open_one_by_one === '1') {
				var active = -1,
					first = -1;

				jQuery('.wpfFilterWrapper', mainWrapper).each(function () {
					var _this = jQuery(this);

					if (first === -1 && _this.hasClass('wpfNotActive') && !_this.hasClass('wpfHidden')) {
						first = _this.data('order-key');
					}

					if (!_this.hasClass('wpfNotActive') && !_this.hasClass('wpfHidden')) {
						active = _this.data('order-key');
					}

				});

				jQuery('.wpfFilterWrapper', mainWrapper).each(function () {
					var _this = jQuery(this);

					if (active === -1 || active < first) {
						if (_this.data('order-key') > first) {
							WpfFrontendPage.toggleOneByOne(_this, settings);
						}
					} else {
						if (_this.data('order-key') > active + 1) {
							WpfFrontendPage.toggleOneByOne(_this, settings);
						}
					}

				});
			}
		});

		WpfFrontendPage.constructor.prototype.toggleOneByOne = (function (_this, settings) {
			if (settings.disable_following === '1') {
				_this.addClass('wpfDisable');
				_this.find('select').attr('disabled', 'disabled');
			} else {
				_this.addClass('hideUntilPrevSelected');
			}
		});

		WpfFrontendPage.constructor.prototype.findNextFilterWrapper = (function (currentOrderKey, selector, mainWrapper) {
			var currentSelect = mainWrapper.find('.wpfFilterWrapper[data-order-key=' + currentOrderKey + ']'),
				next = {};

			if (currentSelect.length === 1 && '' !== currentSelect.find('select option:selected').data('slug')) {

				jQuery('.wpfFilterWrapper', mainWrapper).each(function () {
					var _this = jQuery(this),
						orderKey = _this.data('order-key');
					if (orderKey > currentOrderKey && ((_this.hasClass('hideUntilPrevSelected') && !_this.hasClass('wpfPreselected')) || _this.css('display') === 'block')) {
						next = _this;
						return false;
					}
				});
			}

			return next;
		});

        WpfFrontendPage.constructor.prototype.setCurrentOrderKey = (function (_this) {            
			var mainWrapper = _this.closest('.wpfMainWrapper'),
				settings = WpfFrontendPage.getFilterMainSettings(mainWrapper).settings;

			if (settings.open_one_by_one === '1') {
				mainWrapper.data('current-order-key', _this.data('order-key'));

				if (settings.disable_following === '1') {
					_this.nextAll('.wpfFilterWrapper').each(function () {
                        const next = jQuery(this);
                        const select = next.find('select');
						next.addClass('wpfDisable');
                        select.attr('disabled', 'disabled');
                        select.find('option:selected').prop('selected', false);
                        next.find('input:checked').prop('checked', false);
					});
				}
			}
		});

		WpfFrontendPage.constructor.prototype.beforeFilteringPro = (function (checkWrapper) {
			var _thisObj = this.$obj,
				popup = checkWrapper.closest('.wpfFloatingWrapper');
			if (popup.length && _thisObj.filterClick) {
				if (popup.attr('data-close-after') == '1') {
					popup.find('.wpfFloatingClose').trigger('click'); 
				}
			}
			var $generalSettings = _thisObj.getFilterMainSettings(checkWrapper);
			if ($generalSettings['settings']['clear_other_filters'] && ($generalSettings['settings']['clear_other_filters'] == '1')) {
				var checkId = checkWrapper.data('filter');
				jQuery('.wpfMainWrapper').each(function () {
					var currentWrapper = jQuery(this);
					if (checkId !== currentWrapper.data('filter')) {
						_thisObj.clearFilters(currentWrapper.find('.wpfFilterWrapper'), true);
					}
				});
			}
		});

		WpfFrontendPage.constructor.prototype.collapsibleToggle = (function($icon, icons, parentLi) {
			var filterWrapper = parentLi.closest('.wpfFilterWrapper');
			if (icons.collapsed) {
				if (filterWrapper.data('autounfold-all-levels') === 1) {
					parentLi.find('ul').each(function () {
						var ul = jQuery(this);
						ul.removeClass('wpfHidden');
						ul.find('i.fa, svg').addClass(icons.minusIcon).removeClass(icons.plusIcon);
					});
				} else {
					parentLi.children('ul').removeClass('wpfHidden');
				}
				$icon.addClass(icons.minusIcon).removeClass(icons.plusIcon);
			} else {
				var uls = parentLi.find('ul');
				uls.addClass('wpfHidden');
				parentLi.find('.wpfCollapsible').find('i.fa, svg').addClass(icons.plusIcon).removeClass(icons.minusIcon);

				// automatically collapses the parent if all child categories collapse
				var _thisObj = this.$obj,
					mainWrapper = parentLi.closest('.wpfMainWrapper');
				if (_thisObj.getFilterParam('f_multi_auto_collapses_parent', mainWrapper, filterWrapper)) {
					if (parentLi.siblings('li').find('.wpfCollapsible i').hasClass('fa-minus') === false) {
						var parentUL = parentLi.closest('ul');
						if (!parentUL.hasClass('wpfFilterVerScroll')) {
							parentUL.addClass('wpfHidden');
							parentUL.closest('li').find('.wpfCollapsible').find('i.fa, svg').addClass(icons.plusIcon).removeClass(icons.minusIcon);
						}
					}
				}
			}
		});

		WpfFrontendPage.constructor.prototype.styleCheckboxSelected = (function ($filter) {
			if ($filter.length) {
				$filter.find('.wpfDisplay').removeClass('selected');
				$filter.find('input:checked').each(function () {
					var input = jQuery(this),
						singleType = $filter.data('filter-type') == 'wpfAttribute' ? 'radio' : 'list';

					input.closest('.wpfLiLabel').find('.wpfDisplay').addClass('selected');
					WpfFrontendPage.autoUnfoldByCheck(input);

					if ($filter.data('display-type') === singleType) {
						var parentUL = input.closest('ul');
						if (parentUL.hasClass('wpfFilterVerScroll') && parentUL.find('.wpfLiLabel .wpfCollapsible').length ) {
							$filter.find('.wpfFilterVerScroll > li').each(function () {
								var li = jQuery(this);
								li.find('ul').addClass('wpfHidden');
								li.find('i.fa, svg').each(function () {
									var $icon = jQuery(this),
										icons = WpfFrontendPage.getIcons($icon);
									$icon.addClass(icons.plusIcon).removeClass(icons.minusIcon);
								});
							});
						}
					}

				});
			}
		});

		WpfFrontendPage.constructor.prototype.getAttributeFilterOptionsPro = (function ( $filter, data ) {
			var filterType = $filter.attr('data-display-type'),
				isACF = ($filter.data('get-attribute').indexOf('acf-') === 0);

			if (filterType === 'colors'){
				$filter.find('input:checked').each(function () {
					var input = jQuery(this),
						id = input.attr('data-term-id');

					data.options[data.i] = id;
					data.frontendOptions[data.i] = (isACF) ? id : input.attr('data-term-slug');
					var name = input.parent().find('label.icon').attr('data-term-name');
					data.selectedOptions['list'][id] = name;
					data.statistics.push(name);
					data.i++;
				});
			} else if (filterType === 'slider') {
				var values = $filter.find('.wpfAttrNumFilterRange').attr('data-values').split(','),
					slugs = $filter.find('.wpfAttrNumFilterRange').attr('data-slugs').split(','),
					termIds = $filter.find('.wpfAttrNumFilterRange').attr('data-term-ids').split(','),
					minAttrNum = $filter.find('#wpfMinAttrNum').attr('data-min-numeric-value'),
					maxAttrNum = $filter.find('#wpfMaxAttrNum').attr('data-max-numeric-value'),
					forceNumeric = $filter.find('.ion-range-slider').data('force-numeric') ? $filter.find('.ion-range-slider').data('force-numeric') : 0,
					minAttVal = $filter.find('#wpfMinAttrNum').val(),
					maxAttVal = $filter.find('#wpfMaxAttrNum').val(),
					selectedOptions = {'is_one': true, 'list': [minAttVal + ' - ' + maxAttVal]},
					statistics = [[minAttVal,maxAttVal]];

				slugs.forEach(function(slug, index){
					var value = forceNumeric ? parseFloat(values[index]) : values[index];

					if ( index >= minAttrNum && index <= maxAttrNum ) {
						data.options[data.i] = termIds[index];
						data.frontendOptions[data.i] = slug;
						data.selectedOptions = selectedOptions;
						data.statistics = statistics;
						data.i++;
					}
				});
			}

			return data;
		});
		
		WpfFrontendPage.constructor.prototype.getTagsFilterOptionsPro = (function ( $filter, data ) {
			var filterType = $filter.attr('data-display-type');

			if (filterType === 'colors'){
				$filter.find('input:checked').each(function () {
					var input = jQuery(this),
						id = input.attr('data-term-id');

					data.options[data.i] = id;
					data.frontendOptions[data.i] = input.attr('data-term-slug');
					var name = input.parent().find('label.icon').attr('data-term-name');
					data.selectedOptions['list'][id] = name;
					data.statistics.push(name);
					data.i++;
				});
			} 
			return data;
		});

		WpfFrontendPage.constructor.prototype.eventsFrontendPro = (function () {
			wpfEventsFrontendPro();
		});

		WpfFrontendPage.constructor.prototype.initIonSlider = (function (filter, type) {
			var mainWrapper = filter.closest('.wpfMainWrapper'),
				autoFilteringEnable = (mainWrapper.find('.wpfFilterButton').length == 0),
				decimal = filter.attr('data-decimal'),
				isDecimalFormating = filter.attr('data-decimal-formating') == '1' && decimal,
				step = filter.attr('data-step'),
				minInputId = '#wpfMinPrice',
				maxInputId = '#wpfMaxPrice',
				triggerName = 'wpfPriceChange',
				filterType = typeof type !== 'undefined' ? type : 'price';
			
			if (filterType === 'attr') {
				minInputId = '#wpfMinAttrNum';
				maxInputId = '#wpfMaxAttrNum';
				triggerName = 'wpfAttrSliderChange';
			}
			
			if(filter.find('.ion-range-slider').length) {
				var sliderCurBefore = (filter.attr('data-slider-currency-before') != undefined) ? filter.attr('data-slider-currency-before') : '';
				var sliderCurAfter = (filter.attr('data-slider-currency-after') != undefined) ? filter.attr('data-slider-currency-after') : '';
				
				filter.find('.ion-range-slider').ionRangeSlider({
					prefix: sliderCurBefore,
					postfix: sliderCurAfter,
					decimal: isDecimalFormating ? parseInt(decimal) : 0,
					onStart: function (data) {
						var irsGrid = data.input.siblings('.irs').find('.irs-grid'),
							irsGridTexts = irsGrid.find('.irs-grid-text'),
							forceNumeric = data.input.data('force-numeric') ? data.input.data('force-numeric') : 1,
							wrapper = data.input.closest('.wpfFilterWrapper');

						if ( !forceNumeric ) {
							irsGridTexts.each(function () {
								var tVal = parseFloat(jQuery(this).text()).toFixed(decimal);
								jQuery(this).text(tVal);
							});
						}

						wrapper.find('#wpfMinAttrNum').val(data.from_value);
						wrapper.find('#wpfMaxAttrNum').val(data.to_value);
						wrapper.find('#wpfMinAttrNum').attr('data-min-numeric-value', data.from);
						wrapper.find('#wpfMaxAttrNum').attr('data-max-numeric-value', data.to);
					},
					onChange:  function (data) {
						filter.find(minInputId).val(data.from.toFixed(decimal));
						filter.find(maxInputId).val(data.to.toFixed(decimal));
						data.step = Number(step);
						filter.trigger(triggerName, data);
					},
					onFinish: function (data) {
						filter.removeClass('wpfNotActive');
						if(autoFilteringEnable){
							if (typeof wpfFrontendPage.setCurrentLocation == 'function') wpfFrontendPage.setCurrentLocation();
							WpfFrontendPage.filtering(mainWrapper);
						}
						data.step = Number(step);
						filter.trigger(triggerName, data);
					}
				});


				// attrubute slider
				filter.find('input#wpfMinAttrNum, input#wpfMaxAttrNum').on('blur', function (e) {
					var	parent 		 = jQuery(this).closest('.wpfFilterContent'),
						minAttrValue = parent.find('#wpfMinAttrNum').val().toLowerCase(),
						maxAttrValue = parent.find('#wpfMaxAttrNum').val().toLowerCase(),
						slider       = parent.find('.ion-range-slider'),
						valueList    = slider.attr('data-values').toLowerCase().split(','),
						sliderData   = parent.find('.ion-range-slider').data('ionRangeSlider');

					if (slider.attr('data-force-numeric') === "1") {

						var minAttrValueCut = jQuery.grep(valueList, function (val) {
							if (parseFloat(val) <= parseFloat(minAttrValue)) {
								return true;
							}
						});
						minAttrValue = (minAttrValueCut.length <= 1) ? 0 : minAttrValueCut.length - 1;

						var maxAttrValueCut = jQuery.grep(valueList, function (val) {
							if (parseFloat(val) >= parseFloat(maxAttrValue)) {
								return true;
							}
						});
						maxAttrValue = (maxAttrValueCut.length === 0) ? valueList.length : valueList.indexOf(maxAttrValueCut[0]);

					} else {

						if (valueList.indexOf(minAttrValue) !== -1) {
							minAttrValue = valueList.indexOf(minAttrValue);
						} else {
							minAttrValue = 0;
						}
						if (valueList.indexOf(maxAttrValue) !== -1) {
							maxAttrValue = valueList.indexOf(maxAttrValue);
						} else {
							maxAttrValue = valueList.length;
						}
					}

					if (maxAttrValue < minAttrValue) {
						minAttrValue = 0;
						maxAttrValue = valueList.length;
					}

					parent.find('#wpfMinAttrNum').attr('data-min-numeric-value', minAttrValue);
					parent.find('#wpfMaxAttrNum').attr('data-max-numeric-value', maxAttrValue);

					sliderData.update({from: minAttrValue, to: maxAttrValue});
				});

				// price slider
				filter.find('input#wpfPriceRangeField').on('blur', function (e) {
					var parent 	   = jQuery(this).closest('.wpfFilterContent'),
						sliderData = parent.find('.ion-range-slider').data('ionRangeSlider');
					sliderData.update({from: filter.find(minInputId).val(), to: filter.find(maxInputId).val()});
				});
			}
		});

		WpfFrontendPage.constructor.prototype.initShowMore = (function(verBlock) {
			if (verBlock.length == 0) return;
			var isOpen = verBlock.attr('data-open-more');
			if (isOpen == '1') return;

			var settings = WpfFrontendPage.getFilterMainSettings(verBlock.closest('.wpfMainWrapper'));
			if(settings && settings.settings.display_view_more === '1' && !settings.settings.display_items_in_a_row) {
				verBlock.attr('data-open-more', 0);
				verBlock.css({'maxHeight': ''}).find('li').removeClass('wpfMoreHidden');
				verBlock.find('.wpfShowMoreWrapper').remove();
				verBlock.find('.wpfShowFewerWrapper').remove();
				
				verBlock.scrollTop(0);
				var verObj = verBlock.get(0),
					maxShowMore = verBlock.closest('.wpfFilterWrapper').attr('data-max-showmore') || 0;
				if(verObj.scrollHeight > verObj.clientHeight || (maxShowMore > 0 && verBlock.find('li:visible').length > maxShowMore)) {
					var viewHeigth = verObj.clientHeight,
						rectBlock = verObj.getBoundingClientRect(),
						findHidden = false,
						lastVisible = false,
						cnt = 0;
					verBlock.find('li').each(function () {
						var li = jQuery(this),
							rect = this.getBoundingClientRect();

						if(rect.height > 0) {
							var mTop = parseInt(li.css('margin-top')),
								mBottom = parseInt(li.css('margin-bottom'));
							if(lastVisible && (findHidden || (maxShowMore > 0 && maxShowMore < cnt) || rect.top - (mTop < 0 ? mTop : 0) < rectBlock.top || rect.bottom - (mBottom < 0 ? mBottom : 0) > rectBlock.bottom)) {
								li.addClass('wpfMoreHidden');
								findHidden = true;
							} else if (!findHidden) {
								lastVisible = li;
								cnt++;
							}
						}
					});
					if(lastVisible && (findHidden || (maxShowMore > 0 && maxShowMore < cnt))) {
						var more = jQuery('<li class="wpfShowMoreWrapper"> + ' + wpfTraslate.ShowMore + '</li>');
						if(settings.settings.view_more_full == '1') {
							more.attr('data-full-opening', 1);
						}
						more.insertBefore(lastVisible);
						lastVisible.addClass('wpfMoreHidden');
					}
				}
			}
		});

		WpfFrontendPage.constructor.prototype.addSelectedParamsPro = (function (wrapper, params, filterType, filterName, filterLogic) {
			if(!wrapper || !('selected' in params)) return;

			var selected = params['selected'],
				isOne = 'is_one' in selected && selected['is_one'] ? 1 : 0,
				current = wrapper.find('.wpfSelectedParameter[data-filter-type="'+filterType+'"][data-filter-name="'+filterName+'"]'),
				clear = wrapper.find('.wpfSelectedParametersClear'),
				list = 'list' in selected && selected['list'],
				curLen = current.length;
			if(curLen) {
				if((isOne && curLen > 1) || list.length == 0) {
					current.remove();
					current = [];
				}
				else current.attr('data-delete', 1);
			}

			for(var key in list) {
				var found = current.length ? current.filter('[data-key="'+key+'"]') : false,
					strSelected = list[key].replace(/(<?)(h[1234])(>?)/g, '$1div$3');
				if(found && found.length) {
					found.find('.wpfSelectedTitle').html(strSelected);
					found.removeAttr('data-delete');
				} else {
					var obj = jQuery(
						'<div class="wpfSelectedParameter" data-filter-type="'+ filterType +
						'" data-filter-name="' + filterName +
						'" data-query-logic="' + filterLogic +
						'" data-is-one="' + isOne +
						'" data-key="' + key +
						'"></div>');
					obj.append(jQuery('<div class="wpfSelectedDelete">x</div>'));
					obj.append(jQuery('<div class="wpfSelectedTitle">' + strSelected + '</div>'));
					obj.css('background-color', WpfFrontendPage.selectedParamsColor);

					if (clear.length) {
						obj.insertBefore(clear);
					} else {
						wrapper.append(obj);
					}
				}
			}
			if (current.length) current.filter('[data-delete]').remove();

			if (wrapper.find('.wpfSelectedParameter').length == 0) {
				wrapper.addClass('wpfHidden');
			} else {
				wrapper.removeClass('wpfHidden');
			}
		});

		WpfFrontendPage.constructor.prototype.setSelectedParamsPro = (function (filter, settings) {
			if(!filter.hasClass('wpfFilterWrapper')) filter = filter.closest('.wpfFilterWrapper');
			if(filter.length == 0) return;

			if(typeof settings == 'undefined') settings = WpfFrontendPage.getFilterMainSettings(filter.closest('.wpfMainWrapper'));

			if(!settings || settings.settings.display_selected_params !== '1') return;

			var filterType = filter.attr('data-filter-type'),
				filterName = filter.attr('data-get-attribute'),
				filterLogic = filter.attr('data-query-logic'),
				params = filter.hasClass('wpfNotActive') || filter.hasClass('wpfHidden') ? {'selected': {'is_one': true, 'list': []}} : WpfFrontendPage.getFilterOptionsByType(filter, filterType),
				filterId = filter.closest('.wpfMainWrapper').data('filter'),
				wrapper = jQuery('.wpfSelectedParameters[data-filter="' + filterId + '"]').length ? jQuery('.wpfSelectedParameters[data-filter="' + filterId + '"]') : filter.closest('.wpfMainWrapper').find('.wpfSelectedParameters');
				params = WpfFrontendPage.filterParamByParentPro(settings, params);

			WpfFrontendPage.addSelectedParamsPro(wrapper, params, filterType, filterName, filterLogic);
		});

		WpfFrontendPage.constructor.prototype.eventChangeFilterPro = (function (filter, settings) {
			WpfFrontendPage.setSelectedParamsPro(filter, settings);
		});

		WpfFrontendPage.constructor.prototype.autoUnfoldByCheck = (function (filter) {
			if (filter.closest('.wpfFilterWrapper').data('autounfold') != '1') return;
			if (filter.is('input:checked')) {
				filter.closest('li').find('input:checked').each(function() {
					var $collapsible = jQuery(this).closest('.wpfLiLabel').find('.wpfCollapsible');
					if ($collapsible.length) {
						var $icon = $collapsible.find('i.fa, svg');
						if ($icon.length) {
							var icons = WpfFrontendPage.getIcons($icon);
							if (icons.collapsed) {
								setTimeout(function () {
									WpfFrontendPage.collapsibleToggle($icon, icons, $collapsible.closest('li'));
								}, 100);
							}
						}
					}
				});
			} else {
				if (filter.closest('li').find('ul input:checked').length == 0) {
					var $collapsible = filter.closest('.wpfLiLabel').find('.wpfCollapsible');
					if ($collapsible.length) {
						var $icon = $collapsible.find('i.fa, svg');
						if ($icon.length) {
							var icons = WpfFrontendPage.getIcons($icon);
							if (!icons.collapsed) {
								setTimeout(function () {
									WpfFrontendPage.collapsibleToggle($icon, icons, $collapsible.closest('li'));
								}, 100);
							}
						}
					}
				}
			}
		});
		
		WpfFrontendPage.constructor.prototype.scrollToProductsPro = (function (settings) {
			var _thisObj = this.$obj;
			if (settings && settings.scroll_after_filtration == 1) {
				var speed = settings.scroll_after_filtration_speed || 1500,
					retreat = settings.scroll_after_filtration_retreat || 30,
					listSelector = _thisObj.currentProductBlock ? _thisObj.currentProductBlock : _thisObj.defaultProductSelector;

				if (typeof listSelector !== 'undefined' && listSelector != '') {

					jQuery(document).on('wpfAjaxSuccess', function(e){
						e.preventDefault();

						if (jQuery('body').find(listSelector).length === 1) {
							jQuery('html,body').animate({'scrollTop': jQuery('body').find(listSelector).eq(0).offset().top - retreat}, speed);
						}

	   					return false;

					});

					jQuery('body,html').bind('scroll mousedown wheel DOMMouseScroll mousewheel keyup touchmove', function(e){
		            	jQuery('body,html').stop();
	    	    	});
	    	    }

			}
		});

		WpfFrontendPage.constructor.prototype.changeUrlByFilterParamsPro = (function ($filtersDataFrontend, noWooPage, filterWrapper) {
			var _thisObj = this.$obj;
			switch ($filtersDataFrontend['id']) {
				case 'wpfSearchText':
				case 'wpfSearchNumber':
					var value = $filtersDataFrontend['settings']['value'],
						attr = $filtersDataFrontend['settings']['attribute'];
					if (typeof value !== 'undefined' && value.length > 0 ) {
						_thisObj.QStringWork(attr, encodeURIComponent(value), noWooPage, filterWrapper, 'change');
					}else{
						_thisObj.QStringWork(attr, '', noWooPage, filterWrapper, 'remove');
					}
					break;
				case 'wpfBrand':
					var product_brand = $filtersDataFrontend['settings']['settings'],
						name = $filtersDataFrontend['name'];

					product_brand = product_brand.join(',');
					if (typeof product_brand !== 'undefined' && product_brand.length > 0) {
						_thisObj.QStringWork(name, product_brand, noWooPage, filterWrapper, 'change');
					}else{
						_thisObj.QStringWork(name, '', noWooPage, filterWrapper, 'remove');
					}
					break;
				case 'wpfVendors':
					var vendorsVal = $filtersDataFrontend['settings']['settings'],
						name = $filtersDataFrontend['name'],
						delim = $filtersDataFrontend['delim'];
						vendorsVal = vendorsVal.join(delim ? delim : '|');
					if (typeof vendorsVal !== 'undefined' && vendorsVal.length > 0) {
						_thisObj.QStringWork('vendors', vendorsVal, noWooPage, filterWrapper, 'change');
					}else{
						_thisObj.QStringWork('vendors', '', noWooPage, filterWrapper, 'remove');
					}
					break;
				default:
					break;
			}

			return;
		});
		
		WpfFrontendPage.constructor.prototype.syncronizeFiltersPro = (function ($filter, $synchroFilters) {
			var _thisObj = this.$obj;
			if($filter.find('.ion-range-slider').length) {
				if($filter.attr('data-display-type') == 'slider') {
					
					var min = $filter.find('#wpfMinAttrNum').val(),
						max = $filter.find('#wpfMaxAttrNum').val(),
						valueList = $filter.find('.ion-range-slider').attr('data-values').replaceAll(', ',',').split(','),
						minNum = valueList.indexOf(min.trim()),
						maxNum = valueList.indexOf(max.trim());
					$synchroFilters.each(function() {
						var $slider = jQuery(this);
						$slider.find('#wpfMinAttrNum').val(min);
						$slider.find('#wpfMaxAttrNum').val(max);
						$slider.find('#wpfMinAttrNum').attr('data-min-numeric-value', minNum);
						$slider.find('#wpfMinAttrNum').attr('data-max-numeric-value', maxNum);
						$slider.find('.ion-range-slider').data('ionRangeSlider').update({from: minNum, to: maxNum});
					});
				}
			}
			return;
		});

		WpfFrontendPage.constructor.prototype.clearFiltersPro = (function ($filter) {
			var _thisObj = this.$obj;
			if($filter.find('.ion-range-slider').length) {
				var slider = $filter.find('.ion-range-slider').data('ionRangeSlider');

				if($filter.attr('data-display-type') == 'slider') {

					var min = $filter.attr('data-minvalue-without-filtering'),
						max = $filter.attr('data-maxvalue-without-filtering'),
						slugs = $filter.attr('data-slugs-without-filtering'),
						values = $filter.attr('data-values-without-filtering'),
						ids = $filter.attr('data-ids-without-filtering'),
						valuesArray = values.split(',');

					$filter.find('#wpfMinAttrNum').val(min);
					$filter.find('#wpfMaxAttrNum').val(max);

					$filter.find('.ion-range-slider').attr('data-slugs', slugs);
					$filter.find('.ion-range-slider').attr('data-values', values);
					$filter.find('.ion-range-slider').attr('value', values);
					$filter.find('.ion-range-slider').attr('data-term-ids', ids);
					$filter.find('.ion-range-slider').attr('data-min', min);
					$filter.find('.ion-range-slider').attr('data-max', max);
					$filter.find('#wpfMinAttrNum').attr('data-min-numeric-value', 0);
					$filter.find('#wpfMaxAttrNum').attr('data-max-numeric-value', valuesArray.length - 1);
					slider.update({
						from: 0,
						to: valuesArray.length - 1,
						values: valuesArray
					});
				}
			}
			if($filter.attr('data-display-type') == 'linestars') {
				$filter.find('.wpfStarsAdditional').text($filter.find('.wpfStarsRating').attr('data-add-text'));
			} else {
				$filter.find('.wpfStarsRatingBlock').removeClass('wpfLineChecked');
			}
			$filter.find('.wpfTermChecked').removeClass('wpfTermChecked');
			$filter.find('.wpfPriceRangeCustom input').val('');
			$filter.find('.wpfPriceRangeCustom').closest('li').attr('data-range', '');
			_thisObj.styleCheckboxSelected($filter);
			return;
		});

		WpfFrontendPage.constructor.prototype.getSearchTextFilterOptions = (function ($filter) {
			var optionsArray = [],
				value = $filter.find('input').val(),
				options = {
					attribute: $filter.attr('data-get-attribute'),
					value: value,
					fullword: $filter.attr('data-full-word'),
					excluded: JSON.parse($filter.attr('data-excluded'))
				};

			//options for backend (filtering)
			optionsArray['backend'] = options;

			//options for frontend(change url)
			optionsArray['frontend'] = options;
			optionsArray['selected'] = {'is_one': true, 'list': (value == '' ? [] : [value])};
			if(value != '') optionsArray['stats'] = [value];
			return optionsArray;
		});
		
		WpfFrontendPage.constructor.prototype.getSearchNumberFilterOptions = (function ($filter) {
			var optionsArray = [],
				value = $filter.find('input').val(),
				options = {
					attribute: $filter.attr('data-get-attribute'),
					value: value
				};

			//options for backend (filtering)
			optionsArray['backend'] = options;

			//options for frontend(change url)
			optionsArray['frontend'] = options;
			optionsArray['selected'] = {'is_one': true, 'list': (value == '' ? [] : [value])};
			if(value != '') optionsArray['stats'] = [value];
			return optionsArray;
		});

		WpfFrontendPage.constructor.prototype.getBrandFilterOptions = (function ($filter) {
			var _thisObj = this.$obj,
				optionsArray = [],
				frontendOptions = [],
				options = [],
				filterType = $filter.attr('data-display-type'),
				selectedOptions = {'is_one': (filterType != 'mul_dropdown'), 'list': []},
				statistics = [],
				withCount = $filter.hasClass('wpfShowCount'),
				i = 0;

			//options for backend (filtering)
			if(filterType === 'list' || filterType === 'multi'){
				$filter.find('input:checked').each(function () {
					var li = jQuery(this).closest('li'),
						id = li.attr('data-term-id');
					options[i] = id;
					frontendOptions[i] = li.attr('data-term-slug');
					var name = li.find('.wpfValue').html();
					selectedOptions['list'][id] = name;
					statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
					i++;
				});
			}else if(filterType === 'dropdown'){
				var option = $filter.find(":selected"),
					value = option.val();
				options[i] = value;
				if(value != '') {
					frontendOptions[i] = option.attr('data-slug');
					var name = _thisObj.getClearLabel(option.html(), withCount);
					selectedOptions['list'][option.attr('data-term-id')] = name;
					statistics.push(name);
				}
			}else if(filterType === 'mul_dropdown'){
				$filter.find(':selected').each(function () {
					var option = jQuery(this);
					options[i] = option.val();
					frontendOptions[i] = option.attr('data-slug');
					var name = _thisObj.getClearLabel(option.html(), withCount);
					selectedOptions['list'][option.attr('data-term-id')] = name;
					statistics.push(name);
					i++;
				});
			}
			optionsArray['backend'] = options;

			//options for frontend(change url)
			var getParams = $filter.attr('data-get-attribute');

			optionsArray['frontend'] = [];
			optionsArray['frontend']['taxonomy'] = getParams;
			optionsArray['frontend']['settings'] = frontendOptions;
			optionsArray['selected'] = selectedOptions;
			optionsArray['stats'] = statistics;

			return optionsArray;
		});
		
		WpfFrontendPage.constructor.prototype.getVendorsFilterOptions = (function ($filter) {
			var optionsArray = [],
				 _thisObj = this.$obj,
				options = [],
				frontendOptions = [],
				filterType = $filter.attr('data-display-type'),
				selectedOptions = {'is_one': (filterType == 'dropdown'), 'list': []},
				statistics = [],
				i = 0;

			//options for backend (filtering)
			if (filterType === 'list') {
				$filter.find('input:checked').each(function () {
					var li = jQuery(this).closest('li'),
						id = li.attr('data-term-id');
					options[i] = id;
					frontendOptions[i] = li.attr('data-term-slug');
					var name = li.find('.wpfValue').html();
					selectedOptions['list'][id] = name;
					statistics.push(li.find('.wpfFilterTaxNameWrapper').length ? li.find('.wpfFilterTaxNameWrapper').html() : name);
					i++;
				});
			} else if (filterType === 'dropdown') {
				var option = $filter.find(":selected"),
					value = option.val();
				options[i] = value;
				if(value != '') {
					frontendOptions[i] = option.attr('data-slug');
					var name = option.html();
					selectedOptions['list'][option.attr('data-term-id')] = name;
					statistics.push(name);
				}
			} else if (filterType === 'mul_dropdown') {
				$filter.find(':selected').each(function () {
					var option = jQuery(this);
					options[i] = option.val();
					frontendOptions[i] = option.attr('data-slug');
					var name = _thisObj.getClearLabel(option.html());
					selectedOptions['list'][option.attr('data-term-id')] = name;
					statistics.push(name);
					i++;
				});
			}
			optionsArray['backend'] = options;

			//options for frontend(change url)
			var getParams = $filter.attr('data-get-attribute');
			
			optionsArray['frontend'] = [];
			optionsArray['frontend']['taxonomy'] = getParams;
			optionsArray['frontend']['settings'] = frontendOptions;
			optionsArray['selected'] = selectedOptions;
			optionsArray['stats'] = statistics;
			
			return optionsArray;
		});

		WpfFrontendPage.constructor.prototype.wpfShowHideFiltersAttsPro = (function(filter) {
			filter.find('li').removeClass('wpfMoreHidden');
			var links = filter.find('.wpfAbcLink');
			if(links.length) {
				links.each(function(){
					var link = jQuery(this),
						letter = link.attr('data-letter'),
						anchor = filter.find('.wpfAbcLetter[data-letter="'+letter+'"]');
					if(filter.find('li[data-term-id][data-letter="'+letter+'"]:visible').length) {
						link.removeClass('wpfHidden');
						anchor.removeClass('wpfHidden');
					}
					else {
						link.addClass('wpfHidden');
						anchor.addClass('wpfHidden');
					}
				});
				links.removeClass('wpfAbcLinkFirst').filter(':not(.wpfHidden):first').addClass('wpfAbcLinkFirst');
			}
			filter.find('.wpfFilterVerScroll').each(function () {
				WpfFrontendPage.initShowMore(jQuery(this));
			});
		});

		WpfFrontendPage.constructor.prototype.filterParamByParentPro = (function(settings, params) {

			if (settings.settings.expand_selected_to_child === '0') {
				if (typeof params.selected.removeSelected !== 'undefined' && typeof params.selected.list !== 'undefined') {
					var selectedList = params.selected.list,
						removeSelected = params.selected.removeSelected;

					for (var selectedtId in selectedList) {
						if ( removeSelected.indexOf(parseInt(selectedtId)) !== -1 ) {
							delete selectedList[selectedtId];
						}
					}

					params.selected.list = selectedList;
				}
			}

			return params;
		});

		WpfFrontendPage.constructor.prototype.getHideButtons = (function (mainWrapper, settings, isActive) {
			var isMobile = false,
				screenSize = jQuery(window).width();
			if (settings.settings !== undefined) {
				var isMobileBreakpoin = settings.settings.desctop_mobile_breakpoint_switcher,
					mobileBreakpoinWidth = settings.settings.desctop_mobile_breakpoint_width;

				if (isMobileBreakpoin && '0' !== isMobileBreakpoin && mobileBreakpoinWidth && '0' !== mobileBreakpoinWidth) {
					if (screenSize <= mobileBreakpoinWidth) {
						isMobile = true;
					}
					
					mainWrapper.find('.wfpHideButton[data-show-on-mobile]').each(function () {
						var button = jQuery(this),
							showDesctop = jQuery(this).data('show-on-desctop'),
							showMobile = jQuery(this).data('show-on-mobile'),
							openFilteredD = button.data('filtered-open') == '1',
							openFilteredM = button.data('filtered-open-mobile') == '1',
							mobileFloating = jQuery(this).data('button-mobile-floating');
						button.show();
						if (isMobile) {
							if (mobileFloating) {
								button.addClass('wpfHideButtonMobile');
							}
							if (showMobile == 'yes_close' && (!openFilteredM || !isActive)) {
								WpfFrontendPage.toggleHideFiltersButton(button, 'up');
							} else {
								WpfFrontendPage.toggleHideFiltersButton(button, 'down');
								if (showMobile == 'no') button.hide();
							}
						} else {
							button.removeClass('wpfHideButtonMobile');
							if (showDesctop == 'yes_close' && (!openFilteredD || !isActive)) {
								WpfFrontendPage.toggleHideFiltersButton(button, 'up');
							} else {
								WpfFrontendPage.toggleHideFiltersButton(button, 'down');
								if (showDesctop == 'no') button.hide();
							}
						}
					});
				}
			}
		});

		WpfFrontendPage.constructor.prototype.toggleHideFiltersButton = (function (button, toggle) {
			var txt = button.find('.wfpHideText');
			if (toggle == 'up') {
				button.find('i.fa, svg').removeClass('fa-chevron-up').addClass('fa-chevron-down');
				txt.html(button.attr('data-show-text'));
				button.siblings('div:not(.wpfPreviewLoader):not(.wpfLoaderLayout)').addClass('wpfHideFilter');
			} else if (toggle == 'down') {
				button.find('i.fa, svg').removeClass('fa-chevron-down').addClass('fa-chevron-up');
				txt.html(button.attr('data-hide-text'));
				button.siblings('div').removeClass('wpfHideFilter');
			}
			button.attr('data-closed', toggle == 'up' ? 1 : 0);

			// Hide button
			var hideButtonEvent = document.createEvent('Event');
			hideButtonEvent.initEvent('wpfHideButtonClick', false, true);
			document.dispatchEvent(hideButtonEvent);
		});

		WpfFrontendPage.constructor.prototype.getIcons = (function ($icon) {
			let
				plusIcon  = $icon.attr('class').match(/(fa-plus|fa-[^\s]+-down)/),
				minusIcon = null,
				collapsed = true;
			if (null !== plusIcon) {
				plusIcon  = plusIcon[1];
				minusIcon = ('fa-plus' === plusIcon) ? "fa-minus" : plusIcon.replace(/(fa-[^\s]+-)down/, '$1up');
			} else {
				collapsed = false;
				minusIcon = $icon.attr('class').match(/(fa-minus|fa-[^\s]+-up)/);
				if (null !== minusIcon) {
					minusIcon = minusIcon[1];
					plusIcon  = ('fa-minus' === minusIcon) ? "fa-plus" : minusIcon.replace(/(fa-[^\s]+-)up/, '$1down');
				}
			}

			return {collapsed, plusIcon, minusIcon};
		});

		WpfFrontendPage.constructor.prototype.enableFiltersLoaderPro = (function(idWrapper, productListElem){
			if (productListElem.find('.wpf-loader-decorator').length == 0) {
				var preview = jQuery('#' + idWrapper + ' .wpfPreviewLoader').first().clone().removeClass('wpfHidden');
				productListElem.css('position', 'relative');
				jQuery('<div>', {
					class: 'wpf-loader-decorator',
					title: 'Loading...'
				}).html(preview).appendTo(productListElem);
			}
		});
		
		WpfFrontendPage.constructor.prototype.prepareStatisticsData = (function ($filter, settings) {
			if ($filter.hasClass('wpfPreselected')) return;
			
			if ('stats' in settings && settings['stats'].length > 0) {
				var _thisObj = this.$obj,
					id = _thisObj.filteringId,
					uniqId = $filter.attr('data-uniq-id');
				if (typeof uniqId == 'undefined') return;
				if (!(id in _thisObj.statistics)) {
					var wrapper = $filter.closest('.wpfMainWrapper');
					_thisObj.statistics[id] = {
						id: wrapper.attr('data-filter'), 
						page: wrapper.attr('data-page'), 
						user: wrapper.attr('data-user-id'), 
						found: 0,
						blocks: {}
					};
				}
				var uniqId = $filter.attr('data-uniq-id')
				_thisObj.statistics[id]['blocks'][uniqId] = settings['stats'];
			}
		});
		WpfFrontendPage.constructor.prototype.saveStatistics = (function (fid, isFound, requestData) {
			var _thisObj = this.$obj;
			if (!(fid in _thisObj.statistics)) return;
			
			if (typeof requestData != 'undefined' && isFound == -1) {
				requestData['only_statistics'] = 1;
			} else {
				requestData = {mod: 'statistics', action: 'saveStatistics'};
			}
			_thisObj.statistics[fid]['found'] = isFound;
			requestData['statistics'] = JSON.stringify(_thisObj.statistics[fid]);
			
			jQuery.sendFormWpf({data: requestData});
			
			delete _thisObj.statistics[fid];
		});
		WpfFrontendPage.constructor.prototype.updateAttrSlider = (function ($filter, termIds) {
			var _thisObj = this.$obj;
			if ($filter.attr('data-display-type') == 'slider' && $filter.find('.ion-range-slider').length) {
				var sliderWrapper = $filter.find('.ion-range-slider'),
					slider = sliderWrapper.data('ionRangeSlider'),
					slugsDef = $filter.attr('data-slugs-without-filtering').replaceAll(', ',',').split(','),
					valuesDef = $filter.attr('data-values-without-filtering').replaceAll(', ',',').split(','),
					idsDef = $filter.attr('data-ids-without-filtering').replaceAll(', ',',').split(','),
					slugs = [], values = [], ids = [];
						
				idsDef.forEach(function(id, index) {
					if (id in termIds) {
						slugs.push(slugsDef[index]);
						values.push(valuesDef[index]);
						ids.push(id);
					}
				});
				var cntValues = ids.length,
					hideSingle = $filter.attr('data-hide-single') == '1';
				if (cntValues == 0 || (cntValues <= 1 && hideSingle)) $filter.hide();
				else {
					var min = values[0],
						max = values[values.length-1];

					$filter.find('#wpfMinAttrNum').val(min);
					$filter.find('#wpfMaxAttrNum').val(max);

					sliderWrapper.attr('data-slugs', slugs);
					sliderWrapper.attr('data-values', values.join(','));
					sliderWrapper.attr('value', values.join(','));
					sliderWrapper.attr('data-term-ids', ids.join(','));
					sliderWrapper.attr('data-min', min);
					sliderWrapper.attr('data-max', max);
					$filter.find('#wpfMinAttrNum').attr('data-min-numeric-value', 0);
					$filter.find('#wpfMaxAttrNum').attr('data-max-numeric-value', values.length - 1);
					slider.update({
						from: 0,
						to: values.length - 1,
						values: values
					});
					$filter.show();
				}
			}
		});
		
		WpfFrontendPage.eventsFrontendPro();
	}
	if (window.wpIinitialiseImmediately && window.wpfFrontendPage) {
		doProIntialise();
	} else {
		$(document).ready(function() {
			doProIntialise();
		});
	}
}(window.jQuery));

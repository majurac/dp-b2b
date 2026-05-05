(function ($) {
    "use strict";
    if (!window.wpfAdminPagePro) {
        window.wpfAdminPagePro = function () {
            var WpfAdminPage = window.wpfAdminPage;

            WpfAdminPage.constructor.prototype.initPro = (function () {
                var isWaitLoad = typeof WpfAdminPage.wpfWaitLoad != 'undefined';
                if (isWaitLoad) WpfAdminPage.wpfWaitLoad = true;
                wpfEventsOptionsPro();
                wpfEventsDesingPro();
                wpfEventsFloatingPro();
                if (isWaitLoad) WpfAdminPage.wpfWaitLoad = false;
                jQuery(".wpfProOption").hide();

                function wpfEventsOptionsPro() {
                    jQuery('#row-tab-options select[name="settings[display_page_list][]').chosen({width: "95%"});
                    jQuery('#row-tab-options select[name="settings[display_cat_list][]').chosen({width: "95%"});
					jQuery('#row-tab-options select[name="settings[display_pwb_list][]').chosen({width: "95%"});
                }

                function wpfEventsDesingPro() {
                    var optionsContainer = jQuery('#row-tab-options'),
                        $iconName = jQuery('input[name="settings[filter_loader_icon_name]"]'),
                        $animation = jQuery('[name="settings[filter_loader_custom_animation]"]');
                    jQuery('#wpfSelectLoaderButton').off('click').on('click', function (e) {
                        e.preventDefault();
                        var $button = jQuery(this),
                            $input = $button.parent().find('input'),
                            $preview = jQuery('.wpfIconPreview'),
                            _custom_media = true;
                        wp.media.editor.send.attachment = function (props, attachment) {
                            wp.media.editor._attachSent = true;
                            if (_custom_media) {
                                var selectedUrl = attachment.url,
                                    imgWidth = attachment.width,
                                    imgHeight = attachment.height;
                                if (props && props.size && attachment.sizes && attachment.sizes[props.size] && attachment.sizes[props.size].url) {
                                    var imgSize = attachment.sizes[props.size];
                                    selectedUrl = imgSize.url;
                                    imgWidth = imgSize.width;
                                    imgHeight = imgSize.height;
                                }
                                $input.val('background-image:url(' + selectedUrl + ');width:' + imgWidth + 'px;height:' + imgHeight + 'px;');
                                $iconName.val('custom');
                                if ($preview.length) {
                                    $preview.html('<div class="woobewoo-filter-loader wpfCustomLoader"></div>');
                                    $preview.find('.woobewoo-filter-loader').css({
                                        'background-image': 'url(' + selectedUrl + ')',
                                        'width': imgWidth + 'px',
                                        'height': imgHeight + 'px'
                                    });
                                    $animation.trigger('change');
                                }
                            } else {
                                return _orig_send_attachment.apply(this, [props, attachment]);
                            }
                        };
                        wp.media.editor.insert = function (html) {
                            if (_custom_media) {
                                if (wp.media.editor._attachSent) {
                                    wp.media.editor._attachSent = false;
                                    return;
                                }
                                if (html && html != "") {
                                    var selectedUrl = $(html).attr('src');
                                    if (selectedUrl) {
                                        $input.val('background-image:url(' + selectedUrl + ');');
                                        $iconName.val('custom');
                                        if ($preview.length) {
                                            $preview.html('<div class="woobewoo-filter-loader wpfCustomLoader"></div>');
                                            $preview.find('.woobewoo-filter-loader').css({'background-image': 'url(' + selectedUrl + ')'});
                                            $animation.trigger('change');
                                        }
                                    }
                                }
                            }
                        };
                        wp.media.editor.open($button);
                        return false;
                    });
                    var hideButtons = optionsContainer.find('select[name^="settings[display_hide_button"]');
                    hideButtons.off('change').on('change', function () {
                        var textSettings = optionsContainer.find('input[name="settings[hide_button_hide_text]"], input[name="settings[hide_button_show_text]"]').closest('.settings-value'),
                            isShow = false;
                        hideButtons.each(function () {
                            if (jQuery(this).val() != 'no') isShow = true;
                        });
                        if (isShow) textSettings.removeClass('wpfHidden');
                        else textSettings.addClass('wpfHidden');
                    });

                    var designContainer = jQuery('#row-tab-design');

                    designContainer.find('.wpfCopyStyles').off('click').on('click', function () {
                        var forCopy = jQuery(this).attr('data-style'),
                            selector = 'div[data-style="' + forCopy + '"] ';
                        jQuery('.wpfFiltersTabContents').find(selector + 'input, ' + selector + 'select').each(function () {
                            var $this = jQuery(this),
                                name = $this.attr('name');
                            if (typeof name != 'undefined' && name.length) {
                                var hover = jQuery('.wpfFiltersTabContents ' + this.nodeName + '[name="' + name.slice(0, -1) + '_hover]"]');
                                if (hover.length == 1) {
                                    if (hover.hasClass('woobewoo-color-result-text')) hover.closest('.woobewoo-color-picker').find('.woobewoo-color-result').wpColorPicker('color', $this.val());
                                    else hover.val($this.val());
                                    hover.trigger('wpf-change');
                                }
                            }
                        });
                        WpfAdminPage.getPreviewAjax();
                        return false;
                    });

                    jQuery('[name="settings[filter_loader_icon_color]"]').off('input change').on('input change', function () {
                        var color = jQuery(this).val();
                        jQuery('.woobewoo-filter-loader').css({color: color});
                    }).trigger('change');

                    var $customIcon = jQuery('[name="settings[filter_loader_custom_icon]"]');
                    $animation.off('change').on('change', function () {
                        var $loaderPreview = jQuery('.wpfIconPreview .woobewoo-filter-loader'),
                            customStyle = $customIcon ? $customIcon.val() : '',
                            mask = "animate-\\S+";
                        $loaderPreview.removeClass(function (index, cls) {
                            return (cls.match(new RegExp('\\b' + mask + '', 'g')) || []).join(' ');
                        });

                        if ($iconName.val() == 'custom' && customStyle.length) {
                            $loaderPreview.attr('style', customStyle);
                            var anim = jQuery(this).val();
                            if (anim.length) {
                                $loaderPreview.addClass('animate-' + anim);
                            }
                        }
                    }).trigger('change');

                    jQuery('.applyLoaderIcon').off('click').on('click', function (e) {
                        e.preventDefault();
                        var loaderSettings = {};

                        jQuery('input[data-loader-settings="1"]').each(function () {
                            var $this = jQuery(this);
                            loaderSettings[$this.attr('name').replace('settings[', '').replace(']', '')] = ($this.attr('type') == 'checkbox' ? ($this.is(':checked') ? 1 : 0) : $this.val());
                        });

                        jQuery.sendFormWpf({
                            data: {
                                mod: 'woofilterpro',
                                action: 'applyLoader',
                                settings: loaderSettings,
                                id: jQuery('#wpfFiltersEditForm').attr('data-table-id')
                            },
                            onSuccess: function (res) {
                                if (!res.error && res.data.message) {
                                    jQuery.sNotify({
                                        'icon': 'fa fa-check',
                                        'content': ' <span> ' + res.data.message + '</span>',
                                        'delay': 2500
                                    });
                                }
                            }
                        });
                    });
                }

                function wpfEventsFloatingPro() {
                    var floatingContainer = $('#sub-tab-design-floating'),
                        defaultIcon = floatingContainer.find('#wpfFloatingDefaultIcon').val();
                    $('.wpfSelectFloatingIcon').off('click').on('click', function (e) {
                        e.preventDefault();
                        var $button = $(this),
                            $input = $button.parent().find('input'),
                            $preview = $button.closest('.settings-block-values').find('.wpfFloatingIconPreview'),
                            $reset = $preview.parent().find('.wpfFloatingRemoveIcon'),
                            _custom_media = true;
                        wp.media.editor.send.attachment = function (props, attachment) {
                            wp.media.editor._attachSent = true;
                            if (_custom_media) {
                                var selectedUrl = attachment.url,
                                    imgWidth = attachment.width,
                                    imgHeight = attachment.height;
                                if (props && props.size && attachment.sizes && attachment.sizes[props.size] && attachment.sizes[props.size].url) {
                                    var imgSize = attachment.sizes[props.size];
                                    selectedUrl = imgSize.url;
                                    imgWidth = imgSize.width;
                                    imgHeight = imgSize.height;
                                }
                                var style = 'background-image:url(' + selectedUrl + ');width:' + imgWidth + 'px;height:' + imgHeight + 'px;';
                                $input.val(style);
                                $preview.attr('style', style);
                                $reset.removeClass('wpfHidden');
                            } else {
                                return _orig_send_attachment.apply(this, [props, attachment]);
                            }
                        }
                        wp.media.editor.insert = function (html) {
                            if (_custom_media) {
                                if (wp.media.editor._attachSent) {
                                    wp.media.editor._attachSent = false;
                                    return;
                                }
                                if (html && html != "") {
                                    var selectedUrl = $(html).attr('src');
                                    if (selectedUrl) {
                                        $input.val('background-image:url(' + selectedUrl + ');');
                                        $preview.css({'background-image': 'url(' + selectedUrl + ')'});
                                        $reset.removeClass('wpfHidden');
                                    }
                                }
                            }
                        };
                        wp.media.editor.open($button);
                        return false;
                    });
                    floatingContainer.find('.wpfFloatingRemoveIcon').off('click').on('click', function (e) {
                        e.preventDefault();
                        var $button = $(this),
                            $wrapper = $button.closest('.settings-block-values');
                        $wrapper.find('input.wpfHiddenFloatingIcon').val(defaultIcon);
                        $wrapper.find('.wpfFloatingIconPreview').attr('style', defaultIcon);
                        $button.addClass('wpfHidden');
                    });
                    floatingContainer.find('.wfpFloatingIconClose input, .wfpFloatingIconClose select').off('change').on('change', function (e) {
                        var $elem = $(this),
                            $wrapper = $elem.closest('.settings-block-values');
                        $wrapper.find('.wfpIconClosePreview i').attr('class', 'fa fa-' + $wrapper.find('.wfpIconClose').val()).css({
                            'color': $wrapper.find('.woobewoo-color-result-text').val(),
                            'font-size': $wrapper.find('.wfpIconCloseSize').val() + 'px'
                        });
                    });
                    floatingContainer.find('.wfpIconClose').trigger('change');
                }
            });

            WpfAdminPage.constructor.prototype.setSkinColor = (function (skin, color, withLabels, filter) {
                var _this = this.$obj,
                    dark = _this.lightenDarkenColor(color, -40),
                    darker = _this.lightenDarkenColor(color, -80),
                    light = _this.lightenDarkenColor(color, 50),
                    style = '',
                    filterId = ' filter_id_placeholder ',
                    filterIdAction = ' filter_admin_area_id_placeholder ';

                if (skin == 'default') {
                    color = '#000000';
                    light = '#ffffff';
                    darker = _this.lightenDarkenColor(color, 50);
                }

                jQuery('.irs-bar').css('background-color', '');
                jQuery('.irs-bar').css('border-color', '');
                jQuery('.irs-bar').css('background', '');

                jQuery('.irs-from').css('background-color', '');
                jQuery('.irs-to').css('background-color', '');
                jQuery('.irs-single').css('background-color', '');

                jQuery('.irs-from').css('border-color', '');
                jQuery('.irs-to').css('border-color', '');
                jQuery('.irs-single').css('border-color', '');

                jQuery('.irs-from:before').css('border-top-color', '');
                jQuery('.irs-to:before').css('border-top-color', '');
                jQuery('.irs-single:before').css('border-top-color', '');

                jQuery('.irs-from:before').css('background', '');
                jQuery('.irs-to:before').css('background', '');
                jQuery('.irs-single:before').css('background', '');

                jQuery('.irs-handle').css('background-color', '');
                jQuery('.irs-handle').css('border-color', '');

                jQuery('.irs-handle i').css('border-color', '');
                jQuery('.irs-handle i').css('background', '');

                jQuery('.irs-handle:hover > i:first-child').css('background', '');
                jQuery('.irs-handle:hover').css('background', '');

                jQuery('.irs-handle.from').css('background-color', '');
                jQuery('.irs-handle.to').css('background-color', '');
                jQuery('.irs-handle:hover').css('background-color', '');

                jQuery('.irs-grid-text').css('color', 'silver');
                jQuery('.irs-grid-pol').css('background-color', 'redsilver');

                jQuery('.irs-max').css('background-color', '');
                jQuery('.irs-min').css('background-color', '');

                switch (skin) {
                    case 'flat':
                        style += filterIdAction + ' .irs-bar, ' + filterIdAction + ' .irs-from, ' + filterIdAction + ' .irs-to, ' + filterIdAction + ' .irs-single{background-color:' + color + ';border-color:' + color + ';}';
                        style += filterIdAction + ' .irs-handle > i:first-child{background-color:' + dark + ';}';
                        style += filterIdAction + ' .irs-handle:hover > i:first-child{background-color:' + darker + ';}';
                        style += filterIdAction + ' .irs-from:before, ' + filterIdAction + '.irs-to:before, ' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        break;
                    case 'big':
                    case 'circle':
                        style += filterIdAction + ' .irs-bar{background-color:' + light + ';border-color:' + color + ';background:linear-gradient(to bottom, #ffffff 0%, ' + color + ' 30%, ' + light + ' 100%);}';
                        style += filterIdAction + ' .irs-from, ' + filterIdAction + '.irs-to, ' + filterIdAction + '.irs-single{background-color:' + color + ';background:linear-gradient(to bottom, ' + color + ' 0%, ' + dark + ' 100%);}';
                        style += filterIdAction + ' .irs-grid-text{color:' + color + ';}';
                        style += filterIdAction + ' .irs-grid-pol{background-color:' + color + ';}';
                        break;
                    case 'rail':
                        style += filterIdAction + ' .irs-bar{background-color:' + light + ';border-color:' + color + ';background:linear-gradient(to bottom, #ffffff 0%, ' + color + ' 30%, ' + light + ' 100%);}';
                        style += filterIdAction + ' .irs-grid-text{color:' + color + ';}';
                        style += filterIdAction + ' .irs-grid-pol{background-color:' + color + ';}';
                        break;
                    case 'modern':
                        style += filterIdAction + ' .irs-bar{background-color:' + color + ';border-color:' + color + ';background:linear-gradient(to bottom, ' + color + ' 0%, ' + dark + ' 100%);}';
                        style += filterIdAction + ' .irs-from,' + filterIdAction + '.irs-to,' + filterIdAction + '.irs-single{background-color:' + color + ';}';
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        break;
                    case 'sharp':
                        style += filterIdAction + ' .irs-bar{background-color:' + light + ';}';
                        style += filterIdAction + ' .irs-from,' + filterIdAction + '.irs-to,' + filterIdAction + '.irs-single{background-color:' + color + ';border-color:' + color + ';}';
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        style += filterIdAction + ' .irs-handle{border-color:' + color + '; background-color: ' + color + ';}';
                        style += filterIdAction + ' .irs-handle:hover{background-color: ' + dark + ';}';
                        style += filterIdAction + ' .irs-handle > i:first-child{border-top-color:' + color + ';}';
                        style += filterIdAction + ' .irs-max,' + filterIdAction + '.irs-min{background-color:' + dark + ';}';
                        break;
                    case 'square':
                    case 'compact':
                        style += filterIdAction + ' .irs-bar, ' + filterIdAction + '.irs-from, ' + filterIdAction + '.irs-to, ' + filterIdAction + '.irs-single{background-color:' + color + ';border-color:' + color + ';}';
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        style += filterIdAction + ' .irs-handle{border-color:' + color + ';}';
                        break;
                    case 'trolley':
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        style += filterIdAction + ' .irs-bar {background-color:' + color + ';border-color:' + dark + ';}';
                        break;
                    case 'round':
                        style += filterIdAction + ' .irs-bar, ' + filterIdAction + '.irs-from, ' + filterIdAction + '.irs-to, ' + filterIdAction + '.irs-single{background-color:' + color + ';border-color:' + color + ';}';
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                    case 'default':
                        style += filterIdAction + ' .irs-bar, ' + filterIdAction + '.irs-from, ' + filterIdAction + '.irs-to, ' + filterIdAction + '.irs-single{background-color:' + color + ';border-color:' + color + ';}';
                        style += filterIdAction + ' .irs-from:before,' + filterIdAction + '.irs-to:before,' + filterIdAction + '.irs-single:before{border-top-color:' + color + ';}';
                        style += filterIdAction + ' .irs-handle{border-color:' + color + ';}';
                }

                if (!withLabels) style += filterIdAction + ' .irs {margin-top:-20px}';

                filter.find('input[name="f_skin_css"]').val(style);
            });

            WpfAdminPage.constructor.prototype.lightenDarkenColor = (function (col, amt) {
                var usePound = false;
                if (col[0] == "#") {
                    col = col.slice(1);
                    usePound = true;
                }
                var num = parseInt(col, 16),
                    r = (num >> 16) + amt,
                    b = ((num >> 8) & 0x00FF) + amt,
                    g = (num & 0x0000FF) + amt;
                if (r > 255) r = 255;
                else if (r < 0) r = 0;
                if (b > 255) b = 255;
                else if (b < 0) b = 0;
                if (g > 255) g = 255;
                else if (g < 0) g = 0;
                var res = (g | (b << 8) | (r << 16)).toString(16);
                return (usePound ? "#" : "") + '0'.repeat(6 - res.length) + res;
            });

            WpfAdminPage.constructor.prototype.eventsFiltersPro = (function ($filter, settings) {
                var _this = this.$obj,
                    filterId = $filter.attr('data-filter');

                switch (filterId) {
                    case 'wpfCategory':
                        var $input = $filter.find('input[name="f_select_default_id"]'),
                            text_error = $input.parent().data('error'),                        
                            $errorContainer = jQuery('<div class="wpfErrorContainer"></div>');
                        
                        $input.after($errorContainer);                        
                        $input.off('change').on('keyup', function () {
                            if (isNaN(jQuery(this).val())) {
                                $errorContainer.html('<span class="wpfErrorColorFilter">' + text_error + '</span>');
                            } else {
                                $errorContainer.empty(); 
                            }
                        });

                        break;
                    case 'wpfAttribute':
                        _this.initAttributeColorFilter($filter, settings);
                        _this.initSliderFilter($filter, settings);
                        break;
					case 'wpfTags':
                        _this.initAttributeColorFilter($filter, settings);
                        break;
                    case 'wpfPrice':
                        _this.initSliderFilter($filter, settings);
                        initShowCurrency_SymbolPos($filter);
                        break;

                    case 'wpfSearchText':
                        var searchBy = $filter.find('input[name="f_search_by"]').val(),
                            fields = $filter.find('.wpfSearchFields input[type="checkbox"]');

                        fields.off('change').on('change', function (e) {
                            e.preventDefault();
                            $filter.find('input[name="f_search_autocomplete"]').closest('tr').css('display', (fields.filter(':not([name="f_search_by_title"]):checked').length ? 'none' : 'table-row'));
                        });

                        if (typeof searchBy != 'undefined' && searchBy != '') {
                            if (searchBy == 'title' || searchBy == 'toc' || searchBy == 'tac') {
                                fields.filter('[name="f_search_by_title"]').prop('checked', true);
                            }
                            if (searchBy == 'content' || searchBy == 'coe' || searchBy == 'toc' || searchBy == 'tac') {
                                fields.filter('[name="f_search_by_content"]').prop('checked', true);
                            }
                            if (searchBy == 'excerpt' || searchBy == 'coe') {
                                fields.filter('[name="f_search_by_excerpt"]').prop('checked', true);
                            }
                            if (searchBy == 'tac') {
                                $filter.find('select[name="f_query_logic"]').val('and');
                            }
                            $filter.find('input[name="f_search_by"]').val('');
                        }
                        fields.filter('[name="f_search_by_title"]').trigger('change');
						
						$filter.find('select[name="f_search_by_attributes_list[]"]').chosen({width: "95%"});
                        break;
					case 'wpfSearchNumber':
						var $attList = $filter.find('select[name="f_list"]'),
							$addAttrs = $filter.find('.wpf-multi-attributes');
						$attList.off('change').on('change', function (e) {
                            e.preventDefault();
                            if (isNaN(jQuery(this).val())) $addAttrs.addClass('wpfHidden');
							else $addAttrs.removeClass('wpfHidden');
                        });
						$attList.trigger('change');
						$filter.find('select[name="f_additional_attributes_list[]"]').chosen({width: "95%"});
                        break;

                    case 'wpfRating':
                        _this.initColorPicker($filter.find('.wpfStarsTypeBlock .woobewoo-color-result'));
                        break;
					case 'wpfInStock':
                        $filter.find('select[name="f_frontend_type"]').off('change').on('change', function() {
							var isSingle = jQuery(this).val() == 'dropdown',
								$hidden = $filter.find('select[name="f_hidden_stock_status"]'),
								$curOption = $hidden.val();
							try {
								var options = JSON.parse($hidden.attr('data-'+(isSingle ? 'single' : 'multi')));
							} catch (e) {
								var options = false;
							}
							if (options) {
								$hidden.find('option').remove();
								for (var key in options) {
									$hidden.append(jQuery('<option></option>').attr('value', key).text(options[key])); 
								}
								if ($curOption) {
									var $forSelect = $hidden.find('option[value="'+$curOption+'"]');
									if ($forSelect.length) $forSelect.prop('selected', true);
									else $hidden.find('option:first').prop('selected', true);
								}
							}
						}).trigger('change');
                        break;
                }
                // Frontend Type: Buttons
                _this.initButtonsFilter($filter, settings);
                // Frontend Type: Switch
                _this.initColorPicker($filter.find('.wpfSwitchTypeBlock .woobewoo-color-result'));

                $filter.find('select[name="f_mlist[]"]').off('change').on('change', function () {
                    _this.hideTermsOptions(jQuery(this));
                });

                _this.initColorGroup();
            });

            WpfAdminPage.constructor.prototype.hideTermsOptions = (function ($list) {
                var $termsContainers = $list.closest('.wpfFilter').find('ul.wpfTermsOptions');
                if ($termsContainers.length == 0) return;

                var terms = $list.val();
                $termsContainers.each(function () {
                    var lis = jQuery(this).find('li').removeClass('wpfHidden');
                    if (terms != null && terms.length) {
                        lis.each(function () {
                            var li = jQuery(this);
                            if (!li.hasClass('wpfErrorColorFilter') && terms.indexOf(li.attr('data-term')) == -1) li.addClass('wpfHidden');
                        });
                    }
                });
            });

            WpfAdminPage.constructor.prototype.chooseIconPopup = (function () {
                var color = jQuery('input[name="settings[filter_loader_icon_color]"]').val();
                jQuery('body').off('click', '#chooseIconPopup .item-inner').on('click', '#chooseIconPopup .item-inner', function (e) {
                    e.preventDefault();
                    var el = jQuery(this),
                        name = el.find('.preicon_img').attr('data-name'),
                        countDiv = el.find('.preicon_img').attr('data-items');

                    jQuery('input[name="settings[filter_loader_icon_name]"]').val(name);
                    jQuery('input[name="settings[filter_loader_icon_number]"]').val(countDiv).trigger('change');
                    if (name === 'spinner' || name === 'default') {
                        jQuery('.wpfIconPreview').html('');
                        jQuery('.wpfIconPreview').html('<div class="woobewoo-filter-loader spinner"></div>');
                    } else {
                        jQuery('.wpfIconPreview').html('');
                        var htmlIcon = ' <div class="woobewoo-filter-loader la-' + name + ' la-2x" style="color: ' + color + ';">';
                        //to display active elements for loader icon we need add div tags
                        for (var i = 0; i < countDiv; i++) {
                            htmlIcon += '<div></div>';
                        }
                        htmlIcon += '</div>';
                        jQuery('.wpfIconPreview').html(htmlIcon);
                    }
                    $container.empty();
                    $container.dialog('close');
                });
                var $container = jQuery('<div id="chooseIconPopup" style="display: none;" title="" /></div>').dialog({
                    modal: true,
                    autoOpen: false,
                    width: 900,
                    height: 750,
                    buttons: {
                        OK: function () {
                            $container.empty();
                            $container.dialog('close');
                        },
                        Cancel: function () {
                            $container.empty();
                            $container.dialog('close');
                        }
                    },
                    create: function () {
                        $(this).closest('.ui-dialog').addClass('woobewoo-plugin');
						if (WPF_DATA.isWCLicense) jQuery(this).closest('.ui-dialog').find('.ui-dialog-buttonset button').addClass('button');
                    }
                });

                var contentHtml = jQuery('.wpfLoaderIconTemplate').clone().removeClass('wpfHidden');
                contentHtml.find('div.preicon_img[data-name="default"] div').css({'backgroundColor': color});
                contentHtml.find('div.preicon_img').not('[data-name="default"]').css({'color': color});
                $container.append(contentHtml);

                var title = jQuery('.chooseLoaderIcon').text();
                $container.dialog("option", "title", title);
                $container.dialog('open');
                return false;
            });

            WpfAdminPage.constructor.prototype.changeButtonTermsPro = (function (filter, settings) {
                var _this = this.$obj,
                    buttonsSettings = filter.find('.wpfButtonsTypeBlock'),
                    optionForm = buttonsSettings.find('.wpfTermsOptionsForm').css('display', 'none').appendTo(buttonsSettings.find('.wpfSettingsPerTerm')),
                    optionsContainer = buttonsSettings.find('ul.wpfTermsOptions').html(''),
                    normalText = optionsContainer.attr('data-normal-text'),
                    checkedText = optionsContainer.attr('data-checked-text');
                if (typeof settings == 'undefined') settings = [];

				if (filter.find('select[name="f_mlist[]"] option').length > optionsContainer.attr('data-max-terms')) {
					optionsContainer.append(jQuery('<li data-term="0" class="wpfErrorColorFilter">' + optionsContainer.attr('data-error') + '</li>'));
				} else {
					filter.find('select[name="f_mlist[]"] option').each(function () {
						var term = jQuery(this),
							value = term.attr('value'),
							termName = term.text(),
							normalName = 'f_buttons_term' + value,
							checkedName = 'f_buttons_check_term' + value,
							textName = 'f_buttons_text_term' + value,
							normalColor = (settings[normalName] ? settings[normalName] : ''),
							checkedColor = (settings[checkedName] ? settings[checkedName] : ''),
							lbText = (settings[textName] ? settings[textName] : ''),
							li = jQuery('<li class="settings-block-values" data-term="' + value + '"></li>');

						li.append('<div class="settings-value"><label>' + normalText + '</label><div class="wpfColorTermPreview" data-field="color_bg" style="background-color:' + normalColor + ';"></div></div>');
						li.append('<div class="settings-value"><label>' + checkedText + '</label><div class="wpfColorTermPreview" data-color="checked" data-field="color_bg_check" style="background-color:' + checkedColor + ';"></div></div>');
						li.append('<label class="settings-value wpfLabelTermPreview">' + (lbText == '' ? termName : lbText) + '</label>');
						li.append('<input type="hidden" name="' + normalName + '" data-field="color_bg" value="' + normalColor + '">');
						li.append('<input type="hidden" name="' + checkedName + '" data-field="color_bg_check" value="' + checkedColor + '">');
						li.append('<input type="hidden" name="' + textName + '" data-field="text_label" data-placeholder="' + termName + '" value="' + lbText + '">');
						li.append('<div class="clear"></div>');

						optionsContainer.append(li);
					});
				}
                optionsContainer.find('li .wpfColorTermPreview').off('click').on('click', function () {
                    _this.toggleTermOptions(optionForm, jQuery(this).closest('li'));
                });

                _this.hideTermsOptions(filter.find('select[name="f_mlist[]"]'));
            });

            WpfAdminPage.constructor.prototype.initButtonsFilter = (function (filter, settings) {
                var _this = this.$obj,
                    buttonsSettings = filter.find('.wpfButtonsTypeBlock');
                if (buttonsSettings.length == 0) return;

                buttonsSettings.find('input[name="f_buttons_per_button"]').off('change').on('change', function () {
                    if (jQuery(this).is(':checked')) buttonsSettings.find('.wpfSettingsPerTerm').removeClass('wpfHidden');
                    else buttonsSettings.find('.wpfSettingsPerTerm').addClass('wpfHidden');
                }).trigger('change');

                if (filter.attr('data-filter') != 'wpfAttribute') _this.changeButtonTermsPro(filter, settings);

                buttonsSettings.find('.wpfTermsOptionsForm .wpfTermsColorBg .woobewoo-color-result').wpColorPicker({
                    hide: true,
                    defaultColor: false,
                    width: 200,
                    border: false,
                    change: function (event, ui) {
                        var color = ui.color.toString(),
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            result = wrapper.find('.woobewoo-color-result-text'),
                            field = wrapper.closest('.wpfTermsColorBg').attr('data-field-temp'),
                            li = wrapper.closest('li'),
                            input = li.find('input[data-field="' + field + '"]');

                        result.val(color).trigger('color-change');
                        wrapper.find('.button').css('color', color);
                        li.find('.wpfColorTermPreview[data-field="' + field + '"]').css('background-color', color);
                        if (input.val() != color) input.val(color).trigger('wpf-update');
                    },
                    clear: function (event, ui) {
                        var color = '',
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            field = wrapper.closest('.wpfTermsColorBg').attr('data-field-temp'),
                            li = wrapper.closest('li'),
                            input = li.find('input[data-field="' + field + '"]');
                        li.find('.wpfColorTermPreview[data-field="' + field + '"]').css('background-color', color);
                        if (input.val() != color) input.val(color).trigger('wpf-update');
                    }
                });

                buttonsSettings.find('.wpfTermsOptionsForm .wpfTermsTextLabel input').off('change').on('change', function () {
                    var $this = jQuery(this),
                        value = $this.val(),
                        li = $this.closest('li');
                    li.find('input[data-field="text_label"]').val(value).trigger('wpf-update');
                    li.find('label.wpfLabelTermPreview').text(value == '' ? $this.attr('data-placeholder') : value);
                });

                WpfAdminPage.initColorPicker(buttonsSettings.find('.woobewoo-color-picker>.woobewoo-color-result'));

                return;
            });

            WpfAdminPage.constructor.prototype.initAttributeColorFilter = (function (filter, settings) {
                var _this = this.$obj,
                    colorsSettings = filter.find('.wpfColorsTypeBlock');


                colorsSettings.find('input[name="f_colors_border"]').off('change').on('change', function (e) {
                    e.preventDefault();
                    if ($(this).prop('checked')) {
                        colorsSettings.find('input[name^="f_colors_border_"]').trigger('change').closest('tr').css('display', 'table-row');
                    } else {
                        colorsSettings.find('input[name^="f_colors_border_"]').closest('tr').hide();
                    }
                });//.trigger('change');
                colorsSettings.find('input[name="f_colors_size"]').off('change').on('change', function (e) {
                    e.preventDefault();
                    var size = $(this).val();
                    colorsSettings.find('.wpfColorTermPreview').css({'width': size + 'px', 'height': size + 'px'});
                }).trigger('change');

                colorsSettings.find('.wpfTermsOptionsForm .wpfTermsColorBg .woobewoo-color-result').wpColorPicker({
                    hide: true,
                    defaultColor: false,
                    width: 200,
                    border: false,
                    change: function (event, ui) {
                        var color = ui.color.toString(),
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            result = wrapper.find('.woobewoo-color-result-text'),
                            li = wrapper.closest('li'),
                            input = li.find('input[data-field="color_bg"]'),
                            bicolor = li.find('input[data-field="bicolor_bg"]').val(),
                            icon = li.find('input[data-field="icon_bg"]').val();

                        result.val(color).trigger('color-change');
                        wrapper.find('.button').css('color', color);
                        if (input.val() != color) {
                            input.val(color).trigger('wpf-update');
                        }

                        if (!icon) {
                            if (color && bicolor) {
                                li.find('.wpfColorTermPreview').css({background: 'linear-gradient(45deg, ' + color + ' 50%, ' + bicolor + ' 50%)'});
                            } else {
                                li.find('.wpfColorTermPreview').css('background-color', color);
                            }
                        }
                    },
                    clear: function (event, ui) {
                        var li = jQuery(event.target).closest('li'),
                            input = li.find('input[data-field="color_bg"]'),
                            bicolor = li.find('input[data-field="bicolor_bg"]').val(),
                            icon = li.find('input[data-field="icon_bg"]').val();

                        input.val('').trigger('wpf-update');

                        if (!icon) {
                            li.find('.wpfColorTermPreview').css('background', '');
                            li.find('.wpfColorTermPreview').css('background-color', bicolor);
                        }
                    }
                });

                colorsSettings.find('.wpfTermsOptionsForm .wpfTermsColorBgBicolor .woobewoo-color-result').wpColorPicker({
                    hide: true,
                    defaultColor: false,
                    width: 200,
                    border: false,
                    change: function (event, ui) {
                        var bicolor = ui.color.toString(),
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            li = wrapper.closest('li'),
                            result = wrapper.find('.woobewoo-color-result-text'),
                            input = li.find('input[data-field="bicolor_bg"]'),
                            color = li.find('input[data-field="color_bg"]').val(),
                            icon = li.find('input[data-field="icon_bg"]').val();

                        result.val(bicolor).trigger('color-change');
                        wrapper.find('.button').css('color', bicolor);
                        if (input.val() != bicolor) {
                            input.val(bicolor).trigger('wpf-update');
                        }

                        if (!icon) {
                            if (color && bicolor) {
                                li.find('.wpfColorTermPreview').css({background: 'linear-gradient(45deg, ' + color + ' 50%, ' + bicolor + ' 50%)'});
                            } else {
                                li.find('.wpfColorTermPreview').css('background-color', bicolor);
                            }
                        }
                    },
                    clear: function (event, ui) {
                        var li = jQuery(event.target).closest('li'),
                            input = li.find('input[data-field="bicolor_bg"]'),
                            color = li.find('input[data-field="color_bg"]').val(),
                            icon = li.find('input[data-field="icon_bg"]').val();

                        input.val('').trigger('wpf-update');

                        if (!icon) {
                            li.find('.wpfColorTermPreview').css('background', '');
                            li.find('.wpfColorTermPreview').css('background-color', color);
                        }
                    }
                });

                colorsSettings.find('.wpfTermsOptionsForm .wpfTermsColorLabel .woobewoo-color-result').wpColorPicker({
                    hide: true,
                    defaultColor: false,
                    width: 200,
                    border: false,
                    change: function (event, ui) {
                        var color = ui.color.toString(),
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            result = wrapper.find('.woobewoo-color-result-text'),
                            li = wrapper.closest('li'),
                            input = li.find('input[data-field="color_label"]');

                        result.val(color).trigger('color-change');
                        wrapper.find('.button').css('color', color);
                        li.find('label').css('color', color);
                        if (input.val() != color) input.val(color).trigger('wpf-update');
                    },
                    clear: function (event, ui) {
                        var li = jQuery(event.target).closest('li'),
                            color = '',
                            input = li.find('input[data-field="color_label"]');
                        li.find('label').removeAttr('style');
                        if (input.val() != color) input.val(color).trigger('wpf-update');
                    }
                });

                colorsSettings.find('.wpfTermsOptionsForm .wpfTermsTextLabel input').off('change').on('change', function () {
                    var $this = jQuery(this),
                        value = $this.val(),
                        li = $this.closest('li');
                    li.find('input[data-field="text_label"]').val(value).trigger('wpf-update');
                    li.find('label.wpfLabelTermPreview').text(value == '' ? $this.attr('data-placeholder') : value);
                });
                _this.initIconSelector(colorsSettings.find('.wpfTermsSelectIcon'));

                colorsSettings.find('input[name="f_colors_hor_spacing"]').off('change').on('change', function (e) {
                    e.preventDefault();
                    colorsSettings.find('.wpfColorsFilterHor label[data-term]').css('margin-right', jQuery(this).val() + 'px');
                });

                WpfAdminPage.initColorPicker(colorsSettings.find('.woobewoo-color-picker>.woobewoo-color-result'));
                return;
            });

            WpfAdminPage.constructor.prototype.initSliderFilter = (function (filter, settings) {
                var _this = this.$obj,
                    $filter = filter;

                var skinSettinges = $filter.find('div.wpfSkinsBlock'),
                    skinColor = $filter.find('input[name="f_skin_color"]'),
                    gridCheckbox = $filter.find('input[name="f_skin_grid"]'),
                    labelsMinMax = $filter.find('input[name="f_skin_labels_minmax"]'),
                    labelsFromTo = $filter.find('input[name="f_skin_labels_fromto"]');

                $filter.find('select[name="f_skin_type"]').off('change').on('change', function (e) {
                    e.preventDefault();
                    var skin = jQuery(this).val();
                    if (skin == 'default') {
                        _this.setSkinColor(skin, skinColor.val(), labelsMinMax.is(':checked') || labelsFromTo.is(':checked'), $filter);
                        skinSettinges.addClass('wpfHidden');
                    } else {
                        jQuery('#wpfPriceStyle').html('');

                        _this.setSkinColor(skin, skinColor.val(), labelsMinMax.is(':checked') || labelsFromTo.is(':checked'), $filter);
                        skinSettinges.removeClass('wpfHidden');
                    }
                }).trigger('change');

                $filter.find('input[name="f_skin_labels_fromto"], input[name="f_skin_labels_minmax"]').off('change').on('change', function (e) {

                    e.preventDefault();

                    _this.setSkinColor($filter.find('select[name="f_skin_type"]').val(), skinColor.val(), labelsMinMax.is(':checked') || labelsFromTo.is(':checked'), $filter);
                });

                skinSettinges.find('.woobewoo-color-result').wpColorPicker({
                    hide: true,
                    defaultColor: false,
                    width: 200,
                    border: false,
                    change: function (event, ui) {
                        var color = ui.color.toString(),
                            wrapper = jQuery(event.target).closest('.woobewoo-color-picker'),
                            result = wrapper.find('.woobewoo-color-result-text');

                        result.val(color).trigger('color-change');
                        wrapper.find('.button').css('color', color);

                        _this.setSkinColor($filter.find('select[name="f_skin_type"]').val(), ui.color.toString(), labelsMinMax.is(':checked') || labelsFromTo.is(':checked'), $filter);
                    }
                });

                return;
            });

            WpfAdminPage.constructor.prototype.initIconSelector = (function ($wrapper) {
                $wrapper.find('a').off('click').on('click', function (e) {
                    e.preventDefault();
                    var $button = jQuery(this),
                        $li = $button.closest('li'),
                        $input = $li.find('input[data-field="icon_bg"]'),
                        $preview = $li.find('.wpfColorTermPreview'),
                        _custom_media = true;
                    wp.media.editor.send.attachment = function (props, attachment) {
                        wp.media.editor._attachSent = true;
                        if (_custom_media) {
                            var selectedUrl = attachment.url,
                                imgWidth = attachment.width,
                                imgHeight = attachment.height;
                            if (props && props.size && attachment.sizes && attachment.sizes[props.size] && attachment.sizes[props.size].url) {
                                var imgSize = attachment.sizes[props.size];
                                selectedUrl = imgSize.url;
                                imgWidth = imgSize.width;
                                imgHeight = imgSize.height;
                            }
                            $input.val('background-image:url(' + selectedUrl + ');width:' + imgWidth + 'px!important;height:' + imgHeight + 'px!important;').trigger('wpf-update');
                            $preview.css('background', '');
                            $preview.css({'background-image': 'url(' + selectedUrl + ')', 'width': imgWidth + 'px', 'height': imgHeight + 'px'});
                        } else {
                            return _orig_send_attachment.apply(this, [props, attachment]);
                        }
                    };
                    wp.media.editor.insert = function (html) {
                        if (_custom_media) {
                            if (wp.media.editor._attachSent) {
                                wp.media.editor._attachSent = false;
                                return;
                            }
                            if (html && html != "") {
                                var selectedUrl = $(html).attr('src');
                                if (selectedUrl) {
                                    $input.val('background-image:url(' + selectedUrl + ');').trigger('wpf-update');
                                    $preview.css('background', '');
                                    $preview.css('background-image', 'url(' + selectedUrl + ')');
                                }
                            }
                        }
                    };
                    wp.media.editor.open($button);
                    return false;
                });
                $wrapper.find('.wpfTermsRemoveIcon').off('click').on('click', function (e) {
                    var li = jQuery(this).closest('li'),
                        preview = li.find('.wpfColorTermPreview'),
                        color = li.find('input[data-field="color_bg"]').val(),
                        bicolor = li.find('input[data-field="bicolor_bg"]').val();

                    li.find('input[data-field="icon_bg"]').val('').trigger('wpf-update');
                    li.find('.wpfColorTermPreview').css({'background-image': '', 'width': '', 'height': ''});

                    if (color && bicolor) {
                        preview.css({background: ' linear-gradient(45deg, ' + color + ' 50%, ' + bicolor + ' 50%) '});
                    } else {
                        if (color) {
                            preview.css({background: color});
                        } else if (bicolor) {
                            preview.css({background: bicolor});
                        } else {
                            preview.css('background', '');
                        }
                    }

                });
            });

            WpfAdminPage.constructor.prototype.changeAttributeTermsPro = (function (filter, settings) {
                var _this = this.$obj,
                    colorsSettings = filter.find('.wpfColorsTypeBlock'),
                    optionForm = filter.find('.wpfColorsTypeBlock .wpfTermsOptionsForm').css('display', 'none').appendTo(colorsSettings.find('.wpfColorsTypeOptions')),
                    iconColorsContainer = colorsSettings.find('.wpfAttributesColors ul').html(''),
					isTags = filter.attr('data-filter') == 'wpfTags',
                    attrSlug = filter.find('select[name="f_list"]').val();
                if (attrSlug == '0') return;

                if (typeof settings == 'undefined') {
                    var filterId = filter.attr('id');
                    if (filterId) {
                        var filterNum = filterId.replace('wpfFilter', '');
                        settings = (_this.filtersSettings && filterNum in _this.filtersSettings ? _this.filtersSettings[filterNum]['settings'] : []);
                    } else settings = [];
                }

                var options = isTags ? filter.find('select[name="f_mlist[]"] option') : jQuery('.wpfAttributesTerms input[name="attr-' + attrSlug + '"]'),
                    attrTypes = jQuery('.wpfAttributesTerms input[name="attr_types"]');
                if (typeof (options) == 'undefined' || options.length == 0) return;
				
				var terms = [],
					keys = [],
					types = [];
                try {
					if (isTags) {
						options.each(function(){
							var o = jQuery(this),
								v = o.val();
							terms[v] = o.text();
							keys.push(v);
						});
					} else {
						terms = JSON.parse(options.val());
						keys = JSON.parse(options.attr('data-order'));
						types = JSON.parse(attrTypes.val());
					}
                } catch (e) {
                    terms = []; keys = []; types = [];
                }
                var attrType = attrSlug in types ? types[attrSlug] : '',
                    frontendType = filter.find('select[name="f_frontend_type"]');
                if (frontendType.val() === 'colors') {
					jQuery('.color-group', filter).find('ul').remove();
                    jQuery('.color-group', filter).trigger('color-group');
                }
				if (keys.length > iconColorsContainer.attr('data-max-terms')) {
					iconColorsContainer.append(jQuery('<li data-term="0" class="wpfErrorColorFilter">' + iconColorsContainer.attr('data-error') + '</li>'));
				} else {
					keys.forEach(function (value) {
						if (value in terms) {
							var bgColorName = 'f_colors_term' + value,
								bgBicolorName = 'f_colors_bicolor_term' + value,
								bgColor = (settings[bgColorName] ? settings[bgColorName] : (attrType == 'color_picker' ? value : '')),
								bgBicolor = (settings[bgBicolorName] ? settings[bgBicolorName] : (attrType == 'color_picker' ? value : '')),
								iconName = 'f_colors_icon_term' + value,
								lbColorName = 'f_colors_label_term' + value,
								lbTextName = 'f_colors_text_term' + value,
								termName = terms[value],
								iconStyle = (settings[iconName] ? settings[iconName] : ''),
								lbColor = (settings[lbColorName] ? settings[lbColorName] : ''),
								lbText = (settings[lbTextName] ? settings[lbTextName] : '');

							if (settings[bgColorName] && settings[bgBicolorName]) {
								var background = ' linear-gradient(45deg, ' + settings[bgColorName] + ' 50%, ' + settings[bgBicolorName] + ' 50%) ';
							} else {
								var color = '';
								if (settings[bgColorName]) {
									color = settings[bgColorName];
								} else if (settings[bgBicolorName]) {
									color = settings[bgBicolorName];
								}

								var background = color ? color : '';
							}

							if (iconStyle) {
								var li = jQuery('<li data-term="' + value + '"><div class="wpfColorTermPreview" style="' + iconStyle + '"></div></li>');
							} else {
								var li = jQuery('<li data-term="' + value + '"><div class="wpfColorTermPreview"></div></li>');
								li.find('.wpfColorTermPreview').css({background: background});
							}

							li.append('<label class="wpfLabelTermPreview"' + (lbColorName.length ? ' style="color:' + lbColor + ';"' : '') + '>' + (lbText == '' ? termName : lbText) + '</label>');
							li.append('<input type="hidden" name="' + bgColorName + '" data-field="color_bg" value="' + bgColor + '">');
							li.append('<input type="hidden" name="' + bgBicolorName + '" data-field="bicolor_bg" value="' + bgBicolor + '">');
							li.append('<input type="hidden" name="' + iconName + '" data-field="icon_bg" value="' + iconStyle + '">');
							li.append('<input type="hidden" name="' + lbColorName + '" data-field="color_label" value="' + lbColor + '">');
							li.append('<input type="hidden" name="' + lbTextName + '" data-field="text_label" data-placeholder="' + termName + '" value="' + lbText + '">');
							iconColorsContainer.append(li);
						}
					});
				}
                iconColorsContainer.find('li .wpfColorTermPreview').off('click').on('click', function () {
                    _this.toggleTermOptions(optionForm, jQuery(this).parent());
                });
                _this.hideTermsOptions(filter.find('select[name="f_mlist[]"]'));

                frontendType.find('option').show();
                switch (attrType) {
                    case 'color_picker':
                        frontendType.find('option:not([value="colors"])').hide();
                        frontendType.val('colors').trigger('change');
                        break;
                    case 'true_false':
                        frontendType.find('option:not([value="switch"],[value="list"])').hide();
                        frontendType.val('switch').trigger('change');
                        break;
                    default:
                        break;
                }
                _this.changeButtonTermsPro(filter, settings);
            });
		
			
            WpfAdminPage.constructor.prototype.toggleTermOptions = (function (optionForm, elem) {
                var _this = this.$obj;
                if (optionForm.css('display') == 'none') {
                    _this.showTermOptions(optionForm, elem);
                } else {
                    optionForm.slideToggle(400, function () {
                        if (optionForm.parent().attr('data-term') != elem.attr('data-term')) {
                            _this.showTermOptions(optionForm, elem);
                        }
                    });
                }
            });
            WpfAdminPage.constructor.prototype.showTermOptions = (function (optionForm, elem) {
                optionForm.appendTo(elem);
                elem.find('input[data-field]').each(function () {
                    var $this = jQuery(this),
                        value = $this.val(),
                        tempField = optionForm.find('div[data-field-temp="' + $this.attr('data-field') + '"]');
                    if (tempField.length) {
                        var fieldType = tempField.attr('data-field-type');
                        switch (fieldType) {
                            case 'color-picker':
                                //tempField.find('.woobewoo-color-result-text').val(value).trigger('change');
                                tempField.find('.woobewoo-color-result-text').val('');
                                if (value == '') tempField.find('.wp-picker-clear').trigger('click');
                                else tempField.find('.woobewoo-color-result').wpColorPicker('color', value);
                                break;
                            case 'text':
                                var placeholder = $this.attr('data-placeholder');
                                tempField.find('input').attr('placeholder', placeholder).val(value);
                                break;
                        }
                    }
                });
                optionForm.slideToggle(400);
            });

            WpfAdminPage.constructor.prototype.initModalIcons = (function (itemVal, item) {
                var _this = this.$obj,
                    options = {
                        modal: true,
                        autoOpen: false,
                        width: 800,
                        height: 500,
                        buttons: {
                            OK: function () {
                                $container.dialog('close');
                            },
                            Cancel: function () {
                                $container.dialog('close');
                            }
                        }
                    };
                var $container = jQuery('<div id="wpfSetupImageIcons"></div>').dialog(options),
                    fontAwesome = jQuery('<div class="wpfImageIconsFontAwesome"></div>');
                fontAwesome.append('<table><tbody></tbody></table>');
                if (typeof FONT_AWESOME_DATA !== 'undefined') {
                    var tr = jQuery('<tr/>');
                    FONT_AWESOME_DATA.forEach(function (data, index) {
                        var td = jQuery('<td/>').html('<i class="fa ' + data + '"></i> ' + data);
                        td.attr('data-value', data);
                        tr.append(td);
                        if ((index + 1) % 4 == 0) {
                            fontAwesome.find('tbody').append(tr);
                            tr = jQuery('<tr/>');
                        }
                    });
                }
                $container.append(fontAwesome);
                if (typeof itemVal !== 'undefined') {
                    fontAwesome.find('td[data-value="' + itemVal + '"]').addClass('active');
                }
                fontAwesome.find('td').off('click').on('click', function (e) {
                    jQuery(this).closest('table').find('td').removeClass('active');
                    jQuery(this).addClass('active');
                });
            });
            WpfAdminPage.initModalIcons();

            WpfAdminPage.constructor.prototype.loadModalIcons = (function (itemVal, item) {
                var _this = this.$obj,
                    $container = jQuery('#wpfSetupImageIcons');

                $container.dialog('open');
                if (typeof itemVal !== 'undefined') {
                    $container.find('td[data-value="' + itemVal + '"]').addClass('active');
                }
                $container.dialog({
                    beforeClose: function (event, ui) {
                        var value = $container.find('td.active').attr('data-value');
                        if (typeof item !== 'undefined') {
                            item.attr('data-value', value);
                            item.find('i').attr('class', 'fa ' + value);
                            item.siblings('input').val(value);
                        }
                        $container.find('td').removeClass('active');
                    }
                });
                return false;
            });
            WpfAdminPage.constructor.prototype.addPreselectsPro = (function (preselect, items, filterId) {
                if (items.f_default_stock && 'f_hidden_stock_status' in items) {
                    var stock = items['f_hidden_stock_status'];
                    if (stock) {
                        preselect += 'pr_stock=' + stock + ';';
                    }
                }
                if (items.f_default_sortby && 'f_hidden_sortby' in items) {
                    var sortby = items['f_hidden_sortby'];
                    if (sortby) {
                        preselect += 'pr_sortby=' + sortby + ';';
                        if (items.f_first_instock) preselect += 'pr_oistock=1;';
                    }
                }
                if (items.f_default_onsale) {
                    preselect += 'pr_onsale=1;';
                }
				if (filterId == 'wpfPrice' && items['f_set_min_max_price']) {
					if (typeof items['f_min_price'] != 'undefined') preselect += 'wpf_min_price=' + items['f_min_price'] + ';';
					if (typeof items['f_max_price'] != 'undefined') preselect += 'wpf_max_price=' + items['f_max_price'] + ';';
				}
                return preselect;
            });
            WpfAdminPage.constructor.prototype.saveFiltersPro = (function () {
                var _this = this,
                    defaults = '',
                    input = jQuery('input[name="settings[filters][defaults]"]'),
                    nameWithId = ['wpfCategory', 'wpfTags', 'wpfPerfectBrand'],
                    allowTypes = ['list', 'dropdown', 'radio', 'mul_dropdown', 'text', 'buttons', 'multi'];
                if (input.length == 0) {
                    input = jQuery('<input type="hidden" name="settings[filters][defaults]" value="">');
                    jQuery('#wpfFiltersEditForm').append(input);
                }

                for (var i = 0; i < _this.filtersSettings.length; i++) {
                    var filter = _this.filtersSettings[i],
                        settings = 'settings' in filter ? filter['settings'] : [],
                        id = filter.id,
                        name = filter.name;
                    if ('f_select_default_id' in settings && name.length > 0) {
                        if (settings['f_select_default_id'].length && 'f_frontend_type' in settings && allowTypes.indexOf(settings.f_frontend_type) != -1
                            && !settings.f_hidden_categories && !settings.f_hidden_brands && !settings.f_hidden_tags && !settings.f_hidden_attributes) {
                            if (nameWithId.indexOf(id) != -1) name += '_' + i;
                            var preValue = settings['f_select_default_id'];
                            switch (id) {
                                case 'wpfCategory':
                                    preValue = (settings['f_multi_logic'] == 'or' ? preValue.replace(/,/g, '|') : preValue);
                                    break;
                                case 'wpfTags':
                                case 'wpfAttribute':
                                    preValue = (settings['f_query_logic'] == 'or' ? preValue.replace(/,/g, '|') : preValue);
                                    break;
                                default:
                            }
                            defaults += name + '=' + preValue + ';';
                        }
                    } else if (settings.f_default_stock) {
                        if (!settings.f_hidden_stock && 'f_hidden_stock_status' in settings && settings['f_hidden_stock_status'].length) {
                            if ('f_no_preselect' in settings && settings['f_no_preselect'] != '1') defaults += 'pr_stock=' + settings['f_hidden_stock_status'] + ';';
                        }
                    } else if (settings.f_default_sortby) {
                        if (!settings.f_hidden_sort && 'f_hidden_sortby' in settings && settings['f_hidden_sortby'].length) {
                            defaults += 'pr_sortby=' + settings['f_hidden_sortby'] + ';';
                            if (settings.f_first_instock) defaults += 'pr_oistock=1;';
                        }
                    } else if (settings.f_default_onsale && !settings.f_hidden_onsale) {
                        defaults += 'pr_onsale=1;';
                    }
                }
                input.val(defaults.length ? defaults.slice(0, -1) : '');
            });


            WpfAdminPage.constructor.prototype.initColorGroup = (function () {

                jQuery('.wpfFiltersBlock').off('color-group', '.color-group').on('color-group', '.color-group', function () {
                    var colorGroup = jQuery(this);

                    // if already initialized
                    if (jQuery('ul', colorGroup).length !== 0) {
                        return;
                    }

                    var filterBlock = colorGroup.closest('.wpfFilter'),
                        frontendType = filterBlock.find('select[name="f_frontend_type"]').val();
                    if (frontendType !== 'colors') return;
                    var attrSlug = filterBlock.find('select[name="f_list"]').val();
                    if (attrSlug === '0') return;

                    try {
                        var filters = JSON.parse(jQuery('input[name="settings[filters][order]"]').val()),
                            terms = JSON.parse(jQuery('.wpfAttributesTerms input[name="attr-' + attrSlug + '"]').val());
                    } catch (e) {
                        return;
                    }
					
					if (Object.keys(terms).length <= colorGroup.attr('data-max-terms')) {
						// display all attributes
						colorGroup.append('<ul></ul>');
						var ul = colorGroup.find('ul');
					
						jQuery.each(terms, function (i, v) {
							ul.append('<li data-id="' + i + '"><div>' +
								'<a class="editColorGroup" href="#"><i class="fa fa-fw fa-edit fa-pencil"></i></a>' +
								'<span class="nameColor">' + v + '</span>' +
								'<div class="colorGroupChildren"></div>' +
								'</div></li>');
						});
					}

                    var cgIndex = null,
                        nameColor = '',
                        values = [],
                        cgIndexPrev = 0;

                    // apply the saved state
                    jQuery.each(filters, function (i, v) {

                        if (v.uniqId === filterBlock.data('uniq-id')) {

                            jQuery.each(v.settings, function (i, v) {
                                cgIndex = i.match(/^f_cglist\[(\d+)\]/);

                                if (cgIndex !== null && typeof cgIndex[1] !== 'undefined' && v !== '') {

                                    if (cgIndexPrev !== cgIndex[1]) {
                                        if (0 !== cgIndexPrev) {
                                            if (values.length > 0) {
                                                jQuery('li[data-id="' + cgIndexPrev + '"] .colorGroupChildren').append('<input type="hidden" name="f_cglist[' + cgIndexPrev + '][]" value="' + values.join(',') + '"/>');
                                                values = [];
                                            }
                                        }
                                        cgIndexPrev = cgIndex[1];
                                    }

                                    var selectedArr = v.split(',');

                                    jQuery.each(selectedArr, function (i, v) {
                                        values.push(v);
                                        nameColor = jQuery('li[data-id="' + v + '"] .nameColor').text();
                                        jQuery('li[data-id="' + cgIndex[1] + '"] .colorGroupChildren').append('<span>' + nameColor + '</span>');

                                        jQuery('li[data-id="' + v + '"]', colorGroup).hide();
                                    });

                                }

                            });

                            if (values.length > 0) {
                                jQuery('li[data-id="' + cgIndexPrev + '"] .colorGroupChildren').append('<input type="hidden" name="f_cglist[' + cgIndexPrev + '][]" value="' + values.join(',') + '"/>');
                                values = [];
                            }

                        }

                    });

                });

                jQuery('.color-group').off('click', '.editColorGroup').on('click', '.editColorGroup', function (e) {
                    e.preventDefault();
                    var parent = jQuery(this).parents('li'),
                        parentId = parent.data('id'),
                        colorGroupChildren = jQuery('.colorGroupChildren', parent),
                        colorGroup = jQuery(this).closest('.color-group'),
                        filterBlock = colorGroup.closest('.wpfFilter'),
                        attrSlug = filterBlock.find('select[name="f_list"]').val(),
                        option = '';

                    if (attrSlug === '0') return;

                    try {
                        var filters = JSON.parse(jQuery('input[name="settings[filters][order]"]').val()),
                            terms = JSON.parse(jQuery('.wpfAttributesTerms input[name="attr-' + attrSlug + '"]').val());
                    } catch (e) {
                        return;
                    }

                    jQuery.each(terms, function (id, value) {
                        if (Number(id) !== parentId) {
                            option += '<option value="' + id + '">' + value + '</option>';
                        }
                    });


                    colorGroupChildren.html('<select data-id="' + parentId + '" multiple="multiple" name="f_cglist[' + parentId + '][]">' + option + '</select>');

                    var cgIndex = null;

                    // apply the saved state
                    jQuery.each(filters, function (i, v) {
                        if (v.uniqId === filterBlock.data('uniq-id')) {
                            jQuery.each(v.settings, function (i, v) {
                                cgIndex = i.match(/^f_cglist\[(\d+)\]/);

                                if (cgIndex !== null && typeof cgIndex[1] !== 'undefined' && v !== '') {
                                    var selectedArr = v.split(',');
                                    jQuery.each(selectedArr, function (i, v) {
                                        var select = jQuery('select[name="f_cglist[' + cgIndex[1] + '][]"]', colorGroup);
                                        select.find("option[value='" + v + "']").prop("selected", true);
                                    });
                                    jQuery('select option[value="' + cgIndex[1] + '"]', colorGroup).hide();
                                }
                            });
                        }
                    });

                    var input = null,
                        select = null;

                    // initialization and reaction to changes in selection
                    jQuery('select', colorGroup).chosen({width: "95%"}).change(function (e, action) {

                        if (action.selected !== undefined) {
                            jQuery('li[data-id="' + action.selected + '"]', colorGroup).hide();
                            jQuery('select option[value="' + parentId + '"]', colorGroup).hide();
                            jQuery('select', colorGroup).trigger("chosen:updated");
                        }

                        if (action.deselected !== undefined) {
                            var selectedFromOthers = false;

                            jQuery('li', colorGroup).each(function () {
                                input = jQuery('.colorGroupChildren input', this);
                                select = jQuery('.colorGroupChildren select', this);

                                if ((1 === input.length && input.val().includes(action.deselected))
                                    || (1 === select.length && select.val().includes(action.deselected))) {
                                    selectedFromOthers = true;
                                }
                            });

                            if (!selectedFromOthers) {
                                jQuery('li[data-id="' + action.deselected + '"]', colorGroup).show();
                            }

                            if (jQuery(this).val().length === 0) {
                                jQuery('select option[value="' + jQuery(this).data('id') + '"]', colorGroup).show();
                                jQuery('select', colorGroup).trigger("chosen:updated");
                            }

                        }
                    });

                });

            });

            function initShowCurrency_SymbolPos($blockTemplate) {
                if ($blockTemplate.length > 0) {
                    var $jsBlockShowCurrencySlider = $blockTemplate.find('#jsBlockShowCurrencySlider');

                    if ($jsBlockShowCurrencySlider.length > 0) {
                        setTimeout(function () {
                            checkShowCurrencySlider(null, $blockTemplate);

                        }, 500);

                        $blockTemplate.find('[name="f_show_inputs"], [name="f_price_show_currency_slider"]').off('change').on('change', function (e) {
                            e.preventDefault();

                            checkShowCurrency_SymbolPos(this);
                        });
                        $blockTemplate.find('[name="f_skin_type"]').on('change', function (e) {
                            e.preventDefault();

                            checkShowCurrencySlider(this);
                        });
                    }
                }
            }

            function checkShowCurrency_SymbolPos(obj, $container) {
                ($container == undefined) ? $container = jQuery(obj).closest('div[data-filter="wpfPrice"]') : '';

                var $showInputs = $container.find('[name="f_show_inputs"]'),
                    $showCurrencySlider = $container.find('[name="f_price_show_currency_slider"]');
                var $blockShowCurrencySlider = $container.find('div#jsBlockShowCurrencySlider');

                if ((!$showInputs.is(':checked') && !$showCurrencySlider.is(':checked')) || (!$showInputs.is(':checked') && $blockShowCurrencySlider.hasClass('hidden'))) {
                    $container.find('div.f_show_inputs_enabled_currency').hide();
                } else {
                    setTimeout(function () {
                        $container.find('div.f_show_inputs_enabled_currency').removeAttr('style');
                    }, 500);
                }
            }

            function checkShowCurrencySlider(obj, $container) {
                ($container != undefined) ? '' : $container = jQuery(obj).closest('div[data-filter="wpfPrice"]');

                if (obj == undefined) {
                    obj = $container.find('[name="f_skin_type"]')[0];
                }

                (obj.value == 'default') ? $container.find('div#jsBlockShowCurrencySlider').addClass('hidden') : $container.find('div#jsBlockShowCurrencySlider').removeClass('hidden');
                checkShowCurrency_SymbolPos(null, $container);
            }
        }
    }
}(window.jQuery));

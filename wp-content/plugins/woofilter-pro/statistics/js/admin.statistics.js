/**
 * WBW Product Filter PRO - Statistics Admin JS
 *
 * @author  WBW
 */

(function ($, app) {
"use strict";
	function StatisticsPage() {
		this.$obj = this;
		return this.$obj;
	}

	StatisticsPage.prototype.init = function () {
		var _this = this.$obj;
		_this.dateFormat = 'yy-mm-dd';
		_this.filtersBlock = $('#wpfFilter');
		_this.reportBlock = $('#wpfReport');
		_this.reportLoader = $('#wpfReportLoader');
		_this.wpfReportDiagram = $('#wpfReportDiagram');
		_this.diagramContainerId = 'wpfDiagram';
		_this.diagramContainer = $('#' + _this.diagramContainerId);
		_this.wpfReportTable = $('#wpfReportTable');
		_this.langSettings = wpfParseJSON($('#wpfLangsJson').val());
		_this.filtersBlocks = wpfParseJSON($('#wpfBlocksJson').val());

		_this.eventsStatisticsPage();
	}

	StatisticsPage.prototype.eventsStatisticsPage = function () {
		var _this = this.$obj;

		_this.filtersBlock.find('.wpf-field-date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: _this.dateFormat,
			showAnim: '',
		});

		_this.filtersBlock.find('select').on('change wpf-change', function () {
			var elem = $(this),
				value = elem.val(),
				hidden = elem.closest('.wpf-nosave').length > 0,
				name = elem.attr('name'),
				subOptions = _this.filtersBlock.find('[data-select="' + name + '"]');
			if (subOptions.length) {
				subOptions.addClass('wpf-nosave');
				if (!hidden) {
					subOptions.filter('[data-select-value*="'+value+'"]').removeClass('wpf-nosave');
					subOptions.filter('[data-select-not][data-select-not!="'+value+'"]').removeClass('wpf-nosave');
					subOptions.find('select').trigger('wpf-change');
				}
			}
			if (name == 'filter') {
				var block = _this.filtersBlock.find('select[name="block"]');
				if (value in _this.filtersBlocks) {
					if (_this.filtersBlock.find('select[name="report"]').val() == 'values') block.html('');
					else block.html('<option value=""></option>');
					for (var id in _this.filtersBlocks[value]) {
						$('<option value="'+id+'">'+_this.filtersBlocks[value][id]+'</option>').appendTo(block);
					}
				}
			}
		});

		_this.filtersBlock.find('select, input').on('change', function() {
			_this.reportBlock.find('.wpf-report-block').addClass('wpfHidden');
			var report = _this.filtersBlock.find('select[name="report"]').val(),
				$typ = _this.filtersBlock.find('.settings-value:not(.wpf-nosave) select[name="type"]');
			if ($typ.length && $typ.val() == 'table') {
				_this.wpfReportTable.removeClass('wpfHidden');
				_this.initReportTable();
			} else {
				_this.reportLoader.removeClass('wpfHidden');
				$.sendFormWpf({
					data: _this.filtersBlock.serializeAnythingWpf(),
					appendData: {mod: 'statistics', action: 'getDiagramData', wpfNonce: window.wpfNonce},
					onSuccess: function(res) {
						if (!res.error && res.values) {
							_this.reportBlock.find('.wpf-report-block').addClass('wpfHidden');
							_this.wpfReportDiagram.removeClass('wpfHidden');
							_this.drawDiagram(res.values);
						}
					}
				});
			}
		});
		_this.filtersBlock.find('select[name="report"]').trigger('change');
	}

	StatisticsPage.prototype.drawDiagram = function (values) {
		var _this = this.$obj,
			data = values.data ? values.data : [],
			layout = values.layout ? values.layout : [],
			container = document.getElementById(_this.diagramContainerId),
			config = values.config ? values.config : [];

		_this.diagramContainer.empty();
		layout['height'] = 600;
		Plotly.newPlot(container, data, layout, config);
	}

	StatisticsPage.prototype.initReportTable = function () {
		if(!$.fn.jqGrid) {
			return;
		}
		var _this = this.$obj;

		if (_this.reportTable) {
			_this.reportTable.trigger('reloadGrid');
			return;
		}

		var _this = this.$obj,
			tblId = 'wpfTable';

		_this.reportTable = $('#'+ tblId);
		var grid = _this.reportTable.jqGrid({
				url: WPF_DATA.ajaxurl + '?mod=statistics&action=getTableData&pl=wpf&reqType=ajax&wpfNonce='+window.wpfNonce,
				datatype: 'json',
				autowidth: true,
				shrinkToFit: true,
				colNames:[
					wpfCheckSettings(_this.langSettings, 'col-values'),
					wpfCheckSettings(_this.langSettings, 'col-count'),
					wpfCheckSettings(_this.langSettings, 'col-not'),
				],
				colModel:[
					{name: 'value', index: 'value', searchoptions: {sopt: ['eq']}, align: 'left', sortable:false},
					{name: 'cnt_all', index: 'cnt_all', searchoptions: {sopt: ['eq']}, align: 'center', sorttype: 'number'},
					{name: 'cnt_not', index: 'cnt_not', searchoptions: {sopt: ['eq']}, align: 'center', sorttype: 'number'}
				],
				postData: {
					//params: _this.filtersBlock.serializeAnythingWpf(),
					params: function() {
						return _this.filtersBlock.serializeAnythingWpf();
					}
				},
				rowNum: -1,
				sortname: 'cnt_all',
				viewrecords: true,
				sortorder: 'desc',
				jsonReader: { repeatitems : false, id: '0' },
				height: '100%',
				emptyrecords: wpfCheckSettings(_this.langSettings, 'empty-table'),
				beforeRequest: function() {
					$('#wpfTableNav_center .ui-pg-table').addClass('woobewoo-hidden');
				},
				gridComplete: function(a, b, c) {
					$('#wpfTableNav_center .ui-pg-table').removeClass('woobewoo-hidden');
				},
				loadComplete: function() {
					var tblId = $(this).attr('id');
					if (this.p.reccount === 0) {
						$(this).hide();
						$('#'+ tblId+ 'EmptyMsg').removeClass('woobewoo-hidden');
					} else {
						$(this).show();
						$('#'+ tblId+ 'EmptyMsg').addClass('woobewoo-hidden');
					}
				}
			});

		$(window).on('load resize', _this.reportTable, function(event) {
			_this.reportTable.jqGrid('setGridWidth', jQuery('#wpfReportTable').width());
		});
	}
	function wpfParseJSON(elem) {
		try {
			var obj = JSON.parse(elem);
		} catch(e) {
			var obj = {};
		}
		return obj;
	}
	function wpfCheckSettings(settings, key, def) {
		if (typeof def == 'undefined') var def = '';
		return (settings[key]) ? settings[key] : def;
	}

	app.wpfStatisticsPage = new StatisticsPage();

	$(document).ready(function () {
		app.wpfStatisticsPage.init();
	});

}(window.jQuery, window));

<?php
/**
 * WBW Product Filter PRO - Statistics Admin
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

HtmlWpf::hidden(
	'blocks',
	array(
		'value' => UtilsWpf::jsonEncode($this->filters['blocks']),
		'attrs' => 'id="wpfBlocksJson"',
	)
);

?>
<section class="woobewoo-bar">
	<div id="wpfFilter" class="wpf-list-filter">
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-2">
				<?php esc_html_e('Report', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10">
				<div class="settings-value">
					<?php HtmlWpf::selectbox('report', array('options' => $this->reports, 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
				<div class="settings-value wpf-nosave" data-select="report" data-select-value="blocks">
					<div class="settings-value-label"><?php esc_html_e('Type', 'woo-product-filter'); ?></div>
					<?php HtmlWpf::selectbox('type', array('options' => $this->typesBlocks, 'value' => 'pie', 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
				<div class="settings-value wpf-nosave" data-select="report" data-select-value="values">
					<div class="settings-value-label"><?php esc_html_e('Type', 'woo-product-filter'); ?></div>
					<?php HtmlWpf::selectbox('type', array('options' => $this->typesValues, 'value' => 'pie', 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
				<div class="settings-value wpf-nosave" data-select="report" data-select-value="blocks values">
					<div class="settings-value-label"><?php esc_html_e('Top', 'woo-product-filter'); ?></div>
					<?php HtmlWpf::selectbox('top', array('options' => $this->tops, 'value' => '5', 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-2">
				<?php esc_html_e('Period', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php esc_attr_e('Note that statistics for the current day are accumulated, but will appear in the report only tomorrow.', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10">
				<div class="settings-value">
					<?php HtmlWpf::selectbox('period', array('options' => $this->periods, 'value' => 'month', 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
				<div class="settings-value wpf-nosave" data-select="period" data-select-value="custom">
					<div class="settings-value-label"><?php esc_html_e('from', 'woo-product-filter'); ?></div>
					<?php HtmlWpf::text('from', array('attrs' => 'class="wpf-field-date woobewoo-width100 woobewoo-flat-input" data-default=""', 'value' => $this->from)); ?>
				</div>
				<div class="settings-value wpf-nosave" data-select="period" data-select-value="custom">
					<div class="settings-value-label"><?php esc_html_e('to', 'woo-product-filter'); ?></div>
					<?php HtmlWpf::text('to', array('attrs' => 'class="wpf-field-date woobewoo-width100 woobewoo-flat-input" data-default=""', 'value' => $this->to)); ?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block" data-select="report" data-select-value="requests users no_result">
			<div class="settings-block-label col-xs-2">
				<?php esc_html_e('Filter', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10">
				<div class="settings-value">
					<select name="filter" class="woobewoo-flat-input">
						<option value="0"></option>
						<?php foreach ($this->filters['filters'] as $fId => $filter) { ?>
							<option value="<?php echo esc_attr($fId); ?>">
								<?php echo esc_html($filter); ?>
							</option>
						<?php } ?>
					</select>
				</div>
			</div>
		</div>
		<div class="row row-settings-block wpf-nosave" data-select="report" data-select-value="blocks values">
			<div class="settings-block-label col-xs-2">
				<?php esc_html_e('Filter', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10">
				<div class="settings-value">
					<select name="filter" class="woobewoo-flat-input">
						<?php foreach ($this->filters['filters'] as $fId => $filter) { ?>
							<option value="<?php echo esc_attr($fId); ?>">
								<?php echo esc_html($filter); ?>
							</option>
						<?php } ?>
					</select>
				</div>
			</div>
		</div>
		<div class="row row-settings-block wpf-nosave" data-select="filter" data-select-not="0">
			<div class="settings-block-label col-xs-2">
				<?php esc_html_e('Page', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10">
				<div class="settings-value">
					<?php HtmlWpf::selectbox('page', array('options' => $this->pages, 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block wpf-nosave" data-select="report" data-select-not="blocks">
			<div class="settings-block-label col-xs-2" data-select="filter" data-select-not="0">
				<?php esc_html_e('Block', 'woo-product-filter'); ?>
			</div>
			<div class="settings-block-values settings-w100 col-xs-10" data-select="filter" data-select-not="0">
				<div class="settings-value">
					<?php HtmlWpf::selectbox('block', array('options' => array(), 'attrs' => 'class="woobewoo-flat-input"')); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="woobewoo-clear"></div>
</section>
<section>
	<div class="woobewoo-item woobewoo-panel">
		<div class="wpf-main-tab-content" id="wpfStatisticsWrapper">
			<?php
				HtmlWpf::hidden('', array('value' => UtilsWpf::jsonEncode($this->langs), 'attrs' => 'id="wpfLangsJson"'));
			?>
			<div id="wpfReport">
				<div id="wpfReportLoader" class="wpf-report-block wpf-report-loader">
					<div><?php esc_html_e('Loading', 'woo-product-filter'); ?>...<i class="fa fa-spinner fa-spin"></i></div>
				</div>
				<div id="wpfReportDiagram" class="wpf-report-block wpfHidden">
					<div id="wpfDiagram"></div>
				</div>
				<div id="wpfReportTable" class="wpf-report-block woobewoo-table-list wpfHidden">
					<table id="wpfTable"></table>
				</div>
			</div>
		</div>
	</div>
</section>

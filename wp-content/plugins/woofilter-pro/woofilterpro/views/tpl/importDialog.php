<?php
/**
 * WBW Product Filter PRO - Import Dialog
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wpfHidden">
	<div id="wpfImportWnd" title="<?php echo esc_attr(__('Import filters', 'woo-product-filter')); ?>">
		<p><?php esc_html_e('Upload your export sql file', 'woo-product-filter'); ?></p>
		<form id="wpfImportForm">
			<label class="wpfImportReasonShell">
				<?php
				HtmlWpf::input('import_file', array(
					'type' => 'file',
					'attrs' => ' id="wpfImportInput" accept=".sql"',
				));
				?>
			</label>
			<?php HtmlWpf::hidden('mod', array('value' => 'woofilterpro')); ?>
			<?php HtmlWpf::hidden('action', array('value' => 'importGroup')); ?>
			<?php HtmlWpf::hidden('pl', array('value' => 'wpf')); ?>
		</form>
	</div>
	<div id="wpfStatsEWnd" title="<?php echo esc_attr(__('Enable statistics', 'woo-product-filter')); ?>">
		<p>
			<?php esc_html_e('If this option is enabled, then at each act of filtering products through this filters, information about the filtering parameters will be saved to the database. Charts and summary tables of statistics can be viewed on the tab Statistics.', 'woo-product-filter'); ?>
		</p>
		<p>
			<?php esc_html_e('Please note that collecting and saving filtering statistics may slow down your site and take up a significant amount of database space.', 'woo-product-filter'); ?>
		</p>
		<form id="wpfStatsEForm" data-submit="<?php echo esc_attr(__('Enable', 'woo-product-filter')); ?>">
			<?php HtmlWpf::hidden('mod', array('value' => 'statistics')); ?>
			<?php HtmlWpf::hidden('action', array('value' => 'enableStats')); ?>
			<?php HtmlWpf::hidden('pl', array('value' => 'wpf')); ?>
			<?php HtmlWpf::hidden('id', array('value' => '0')); ?>
		</form>
	</div>
	<div id="wpfStatsDWnd" title="<?php echo esc_attr(__('Disable statistics', 'woo-product-filter')); ?>">
		<p><?php esc_html_e('Are you sure you want to disable the collection of filtering statistics for this filter?', 'woo-product-filter'); ?></p>
		<form id="wpfStatsDForm" data-submit="<?php echo esc_attr(__('Disable', 'woo-product-filter')); ?>">
			<?php HtmlWpf::hidden('mod', array('value' => 'statistics')); ?>
			<?php HtmlWpf::hidden('action', array('value' => 'disableStats')); ?>
			<?php HtmlWpf::hidden('pl', array('value' => 'wpf')); ?>
			<?php HtmlWpf::hidden('id', array('value' => '0')); ?>
		</form>
	</div>
</div>

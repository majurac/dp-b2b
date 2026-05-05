<?php
/**
 * WBW Product Filter PRO - Part Admin Buttons Pro
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

?>
<li title="<?php echo esc_attr(__('Import filters', 'woo-product-filter')); ?>">
	<button class="button button-small" id="wpfTableImportBtn" data-toolbar-button>
		<i class="fa fa-fw fa-download"></i>
		<?php esc_html_e('Import filters', 'woo-product-filter'); ?>
	</button>
</li>
<li title="<?php echo esc_attr(__('Export selected', 'woo-product-filter')); ?>">
	<button class="button button-small" id="wpfTableExportBtn" disabled data-toolbar-button>
		<i class="fa fa-fw fa-upload"></i>
		<?php esc_html_e('Export selected', 'woo-product-filter'); ?>
	</button>
</li>

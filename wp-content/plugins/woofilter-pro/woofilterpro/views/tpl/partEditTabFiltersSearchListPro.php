		<div class="settings-value settings-w100" data-parent="f_show_search_input">
			<div class="settings-value-label">
				<?php esc_html_e('Position', 'woo-product-filter'); ?>
			</div>
			<?php
				HtmlWpf::selectbox('f_search_position', array(
					'options' => array('before' => esc_attr__('Before', 'woo-product-filter'), 'after' => esc_attr__('After', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input"',
				));
				?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_show_search_input">
			<div class="settings-value-label">
				<?php esc_html_e('Search button', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::checkboxToggle('f_show_search_button', array()); ?>
		</div>

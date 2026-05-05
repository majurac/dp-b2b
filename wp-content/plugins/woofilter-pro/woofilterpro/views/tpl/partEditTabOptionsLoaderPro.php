				<div class="clear"></div>
				<div class="settings-value wpfSelectFile settings-w100">
					<?php
						HtmlWpf::hidden('settings[filter_loader_custom_icon]', array(
							'value' => ( isset($this->settings['settings']['filter_loader_custom_icon']) ? $this->settings['settings']['filter_loader_custom_icon'] : '' ),
							'attrs' => ' data-loader-settings="1"',
						));
						HtmlWpf::buttonA(array(
							'value' => esc_attr__('Select icon', 'woo-product-filter'),
							'attrs' => 'id="wpfSelectLoaderButton" data-type="image"',
						));
						?>
				</div>
				<div class="settings-value">
					<div class="settings-value-label">
						<?php esc_html_e('animation', 'woo-product-filter'); ?>
					</div>
					<?php
						HtmlWpf::selectbox('settings[filter_loader_custom_animation]', array(
							'options' => array(
								'' => esc_attr__('none', 'woo-product-filter'),
								'flip' => esc_attr__('flip', 'woo-product-filter'),
								'jump' => esc_attr__('jump', 'woo-product-filter'),
								'rotate' => esc_attr__('rotate', 'woo-product-filter'),
							),
							'value' => ( isset($this->settings['settings']['filter_loader_custom_animation']) ? $this->settings['settings']['filter_loader_custom_animation'] : '' ),
							'attrs' => 'class="woobewoo-flat-input"',
						));
						?>
				</div>

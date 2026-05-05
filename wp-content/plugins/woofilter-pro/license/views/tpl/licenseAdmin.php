<?php
/**
 * WBW Product Filter PRO - LicenseAdmin
 *
 * @author  WBW
 */

defined( 'ABSPATH' ) || exit;

?>
<section class="woobewoo-bar">
	<h4>
		<?php if ($this->isActive) {
			/* translators: %s: plugin name */
			echo sprintf(esc_html__('Congratulations! PRO version of %s plugin is activated and working fine!', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME));
		} elseif ($this->isExpired) {
			/* translators: %s: plugin name */
			echo sprintf(esc_html__('Your license for PRO version of %s plugin - expired.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME));

			if (empty($this->licenseType)) {
				/* translators: %s url */
				echo ' ' . sprintf(esc_html__('You can %s to extend your license, then - click on "Re-activate" button to re-activate your PRO version.', 'woo-product-filter'), '<a href="' . esc_url($this->extendUrl) . '" target="_blank">click here</a>');
			}
		} else {
			/* translators: %s: plugin name */
			echo sprintf(esc_html__('Congratulations! You have successfully installed PRO version of %s plugin.', 'woo-product-filter'), esc_html(WPF_WP_PLUGIN_NAME)) . ' ';
			if ('wc' == $this->licenseType) {
				/* translators: %s url */
				echo sprintf(esc_html__('To activate your PRO version verification of your subscription is required. Go to %s and make sure you are logged in to your WooCommerce.com account.', 'woo-product-filter'), '<b>' . esc_html('WooCommerce > Extensions > WooCommerce.com Subscriptions') . '</b>');
			} else if ('cc' == $this->licenseType) {
				echo esc_html__('Final step to finish Your PRO version setup - is to enter your Username for codecanyon.net and Purchase code on this page. This will activate Your copy of software on this site.', 'woo-product-filter');
			} else {
				echo esc_html__('Final step to finish Your PRO version setup - is to enter your Email and License Key on this page. This will activate Your copy of software on this site.', 'woo-product-filter');
			}
		}
		?>
	</h4>
	<div class="woobewoo-clear"></div>
	<hr />
</section>
<section>
	<form id="wpfLicenseForm" class="">
		<div class="woobewoo-item woobewoo-panel">
			<table class="form-table">
				<tr
				<?php
				if (!empty($this->licenseType)) {
					echo ' class="woobewoo-hidden"';
				}
				?>
					>
					<th scope="row">
						<?php esc_html_e('Email', 'woo-product-filter'); ?>
					</th>
					<td class="woobewoo-width1">
						<?php
						echo '<i class="fa fa-question woobewoo-tooltip" title="' . esc_attr(__('Your email address, used on checkout procedure on', 'woo-product-filter') . ' <a href="' . esc_url('http://' . WPF_WP_PLUGIN_URL) . '/" target="_blank">http://' . esc_html(WPF_WP_PLUGIN_URL) . '/</a>') . '"></i>';
						?>
					</td>
					<td>
						<?php HtmlWpf::text('email', array('value' => $this->credentials['email'], 'attrs' => 'class="woobewoo-width300"')); ?>
					</td>
				</tr>
				<tr
				<?php
				if ('cc' != $this->licenseType) {
					echo ' class="woobewoo-hidden"';
				}
				?>
					>
					<th scope="row">
						<?php esc_html_e('Username', 'woo-product-filter'); ?>
					</th>
					<td class="woobewoo-width1">
						<?php
						echo '<i class="fa fa-question woobewoo-tooltip" title="' . esc_attr__('Username for codecanyon.net', 'woo-product-filter') . '"></i>';
						?>
					</td>
					<td>
						<?php HtmlWpf::text('name', array('value' => $this->credentials['name'], 'attrs' => 'class="woobewoo-width300"')); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php
						if ('cc' == $this->licenseType) {
							esc_html_e('Purchase code', 'woo-product-filter');
						} else {
							esc_html_e('License Key', 'woo-product-filter');
						}
						?>
					</th>
					<td>
						<?php
						if ('wc' == $this->licenseType) {
							echo '<i class="fa fa-question woobewoo-tooltip" title="' . esc_attr__('Your License Key from your WooCommerce.com Subscriptions', 'woo-product-filter') . '"></i>';
						} else if ('cc' == $this->licenseType) {
							echo '<i class="fa fa-question woobewoo-tooltip" title="' . esc_attr__('Your Purchase code from', 'woo-product-filter') . '"></i>';
						} else {
							echo '<i class="fa fa-question woobewoo-tooltip" title="' . esc_attr(__('Your License Key from your account on', 'woo-product-filter') . ' <a href="' . esc_url('http://' . WPF_WP_PLUGIN_URL) . '/" target="_blank">http://' . esc_html(WPF_WP_PLUGIN_URL) . '/</a>') . '"></i>';
						}
						?>
					</td>
					<td>
						<?php HtmlWpf::password('key', array('value' => $this->credentials['key'], 'attrs' => 'class="woobewoo-width300"' . ( 'wc' == $this->licenseType ? ' disabled' : '' ))); ?>
					</td>
				</tr>
				<tr
				<?php
				if ('wc' == $this->licenseType) {
					echo ' class="woobewoo-hidden"';
				}
				?>
					>
					<th scope="row">
						<?php HtmlWpf::hidden('type', array('value' => $this->credentials['type'])); ?>
						<?php HtmlWpf::hidden('mod', array('value' => 'license')); ?>
						<?php HtmlWpf::hidden('action', array('value' => 'activate')); ?>
						<button class="button button-primary">
							<i class="fa fa-fw fa-save"></i>
							<?php
							if ($this->isExpired) {
								esc_html_e('Re-activate', 'woo-product-filter');
							} else {
								esc_html_e('Activate', 'woo-product-filter');
							}
							?>
						</button>
					</th>
					<td colspan="2" class="wpfHidden">
						<?php
						$checkErrors = get_option('_last_check_errors_' . WPF_CODE);
						if (!empty($checkErrors)) {
							echo '<b>' . esc_html__('Last check error', 'woo-product-filter') . ': </b>';
							if (is_array($checkErrors)) {
								foreach ($checkErrors as $err) {
									echo esc_html($err) . '<br>';
								}
							} else {
								echo esc_html($checkErrors);
							}
						}
						?>
					</td>
				</tr>
			</table>
			<div class="woobewoo-clear"></div>
		</div>
	</form>
</section>

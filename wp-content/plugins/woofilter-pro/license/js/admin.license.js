/**
 * WBW Product Filter PRO - Admin License JS
 *
 * @author  WBW
 */

"use strict";
jQuery(document).ready(function(){
	jQuery('#wpfLicenseForm').submit(function(){
		jQuery(this).sendFormWpf({
			btn: jQuery(this).find('button.button-primary')
		,	onSuccess: function(res) {
				if(!res.error) {
					toeReload();
				}
			}
		});
		return false;
	});
});

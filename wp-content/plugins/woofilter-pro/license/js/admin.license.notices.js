/**
 * WBW Product Filter PRO - Admin License Notices JS
 *
 * @author  WBW
 */

"use strict";
jQuery(document).ready(function(){
	jQuery(document).on('click', '.woobewoo-pro-notice.wpf-notification .notice-dismiss', function(){
		jQuery.sendFormWpf({
			msgElID: 'noMessages'
		,	data: {mod: 'license', action: 'dismissNotice'}
		});
	});
});

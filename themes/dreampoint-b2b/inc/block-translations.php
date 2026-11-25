<?php
/**
 * WooCommerce Blocks JS translation overrides (Checkout validation messages, Croatian).
 *
 * Uses the `load_script_translations` filter to correct specific JS translation keys at runtime, so the
 * `wp-content/languages/plugins/woocommerce-hr-*.json` files are never edited and the fix survives WordPress,
 * WooCommerce and language-pack updates. The same mechanism runs on Cotra B2B (`inc/block-translations.php`).
 *
 * The `%s` placeholder receives the field label (e.g. "Poštanski broj"), which the original wording inflects
 * incorrectly:
 *  - "Please enter a valid %s"  -> "Unesite ispravni %s"   becomes "Pogrešan unos za polje: %s"
 *  - "Please select a %s"       -> "Molimo odaberite %s"   becomes "Odaberite vrijednost za polje: %s"
 *
 * Only the wording changes: the errors are still raised by the same validation, nothing is suppressed.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'load_script_translations',
	static function ( $translations, $file, $handle, $domain ) {
		if ( 'woocommerce' !== $domain || ! is_string( $translations ) || '' === $translations ) {
			return $translations;
		}

		$data = json_decode( $translations, true );
		if ( ! is_array( $data ) || ! isset( $data['locale_data']['messages'] ) ) {
			return $translations;
		}

		$overrides = [
			'Please enter a valid %s' => 'Pogrešan unos za polje: %s',
			'Please select a %s'      => 'Odaberite vrijednost za polje: %s',
		];

		$changed = false;
		foreach ( $overrides as $key => $text ) {
			if ( isset( $data['locale_data']['messages'][ $key ] ) && is_array( $data['locale_data']['messages'][ $key ] ) ) {
				$data['locale_data']['messages'][ $key ][0] = $text;
				$changed                                     = true;
			}
		}

		return $changed ? wp_json_encode( $data ) : $translations;
	},
	10,
	4
);

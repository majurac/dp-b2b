<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		if ( ! $this->is_quick_order_page() ) {
			return;
		}

		wp_enqueue_style(
			'dp-quick-order',
			DP_QUICK_ORDER_URL . 'assets/dist/quick-order.css',
			[],
			DP_QUICK_ORDER_VERSION
		);

		wp_enqueue_script(
			'dp-quick-order',
			DP_QUICK_ORDER_URL . 'assets/dist/quick-order.js',
			[],
			DP_QUICK_ORDER_VERSION,
			true
		);

		wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
			'restUrl'          => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
			'cartSyncUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/cart/sync'
			) ),
			'productsUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/products'
			) ),
			'storeUrl'         => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'            => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
			'wpNonce'          => wp_create_nonce( 'wp_rest' ),
			'timeoutMs'        => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
			'cartSyncMaxBatch' => DP_Quick_Order_Config::CART_SYNC_MAX_BATCH,
			'searchMinChars'   => DP_Quick_Order_Config::SEARCH_MIN_CHARS,
			// Excel/CSV import foundation (parser + read-only validation). No UI yet.
			'importValidateUrl'         => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/import/validate'
			) ),
			'cartUrl'                   => esc_url_raw( wc_get_cart_url() ),
			'importMaxRows'             => DP_Quick_Order_Config::IMPORT_MAX_ROWS,
			'importMaxFileBytes'        => DP_Quick_Order_Config::IMPORT_MAX_FILE_BYTES,
			'importMaxIdentifierLength' => DP_Quick_Order_Config::IMPORT_MAX_IDENTIFIER_LENGTH,
			'importTimeoutMs'           => DP_Quick_Order_Config::IMPORT_VALIDATE_TIMEOUT_MS,
			'importTemplates'           => [
				'xlsx' => esc_url_raw( DP_QUICK_ORDER_URL . 'assets/templates/dp-quick-order-import-template.xlsx' ),
				'csv'  => esc_url_raw( DP_QUICK_ORDER_URL . 'assets/templates/dp-quick-order-import-template.csv' ),
			],
			'currency'         => get_woocommerce_currency(),
			// WooCommerce's own money settings, so the footer subtotal is formatted exactly like the
			// server-rendered prices (separators, symbol position) regardless of the site locale.
			// Presentation only — no amount is computed from these.
			'money'            => [
				'decimals'     => wc_get_price_decimals(),
				'decimalSep'   => wc_get_price_decimal_separator(),
				'thousandSep'  => wc_get_price_thousand_separator(),
				'symbol'       => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'format'       => get_woocommerce_price_format(), // e.g. '%2$s&nbsp;%1$s' (1 = symbol, 2 = amount)
			],
			'placeholderImg'   => esc_url( wc_placeholder_img_src() ),
			'i18n'             => [
				'skuLabel'           => __( 'Kataloški broj:', 'dp-b2b-quick-order' ),
				// Croatian declension forms for the footer counts: [1, 2-4, 5+].
				'itemForms'          => [
					__( 'artikl', 'dp-b2b-quick-order' ),
					__( 'artikla', 'dp-b2b-quick-order' ),
					__( 'artikala', 'dp-b2b-quick-order' ),
				],
				'skuForms'           => [
					__( 'različiti SKU', 'dp-b2b-quick-order' ),
					__( 'različita SKU-a', 'dp-b2b-quick-order' ),
					__( 'različitih SKU-a', 'dp-b2b-quick-order' ),
				],
				'priceLabel'         => __( 'Cijena:', 'dp-b2b-quick-order' ),
				'qtyLabel'           => __( 'Količina', 'dp-b2b-quick-order' ),
				'qtyDecrease'        => __( 'Smanji količinu', 'dp-b2b-quick-order' ),
				'qtyIncrease'        => __( 'Povećaj količinu', 'dp-b2b-quick-order' ),
				'loadingVariations'  => __( 'Učitavanje varijacija...', 'dp-b2b-quick-order' ),
				'variationLoadError' => __( 'Greška pri učitavanju varijacija.', 'dp-b2b-quick-order' ),
				'adding'             => __( 'Dodavanje...', 'dp-b2b-quick-order' ),
				'noResultsTitle'     => __( 'Nismo pronašli proizvode', 'dp-b2b-quick-order' ),
				/* translators: %s: current search term */
				'noResultsSearchFilters' => __( 'Za pojam “%s” i odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				/* translators: %s: current search term */
				'noResultsSearch'    => __( 'Za pojam “%s” nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				'noResultsFilters'   => __( 'Za odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				'noResultsTipSearch' => __( 'Provjeriti pravopis ili koristiti drugi pojam za pretragu', 'dp-b2b-quick-order' ),
				'noResultsTipFilters' => __( 'Ukloniti neke filtre kako biste vidjeli više rezultata', 'dp-b2b-quick-order' ),
				'noResultsTipBrowse' => __( 'Pregledati ostale kategorije ili brendove', 'dp-b2b-quick-order' ),
				'clearAllFilters'    => __( 'Očisti sve filtre', 'dp-b2b-quick-order' ),
				'viewAllProducts'    => __( 'Pogledaj sve proizvode', 'dp-b2b-quick-order' ),
				'emptyCatalog'       => __( 'Nema dostupnih proizvoda.', 'dp-b2b-quick-order' ),
				/* translators: %s: filter name */
				'removeFilter'       => __( 'Ukloni filter: %s', 'dp-b2b-quick-order' ),
				// Server-confirmed row failures (class-cart-sync.php error codes). No stock figure, ever.
				'rowErrors'          => [
					'out_of_stock'         => __( 'Trenutno nije na stanju.', 'dp-b2b-quick-order' ),
					'quantity_unavailable' => __( 'Količina nije dostupna.', 'dp-b2b-quick-order' ),
					'product_unavailable'  => __( 'Proizvod trenutno nije dostupan.', 'dp-b2b-quick-order' ),
					'not_addable'          => __( 'Proizvod nije moguće dodati u košaricu.', 'dp-b2b-quick-order' ),
				],
				// Global submit status. {added}/{failed} count Quick Order rows.
				'submitAdded'        => __( 'Dodano: {added}.', 'dp-b2b-quick-order' ),
				'submitPartial'      => __( 'Dodano: {added}. Nije dodano: {failed} — pogledajte označene retke.', 'dp-b2b-quick-order' ),
				'submitNoneAdded'    => __( 'Nije dodano: {failed} — pogledajte označene retke.', 'dp-b2b-quick-order' ),
				// Network/HTTP/timeout: the additive sync may have been applied — outcome unknown.
				'requestFailed'      => __( 'Nismo mogli potvrditi je li dodano. Provjerite košaricu prije ponovnog pokušaja.', 'dp-b2b-quick-order' ),
				'import'             => $this->import_copy(),
			],
		] );

		// Excel/CSV import modal (parser + validation client + UI). Separate bundle (carries fflate), loaded after
		// the main bundle, which publishes the shared `dpQuickOrder` config this script reads.
		wp_enqueue_script(
			'dp-quick-order-import',
			DP_QUICK_ORDER_URL . 'assets/dist/quick-order-import.js',
			[ 'dp-quick-order' ],
			DP_QUICK_ORDER_VERSION,
			true
		);
	}

	/**
	 * Every user-facing string of the import modal, in one place (Croatian). The JS never builds sentences of its
	 * own: placeholders in curly braces are filled client-side with counts only.
	 *
	 * @return array<string, mixed>
	 */
	private function import_copy(): array {
		return [
			'title'            => __( 'Uvoz narudžbe iz Excel fajla', 'dp-b2b-quick-order' ),
			'intro'            => __( 'Pomoću Excel predloška možete brzo dodati više artikala odjednom', 'dp-b2b-quick-order' ),
			'requiredColumns'  => [ __( 'Količina', 'dp-b2b-quick-order' ), __( 'SKU', 'dp-b2b-quick-order' ) ],
			'step1Title'       => __( '1. Preuzmite template', 'dp-b2b-quick-order' ),
			'step1Text'        => __( 'Preuzmite prazni template za popunjavanje proizvoda tako da ga naš sustav može prepoznati. U stupac SKU upišite kataloški broj ili SKU artikla.', 'dp-b2b-quick-order' ),
			'download'         => __( 'Preuzmi file', 'dp-b2b-quick-order' ),
			'downloadCsv'      => __( 'ili preuzmi .csv predložak', 'dp-b2b-quick-order' ),
			'step2Title'       => __( '2. Popunite i uvezite file', 'dp-b2b-quick-order' ),
			'step2Text'        => __( 'Popunite količine i uvezite vaš Excel file (.xlsx ili .csv).', 'dp-b2b-quick-order' ),
			'dropText'         => __( 'Prevucite excel file ovdje', 'dp-b2b-quick-order' ),
			'dropOr'           => __( 'ili', 'dp-b2b-quick-order' ),
			'addFile'          => __( 'Dodaj file', 'dp-b2b-quick-order' ),
			'fileInputLabel'   => __( 'Odaberite Excel (.xlsx) ili CSV (.csv) datoteku', 'dp-b2b-quick-order' ),
			'exampleTitle'     => __( 'Primjer popunjenog reda u tabeli', 'dp-b2b-quick-order' ),
			'exampleSku'       => 'LL-502181',
			'exampleQty'       => '5',
			'colSku'           => __( 'SKU', 'dp-b2b-quick-order' ),
			'colQty'           => __( 'Količina', 'dp-b2b-quick-order' ),
			'steps'            => [
				__( 'File upload', 'dp-b2b-quick-order' ),
				__( 'Validacija', 'dp-b2b-quick-order' ),
				__( 'Rezultat', 'dp-b2b-quick-order' ),
				__( 'Dodavanje u košaricu', 'dp-b2b-quick-order' ),
			],
			'stepState'        => [
				'done'    => __( 'završeno', 'dp-b2b-quick-order' ),
				'current' => __( 'trenutni korak', 'dp-b2b-quick-order' ),
				'todo'    => __( 'na redu', 'dp-b2b-quick-order' ),
			],
			'close'            => __( 'Zatvori', 'dp-b2b-quick-order' ),
			'closeLabel'       => __( 'Zatvori uvoz narudžbe', 'dp-b2b-quick-order' ),
			'cancel'           => __( 'Otkaži uvoz', 'dp-b2b-quick-order' ),
			'parsingTitle'     => __( 'Čitamo vaš file', 'dp-b2b-quick-order' ),
			'parsingText'      => __( 'Provjeravamo strukturu datoteke.', 'dp-b2b-quick-order' ),
			'validatingTitle'  => __( 'Provjeravamo vaš excel file', 'dp-b2b-quick-order' ),
			'validatingText'   => __( 'Validiramo SKU-ove, količine i dostupnost artikala.', 'dp-b2b-quick-order' ),
			'working'          => __( 'Obrada je u tijeku…', 'dp-b2b-quick-order' ),
			'checksTitle'      => __( 'Što provjeravamo?', 'dp-b2b-quick-order' ),
			'checks'           => [
				__( 'Postoji li SKU u našem sustavu.', 'dp-b2b-quick-order' ),
				__( 'Jesu li količine valjane (brojevi veći od 0).', 'dp-b2b-quick-order' ),
				__( 'Jesu li artikli dostupni.', 'dp-b2b-quick-order' ),
				__( 'Prelazi li količina dostupnu zalihu.', 'dp-b2b-quick-order' ),
			],
			'validateFailed'   => __( 'Provjera nije uspjela. Provjerite vezu i pokušajte ponovno.', 'dp-b2b-quick-order' ),
			'retry'            => __( 'Pokušaj ponovno', 'dp-b2b-quick-order' ),
			'resultDone'       => __( 'Provjera je završena.', 'dp-b2b-quick-order' ),
			'resultNone'       => __( 'Nijedan artikl nije moguće dodati.', 'dp-b2b-quick-order' ),
			/* {ready}: counted + declined noun, {errors}: counted + declined noun */
			'resultReady'      => __( '{ready} spremno za dodavanje u košaricu.', 'dp-b2b-quick-order' ),
			'resultErrorsOne'  => __( '{errors} ima grešku.', 'dp-b2b-quick-order' ),
			'resultErrorsMany' => __( '{errors} imaju greške.', 'dp-b2b-quick-order' ),
			'resultRows'       => __( 'Učitano redaka: {rows}.', 'dp-b2b-quick-order' ),
			'resultMerged'     => __( 'Duplikati su spojeni po artiklu.', 'dp-b2b-quick-order' ),
			'tableCaption'     => __( 'Rezultat provjere uvezenih artikala', 'dp-b2b-quick-order' ),
			'colName'          => __( 'Naziv proizvoda', 'dp-b2b-quick-order' ),
			'colQtyShort'      => __( 'Kol.', 'dp-b2b-quick-order' ),
			'colStatus'        => __( 'Status', 'dp-b2b-quick-order' ),
			'colMessage'       => __( 'Poruka', 'dp-b2b-quick-order' ),
			'qtyAdjusted'      => __( 'traženo {requested}, prilagođeno na {final}', 'dp-b2b-quick-order' ),
			'status'           => [
				'ready'    => __( 'Spremno', 'dp-b2b-quick-order' ),
				'adjusted' => __( 'Prilagođeno', 'dp-b2b-quick-order' ),
				'error'    => __( 'Greška', 'dp-b2b-quick-order' ),
			],
			'messages'         => [
				'ready'                => __( 'Spremno za dodavanje.', 'dp-b2b-quick-order' ),
				'adjusted'             => __( 'Količina je prilagođena dostupnoj zalihi.', 'dp-b2b-quick-order' ),
				'identifier_not_found' => __( 'Artikl nije pronađen ili nije dostupan.', 'dp-b2b-quick-order' ),
				'invalid_identifier'   => __( 'SKU nije ispravan.', 'dp-b2b-quick-order' ),
				'invalid_quantity'     => __( 'Količina nije ispravna.', 'dp-b2b-quick-order' ),
				'quantity_limit'       => __( 'Količina prelazi dopuštenu granicu.', 'dp-b2b-quick-order' ),
				'variable_parent'      => __( 'Proizvod ima varijacije.', 'dp-b2b-quick-order' ),
				'ambiguous_identifier' => __( 'Artikl nije moguće jednoznačno prepoznati.', 'dp-b2b-quick-order' ),
				'unavailable'          => __( 'Artikl trenutno nije dostupan.', 'dp-b2b-quick-order' ),
				'not_purchasable'      => __( 'Artikl trenutno nije moguće naručiti.', 'dp-b2b-quick-order' ),
				'malformed_row'        => __( 'Redak ima neispravan broj stupaca.', 'dp-b2b-quick-order' ),
			],
			'mergedRows'       => __( 'Spojeni duplikati (retci: {rows}).', 'dp-b2b-quick-order' ),
			'warnings'         => [
				'multiple_sheets'            => __( 'Datoteka ima više listova; korišten je prvi vidljivi.', 'dp-b2b-quick-order' ),
				'hidden_rows_included'       => __( 'Skriveni retci su uključeni u uvoz.', 'dp-b2b-quick-order' ),
				'extra_columns_ignored'      => __( 'Dodatni stupci su zanemareni.', 'dp-b2b-quick-order' ),
				'formula_without_value'      => __( 'Neke ćelije s formulama nemaju izračunatu vrijednost.', 'dp-b2b-quick-order' ),
				'formula_cached_values_used' => __( 'Za ćelije s formulama korištene su spremljene vrijednosti.', 'dp-b2b-quick-order' ),
				'external_links_ignored'     => __( 'Vanjske poveznice u datoteci su zanemarene.', 'dp-b2b-quick-order' ),
			],
			'fileErrors'       => [
				'unsupported_format'          => __( 'Podržani su samo formati .xlsx i .csv.', 'dp-b2b-quick-order' ),
				'file_too_large'              => __( 'Datoteka je prevelika (najviše 2 MB).', 'dp-b2b-quick-order' ),
				'empty_file'                  => __( 'Datoteka ne sadrži nijedan redak za uvoz.', 'dp-b2b-quick-order' ),
				'invalid_header'              => __( 'Zaglavlje nije prepoznato. Prvi redak mora sadržavati stupce SKU i Količina.', 'dp-b2b-quick-order' ),
				'too_many_rows'               => __( 'Datoteka sadrži više od 500 redaka.', 'dp-b2b-quick-order' ),
				'malformed_csv'               => __( 'CSV datoteka nije ispravno oblikovana (provjerite navodnike).', 'dp-b2b-quick-order' ),
				'xlsx_macro_content'          => __( 'Datoteke s makroima nisu podržane.', 'dp-b2b-quick-order' ),
				'xlsx_no_visible_sheet'       => __( 'Datoteka nema nijedan vidljiv list.', 'dp-b2b-quick-order' ),
				'xlsx_too_many_entries'       => __( 'Datoteka je neispravna ili preopsežna.', 'dp-b2b-quick-order' ),
				'xlsx_too_large_uncompressed' => __( 'Datoteka je neispravna ili preopsežna.', 'dp-b2b-quick-order' ),
				'unsupported_encoding'        => __( 'Kodiranje datoteke nije podržano.', 'dp-b2b-quick-order' ),
				'invalid_file'                => __( 'Datoteku nije moguće pročitati.', 'dp-b2b-quick-order' ),
				'generic'                     => __( 'Datoteku nije moguće obraditi.', 'dp-b2b-quick-order' ),
			],
			'chooseAnother'    => __( 'Odaberi drugi file', 'dp-b2b-quick-order' ),
			'addValid'         => __( 'Dodaj ispravne artikle', 'dp-b2b-quick-order' ),
			'addingTitle'      => __( 'Dodavanje u košaricu je u toku', 'dp-b2b-quick-order' ),
			/* {n}: counted + declined noun */
			'addingText'       => __( 'Dodajemo {n} u vašu košaricu', 'dp-b2b-quick-order' ),
			'chunkProgress'    => __( 'Dio {done} od {total}', 'dp-b2b-quick-order' ),
			'viewCart'         => __( 'Pogledaj košaricu', 'dp-b2b-quick-order' ),
			'reviewCart'       => __( 'Pregled košarice', 'dp-b2b-quick-order' ),
			'doneTitle'        => __( 'Dodavanje u košaricu je završeno', 'dp-b2b-quick-order' ),
			'doneBoxTitle'     => __( 'Artikli uspješno dodani u košaricu', 'dp-b2b-quick-order' ),
			/* {n}: counted + declined noun */
			'doneBoxText'      => __( '{n} uspješno dodano!', 'dp-b2b-quick-order' ),
			'partialBoxTitle'  => __( 'Dio artikala nije dodan u košaricu', 'dp-b2b-quick-order' ),
			'noneBoxTitle'     => __( 'Nijedan artikl nije dodan u košaricu', 'dp-b2b-quick-order' ),
			'ambiguousBoxTitle' => __( 'Ishod dodavanja nije potvrđen', 'dp-b2b-quick-order' ),
			'addedCount'       => __( 'Dodano: {n}.', 'dp-b2b-quick-order' ),
			'failedCount'      => __( 'Nije dodano: {n}.', 'dp-b2b-quick-order' ),
			'unknownCount'     => __( 'Ishod nepoznat: {n}.', 'dp-b2b-quick-order' ),
			'unsentCount'      => __( 'Nije poslano: {n}.', 'dp-b2b-quick-order' ),
			'overlapWarning'   => __( 'Neki artikli i dalje imaju unesene količine u Quick Order popisu.', 'dp-b2b-quick-order' ),
			'itemForms'        => [ __( 'artikl', 'dp-b2b-quick-order' ), __( 'artikla', 'dp-b2b-quick-order' ), __( 'artikala', 'dp-b2b-quick-order' ) ],
			'validItemForms'   => [ __( 'ispravan artikl', 'dp-b2b-quick-order' ), __( 'ispravna artikla', 'dp-b2b-quick-order' ), __( 'ispravnih artikala', 'dp-b2b-quick-order' ) ],
		];
	}

	public function is_quick_order_page(): bool {
		return is_page( DP_Quick_Order_Config::PAGE_SLUG );
	}
}

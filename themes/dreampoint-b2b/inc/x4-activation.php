<?php
/**
 * X4 — aktivacija postojećih računa iz APROS sinkronizacije, spremnost i obavijest (ADR-051).
 *
 * Sve je pod gateom opcije `dp_x4_mode` (off | dry_run | live; zadano off). Dok gate nije "live":
 * nema aktivacije, nema obavijesti, bucket hook je no-op. Postojeće ručno odobravanje
 * (registration-approval.php) se ne mijenja.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Gate: off | dry_run | live. */
function dreampoint_b2b_x4_mode(): string {
    $mode = (string) get_option( 'dp_x4_mode', 'off' );

    return in_array( $mode, [ 'off', 'dry_run', 'live' ], true ) ? $mode : 'off';
}

/**
 * Svježe stanje korisničkog meta iz baze (zaobilazi zastarjeli cache): za provjere spremnosti koje
 * pokreću dva procesa (sinkronizacija i dodjela bucketa) — svaki piše pa tek onda čita, pa barem jedan vidi oba.
 */
function dreampoint_b2b_x4_fresh_meta( int $user_id, string $key ) {
    wp_cache_delete( $user_id, 'user_meta' );

    return get_user_meta( $user_id, $key, true );
}

/**
 * Spremnost za kupovinu: stvarno odobren (user meta, NE bypass) + valjan bucket.
 */
function dreampoint_b2b_x4_user_can_purchase( int $user_id ): bool {
    return (bool) dreampoint_b2b_x4_fresh_meta( $user_id, 'approved' ) && dreampoint_b2b_user_has_valid_bucket( $user_id );
}

// ----------------------------------------------------------------------------
// Atomska rezervacija obavijesti (jedan red po korisniku u wp_options: dp_x4_mail_<id>)
// Vrijednost: JSON {st: claimed|handed_off|failed|ambiguous, tok, ts, n}. Nema retka = nije pokušano.
// Rezervacija = INSERT IGNORE (jedinstveni option_name); prijelazi = CAS po točnoj prethodnoj vrijednosti.
// ----------------------------------------------------------------------------

function dreampoint_b2b_x4_mail_option( int $user_id ): string {
    return 'dp_x4_mail_' . $user_id;
}

/**
 * Pokušaj rezervirati obavijest. @return array{tok:string,val:string}|null null = netko drugi je (ili je već) obradio.
 */
function dreampoint_b2b_x4_mail_claim( int $user_id ): ?array {
    global $wpdb;

    $name = dreampoint_b2b_x4_mail_option( $user_id );
    $tok  = bin2hex( random_bytes( 8 ) );
    $val  = wp_json_encode( [ 'st' => 'claimed', 'tok' => $tok, 'ts' => time(), 'n' => 1 ] );

    $ins = $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
        $name,
        $val
    ) );

    if ( 1 === (int) $ins ) {
        return [ 'tok' => $tok, 'val' => $val ];
    }

    // Postoji redak: samo eksplicitni "failed" (wp_mail false) smije ponovni pokušaj, najviše 3 puta.
    $cur = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
    $d   = is_string( $cur ) ? json_decode( $cur, true ) : null;

    if ( ! is_array( $d ) || 'failed' !== ( $d['st'] ?? '' ) || (int) ( $d['n'] ?? 0 ) >= 3 ) {
        return null; // handed_off / claimed / ambiguous / iscrpljeno: nikad automatski ponovno slanje
    }

    $new = wp_json_encode( [ 'st' => 'claimed', 'tok' => $tok, 'ts' => time(), 'n' => (int) $d['n'] + 1 ] );
    $upd = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        $new,
        $name,
        $cur
    ) );

    return 1 === (int) $upd ? [ 'tok' => $tok, 'val' => $new ] : null;
}

/** Završni prijelaz iz "claimed" (CAS po vlastitoj rezervaciji). */
function dreampoint_b2b_x4_mail_finish( int $user_id, array $claim, string $state ): bool {
    global $wpdb;

    $d   = json_decode( $claim['val'], true );
    $new = wp_json_encode( [ 'st' => $state, 'tok' => $claim['tok'], 'ts' => time(), 'n' => (int) ( $d['n'] ?? 1 ) ] );

    return 1 === (int) $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        $new,
        dreampoint_b2b_x4_mail_option( $user_id ),
        $claim['val']
    ) );
}

/** Stanje obavijesti za korisnika: none|claimed|handed_off|failed|ambiguous. */
function dreampoint_b2b_x4_mail_state( int $user_id ): string {
    global $wpdb;

    $cur = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", dreampoint_b2b_x4_mail_option( $user_id ) ) );
    $d   = is_string( $cur ) ? json_decode( $cur, true ) : null;

    return is_array( $d ) ? (string) ( $d['st'] ?? 'none' ) : 'none';
}

/**
 * Nerazriješena stanja obavijesti za izvještaj: failed, ambiguous, claimed starije od 15 min.
 * Samo ID-evi korisnika (bez osobnih podataka).
 *
 * @return string[]
 */
function dreampoint_b2b_x4_notification_issues(): array {
    global $wpdb;

    $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 200", $wpdb->esc_like( 'dp_x4_mail_' ) . '%' ) );
    $issues = [];

    foreach ( (array) $rows as $row ) {
        $d  = json_decode( (string) $row->option_value, true );
        $st = is_array( $d ) ? (string) ( $d['st'] ?? '' ) : 'ambiguous';

        if ( 'handed_off' === $st ) {
            continue;
        }

        if ( 'claimed' === $st && time() - (int) ( $d['ts'] ?? 0 ) < 900 ) {
            continue; // još u tijeku
        }

        $issues[] = substr( (string) $row->option_name, strlen( 'dp_x4_mail_' ) ) . ':' . $st;
    }

    return array_slice( $issues, 0, 25 );
}

/**
 * Aktivacija POSTOJEĆEG računa iz APROS sinkronizacije (poziva je samo apros-pricing u live gateu).
 * Samo prijelaz neodobren → odobren dobiva marker; već odobreni (legacy/ručni) korisnici se ne diraju
 * i nikad ne dobivaju X4 obavijest.
 *
 * Redoslijed upisa (svaki je zaseban upit): kod aktivacije -> vrijeme aktivacije (marker) -> `approved` ZADNJE.
 * Prekid usred niza stoga nikad ne daje pristup kupnji (nema `approved`), ne pokreće obavijest (spremnost traži
 * `approved`) i sljedeći legitimni run dovršava niz. `$write( user_id, key, value )` je zaštićeni pisač sinkronizacije
 * (ok | unchanged | lost | error; vlasništvo se provjerava unutar SQL-a); bez njega (npr. testovi) koristi se update_user_meta.
 *
 * @param callable|null $write Zaštićeni pisač meta.
 * @return string activated|already_approved|skipped_staff|gate_closed|not_found|lock_lost|write_error
 */
function dreampoint_b2b_x4_activate_user( int $user_id, int $partner_code, ?callable $write = null ): string {
    if ( 'live' !== dreampoint_b2b_x4_mode() ) {
        return 'gate_closed';
    }

    if ( ! get_userdata( $user_id ) ) {
        return 'not_found';
    }

    if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'manage_woocommerce' ) ) {
        return 'skipped_staff';
    }

    if ( dreampoint_b2b_x4_fresh_meta( $user_id, 'approved' ) ) {
        return 'already_approved';
    }

    $write ??= static function ( int $uid, string $key, $value ): string {
        update_user_meta( $uid, $key, $value );
        return 'ok';
    };

    foreach ( [ [ '_dp_x4_activated_code', $partner_code ], [ '_dp_x4_activated_at', time() ], [ 'approved', true ] ] as [ $key, $value ] ) {
        $r = $write( $user_id, $key, $value );

        if ( 'lost' === $r ) {
            return 'lock_lost'; // vlasništvo izgubljeno: ostatak niza se NE nastavlja
        }

        if ( 'ok' !== $r && 'unchanged' !== $r ) {
            return 'write_error'; // SQL greška nije uspjeh
        }
    }

    dreampoint_b2b_x4_maybe_notify( $user_id );

    return 'activated';
}

/**
 * Zajednička provjera spremnosti + obavijest (oba redoslijeda: odobrenje→bucket i bucket→odobrenje).
 * Šalje se najviše jednom (atomska rezervacija); "handed_off" = predano mail sustavu, ne potvrda dostave.
 *
 * @return string not_ready|gate_closed|not_claimed|handed_off|failed|ambiguous
 */
function dreampoint_b2b_x4_maybe_notify( int $user_id ): string {
    if ( 'live' !== dreampoint_b2b_x4_mode() ) {
        return 'gate_closed';
    }

    // Marker postoji samo za račune koje je aktivirao X4: legacy/ručno odobreni korisnici ga nemaju.
    $code = (int) dreampoint_b2b_x4_fresh_meta( $user_id, 'apros_partner_code' );

    if ( ! dreampoint_b2b_x4_fresh_meta( $user_id, '_dp_x4_activated_at' ) || $code <= 0
        // Pouzdan identitet i APROS prihvatljivost iz same aktivacije: potvrđena veza i kod aktivacije = trenutni kod računa.
        || (int) dreampoint_b2b_x4_fresh_meta( $user_id, '_dp_x4_link_verified' ) !== $code
        || (int) dreampoint_b2b_x4_fresh_meta( $user_id, '_dp_x4_activated_code' ) !== $code ) {
        return 'not_ready';
    }

    // Statički cache konteksta A14 po zahtjevu može čuvati raniji "no_access" (npr. izračunat pri aktivaciji u istom
    // zahtjevu prije dodjele bucketa): za provjeru spremnosti se odbacuje, pa se bucket procjenjuje svježe.
    if ( function_exists( 'dreampoint_b2b_visibility_engine' ) ) {
        dreampoint_b2b_visibility_engine()->invalidate_user( $user_id );
    }

    if ( ! dreampoint_b2b_x4_user_can_purchase( $user_id ) ) {
        return 'not_ready';
    }

    $claim = dreampoint_b2b_x4_mail_claim( $user_id );

    if ( null === $claim ) {
        return 'not_claimed';
    }

    try {
        $ok = dreampoint_b2b_send_approval_email( $user_id );
    } catch ( \Throwable $e ) {
        // Nejasno je je li poruka predana: konzervativno, bez automatskog ponovnog slanja.
        dreampoint_b2b_x4_mail_finish( $user_id, $claim, 'ambiguous' );

        return 'ambiguous';
    }

    $state = $ok ? 'handed_off' : 'failed';
    dreampoint_b2b_x4_mail_finish( $user_id, $claim, $state );

    return $state;
}

/** Dodjela / promjena bucketa pokreće istu provjeru spremnosti (gate live + marker; inače no-op). */
function dreampoint_b2b_x4_on_bucket_meta( $meta_id, $user_id, $meta_key ): void {
    if ( 'dp_bucket_id' !== $meta_key || 'live' !== dreampoint_b2b_x4_mode() ) {
        return;
    }

    dreampoint_b2b_x4_maybe_notify( (int) $user_id );
}
add_action( 'added_user_meta', 'dreampoint_b2b_x4_on_bucket_meta', 10, 3 );
add_action( 'updated_user_meta', 'dreampoint_b2b_x4_on_bucket_meta', 10, 3 );

// ----------------------------------------------------------------------------
// Administracija: stupac "B2B status" + sažetak zadnjeg runa na ekranu Korisnici
// ----------------------------------------------------------------------------

/**
 * Status korisnika kao [slug, oznaka]: neodobren = Čeka odobrenje (bez obzira na bucket) | odobren bez valjanog
 * bucketa = Čeka dodjelu bucketa | odobren s valjanim bucketom = Aktivan | osoblje = —.
 * Čita STVARNO spremljeno odobrenje (user meta), ne razvojni DP_BYPASS_APPROVAL.
 *
 * @return array{0:string,1:string}
 */
function dreampoint_b2b_x4_user_status( int $user_id ): array {
    if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'manage_woocommerce' ) ) {
        return [ 'staff', '—' ];
    }

    if ( ! get_user_meta( $user_id, 'approved', true ) ) {
        return [ 'pending', __( 'Čeka odobrenje', 'dreampoint-b2b' ) ];
    }

    return dreampoint_b2b_user_has_valid_bucket( $user_id )
        ? [ 'active', __( 'Aktivan', 'dreampoint-b2b' ) ]
        : [ 'bucket', __( 'Čeka dodjelu bucketa', 'dreampoint-b2b' ) ];
}

/** Samo oznaka statusa (zadržano radi postojećih pozivatelja / testova). */
function dreampoint_b2b_x4_user_status_label( int $user_id ): string {
    return dreampoint_b2b_x4_user_status( $user_id )[1];
}

/** Ključevi izvještaja koji predstavljaju probleme za provjeru (bez prijedloga would_*). */
function dreampoint_b2b_x4_issue_keys(): array {
    return [ 'identity_conflicts', 'duplicate_partner_codes', 'duplicate_apros_emails', 'duplicate_wp_assignments',
        'ambiguous_email', 'missing_email', 'unmatched', 'missing_partners', 'email_discrepancies', 'link_unverified', 'invalid_records' ];
}

/** Razumljiv hrvatski opis razloga neuspjeha zadnjeg runa. */
function dreampoint_b2b_x4_reason_label( string $reason ): string {
    $map = [
        'transport_error'        => __( 'APROS nije dostupan (greška veze).', 'dreampoint-b2b' ),
        'http_error'             => __( 'APROS je vratio grešku.', 'dreampoint-b2b' ),
        'empty_response'         => __( 'APROS je vratio prazan odgovor.', 'dreampoint-b2b' ),
        'malformed_json'         => __( 'Odgovor APROS-a nije ispravan.', 'dreampoint-b2b' ),
        'unexpected_structure'   => __( 'Odgovor APROS-a ima neočekivan oblik.', 'dreampoint-b2b' ),
        'empty_list'             => __( 'APROS nije vratio nijednog partnera.', 'dreampoint-b2b' ),
        'mostly_invalid_records' => __( 'Većina zapisa u odgovoru je neispravna.', 'dreampoint-b2b' ),
        'lock_lost'              => __( 'Izvršavanje je prekinuto jer ga je preuzeo drugi proces.', 'dreampoint-b2b' ),
        'no_provider'            => __( 'Veza prema APROS-u nije konfigurirana.', 'dreampoint-b2b' ),
    ];

    return $map[ $reason ] ?? __( 'Provjera nije uspjela.', 'dreampoint-b2b' );
}

/**
 * HTML kompaktne, zadano SAŽETE napomene o zadnjem izvještaju sinkronizacije (native details/summary, bez JavaScripta,
 * bez spremanja stanja). Sažetak uvijek prikazuje i neuspjeh / upozorenja. Sve vrijednosti dolaze iz spremljenog
 * izvještaja i trenutne postavke, sve je escapano.
 *
 * @param mixed  $run  Sadržaj opcije dp_x4_last_run (ili false kad ne postoji).
 * @param string $mode Trenutna postavka sinkronizacije: off | dry_run | live.
 */
function dreampoint_b2b_x4_render_sync_notice( $run, string $mode ): string {
	$state = [
		'live'    => __( 'Aktivna (svakih 30 min)', 'dreampoint-b2b' ),
		'dry_run' => __( 'Probni način (svakih 30 min, bez izmjena računa)', 'dreampoint-b2b' ),
	][ $mode ] ?? __( 'Isključena', 'dreampoint-b2b' );
	$state_short = [
		'live'    => __( 'Aktivna', 'dreampoint-b2b' ),
		'dry_run' => __( 'Probni način', 'dreampoint-b2b' ),
	][ $mode ] ?? __( 'Isključena', 'dreampoint-b2b' );

	$lines   = [ [ __( 'Automatska sinkronizacija', 'dreampoint-b2b' ), $state ] ];
	$class   = 'notice-info';
	$extra   = [];   // rečenice u detaljima
	$summary = [ $state_short ];
	$flags   = [];   // kratka upozorenja koja moraju biti vidljiva i u sažetku

	if ( ! is_array( $run ) || empty( $run['time'] ) ) {
		$lines[]   = [ __( 'Posljednja provjera', 'dreampoint-b2b' ), __( 'još nije izvršena', 'dreampoint-b2b' ) ];
		$summary[] = __( 'Posljednja provjera: još nije izvršena', 'dreampoint-b2b' );
	} else {
		$is_live = ! empty( $run['live'] );
		$ok      = 'ok' === ( $run['outcome'] ?? '' );
		$ts      = strtotime( (string) $run['time'] );
		$when    = false !== $ts ? wp_date( 'd.m.Y. \u H:i', $ts ) : __( 'nepoznato', 'dreampoint-b2b' );
		$when_lb = $is_live ? __( 'Posljednja sinkronizacija', 'dreampoint-b2b' ) : __( 'Posljednja provjera', 'dreampoint-b2b' );

		$lines[]   = [ $when_lb, $when ];
		$lines[]   = [ __( 'Način izvršavanja', 'dreampoint-b2b' ), $is_live ? __( 'Stvarna sinkronizacija', 'dreampoint-b2b' ) : __( 'Probna provjera (bez izmjena računa)', 'dreampoint-b2b' ) ];
		$summary[] = $when_lb . ': ' . $when;

		$lines[] = [ $is_live ? __( 'Rezultat sinkronizacije', 'dreampoint-b2b' ) : __( 'Rezultat provjere', 'dreampoint-b2b' ), $ok ? __( 'Uspješno', 'dreampoint-b2b' ) : __( 'Neuspješno', 'dreampoint-b2b' ) ];

		if ( ! $ok ) {
			$class     = 'notice-error';
			$reason    = dreampoint_b2b_x4_reason_label( (string) ( $run['reason'] ?? '' ) );
			$extra[]   = $reason;
			$summary[] = __( 'Neuspješno', 'dreampoint-b2b' ) . ' — ' . $reason;
		} else {
			$summary[] = __( 'Uspješno', 'dreampoint-b2b' );

			if ( isset( $run['partners_count'] ) ) {
				$lines[] = [ __( 'Zaprimljeni partneri', 'dreampoint-b2b' ), (string) (int) $run['partners_count'] ];
			}

			$counts = is_array( $run['counts'] ?? null ) ? $run['counts'] : [];

			if ( $is_live ) {
				$lines[] = [ __( 'Novoaktivirani kupci', 'dreampoint-b2b' ), (string) (int) ( $counts['activated'] ?? 0 ) ];
			} else {
				// Probni run po konstrukciji ne mijenja račune (live=false u izvještaju).
				$lines[] = [ __( 'Izmijenjeni korisnički računi', 'dreampoint-b2b' ), '0' ];

				if ( ! empty( $counts['would_activate'] ) ) {
					$lines[] = [ __( 'Predložena odobrenja kupaca (nisu provedena)', 'dreampoint-b2b' ), (string) (int) $counts['would_activate'] ];
				}
			}

			$issues = 0;

			foreach ( dreampoint_b2b_x4_issue_keys() as $k ) {
				$issues += (int) ( $counts[ $k ] ?? 0 );
			}

			if ( $issues > 0 ) {
				$lines[] = [ __( 'Problemi za provjeru', 'dreampoint-b2b' ), (string) $issues ];
				$flags[] = sprintf( /* translators: %d: number of issues */ __( 'problemi za provjeru: %d', 'dreampoint-b2b' ), $issues );
				$class   = 'notice-warning';
			}

			if ( ! $is_live ) {
				$extra[] = __( 'Izvršena je probna provjera bez izmjena korisničkih računa.', 'dreampoint-b2b' );
			}
		}

		$warn_map = [
			'size_drop_report_only'       => [ __( 'Broj partnera naglo je pao u odnosu na prethodni put, pa je provedena samo provjera bez izmjena.', 'dreampoint-b2b' ), __( 'nagli pad broja partnera', 'dreampoint-b2b' ) ],
			'invalid_records_report_only' => [ __( 'Odgovor sadrži neispravne zapise, pa je provedena samo provjera bez izmjena.', 'dreampoint-b2b' ), __( 'neispravni zapisi u odgovoru', 'dreampoint-b2b' ) ],
		];

		foreach ( (array) ( $run['warnings'] ?? [] ) as $w ) {
			if ( isset( $warn_map[ $w ] ) ) {
				$extra[] = $warn_map[ $w ][0];
				$flags[] = $warn_map[ $w ][1];
				if ( 'notice-error' !== $class ) { $class = 'notice-warning'; }
			}
		}

		if ( ! empty( $run['previous_interrupted'] ) ) {
			$extra[] = __( 'Prethodno izvršavanje bilo je prekinuto.', 'dreampoint-b2b' );
			$flags[] = __( 'prekinuto prethodno izvršavanje', 'dreampoint-b2b' );
			if ( 'notice-error' !== $class ) { $class = 'notice-warning'; }
		}

		if ( ! empty( $run['notification_issues'] ) ) {
			$n       = count( (array) $run['notification_issues'] );
			$extra[] = sprintf( /* translators: %d: number of notifications needing review */ __( 'Obavijesti koje treba provjeriti: %d.', 'dreampoint-b2b' ), $n );
			$flags[] = sprintf( /* translators: %d: number */ __( 'obavijesti za provjeru: %d', 'dreampoint-b2b' ), $n );
			if ( 'notice-error' !== $class ) { $class = 'notice-warning'; }
		}
	}

	if ( $flags ) {
		$summary[] = __( 'Upozorenje', 'dreampoint-b2b' ) . ': ' . implode( ', ', $flags );
	}

	$body = '';

	foreach ( $lines as [ $label, $value ] ) {
		$body .= '<div>' . esc_html( $label ) . ': ' . esc_html( $value ) . '</div>';
	}

	foreach ( $extra as $e ) {
		$body .= '<div>' . esc_html( $e ) . '</div>';
	}

	return '<div class="notice ' . esc_attr( $class ) . ' dp-x4-sync-notice"><details>'
		. '<summary><strong>' . esc_html__( 'APROS sinkronizacija', 'dreampoint-b2b' ) . '</strong>'
		. '<span class="dp-x4-sync-summary">' . esc_html( implode( ' · ', $summary ) ) . '</span>'
		. '<span class="dp-x4-toggle dp-x4-toggle-open">' . esc_html__( 'Prikaži detalje', 'dreampoint-b2b' ) . '</span>'
		. '<span class="dp-x4-toggle dp-x4-toggle-close">' . esc_html__( 'Sakrij detalje', 'dreampoint-b2b' ) . '</span></summary>'
		. '<div class="dp-x4-sync-details">' . $body . '</div></details></div>';
}

if ( is_admin() ) {
    add_filter( 'manage_users_columns', static function ( array $cols ): array {
        if ( current_user_can( 'list_users' ) ) {
            $cols['dp_b2b_status'] = esc_html__( 'B2B status', 'dreampoint-b2b' );
        }

        return $cols;
    } );

    add_filter( 'manage_users_custom_column', static function ( $output, string $column, int $user_id ) {
        if ( 'dp_b2b_status' !== $column || ! current_user_can( 'list_users' ) ) {
            return $output;
        }

        [ $slug, $label ] = dreampoint_b2b_x4_user_status( $user_id );

        if ( 'staff' === $slug ) {
            return esc_html( $label );
        }

        return '<span class="dp-x4-status dp-x4-status--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span>';
    }, 10, 3 );

    // Ograničene, pristupačne boje (tekst + boja, nikad samo boja); samo na ekranu Korisnici.
    add_action( 'admin_head-users.php', static function (): void {
        echo '<style>.dp-x4-status{display:inline-block;padding:2px 8px;border-radius:3px;font-weight:600;line-height:1.5}'
            . '.dp-x4-status--pending{background:#f0f0f1;color:#3c434a}'
            . '.dp-x4-status--bucket{background:#fcf0d4;color:#704a00}'
            . '.dp-x4-status--active{background:#e3f4e8;color:#0a5b25}'
            . '.dp-x4-sync-notice{padding:0}'
            . '.dp-x4-sync-notice summary{display:flex;flex-wrap:wrap;align-items:baseline;gap:2px 10px;padding:7px 12px;cursor:pointer;list-style:none}'
            . '.dp-x4-sync-notice summary::-webkit-details-marker{display:none}'
            . '.dp-x4-sync-notice summary::before{content:"\\25B8";font-size:12px}'
            . '.dp-x4-sync-notice details[open] summary::before{content:"\\25BE"}'
            . '.dp-x4-sync-notice summary:focus-visible{outline:2px solid #2271b1;outline-offset:-2px}'
            . '.dp-x4-sync-summary{flex:1 1 auto;min-width:0;overflow-wrap:anywhere}'
            . '.dp-x4-toggle{color:#2271b1;text-decoration:underline;white-space:nowrap}'
            . '.dp-x4-toggle-close{display:none}'
            . '.dp-x4-sync-notice details[open] .dp-x4-toggle-open{display:none}'
            . '.dp-x4-sync-notice details[open] .dp-x4-toggle-close{display:inline}'
            . '.dp-x4-sync-details{padding:0 12px 9px 28px;line-height:1.6}</style>';
    } );

    add_action( 'admin_notices', static function (): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'users' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Svaka vrijednost je escapana unutar dreampoint_b2b_x4_render_sync_notice().
        echo dreampoint_b2b_x4_render_sync_notice( get_option( 'dp_x4_last_run' ), dreampoint_b2b_x4_mode() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } );
}

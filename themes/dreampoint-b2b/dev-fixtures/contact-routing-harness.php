<?php
/**
 * Contact form routing: NON-SENDING verification harness (WP-CLI only).
 *
 *   sudo -u <siteUser> wp --path=<wp root> eval-file wp-content/themes/dreampoint-b2b/dev-fixtures/contact-routing-harness.php
 *
 * It drives the REAL Contact Form 7 REST feedback route (rest_pre_dispatch gate -> CF7 validation -> before_send_mail
 * -> mail components -> wp_mail) in-process and captures every mail with the `pre_wp_mail` short-circuit, so nothing is
 * ever delivered (a `phpmailer_init` canary aborts the run if PHPMailer were reached). Test conditions that cannot
 * be produced from real data (invalid e-mail, missing representative, exception) are injected with core metadata
 * filters; no partner / representative / form data is written. Login cookies are generated for the run and the
 * users' `session_tokens` meta is restored byte for byte afterwards. Output masks e-mail addresses.
 *
 * Not for production use; refuses to run outside WP-CLI.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( "CLI only\n" );
}

// ---- safety: no mail may leave this process ----
$GLOBALS['dp_h_mails'] = array();
add_filter(
    'pre_wp_mail',
    static function ( $null, $atts ) {
        $GLOBALS['dp_h_mails'][] = $atts;
        return true; // short-circuits wp_mail(): nothing is sent.
    },
    1,
    2
);
add_action(
    'phpmailer_init',
    static function () {
        fwrite( STDERR, "CANARY: PHPMailer reached, aborting\n" );
        exit( 1 );
    },
    1
);

$mask = static function ( string $e ): string {
    return preg_match( '/^(.).*@(.+)$/', $e, $m ) ? $m[1] . '***@' . $m[2] : $e;
};

$form = dreampoint_b2b_contact_form();
if ( ! $form ) {
    echo "ABORT: Contact form not resolvable\n";
    return;
}
$fallback = dreampoint_b2b_contact_fallback_email();

// ---- fixtures from real data (read only) ----
global $wpdb;
$with_rep_id = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='assigned_komercijalist' AND meta_value<>'' AND meta_value<>'0' LIMIT 1" );
$without_id  = (int) $wpdb->get_var( "SELECT u.ID FROM {$wpdb->users} u WHERE u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='assigned_komercijalist' AND meta_value<>'' AND meta_value<>'0') AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%') LIMIT 1" );
if ( ! $with_rep_id || ! $without_id ) {
    echo "ABORT: need one partner with and one without an assigned representative\n";
    return;
}
$rep_id    = (int) get_user_meta( $with_rep_id, 'assigned_komercijalist', true );
$rep_email = (string) get_post_meta( $rep_id, 'email_commercialist', true );
$other_ids = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids' ) ); // a post that is not a komercijalist
echo "fixtures: with_rep user=$with_rep_id rep_post=$rep_id rep_email=" . $mask( $rep_email ) . " | without_rep user=$without_id | fallback=" . $mask( $fallback ) . "\n";
echo "representative email equals fallback: " . ( $rep_email === $fallback ? "yes (case 1b disambiguates)" : "no" ) . "
";
echo "form id=" . $form->id() . " hash=" . substr( $form->hash(), 0, 7 ) . "\n\n";

// ---- helpers ----
$snapshots = array();
// Restore the users' session_tokens even if the run dies half way (e.g. a fatal error inside CF7).
register_shutdown_function(
    static function () use ( &$snapshots ) {
        foreach ( $snapshots as $uid => $orig ) {
            if ( null === $orig ) {
                delete_user_meta( $uid, 'session_tokens' );
            } else {
                update_user_meta( $uid, 'session_tokens', $orig );
            }
        }
    }
);
$login_cookie = static function ( int $uid, int $expiry_offset = 3600 ) use ( &$snapshots ): string {
    if ( ! isset( $snapshots[ $uid ] ) ) {
        $snapshots[ $uid ] = metadata_exists( 'user', $uid, 'session_tokens' ) ? get_user_meta( $uid, 'session_tokens', true ) : null;
    }
    $mgr   = WP_Session_Tokens::get_instance( $uid );
    $token = $mgr->create( time() + 3600 );
    return wp_generate_auth_cookie( $uid, time() + $expiry_offset, 'logged_in', $token );
};

$run = static function ( string $label, array $opts ) use ( $form, $mask ) {
    $GLOBALS['dp_h_mails'] = array();
    // CF7 keeps ONE submission object per PHP process (singleton); a real HTTP request is a fresh process, so reset it.
    $ref = new ReflectionProperty( 'WPCF7_Submission', 'instance' );
    $ref->setAccessible( true );
    $ref->setValue( null, null );
    $_COOKIE = array();
    if ( ! empty( $opts['cookie'] ) ) {
        $_COOKIE[ LOGGED_IN_COOKIE ] = $opts['cookie'];
    }
    wp_set_current_user( 0 ); // emulates the nonce-less CF7 REST request: WordPress sees no user.
    $seen_current_user = null;
    $observer = static function () use ( &$seen_current_user ) {
        $seen_current_user = get_current_user_id();
    };
    add_action( 'wpcf7_before_send_mail', $observer, 1 );

    $post = array(
        '_wpcf7'                 => (string) $form->id(),
        '_wpcf7_version'         => WPCF7_VERSION,
        '_wpcf7_locale'          => 'en_US',
        '_wpcf7_unit_tag'        => 'wpcf7-f' . $form->id() . '-o1',
        '_wpcf7_container_post'  => '0',
        'vrsta-upita'            => 'Narudžbe',
        'vas-upit'               => 'Testna poruka harnessa (nije poslana).',
    ) + ( $opts['post'] ?? array() );
    if ( isset( $opts['post_override'] ) ) {
        $post = array_merge( $post, $opts['post_override'] );
    }
    $_POST = $post;
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'POST'; // CF7's submission object only exists for POST requests.

    $req = new WP_REST_Request( 'POST', '/contact-form-7/v1/contact-forms/' . $form->id() . '/feedback' );
    $req->set_header( 'origin', $opts['origin'] ?? home_url() );
    $req->set_header( 'content-type', 'multipart/form-data; boundary=----dpharness' ); // CF7 rejects other media types (415).
    $req->set_body_params( $post );
    $req->set_param( '_wpcf7_unit_tag', $post['_wpcf7_unit_tag'] );

    $res  = rest_get_server()->dispatch( $req );
    $data = $res->get_data();
    remove_action( 'wpcf7_before_send_mail', $observer, 1 );

    $mails = $GLOBALS['dp_h_mails'];
    $to    = array();
    $hdrs  = array();
    foreach ( $mails as $m ) {
        $to    = array_merge( $to, (array) $m['to'] );
        $hdrs  = array_merge( $hdrs, (array) $m['headers'] );
    }
    $hdr_text  = implode( "\n", array_map( 'strval', $hdrs ) );
    $extra_rcp = (bool) preg_match( '/^(cc|bcc|to):/im', $hdr_text );
    $body      = $mails ? (string) $mails[0]['message'] : '';

    return array(
        'label'      => $label,
        'http'       => $res->get_status(),
        'cf7'        => $data['status'] ?? '?',
        'mail_count' => count( $mails ),
        'to'         => array_map( $mask, $to ),
        'to_raw'     => $to,
        'extra_rcp'  => $extra_rcp,
        'headers'    => $hdr_text,
        'body'       => $body,
        'subject'    => $mails ? (string) $mails[0]['subject'] : '',
        'cur_user'   => $seen_current_user,
    );
};

$results = array();
$add = static function ( array $r, bool $pass, string $expect ) use ( &$results ) {
    $r['pass']   = $pass;
    $r['expect'] = $expect;
    $results[]   = $r;
};

// 1. Partner with a valid representative -> that representative only
$cookie = $login_cookie( $with_rep_id );
$r = $run( '1 partner + valid representative', array( 'cookie' => $cookie ) );
$add( $r, 'mail_sent' === $r['cf7'] && 1 === $r['mail_count'] && array( $rep_email ) === $r['to_raw'] && ! $r['extra_rcp'] && 0 === $r['cur_user'], 'to = representative only, no Cc/Bcc, WP current user = 0' );
$body_1 = $r['body'];
$subject_1 = $r['subject'];

// 1b. The same with a representative address that differs from the fallback, so "representative only" is unambiguous
//     (on staging the real representative and the fallback may be the same mailbox).
add_filter( 'get_post_metadata', $f1b = static function ( $v, $oid, $key ) use ( $rep_id ) {
    return ( (int) $oid === $rep_id && 'email_commercialist' === $key ) ? array( 'rep.harness@example.test' ) : $v;
}, 10, 3 );
$r = $run( '1b partner + representative with a distinct address', array( 'cookie' => $cookie ) );
remove_filter( 'get_post_metadata', $f1b, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && 1 === $r['mail_count'] && array( 'rep.harness@example.test' ) === $r['to_raw'] && ! $r['extra_rcp'] && false === stripos( $r['headers'], $fallback ) && false === stripos( $r['headers'], 'cc:' ), 'to = ONLY rep.harness@example.test (fallback absent from recipients and headers)' );

// 2. Partner without an assignment -> fallback
$r = $run( '2 partner without assignment', array( 'cookie' => $login_cookie( $without_id ) ) );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'] && ! $r['extra_rcp'], 'to = fallback only' );

// 3. Representative with an invalid e-mail -> fallback
add_filter( 'get_post_metadata', $f3 = static function ( $v, $oid, $key ) use ( $rep_id ) {
    return ( (int) $oid === $rep_id && 'email_commercialist' === $key ) ? array( 'not-an-email' ) : $v;
}, 10, 3 );
$r = $run( '3 representative e-mail invalid', array( 'cookie' => $cookie ) );
remove_filter( 'get_post_metadata', $f3, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'] && ! $r['extra_rcp'], 'to = fallback only' );

// 3b. Representative with an e-mail that carries a header-injection payload -> fallback
add_filter( 'get_post_metadata', $f3b = static function ( $v, $oid, $key ) use ( $rep_id ) {
    return ( (int) $oid === $rep_id && 'email_commercialist' === $key ) ? array( "a@b.hr\r\nBcc: evil@example.com" ) : $v;
}, 10, 3 );
$r = $run( '3b representative e-mail with injected header', array( 'cookie' => $cookie ) );
remove_filter( 'get_post_metadata', $f3b, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'] && ! $r['extra_rcp'], 'to = fallback only' );

// 4. Representative post missing / wrong type -> fallback
add_filter( 'get_user_metadata', $f4 = static function ( $v, $oid, $key ) use ( $with_rep_id ) {
    return ( (int) $oid === $with_rep_id && 'assigned_komercijalist' === $key ) ? array( '999999999' ) : $v;
}, 10, 3 );
$r = $run( '4a representative post missing', array( 'cookie' => $cookie ) );
remove_filter( 'get_user_metadata', $f4, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'], 'to = fallback only' );

add_filter( 'get_user_metadata', $f4b = static function ( $v, $oid, $key ) use ( $with_rep_id, $other_ids ) {
    return ( (int) $oid === $with_rep_id && 'assigned_komercijalist' === $key ) ? array( (string) ( $other_ids[0] ?? 1 ) ) : $v;
}, 10, 3 );
$r = $run( '4b assigned post is not a komercijalist', array( 'cookie' => $cookie ) );
remove_filter( 'get_user_metadata', $f4b, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'], 'to = fallback only' );

// 5. Guest
$r = $run( '5 guest (no cookie)', array() );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );

// 6. Invalid / expired authentication
$tampered = substr( $cookie, 0, -4 ) . ( substr( $cookie, -4 ) === 'aaaa' ? 'bbbb' : 'aaaa' );
$r = $run( '6a tampered cookie', array( 'cookie' => $tampered ) );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );
// WordPress itself allows a 1 hour grace period for POST / AJAX requests, so the cookie must be older than that.
$r = $run( '6b expired cookie (older than the POST grace)', array( 'cookie' => $login_cookie( $with_rep_id, -7300 ) ) );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );
$r = $run( '6c cookie of a non-existent user', array( 'cookie' => wp_generate_auth_cookie( 999999, time() + 3600, 'logged_in', 'x' ) ) );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );
$r = $run( '6d valid cookie, cross-origin Origin header', array( 'cookie' => $cookie, 'origin' => 'https://evil.example' ) );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );

// 7. Recipient manipulation through submitted fields (guest and partner)
$evil = array(
    'recipient'              => 'evil@example.com',
    'your-email'             => 'evil@example.com',
    '_wpcf7_recipient'       => 'evil@example.com',
    'assigned_komercijalist' => '1',
    'user_id'                => '1',
    '_wpcf7_user_id'         => '1',
    'cc'                     => 'evil@example.com',
    'bcc'                    => 'evil@example.com',
    'additional_headers'     => "Bcc: evil@example.com",
    'vas-upit'               => "Poruka\r\nBcc: evil@example.com\r\nTo: evil@example.com",
);
$r = $run( '7a partner + manipulated fields', array( 'cookie' => $cookie, 'post_override' => $evil ) );
$add( $r, 'mail_sent' === $r['cf7'] && array( $rep_email ) === $r['to_raw'] && ! $r['extra_rcp'] && false === strpos( $r['to_raw'][0] ?? '', 'evil' ), 'recipient unchanged, no Cc/Bcc/To headers' );
$r = $run( '7b guest + manipulated fields', array( 'post_override' => $evil ) );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );
$r = $run( '7c partner + invalid select value (header payload)', array( 'cookie' => $cookie, 'post_override' => array( 'vrsta-upita' => "Narudžbe\r\nBcc: evil@example.com" ) ) );
$add( $r, 'validation_failed' === $r['cf7'] && 0 === $r['mail_count'], 'validation_failed (value not in the list), no mail' );

// 8. Unexpected resolution failure -> fallback for an authenticated partner
add_filter( 'get_user_metadata', $f8 = static function ( $v, $oid, $key ) use ( $with_rep_id ) {
    if ( (int) $oid === $with_rep_id && 'assigned_komercijalist' === $key ) {
        throw new RuntimeException( 'simulated failure' );
    }
    return $v;
}, 10, 3 );
$r = $run( '8 resolver throws', array( 'cookie' => $cookie ) );
remove_filter( 'get_user_metadata', $f8, 10 );
$add( $r, 'mail_sent' === $r['cf7'] && array( $fallback ) === $r['to_raw'] && ! $r['extra_rcp'], 'to = fallback only' );

// 9. Fallback itself invalid -> reject instead of guessing
add_filter( 'dreampoint_b2b_contact_fallback_email', $f9 = static function () { return 'not-valid'; } );
$r = $run( '9 fallback misconfigured (partner without assignment)', array( 'cookie' => $login_cookie( $without_id ) ) );
remove_filter( 'dreampoint_b2b_contact_fallback_email', $f9 );
$add( $r, 'aborted' === $r['cf7'] && 0 === $r['mail_count'], 'aborted, no mail' );

// 10. Other CF7 forms / routes are untouched by the gate (route of a non-existent form id is NOT handled by the gate)
$req = new WP_REST_Request( 'POST', '/contact-form-7/v1/contact-forms/999999/feedback' );
$res = rest_get_server()->dispatch( $req );
$results[] = array( 'label' => '10 other form id passes through the gate', 'http' => $res->get_status(), 'cf7' => 'n/a', 'mail_count' => 0, 'to' => array(), 'pass' => 'aborted' !== ( $res->get_data()['status'] ?? '' ), 'expect' => 'not rejected by our gate (CF7 handles it)' );

// ---- restore session_tokens exactly ----
foreach ( $snapshots as $uid => $orig ) {
    if ( null === $orig ) {
        delete_user_meta( $uid, 'session_tokens' );
    } else {
        update_user_meta( $uid, 'session_tokens', $orig );
    }
}

// ---- report ----
$all = true;
foreach ( $results as $r ) {
    $all = $all && $r['pass'];
    printf( "%-4s %-58s http=%s cf7=%-17s mails=%d to=%s  [%s]\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'], $r['http'], $r['cf7'], $r['mail_count'], implode( ',', $r['to'] ), $r['expect'] );
}
echo "\nbody/subject of case 1 (what the representative would receive):\n--- subject: " . $subject_1 . "\n" . $body_1 . "\n---\n";
echo "headers of case 1: [" . ( $results[0]['headers'] ?? '' ) . "]\n";
echo "\nTOTAL: " . ( $all ? 'ALL PASS' : 'FAILURES' ) . " | mails delivered: 0 (pre_wp_mail short-circuit, PHPMailer canary never fired)\n";
echo "session_tokens restored for users: " . implode( ',', array_keys( $snapshots ) ) . "\n";

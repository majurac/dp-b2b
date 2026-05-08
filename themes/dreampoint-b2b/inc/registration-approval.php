<?php
/**
 * B2B Registration Approval
 *
 * Upravljanje odobravanjem B2B korisničkih računa:
 * - Novi korisnici dobivaju status "neodobren" na registraciji
 * - Neodobreni korisnici se preusmjeravaju na /approval-pending
 * - Admin odobrava korisnike iz Users tablice u WP adminu
 * - Email obavijest se šalje korisniku po odobrenju
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Postavi zadani status odobrenja na false pri registraciji korisnika.
 */
function dreampoint_b2b_set_default_approval_status( int $user_id ): void {
    update_user_meta( $user_id, 'approved', false );
}
add_action( 'user_register', 'dreampoint_b2b_set_default_approval_status' );

/**
 * Preusmjeri logirane ali neodobrene korisnike na /approval-pending.
 */
function dreampoint_b2b_restrict_unapproved_access(): void {
    // TEMPORARY — staging/local bypass. Remove before production go-live.
    // Enabled via: define( 'DP_BYPASS_APPROVAL', true ); in wp-config.php.
    if ( defined( 'DP_BYPASS_APPROVAL' ) && DP_BYPASS_APPROVAL ) {
        return;
    }

    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( headers_sent() ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // Primary guard: URL-based check works even if the page does not exist in WP database.
    // is_page() and is_page_template() both return false when the page record is absent,
    // which is the root cause of the redirect loop on local/staging environments.
    $on_approval_page = str_contains( $request_uri, 'approval-pending' )
        || is_page_template( 'template-approval-pending.php' )
        || is_page( 'approval-pending' );

    if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
        error_log( sprintf(
            '[dp_approval] restrict | URI=%s | on_approval_page=%s | tpl=%s | is_page=%s',
            $request_uri,
            $on_approval_page ? 'YES — skipping' : 'NO',
            is_page_template( 'template-approval-pending.php' ) ? 'true' : 'false',
            is_page( 'approval-pending' ) ? 'true' : 'false'
        ) );
    }

    if ( $on_approval_page ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id     = get_current_user_id();
    $is_approved = get_user_meta( $user_id, 'approved', true );

    if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
        error_log( sprintf(
            '[dp_approval] restrict | user_id=%d | approved=%s',
            $user_id,
            $is_approved ? 'true — PASS' : 'false — REDIRECT'
        ) );
    }

    if ( ! $is_approved ) {
        $target = site_url( '/approval-pending' );

        // Final guard: never redirect if we are already at the target URL.
        $current = set_url_scheme( 'http://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . $request_uri );
        if ( rtrim( $target, '/' ) === rtrim( $current, '/' ) ) {
            return;
        }

        wp_safe_redirect( $target );
        exit;
    }
}
add_action( 'template_redirect', 'dreampoint_b2b_restrict_unapproved_access' );

/**
 * Dodaj kolone "Tvrtka" i "Odobreno" u tablicu korisnika u WP adminu.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function dreampoint_b2b_approval_column( array $columns ): array {
    $columns['billing_company'] = __( 'Tvrtka', 'dreampoint-b2b' );
    $columns['user_approval']   = __( 'Odobreno', 'dreampoint-b2b' );
    $columns['dp_bucket']       = __( 'Bucket', 'dreampoint-b2b' );
    return $columns;
}
add_filter( 'manage_users_columns', 'dreampoint_b2b_approval_column' );

/**
 * Prikaži sadržaj kolona Tvrtka i Odobreno.
 *
 * @param string $value
 * @param string $column_name
 * @param int    $user_id
 * @return string
 */
function dreampoint_b2b_approval_column_content( string $value, string $column_name, int $user_id ): string {
    switch ( $column_name ) {
        case 'billing_company':
            return esc_html( get_user_meta( $user_id, 'billing_company', true ) ?: '—' );
        case 'user_approval':
            $approved = get_user_meta( $user_id, 'approved', true );
            return $approved
                ? '<span style="color:#00a32a;font-weight:600">' . __( 'Da', 'dreampoint-b2b' ) . '</span>'
                : '<span style="color:#d63638">' . __( 'Ne', 'dreampoint-b2b' ) . '</span>';

        case 'dp_bucket':
            $bucket_id = (int) get_user_meta( $user_id, 'dp_bucket_id', true );
            if ( ! $bucket_id ) {
                return '—';
            }
            $bucket = get_post( $bucket_id );
            if ( ! $bucket || 'dp_bucket' !== $bucket->post_type ) {
                return '<em style="color:#999">' . esc_html( sprintf(
                    /* translators: %d: bucket post ID */
                    __( 'Obrisano (ID %d)', 'dreampoint-b2b' ),
                    $bucket_id
                ) ) . '</em>';
            }
            $title       = esc_html( $bucket->post_title );
            $access_type = esc_html( get_post_meta( $bucket_id, 'dp_access_type', true ) );
            $label       = $access_type ? ' <span style="color:#666">(' . $access_type . ')</span>' : '';
            $edit_link   = get_edit_post_link( $bucket_id );
            if ( $edit_link ) {
                return '<a href="' . esc_url( $edit_link ) . '">' . $title . '</a>' . $label;
            }
            return $title . $label;
    }
    return $value;
}
add_filter( 'manage_users_custom_column', 'dreampoint_b2b_approval_column_content', 10, 3 );

/**
 * Dodaj Approve/Unapprove akcijski link u retku korisnika.
 *
 * @param array<string, string> $actions
 * @param WP_User               $user
 * @return array<string, string>
 */
function dreampoint_b2b_approval_row_actions( array $actions, WP_User $user ): array {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return $actions;
    }

    $is_approved = get_user_meta( $user->ID, 'approved', true );

    if ( ! $is_approved ) {
        $url = wp_nonce_url(
            admin_url( "users.php?action=dp_approve&user_id={$user->ID}" ),
            "dp_approve_user_{$user->ID}"
        );
        $actions['approve'] = "<a href='" . esc_url( $url ) . "'>" . esc_html__( 'Odobri', 'dreampoint-b2b' ) . "</a>";
    } else {
        $url = wp_nonce_url(
            admin_url( "users.php?action=dp_unapprove&user_id={$user->ID}" ),
            "dp_unapprove_user_{$user->ID}"
        );
        $actions['unapprove'] = "<a href='" . esc_url( $url ) . "'>" . esc_html__( 'Opozovi odobrenje', 'dreampoint-b2b' ) . "</a>";
    }

    return $actions;
}
add_filter( 'user_row_actions', 'dreampoint_b2b_approval_row_actions', 10, 2 );

/**
 * Obradi approve/unapprove akciju uz nonce provjeru.
 */
function dreampoint_b2b_handle_approval_action(): void {
    $action  = $_GET['action']  ?? '';
    $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

    if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( 'dp_approve' === $action ) {
        check_admin_referer( "dp_approve_user_{$user_id}" );
        update_user_meta( $user_id, 'approved', true );
        dreampoint_b2b_send_approval_email( $user_id );
        wp_safe_redirect( admin_url( 'users.php' ) );
        exit;
    }

    if ( 'dp_unapprove' === $action ) {
        check_admin_referer( "dp_unapprove_user_{$user_id}" );
        update_user_meta( $user_id, 'approved', false );
        wp_safe_redirect( admin_url( 'users.php' ) );
        exit;
    }
}
add_action( 'admin_init', 'dreampoint_b2b_handle_approval_action' );

/**
 * Preusmjeri neodobrene korisnike na /approval-pending odmah po prijavi.
 *
 * @param string           $redirect_to
 * @param string           $requested_redirect_to
 * @param WP_User|WP_Error $user
 * @return string
 */
function dreampoint_b2b_login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
    if ( is_wp_error( $user ) || ! isset( $user->ID ) ) {
        return $redirect_to;
    }

    // TEMPORARY — staging/local bypass. Remove before production go-live.
    // Enabled via: define( 'DP_BYPASS_APPROVAL', true ); in wp-config.php.
    if ( defined( 'DP_BYPASS_APPROVAL' ) && DP_BYPASS_APPROVAL ) {
        if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
            error_log( "[dp_approval] login_redirect | user_id={$user->ID} | bypass active → {$redirect_to}" );
        }
        return $redirect_to;
    }

    $is_approved = (bool) get_user_meta( $user->ID, 'approved', true );
    $target      = $is_approved ? $redirect_to : site_url( '/approval-pending' );

    if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
        error_log( sprintf(
            '[dp_approval] login_redirect | user_id=%d | approved=%s | redirect_to=%s',
            $user->ID,
            $is_approved ? 'true' : 'false',
            $target
        ) );
    }

    return $target;
}
add_filter( 'login_redirect', 'dreampoint_b2b_login_redirect', 10, 3 );

/**
 * Pošalji WooCommerce email korisniku po odobrenju računa.
 *
 * @param int $user_id
 */
function dreampoint_b2b_send_approval_email( int $user_id ): void {
    if ( ! function_exists( 'WC' ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $shop_name      = get_bloginfo( 'name' );
    $my_account_url = wc_get_page_permalink( 'myaccount' );

    $email_subject = sprintf( __( 'Vaša registracija je odobrena — %s', 'dreampoint-b2b' ), $shop_name );
    $email_heading = sprintf( __( 'Dobrodošli u %s', 'dreampoint-b2b' ), $shop_name );

    $email_message = '
        <p>' . __( 'Poštovani,', 'dreampoint-b2b' ) . '</p>
        <p>' . sprintf( __( 'Vaša registracija za %s je potvrđena. Od sada možete pristupiti svim sadržajima i funkcionalnostima.', 'dreampoint-b2b' ), $shop_name ) . '</p>
        <p>' . sprintf( __( 'Svojem računu možete pristupiti na: %s', 'dreampoint-b2b' ), '<a href="' . esc_url( $my_account_url ) . '">' . esc_url( $my_account_url ) . '</a>' ) . '</p>
        <p>' . __( 'Hvala što ste odabrali nas!', 'dreampoint-b2b' ) . '</p>
    ';

    $mailer     = WC()->mailer();
    $email_body = $mailer->wrap_message( $email_heading, $email_message );
    $mailer->send( $user->user_email, $email_subject, $email_body );
}

// ============================================================================
// ERP WEBHOOK — Auto-odobrenje korisničkog računa
// ============================================================================
//
// ERP poziva: POST /wp-json/dreampoint-b2b/v1/approve-user
// Auth header: X-DP-ERP-Token: <vrijednost konstante DP_ERP_WEBHOOK_SECRET>
// Body (JSON): { "email": "partner@tvrtka.hr" }  ili  { "user_id": 42 }
//
// Konstanta se dodaje u wp-config.php:
//   define( 'DP_ERP_WEBHOOK_SECRET', 'generirani-tajni-token' );
// ============================================================================

/**
 * Provjeri ERP token iz X-DP-ERP-Token headera.
 *
 * @param WP_REST_Request $request
 * @return true|WP_Error
 */
function dreampoint_b2b_erp_verify_token( WP_REST_Request $request ): bool|WP_Error {
    if ( ! defined( 'DP_ERP_WEBHOOK_SECRET' ) || '' === DP_ERP_WEBHOOK_SECRET ) {
        return new WP_Error(
            'erp_secret_missing',
            'Webhook secret nije konfiguriran na CMS strani.',
            [ 'status' => 500 ]
        );
    }

    $token = $request->get_header( 'X-DP-ERP-Token' );

    if ( ! $token || ! hash_equals( DP_ERP_WEBHOOK_SECRET, $token ) ) {
        return new WP_Error( 'erp_unauthorized', 'Nevažeći token.', [ 'status' => 403 ] );
    }

    return true;
}

/**
 * Callback — odobri B2B korisnika na signal ERP-a.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function dreampoint_b2b_erp_approve_user( WP_REST_Request $request ): WP_REST_Response {
    $logger = wc_get_logger();
    $ctx    = [ 'source' => 'dp-erp-approve' ];

    $user = null;

    $email   = $request->get_param( 'email' );
    $user_id = absint( $request->get_param( 'user_id' ) );

    if ( $email ) {
        $user = get_user_by( 'email', sanitize_email( $email ) );
    } elseif ( $user_id ) {
        $user = get_userdata( $user_id );
    }

    if ( ! $user ) {
        $logger->warning(
            'ERP approve: korisnik nije pronađen. Payload: ' . wp_json_encode( $request->get_params() ),
            $ctx
        );
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Korisnik nije pronađen.' ],
            404
        );
    }

    if ( get_user_meta( $user->ID, 'approved', true ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Korisnik je već odobren.' ],
            200
        );
    }

    update_user_meta( $user->ID, 'approved', true );
    update_user_meta( $user->ID, '_erp_approved_at', current_time( 'mysql' ) );

    dreampoint_b2b_send_approval_email( $user->ID );

    $logger->info( 'ERP approve: odobren korisnik ' . $user->user_email, $ctx );

    return new WP_REST_Response(
        [ 'success' => true, 'message' => 'Korisnik odobren.' ],
        200
    );
}

add_action( 'rest_api_init', function(): void {
    register_rest_route(
        'dreampoint-b2b/v1',
        '/approve-user',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'dreampoint_b2b_erp_approve_user',
            'permission_callback' => 'dreampoint_b2b_erp_verify_token',
            'args'                => [
                'email'   => [
                    'type'              => 'string',
                    'format'            => 'email',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'user_id' => [
                    'type'              => 'integer',
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );
} );

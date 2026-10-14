<?php
/**
 * Contact Form 7: server-side routing of the Contact page form to the partner's assigned sales representative.
 *
 * Owner-approved rules (2026-10-14), docs/active/contact-form-routing-investigation.md and figma-alignment.md section 36:
 *  A. Authenticated, activated partner with a valid representative -> that representative ONLY (no Cc / Bcc, no default recipient).
 *  B. Authenticated partner without an assigned representative     -> temporary fallback mailbox.
 *  C. Representative post missing / not published / empty or invalid email -> fallback mailbox.
 *  D. Guest or invalid / expired authentication                    -> the submission is rejected, no mail.
 *  E. Unexpected resolution failure: authenticated -> fallback; authentication unreliable -> rejected.
 * The recipient is NEVER taken from the request: only from the validated login cookie and server-side account data.
 *
 * Why the cookie is validated by hand: Contact Form 7's front-end script deletes the X-WP-Nonce header of its REST
 * feedback request, so WordPress treats that request as unauthenticated and get_current_user_id() is 0 even for a
 * logged-in partner (CF7's own meta 'current_user_id' has the same limitation). wp_validate_auth_cookie() is the
 * supported WordPress API that verifies the logged_in cookie (HMAC, expiry and session token) without a nonce.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Temporary fallback mailbox (cases B and C). SINGLE configuration point.
 *
 * To replace it with the client's final mailbox, either define it in wp-config.php:
 *     define( 'DREAMPOINT_B2B_CONTACT_FALLBACK_EMAIL', 'kontakt@example.com' );
 * or change the default below. Never expose it as a form field. An invalid value disables sending for the
 * fallback cases (the submission is rejected) instead of guessing another address.
 */
function dreampoint_b2b_contact_fallback_email(): string {
    $email = defined( 'DREAMPOINT_B2B_CONTACT_FALLBACK_EMAIL' )
        ? (string) DREAMPOINT_B2B_CONTACT_FALLBACK_EMAIL
        : 'uncledev82@gmail.com';

    $email = (string) apply_filters( 'dreampoint_b2b_contact_fallback_email', $email );
    $email = sanitize_email( trim( $email ) );

    return is_email( $email ) ? $email : '';
}

/**
 * The Contact page CF7 form: ACF option `contact_form_id` holds either the numeric ID or the CF7 hash.
 */
function dreampoint_b2b_contact_form(): ?WPCF7_ContactForm {
    if ( ! function_exists( 'wpcf7_contact_form' ) || ! function_exists( 'wpcf7_get_contact_form_by_hash' ) ) {
        return null;
    }

    $ref = function_exists( 'get_field' )
        ? get_field( 'contact_form_id', 'option' )
        : get_option( 'options_contact_form_id' );
    $ref = trim( (string) $ref );

    if ( '' === $ref ) {
        return null;
    }

    $form = ctype_digit( $ref ) ? wpcf7_contact_form( (int) $ref ) : wpcf7_get_contact_form_by_hash( $ref );

    return $form instanceof WPCF7_ContactForm ? $form : null;
}

/**
 * Is this CF7 form the Contact page form? $form may be a form object or the id / hash used in a REST route.
 *
 * @param WPCF7_ContactForm|string|int $form Form object, numeric id or hash.
 */
function dreampoint_b2b_is_contact_form( $form ): bool {
    $contact = dreampoint_b2b_contact_form();
    if ( ! $contact ) {
        return false;
    }

    if ( $form instanceof WPCF7_ContactForm ) {
        return $form->id() === $contact->id();
    }

    $form = (string) $form;

    return '' !== $form && ( (string) $contact->id() === $form || ( strlen( $form ) >= 7 && 0 === strpos( $contact->hash(), $form ) ) );
}

/**
 * Authenticated and activated partner ID from the VALIDATED logged_in cookie, or 0.
 * Does not use get_current_user_id() (0 on CF7 REST requests) and trusts no request parameter.
 */
function dreampoint_b2b_contact_authenticated_user_id(): int {
    $user_id = wp_validate_auth_cookie( '', 'logged_in' );

    if ( ! $user_id || ! get_userdata( (int) $user_id ) ) {
        return 0;
    }

    // Same definition of an approved partner as the rest of the project (staff and DP_BYPASS_APPROVAL included).
    if ( ! function_exists( 'dreampoint_b2b_user_is_activated' ) || ! dreampoint_b2b_user_is_activated( (int) $user_id ) ) {
        return 0;
    }

    return (int) $user_id;
}

/**
 * Cheap cross-site guard for a cookie-authenticated request that carries no nonce: a browser always sends Origin /
 * Sec-Fetch-Site on cross-origin POSTs, so reject those. Non-browser clients still need a valid cookie.
 */
function dreampoint_b2b_contact_request_is_same_origin( WP_REST_Request $request ): bool {
    $site = wp_parse_url( home_url(), PHP_URL_HOST );

    $origin = (string) $request->get_header( 'origin' );
    if ( '' !== $origin && 'null' !== $origin ) {
        $origin_host = wp_parse_url( $origin, PHP_URL_HOST );
        if ( ! $origin_host || ! $site || strtolower( $origin_host ) !== strtolower( $site ) ) {
            return false;
        }
    } elseif ( 'null' === $origin ) {
        return false;
    }

    $fetch_site = strtolower( (string) $request->get_header( 'sec_fetch_site' ) );

    return '' === $fetch_site || in_array( $fetch_site, array( 'same-origin', 'none' ), true );
}

/**
 * Recipient for an authenticated partner (cases A, B, C, E).
 *
 * @return array{email:string,source:string,representative_id:int} `email` is '' when not even the fallback is valid.
 */
function dreampoint_b2b_contact_resolve_recipient( int $user_id ): array {
    $fallback = static function ( string $source ): array {
        return array(
            'email'             => dreampoint_b2b_contact_fallback_email(),
            'source'            => $source,
            'representative_id' => 0,
        );
    };

    try {
        $rep_id = absint( get_user_meta( $user_id, 'assigned_komercijalist', true ) );
        if ( ! $rep_id ) {
            return $fallback( 'no_assignment' );
        }

        $rep = get_post( $rep_id );
        if ( ! $rep instanceof WP_Post || 'komercijalist' !== $rep->post_type || 'publish' !== $rep->post_status ) {
            return $fallback( 'invalid_representative' );
        }

        $raw   = trim( (string) get_post_meta( $rep_id, 'email_commercialist', true ) );
        $email = sanitize_email( $raw );
        if ( '' === $raw || $email !== $raw || ! is_email( $email ) ) {
            return $fallback( 'invalid_email' );
        }

        return array(
            'email'             => $email,
            'source'            => 'representative',
            'representative_id' => $rep_id,
        );
    } catch ( Throwable $e ) {
        return $fallback( 'resolution_error' );
    }
}

/**
 * CF7-shaped REST payload for a rejected submission (status `aborted` renders the message in the form; a non-2xx
 * response would make CF7's script fail silently, which is bad for a partner whose session expired on the page).
 *
 * @return WP_REST_Response
 */
function dreampoint_b2b_contact_rejection_response( WPCF7_ContactForm $form, WP_REST_Request $request ): WP_REST_Response {
    $unit_tag = function_exists( 'wpcf7_sanitize_unit_tag' )
        ? wpcf7_sanitize_unit_tag( (string) $request->get_param( '_wpcf7_unit_tag' ) )
        : '';

    return new WP_REST_Response(
        array(
            'contact_form_id'  => $form->id(),
            'status'           => 'aborted',
            'message'          => __( 'Za slanje poruke morate biti prijavljeni. Prijavite se i pokušajte ponovno.', 'dreampoint-b2b' ),
            'posted_data_hash' => '',
            'into'             => '#' . $unit_tag,
            'invalid_fields'   => array(),
        ),
        200
    );
}

/**
 * Gate 1 (earliest, before any CF7 processing, validation or mail): reject guests and invalid authentication on
 * the public CF7 REST feedback route of the Contact form. Other forms are untouched.
 *
 * @param mixed           $result  Pre-dispatch result (null = continue).
 * @param WP_REST_Server  $server  Server.
 * @param WP_REST_Request $request Request.
 * @return mixed
 */
function dreampoint_b2b_contact_rest_gate( $result, $server, $request ) {
    if ( null !== $result || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
        return $result;
    }

    if ( ! preg_match( '#^/contact-form-7/v1/contact-forms/([^/]+)/feedback$#', $request->get_route(), $m ) ) {
        return $result;
    }

    if ( ! dreampoint_b2b_is_contact_form( $m[1] ) ) {
        return $result;
    }

    $form = dreampoint_b2b_contact_form();
    if ( $form && ( ! dreampoint_b2b_contact_request_is_same_origin( $request ) || ! dreampoint_b2b_contact_authenticated_user_id() ) ) {
        return dreampoint_b2b_contact_rejection_response( $form, $request );
    }

    return $result;
}
add_filter( 'rest_pre_dispatch', 'dreampoint_b2b_contact_rest_gate', 10, 3 );

/**
 * Gate 2 (defence in depth, still before the mail is built): abort when authentication or the recipient is not
 * available, and switch off Mail (2) so no autoresponder can ever go to a submitted address.
 *
 * @param WPCF7_ContactForm $form       Form.
 * @param bool              $abort      Abort flag (by reference).
 * @param WPCF7_Submission  $submission Submission.
 */
function dreampoint_b2b_contact_before_send_mail( $form, &$abort, $submission ): void {
    if ( ! dreampoint_b2b_is_contact_form( $form ) ) {
        return;
    }

    $user_id = dreampoint_b2b_contact_authenticated_user_id();
    $route   = $user_id ? dreampoint_b2b_contact_resolve_recipient( $user_id ) : array( 'email' => '' );

    if ( ! $user_id || '' === $route['email'] ) {
        $abort = true;
        $submission->set_response(
            __( 'Poruku trenutno nije moguće poslati. Prijavite se i pokušajte ponovno.', 'dreampoint-b2b' )
        );
        return;
    }

    $mail_2           = (array) $form->prop( 'mail_2' );
    $mail_2['active'] = false;
    $form->set_properties( array( 'mail_2' => $mail_2 ) );
}
add_action( 'wpcf7_before_send_mail', 'dreampoint_b2b_contact_before_send_mail', 10, 3 );

/**
 * Final enforcement on the built mail: the resolved address is the ONLY recipient and every other header that could
 * add recipients (Cc, Bcc, To, Reply-To injected from the DB template) is removed. Runs on the real mail components,
 * so it also wins over anything configured in the CF7 Mail tab.
 *
 * @param array<string,mixed> $components Mail components.
 * @param mixed               $form       Form.
 * @return array<string,mixed>
 */
function dreampoint_b2b_contact_mail_components( $components, $form ) {
    if ( ! $form instanceof WPCF7_ContactForm || ! dreampoint_b2b_is_contact_form( $form ) ) {
        return $components;
    }

    $user_id = dreampoint_b2b_contact_authenticated_user_id();
    $route   = $user_id ? dreampoint_b2b_contact_resolve_recipient( $user_id ) : array( 'email' => '' );

    $components['recipient'] = $route['email']; // '' (no valid recipient) can never deliver.

    $kept = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $components['additional_headers'] ?? '' ) ) as $line ) {
        $line = trim( $line );
        // Only harmless, recipient-free headers survive; recipient headers are dropped.
        if ( '' !== $line && ! preg_match( '/^(to|cc|bcc|reply-to|from|sender|return-path):/i', $line ) ) {
            $kept[] = $line;
        }
    }
    $components['additional_headers'] = implode( "\n", $kept );

    return $components;
}
add_filter( 'wpcf7_mail_components', 'dreampoint_b2b_contact_mail_components', 20, 2 );

/**
 * Server-side mail tags for the Contact form body: [dp_partner_name] [dp_partner_company] [dp_partner_email]
 * [dp_partner_id]. Values come from the validated account, never from the request.
 *
 * @param mixed          $output   Current value (null = not handled).
 * @param string         $name     Tag name.
 * @param bool           $html     HTML mail?
 * @param WPCF7_MailTag  $mail_tag Tag.
 * @return mixed
 */
function dreampoint_b2b_contact_special_mail_tags( $output, $name, $html ) {
    if ( 0 !== strpos( (string) $name, 'dp_partner_' ) || ! dreampoint_b2b_is_contact_form( wpcf7_get_current_contact_form() ?: '' ) ) {
        return $output;
    }

    $user_id = dreampoint_b2b_contact_authenticated_user_id();
    if ( ! $user_id ) {
        return '';
    }

    $user = get_userdata( $user_id );
    switch ( $name ) {
        case 'dp_partner_name':
            $value = trim( (string) get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . (string) get_user_meta( $user_id, 'billing_last_name', true ) );
            $value = '' !== $value ? $value : (string) $user->display_name;
            break;
        case 'dp_partner_company':
            $value = (string) get_user_meta( $user_id, 'billing_company', true );
            break;
        case 'dp_partner_email':
            $value = (string) $user->user_email;
            break;
        case 'dp_partner_id':
            $value = (string) $user_id;
            break;
        default:
            return $output;
    }

    $value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) );

    return $html ? esc_html( $value ) : $value;
}
add_filter( 'wpcf7_special_mail_tags', 'dreampoint_b2b_contact_special_mail_tags', 10, 3 );

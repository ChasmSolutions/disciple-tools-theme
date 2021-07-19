<?php

/**
 * Disciple_Tools_Notifications_Email
 *
 * @see     https://github.com/techcrunch/wp-async-task
 * @class   Disciple_Tools_Notifications_Email
 * @version 0.1.0
 * @since   0.1.0
 * @package Disciple.Tools
 *
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Shared DT email function to be used throughout the DT system. It provides asynchonous mail delivery that does not halt page load.
 *
 * Example:
 * dt_send_email(
 *     'recipients@email.com',
 *     'subject line',
 *     'content of the message'
 * );
 *
 * @param $email
 * @param $subject
 * @param $message_plain_text
 *
 * @return bool|\WP_Error
 */
function dt_send_email( $email, $subject, $message_plain_text ) {

    /**
     * Filter for developement use.
     * If set to true this filter will catch all email traffic from the system and generate a log report,
     * this protects against accidental email sending when working on systems with live data.
     */
    $disabled = apply_filters( 'dt_block_development_emails', false );
    if ( $disabled ) {
        $email = [];
        $email['email'] = $email;
        $email['subject'] = $subject;
        $email['message'] = $message_plain_text;

        dt_write_log( __METHOD__ );
        dt_write_log( $email );

        return true;
    }

    // Sanitize
    $email = sanitize_email( $email );
    $subject = sanitize_text_field( $subject );
    $message_plain_text = sanitize_textarea_field( $message_plain_text );

    $subject = dt_get_option( "dt_email_base_subject" ) . ": " . $subject;

    $user = get_user_by( "email", $email );
    $continue = true;
    // don't send notifications if the user only has "registered" role.
    if ( $user && in_array( "registered", $user->roles ) && sizeof( $user->roles ) === 1 ){
        $continue = false;
    }
    $continue = apply_filters( "dt_sent_email_check", $continue, $email, $subject, $message_plain_text );
    if ( !$continue ){
        return false;
    }
    $is_sent = wp_mail( $email, $subject, $message_plain_text );
//    @todo figure why this async method is slower.
    // Send email
//    try {
//        $send_email = new Disciple_Tools_Notifications_Email();
//        $send_email->launch(
//            [
//                'email'              => $email,
//                'subject'            => $subject,
//                'message_plain_text' => $message_plain_text,
//            ]
//        );
//    } catch ( Exception $e ) {
//        return false;
//    }

    return $is_sent;
}

/**
 * Shared DT email function, similar to dt_send_email, but intended for use for
 * emails that are related to a particular contact record.
 *
 * We want to keep the subject line for all updates related to a particular
 * contact the same. For contact 43, the subject line should always be the
 * same:
 *
 * Subject: Update on contact43
 *
 * That way, Gmail.com will group these emails in a single conversation
 * view. Ideally, we would use the `Message-ID` and `References` email
 * headers to make this more robust and more portable in other email
 * clients, but that would make this code more complex, as we probably
 * would have to store the Message-IDs for previous sent emails.
 *
 * This function also appends a link in the email body to the contact record.
 *
 * @param string $email
 * @param int    $post_id
 * @param string $message_plain_text
 *
 * @return bool|\WP_Error
 */
function dt_send_email_about_post( string $email, int $post_id, string $message_plain_text ) {
    $full_message = $message_plain_text . dt_make_post_email_footer( $post_id );
    $full_message .= dt_make_email_footer();
    $subject = dt_make_post_email_subject( $post_id );

    return dt_send_email(
        $email,
        $subject,
        $full_message
    );
}

function dt_make_post_email_footer( int $post_id ) {
    $post_type = get_post_type( $post_id );
    $contact_url = home_url( '/' ) . $post_type . '/' . $post_id;
    return "\r\n\r\n--\r\n" . __( 'Click here to view or reply', 'disciple_tools' ) . ": $contact_url";
}

function dt_make_post_email_subject( $post_id ) {
    $post_type = get_post_type( $post_id );
    $post_label = Disciple_Tools_Posts::get_label_for_post_type( $post_type, true );
    return sprintf( esc_html_x( 'Update on %1$s #%2$s', 'ex: Update on Contact #323', 'disciple_tools' ), $post_label, $post_id );
}

function dt_make_email_footer() {
    return "\r\n" . __( 'Do not reply directly to this email.', 'disciple_tools' );
}



/**
 * Class Disciple_Tools_Notifications_Email
 */
class Disciple_Tools_Notifications_Email extends Disciple_Tools_Async_Task
{
    protected $action = 'email_notification';

    /**
     * Prepare data for the asynchronous request
     *
     * @throws Exception If for any reason the request should not happen.
     *
     * @param array $data An array of data sent to the hook
     *
     * @return array
     */
    protected function prepare_data( $data ) {
        return $data;
    }

    /**
     * Send email
     */
    public function send_email() {
        /**
         * Nonce validation is done through a custom nonce process inside Disciple_Tools_Async_Task
         * to allow for asynchronous processing. This is a valid nonce but is not recognized by the WP standards checker.
         */
        // WordPress.CSRF.NonceVerification.NoNonceVerification
        // @phpcs:disable
        $id = get_user_by( 'email', sanitize_email( $_POST[0]['email'] ) );
        if ( isset( $_POST['action'] ) ) {
//            ( metadata_exists( 'user', $id->ID, 'default_password_nag' ) || metadata_exists( 'user', $id->ID, 'session_tokens' )
            if ( sanitize_text_field( wp_unslash( $_POST['action'] ) ) == 'dt_async_email_notification' &&
                 isset( $_POST['_nonce'] ) && $this->verify_async_nonce( sanitize_key( wp_unslash( $_POST['_nonce'] ) ) ) ) {

                wp_mail( sanitize_email( $_POST[0]['email'] ), sanitize_text_field( wp_unslash( $_POST[0]['subject'] ) ), sanitize_textarea_field( wp_unslash( $_POST[0]['message_plain_text'] ) ) );

            }
        }
        // phpcs:enable
    }

    /**
     * Run the async task action
     * Used when loading long running process with add_action
     * Not used when launching via the dt_send_email() function.
     */
    protected function run_action() {
        /**
         * Nonce validation is done through a custom nonce process inside Disciple_Tools_Async_Task
         * to allow for asynchronous processing. This is a valid nonce but is not recognized by the WP standards checker.
         */
        // WordPress.CSRF.NonceVerification.NoNonceVerification
        // @phpcs:disable
        $email = isset( $_POST[0]['email'] ) ? sanitize_email( wp_unslash( $_POST[0]['email'] ) ) : '';
        $subject = isset( $_POST[0]['subject'] ) ? sanitize_text_field( wp_unslash( $_POST[0]['subject'] ) ) : '';
        $message_plain_text = isset( $_POST[0]['message_plain_text'] ) ? sanitize_textarea_field( $_POST[0]['message_plain_text'] ) : '';
        // phpcs:enable

        do_action( "dt_async_$this->action", $email, $subject, $message_plain_text );

    }
}

/**
 * This hook function listens for the prepared async process on every page load.
 */
function dt_load_async_email() {
    if ( isset( $_POST['_wp_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wp_nonce'] ) ) ) && isset( $_POST['action'] ) && sanitize_key( wp_unslash( $_POST['action'] ) ) == 'dt_async_email_notification' ) {
        try {
            $send_email = new Disciple_Tools_Notifications_Email();
            $send_email->send_email();
        } catch ( Exception $e ) {
            dt_write_log( __METHOD__ . ': Failed to send email' );
            return new WP_Error( __METHOD__, 'Failed to send email with Async' );
        }
    }
}
add_action( 'init', 'dt_load_async_email' );




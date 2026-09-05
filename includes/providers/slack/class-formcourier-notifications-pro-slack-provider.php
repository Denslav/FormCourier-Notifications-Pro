<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Slack_Provider implements FormCourier_Notifications_Pro_Provider_Interface {
    private FormCourier_Notifications_Pro_Settings $settings;

    public function __construct( FormCourier_Notifications_Pro_Settings $settings ) {
        $this->settings = $settings;
    }

    public function get_id(): string {
        return 'slack';
    }

    public function get_name(): string {
        return 'Slack';
    }

    public function send( FormCourier_Notifications_Pro_Submission $submission, array $context = [] ): array {
        $destination_id = sanitize_key( (string) ( $context['destination'] ?? $this->settings->get_slack_default_destination_id() ) );
        $destination = $this->settings->get_slack_destination( $destination_id );
        $webhook_url = $this->settings->get_slack_destination_webhook_url( $destination_id );

        if ( '' === $webhook_url ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack Incoming Webhook URL is empty for the selected destination.',
                'retryable' => false,
            ];
        }

        if ( 0 !== strpos( $webhook_url, 'https://' ) ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack Incoming Webhook URL must use HTTPS.',
                'retryable' => false,
            ];
        }

        $message = FormCourier_Notifications_Pro_Message_Builder::build(
            $submission,
            $this->settings->get_message_template_for_submission( $submission ),
            [
                'destination_id'   => $destination_id,
                'destination_name' => (string) ( $destination['name'] ?? $destination_id ),
                'channel'          => $this->get_name(),
            ]
        );

        // Message templates are shared with Telegram. Convert the safe HTML
        // representation to readable plain text before sending it to Slack.
        $message = wp_strip_all_tags( $message );
        $message = html_entity_decode( $message, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $message = trim( preg_replace( "/\r\n?|\r/", "\n", (string) $message ) );

        return $this->request( $webhook_url, $message );
    }

    public function send_test( string $destination_id = '' ): array {
        $submission = new FormCourier_Notifications_Pro_Submission(
            'test',
            'Test Form Provider',
            '123',
            'Test Form',
            [
                'Name'    => 'Test User',
                'Email'   => 'test@example.com',
                'Phone'   => '+380 00 000 00 00',
                'Message' => 'Test message from FormCourier Notifications Pro.',
            ]
        );

        return $this->send(
            $submission,
            [ 'destination' => $destination_id ?: $this->settings->get_slack_default_destination_id() ]
        );
    }

    private function request( string $webhook_url, string $message ): array {
        $response = wp_remote_post(
            $webhook_url,
            [
                'timeout'            => 15,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
                'headers'            => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'               => wp_json_encode( [ 'text' => $message ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack network error: ' . sanitize_text_field( $response->get_error_message() ),
                'retryable' => true,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = trim( (string) wp_remote_retrieve_body( $response ) );

        if ( $code >= 200 && $code < 300 && 'ok' === strtolower( $body ) ) {
            return [ 'status' => 'success', 'message' => 'Slack message sent successfully.' ];
        }

        $retry_after = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );
        $retryable = ( 429 === $code || $code >= 500 );

        if ( 400 === $code ) {
            $friendly = 'Slack rejected the webhook request. Check the destination configuration.';
        } elseif ( 403 === $code ) {
            $friendly = 'Slack access denied for this webhook.';
        } elseif ( 404 === $code || 410 === $code ) {
            $friendly = 'Slack webhook was not found or has been disabled.';
        } elseif ( 429 === $code ) {
            $friendly = 'Slack rate limit reached.';
        } elseif ( $code >= 500 ) {
            $friendly = 'Slack is temporarily unavailable.';
        } else {
            $friendly = 'Slack webhook error' . ( $code ? ' ' . $code : '' ) . '.';
        }

        if ( '' !== $body && 'ok' !== strtolower( $body ) ) {
            $friendly .= ' ' . sanitize_text_field( $body );
        }
        if ( $retry_after > 0 ) {
            $friendly .= ' Retry after ' . $retry_after . ' seconds.';
        }

        return [
            'status'      => 'error',
            'message'     => $friendly,
            'error_code'  => $code,
            'retryable'   => $retryable,
            'retry_after' => $retry_after,
        ];
    }
}

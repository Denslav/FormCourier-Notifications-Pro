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
        $destination_id = sanitize_key( (string) ( $context['destination'] ?? '' ) );
        if ( '' === $destination_id && method_exists( $this->settings, 'get_slack_default_destination_id' ) ) {
            $destination_id = sanitize_key( (string) $this->settings->get_slack_default_destination_id() );
        }

        $destination = method_exists( $this->settings, 'get_slack_destination' )
            ? $this->settings->get_slack_destination( $destination_id )
            : [];

        if ( empty( $destination ) || '1' !== ( $destination['enabled'] ?? '1' ) ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack destination is missing or disabled.',
                'retryable' => false,
            ];
        }

        $webhook_url = trim( (string) ( $destination['webhook_url'] ?? '' ) );
        if ( '' === $webhook_url ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack Incoming Webhook URL is empty for the selected destination.',
                'retryable' => false,
            ];
        }

        if ( ! wp_http_validate_url( $webhook_url ) || 0 !== strpos( $webhook_url, 'https://hooks.slack.com/' ) ) {
            return [
                'status'    => 'error',
                'message'   => 'Slack Incoming Webhook URL is invalid.',
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

        $plain_message = $this->to_slack_text( $message );

        // Temporary QA switch for validating the common retry queue.
        // Define FORMCOURIER_NOTIFICATIONS_PRO_SLACK_SIMULATE_FAILURE as true in wp-config.php,
        // submit one form, then set it back to false before the scheduled retry runs.
        if ( defined( 'FORMCOURIER_NOTIFICATIONS_PRO_SLACK_SIMULATE_FAILURE' ) && FORMCOURIER_NOTIFICATIONS_PRO_SLACK_SIMULATE_FAILURE ) {
            return [
                'status'      => 'error',
                'message'     => 'Simulated temporary Slack network error for retry testing.',
                'retryable'   => true,
                'retry_after' => 0,
            ];
        }

        $response = wp_remote_post(
            $webhook_url,
            [
                'timeout'            => 15,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
                'headers'            => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'               => wp_json_encode( [ 'text' => $plain_message ] ),
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
            return [
                'status'  => 'success',
                'message' => 'Slack message sent successfully.',
            ];
        }

        $retry_after = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );
        $retryable   = ( 429 === $code || $code >= 500 );

        if ( 429 === $code ) {
            $message = 'Slack rate limit reached.';
        } elseif ( $code >= 500 ) {
            $message = 'Slack is temporarily unavailable.';
        } elseif ( in_array( $code, [ 400, 403, 404, 410 ], true ) ) {
            $message = 'Slack webhook rejected the request. Check the Incoming Webhook URL and channel access.';
        } else {
            $message = 'Slack API request failed' . ( $code ? ' with HTTP ' . $code : '' ) . '.';
        }

        if ( '' !== $body && 'ok' !== strtolower( $body ) ) {
            $message .= ' ' . sanitize_text_field( $body );
        }
        if ( $retry_after > 0 ) {
            $message .= ' Retry after ' . $retry_after . ' seconds.';
        }

        return [
            'status'      => 'error',
            'message'     => $message,
            'error_code'  => $code,
            'retryable'   => $retryable,
            'retry_after' => $retry_after,
        ];
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

        return $this->send( $submission, [ 'destination' => $destination_id ] );
    }

    private function to_slack_text( string $message ): string {
        $message = str_replace( [ '<br>', '<br/>', '<br />' ], "\n", $message );
        $message = preg_replace( '#</p>\s*#i', "\n\n", $message );
        $message = preg_replace( '#</div>\s*#i', "\n", $message );
        $message = preg_replace( '#<b>(.*?)</b>#is', '*$1*', $message );
        $message = preg_replace( '#<strong>(.*?)</strong>#is', '*$1*', $message );
        $message = preg_replace( '#<i>(.*?)</i>#is', '_$1_', $message );
        $message = preg_replace( '#<em>(.*?)</em>#is', '_$1_', $message );
        $message = wp_strip_all_tags( $message );
        $message = html_entity_decode( $message, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $message ) );
    }
}

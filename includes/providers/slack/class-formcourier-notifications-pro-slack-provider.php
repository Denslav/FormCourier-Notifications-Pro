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

    public function send( FormCourier_Notifications_Pro_Submission $submission, string $message, array $destination = [] ) {
        $destination_id = sanitize_key( (string) ( $destination['id'] ?? '' ) );
        $destination    = $destination_id ? $this->settings->get_slack_destination( $destination_id ) : $destination;
        $destination_name = (string) ( $destination['name'] ?? $destination_id );
        $webhook_url = (string) ( $destination['webhook_url'] ?? '' );

        if ( '' === $webhook_url ) {
            $error = 'Slack Incoming Webhook URL is empty for the selected destination.';
            FormCourier_Notifications_Pro_Logger::log( 'slack', $submission, false, $error, $destination_name, 1 );
            return new WP_Error( 'fcnp_slack_missing_webhook', $error );
        }

        if ( ! wp_http_validate_url( $webhook_url ) || 0 !== strpos( $webhook_url, 'https://hooks.slack.com/' ) ) {
            $error = 'Slack Incoming Webhook URL is invalid.';
            FormCourier_Notifications_Pro_Logger::log( 'slack', $submission, false, $error, $destination_name, 1 );
            return new WP_Error( 'fcnp_slack_invalid_webhook', $error );
        }

        $plain_message = $this->to_slack_text( $message );
        $response = wp_remote_post(
            $webhook_url,
            [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'    => wp_json_encode( [ 'text' => $plain_message ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            $details = 'Slack request failed: ' . $response->get_error_message();
            FormCourier_Notifications_Pro_Logger::log( 'slack', $submission, false, $details, $destination_name, 1 );
            $this->maybe_schedule_retry( $submission, $message, $destination_id, 1, $details );
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = trim( (string) wp_remote_retrieve_body( $response ) );
        if ( $code < 200 || $code >= 300 || 'ok' !== strtolower( $body ) ) {
            $details = sprintf( 'Slack returned HTTP %d%s.', $code, $body ? ': ' . sanitize_text_field( $body ) : '' );
            FormCourier_Notifications_Pro_Logger::log( 'slack', $submission, false, $details, $destination_name, 1 );
            if ( 429 === $code || $code >= 500 ) {
                $this->maybe_schedule_retry( $submission, $message, $destination_id, 1, $details );
            }
            return new WP_Error( 'fcnp_slack_api_error', $details );
        }

        FormCourier_Notifications_Pro_Logger::log( 'slack', $submission, true, 'Slack message sent successfully.', $destination_name, 1 );
        return true;
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
        return trim( preg_replace( "/\n{3,}/", "\n\n", $message ) );
    }

    private function maybe_schedule_retry( FormCourier_Notifications_Pro_Submission $submission, string $message, string $destination_id, int $attempt, string $details ): void {
        if ( class_exists( 'FormCourier_Notifications_Pro_Retry_Queue' ) && $destination_id ) {
            FormCourier_Notifications_Pro_Retry_Queue::schedule( 'slack', $submission, $message, $destination_id, $attempt, $details );
        }
    }
}

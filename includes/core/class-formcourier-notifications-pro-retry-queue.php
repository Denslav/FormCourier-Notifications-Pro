<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Schedules and processes automatic retries for failed notification deliveries.
 *
 * WordPress single cron events are used, so no polling loop runs between attempts.
 */
final class FormCourier_Notifications_Pro_Retry_Queue {
    private const HOOK = 'formcourier_notifications_pro_retry_delivery';

    public static function init(): void {
        add_action( self::HOOK, [ __CLASS__, 'process' ], 10, 1 );
    }

    /**
     * Schedule the next automatic retry when the provider says the failure is temporary.
     *
     * Attempts are counted including the original delivery:
     * 1 -> retry in 1 minute, 2 -> 5 minutes, 3 -> 15 minutes, 4 -> stop auto retrying.
     */
    public static function schedule( string $log_id, array $result, int $attempts ): bool {
        $log_id = sanitize_text_field( $log_id );
        if ( '' === $log_id || empty( $result['retryable'] ) ) {
            return false;
        }

        $delay = self::delay_after_attempt( $attempts );
        if ( $delay <= 0 ) {
            FormCourier_Notifications_Pro_Logger::update(
                $log_id,
                [
                    'next_retry_at'    => '',
                    'auto_retry_state' => 'exhausted',
                ]
            );
            return false;
        }

        $provider_retry_after = absint( $result['retry_after'] ?? 0 );
        if ( $provider_retry_after > $delay ) {
            $delay = $provider_retry_after;
        }

        $args = [ $log_id ];
        $existing = wp_next_scheduled( self::HOOK, $args );
        if ( false !== $existing ) {
            return true;
        }

        $timestamp = time() + $delay;
        $scheduled = wp_schedule_single_event( $timestamp, self::HOOK, $args );
        if ( is_wp_error( $scheduled ) || false === $scheduled ) {
            FormCourier_Notifications_Pro_Logger::update(
                $log_id,
                [
                    'next_retry_at'    => '',
                    'auto_retry_state' => 'schedule_failed',
                ]
            );
            return false;
        }

        FormCourier_Notifications_Pro_Logger::update(
            $log_id,
            [
                'next_retry_at'    => wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() ),
                'auto_retry_state' => 'scheduled',
            ]
        );

        return true;
    }

    public static function process( string $log_id ): void {
        self::retry_log( $log_id, true );
    }

    /**
     * Retry one failed log entry. Used by both WP-Cron and the manual Retry action.
     */
    public static function retry_log( string $log_id, bool $automatic = false ): array {
        $log_id = sanitize_text_field( $log_id );
        $log = FormCourier_Notifications_Pro_Logger::get( $log_id );
        $payload = isset( $log['retry_payload'] ) && is_array( $log['retry_payload'] ) ? $log['retry_payload'] : [];

        if ( empty( $log ) || empty( $payload ) || ! in_array( sanitize_key( (string) ( $log['channel_id'] ?? '' ) ), [ 'telegram', 'slack' ], true ) ) {
            return [
                'status'    => 'error',
                'message'   => 'This log entry cannot be retried.',
                'retryable' => false,
            ];
        }

        if ( ! $automatic ) {
            wp_clear_scheduled_hook( self::HOOK, [ $log_id ] );
        }

        $submission = new FormCourier_Notifications_Pro_Submission(
            (string) ( $payload['provider_key'] ?? '' ),
            (string) ( $payload['provider_label'] ?? '' ),
            (string) ( $payload['form_id'] ?? '' ),
            (string) ( $payload['form_name'] ?? '' ),
            isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : [],
            isset( $payload['field_aliases'] ) && is_array( $payload['field_aliases'] ) ? $payload['field_aliases'] : [],
            (string) ( $payload['submission_id'] ?? '' )
        );
        $submission->page_url = esc_url_raw( (string) ( $payload['page_url'] ?? '' ) );
        $submission->referrer = esc_url_raw( (string) ( $payload['referrer'] ?? '' ) );
        if ( ! empty( $payload['submitted_at'] ) ) {
            $submission->submitted_at = sanitize_text_field( (string) $payload['submitted_at'] );
        }

        $destination_id = sanitize_key( (string) ( $log['destination_id'] ?? '' ) );
        $channel_id = sanitize_key( (string) ( $log['channel_id'] ?? '' ) );
        $settings = new FormCourier_Notifications_Pro_Settings();
        if ( 'slack' === $channel_id ) {
            $provider = new FormCourier_Notifications_Pro_Slack_Provider( $settings );
        } else {
            $provider = new FormCourier_Notifications_Pro_Telegram_Provider( $settings );
        }
        $result = $provider->send( $submission, [ 'destination' => $destination_id ] );

        $status   = (string) ( $result['status'] ?? 'error' );
        $message  = (string) ( $result['message'] ?? '' );
        $attempts = max( 1, absint( $log['attempts'] ?? 1 ) ) + 1;

        $changes = [
            'time'              => current_time( 'mysql' ),
            'status'            => $status,
            'message'           => $message,
            'http_status'       => absint( $result['http_status'] ?? $result['error_code'] ?? 0 ),
            'last_error'        => 'success' === $status ? '' : $message,
            'provider_response' => sanitize_text_field( (string) ( $result['provider_response'] ?? '' ) ),
            'retryable'         => ! empty( $result['retryable'] ),
            'retry_after'       => absint( $result['retry_after'] ?? 0 ),
            'attempts'          => $attempts,
            'next_retry_at'     => '',
            'auto_retry_state'  => $automatic ? 'processing' : 'manual',
        ];

        if ( 'success' === $status ) {
            $changes['retry_payload'] = [];
            $changes['auto_retry_state'] = 'completed';
            FormCourier_Notifications_Pro_Logger::update( $log_id, $changes );
            wp_clear_scheduled_hook( self::HOOK, [ $log_id ] );
            return array_merge( $result, [ 'attempts' => $attempts ] );
        }

        FormCourier_Notifications_Pro_Logger::update( $log_id, $changes );

        if ( ! empty( $result['retryable'] ) ) {
            if ( ! self::schedule( $log_id, $result, $attempts ) ) {
                if ( self::delay_after_attempt( $attempts ) <= 0 ) {
                    FormCourier_Notifications_Pro_Logger::update(
                        $log_id,
                        [ 'auto_retry_state' => 'exhausted', 'next_retry_at' => '' ]
                    );
                }
            }
        } else {
            FormCourier_Notifications_Pro_Logger::update(
                $log_id,
                [ 'auto_retry_state' => 'not_retryable', 'next_retry_at' => '' ]
            );
        }

        return array_merge( $result, [ 'attempts' => $attempts ] );
    }

    public static function unschedule( string $log_id ): void {
        $log_id = sanitize_text_field( $log_id );
        if ( '' !== $log_id ) {
            wp_clear_scheduled_hook( self::HOOK, [ $log_id ] );
        }
    }

    private static function delay_after_attempt( int $attempts ): int {
        switch ( $attempts ) {
            case 1:
                return MINUTE_IN_SECONDS;
            case 2:
                return 5 * MINUTE_IN_SECONDS;
            case 3:
                return 15 * MINUTE_IN_SECONDS;
            default:
                return 0;
        }
    }
}

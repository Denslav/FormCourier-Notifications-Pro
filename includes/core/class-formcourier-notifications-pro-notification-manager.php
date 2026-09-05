<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Notification_Manager {
    private FormCourier_Notifications_Pro_Settings $settings;
    private FormCourier_Notifications_Pro_Routing_Engine $routing;
    /** @var array<string,FormCourier_Notifications_Pro_Provider_Interface> */
    private array $providers = [];

    public function __construct( FormCourier_Notifications_Pro_Settings $settings, FormCourier_Notifications_Pro_Routing_Engine $routing ) {
        $this->settings = $settings;
        $this->routing  = $routing;
    }

    public function register_provider( FormCourier_Notifications_Pro_Provider_Interface $provider ): void {
        $provider_id = sanitize_key( $provider->get_id() );
        if ( '' !== $provider_id ) {
            $this->providers[ $provider_id ] = $provider;
        }
    }

    public function handle( FormCourier_Notifications_Pro_Submission $submission ): void {
        if ( ! $this->settings->is_form_provider_enabled( $submission->provider_key ) ) {
            return;
        }

        $submission = apply_filters( 'formcourier_notifications_pro_submission', $submission );
        if ( ! $submission instanceof FormCourier_Notifications_Pro_Submission ) {
            return;
        }

        $this->settings->remember_form( $submission );

        if ( $this->is_duplicate( $submission ) ) {
            return;
        }

        $routes = $this->routing->resolve( $submission );
        if ( empty( $routes ) ) {
            return;
        }

        do_action( 'formcourier_notifications_pro_before_send', $submission, $routes );

        foreach ( $routes as $route ) {
            if ( ! is_array( $route ) ) { continue; }
            $provider_id    = sanitize_key( (string) ( $route['provider'] ?? '' ) );
            $destination_id = sanitize_key( (string) ( $route['destination'] ?? '' ) );

            if ( ! isset( $this->providers[ $provider_id ] ) ) {
                continue;
            }

            $provider = $this->providers[ $provider_id ];
            $result   = $provider->send( $submission, [ 'destination' => $destination_id ] );
            $destination = 'slack' === $provider_id
                ? $this->settings->get_slack_destination( $destination_id )
                : $this->settings->get_destination( $destination_id );
            $destination_name = (string) ( $destination['name'] ?? $destination_id );

            $status  = (string) ( $result['status'] ?? 'error' );
            $message = (string) ( $result['message'] ?? '' );
            $log_entry = [
                'channel'           => $provider->get_name(),
                'channel_id'        => $provider->get_id(),
                'destination'       => $destination_name,
                'destination_id'    => $destination_id,
                'provider'          => $submission->provider_label,
                'provider_key'      => $submission->provider_key,
                'form_id'           => $submission->form_id,
                'form_name'         => $submission->form_name,
                'status'            => $status,
                'message'           => $message,
                'http_status'       => absint( $result['http_status'] ?? $result['error_code'] ?? 0 ),
                'last_error'        => 'success' === $status ? '' : $message,
                'provider_response' => sanitize_text_field( (string) ( $result['provider_response'] ?? '' ) ),
                'retryable'         => ! empty( $result['retryable'] ),
                'retry_after'       => absint( $result['retry_after'] ?? 0 ),
                'submission_id'     => $submission->submission_id,
                'submitted_at'      => $submission->submitted_at,
                'attempts'          => 1,
            ];

            // Store the minimum submission payload only for failed deliveries so
            // an administrator can retry them later without asking the visitor
            // to submit the form again.
            if ( 'success' !== $status ) {
                $log_entry['retry_payload'] = [
                    'provider_key'   => $submission->provider_key,
                    'provider_label' => $submission->provider_label,
                    'form_id'        => $submission->form_id,
                    'form_name'      => $submission->form_name,
                    'fields'         => $submission->fields,
                    'field_aliases'  => $submission->field_aliases,
                    'submission_id'  => $submission->submission_id,
                    'page_url'       => $submission->page_url,
                    'referrer'       => $submission->referrer,
                    'submitted_at'   => $submission->submitted_at,
                ];
            }

            $log_id = FormCourier_Notifications_Pro_Logger::add( $log_entry );

            if ( 'success' !== $status ) {
                if ( ! empty( $result['retryable'] ) ) {
                    FormCourier_Notifications_Pro_Retry_Queue::schedule( $log_id, $result, 1 );
                } else {
                    FormCourier_Notifications_Pro_Logger::update(
                        $log_id,
                        [ 'auto_retry_state' => 'not_retryable', 'next_retry_at' => '' ]
                    );
                }
            }

            do_action( 'formcourier_notifications_pro_after_send', $submission, $provider, $result );
        }
    }

    private function is_duplicate( FormCourier_Notifications_Pro_Submission $submission ): bool {
        if ( '' === $submission->submission_id ) { return false; }
        $key = 'fcnp_sent_' . md5( $submission->provider_key . '|' . $submission->form_id . '|' . $submission->submission_id );
        if ( get_transient( $key ) ) { return true; }
        set_transient( $key, '1', MINUTE_IN_SECONDS );
        return false;
    }
}

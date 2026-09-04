<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Submission {
    public string $provider_key;
    public string $provider_label;
    public string $form_id;
    public string $form_name;
    public array $fields;
    public array $field_aliases;
    public string $page_url;
    public string $referrer;
    public string $submission_id;
    public string $submitted_at;

    public function __construct( string $provider_key, string $provider_label, $form_id, string $form_name, array $fields, array $field_aliases = [], $submission_id = '' ) {
        $this->provider_key   = sanitize_key( $provider_key );
        $this->provider_label = sanitize_text_field( $provider_label );
        $this->form_id        = sanitize_text_field( (string) $form_id );
        $this->form_name      = sanitize_text_field( $form_name );
        $this->fields         = FormCourier_Notifications_Pro_Sanitizer::fields( $fields );
        $this->field_aliases  = FormCourier_Notifications_Pro_Sanitizer::fields( $field_aliases );
        $this->submission_id   = sanitize_text_field( (string) $submission_id );
        $this->submitted_at    = current_time( 'mysql' );
        $this->page_url       = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $this->referrer       = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
    }
}

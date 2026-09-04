<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_WPForms_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'wpforms_process_complete', [ $this, 'handle' ], 20, 4 );
    }

    public function handle( array $fields, array $entry, array $form_data, int $entry_id ): void {
        $data    = [];
        $aliases = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['id'] ) ) {
                continue;
            }

            $value = FormCourier_Notifications_Pro_Sanitizer::value( $field['value'] ?? ( $field['value_raw'] ?? '' ) );
            if ( '' === $value ) {
                continue;
            }

            $field_id = (string) absint( $field['id'] );
            $label    = ! empty( $field['name'] ) ? sanitize_text_field( (string) $field['name'] ) : 'Field ' . $field_id;

            // Human-readable fields are used by {all_fields}.
            $data[ $label ] = $value;

            // Numeric field IDs remain available to {field:ID} without being displayed twice.
            $aliases[ $field_id ] = $value;
            $aliases[ 'field_' . $field_id ] = $value;
            $aliases[ $label ] = $value;
        }

        $form_id   = ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
        $form_name = ! empty( $form_data['settings']['form_title'] ) ? sanitize_text_field( (string) $form_data['settings']['form_title'] ) : '';

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'wpforms',
                'WPForms',
                $form_id,
                $form_name,
                $data,
                $aliases,
                $entry_id
            )
        );
    }
}

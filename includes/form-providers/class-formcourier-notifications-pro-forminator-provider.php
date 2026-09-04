<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Forminator_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'forminator_custom_form_submit_before_set_fields', [ $this, 'handle' ], 20, 3 );
    }

    public function handle( $entry, $form_id, $field_data_array ): void {
        if ( empty( $field_data_array ) || ! is_array( $field_data_array ) ) {
            return;
        }

        $fields  = [];
        $aliases = [];

        foreach ( $field_data_array as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $field_name = $this->get_field_name( $field );
            if ( '' === $field_name ) {
                continue;
            }

            $value = FormCourier_Notifications_Pro_Sanitizer::value( $field['value'] ?? '', ', ' );
            if ( '' === $value ) {
                continue;
            }

            $fields[ $field_name ]  = $value;
            $aliases[ $field_name ] = $value;
        }

        if ( empty( $fields ) ) {
            return;
        }

        $form_id = absint( $form_id );

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'forminator',
                'Forminator',
                $form_id,
                $this->get_form_title( $form_id ),
                $fields,
                $aliases,
                is_object( $entry ) && isset( $entry->entry_id ) ? $entry->entry_id : ( is_array( $entry ) && isset( $entry['entry_id'] ) ? $entry['entry_id'] : '' )
            )
        );
    }

    private function get_field_name( array $field ): string {
        foreach ( [ 'name', 'field_name', 'element_id', 'slug' ] as $key ) {
            if ( ! empty( $field[ $key ] ) ) {
                return FormCourier_Notifications_Pro_Sanitizer::key( $field[ $key ] );
            }
        }
        return '';
    }

    private function get_form_title( int $form_id ): string {
        if ( ! class_exists( 'Forminator_API' ) || ! method_exists( 'Forminator_API', 'get_form' ) ) {
            return '';
        }

        try {
            $form = Forminator_API::get_form( $form_id );
            if ( is_object( $form ) && ! empty( $form->settings['formName'] ) ) {
                return sanitize_text_field( (string) $form->settings['formName'] );
            }
            if ( is_object( $form ) && ! empty( $form->name ) ) {
                return sanitize_text_field( (string) $form->name );
            }
        } catch ( Throwable $exception ) {
            return '';
        }

        return '';
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_GravityForms_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'gform_after_submission', [ $this, 'handle' ], 20, 2 );
    }

    public function handle( $entry, $form ): void {
        if ( ! is_array( $entry ) || ! is_array( $form ) ) {
            return;
        }

        $data    = [];
        $aliases = [];

        if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
            foreach ( $form['fields'] as $field ) {
                $field_id = $this->field_id( $field );
                if ( '' === $field_id ) {
                    continue;
                }

                $field_label = $this->field_label( $field, $field_id );
                $inputs      = $this->field_inputs( $field );
                $parts       = [];

                if ( ! empty( $inputs ) ) {
                    foreach ( $inputs as $input ) {
                        $input_id = $this->input_value( $input, 'id' );
                        if ( '' === $input_id || ! isset( $entry[ $input_id ] ) ) {
                            continue;
                        }

                        $value = FormCourier_Notifications_Pro_Sanitizer::value( $entry[ $input_id ] );
                        if ( '' === $value ) {
                            continue;
                        }

                        $input_label = $this->input_value( $input, 'label' );
                        $display     = '' !== $input_label ? $field_label . ' - ' . $input_label : $field_label;

                        $data[ $display ]       = $value;
                        $aliases[ $input_id ]   = $value;
                        $aliases[ $display ]    = $value;
                        $parts[]                = $value;
                    }
                }

                $value = isset( $entry[ $field_id ] )
                    ? FormCourier_Notifications_Pro_Sanitizer::value( $entry[ $field_id ] )
                    : implode( ' ', $parts );

                if ( '' !== $value ) {
                    // Only add the parent field when it was not already represented by its sub-inputs.
                    if ( empty( $parts ) ) {
                        $data[ $field_label ] = $value;
                    }
                    $aliases[ $field_id ]    = $value;
                    $aliases[ $field_label ] = $value;
                } elseif ( ! empty( $parts ) ) {
                    $aliases[ $field_id ]    = implode( ' ', $parts );
                    $aliases[ $field_label ] = implode( ' ', $parts );
                }
            }
        }

        $form_id   = ! empty( $form['id'] ) ? absint( $form['id'] ) : ( ! empty( $entry['form_id'] ) ? absint( $entry['form_id'] ) : 0 );
        $form_name = ! empty( $form['title'] ) ? sanitize_text_field( (string) $form['title'] ) : '';

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'gravity_forms',
                'Gravity Forms',
                $form_id,
                $form_name,
                $data,
                $aliases,
                $entry['id'] ?? ''
            )
        );
    }

    private function field_id( $field ): string {
        if ( is_object( $field ) && isset( $field->id ) ) {
            return (string) $field->id;
        }
        if ( is_array( $field ) && isset( $field['id'] ) ) {
            return (string) $field['id'];
        }
        return '';
    }

    private function field_label( $field, string $field_id ): string {
        if ( is_object( $field ) && ! empty( $field->label ) ) {
            return sanitize_text_field( (string) $field->label );
        }
        if ( is_array( $field ) && ! empty( $field['label'] ) ) {
            return sanitize_text_field( (string) $field['label'] );
        }
        return 'Field ' . $field_id;
    }

    private function field_inputs( $field ): array {
        if ( is_object( $field ) && ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
            return $field->inputs;
        }
        if ( is_array( $field ) && ! empty( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
            return $field['inputs'];
        }
        return [];
    }

    private function input_value( $input, string $key ): string {
        if ( is_array( $input ) && isset( $input[ $key ] ) ) {
            return sanitize_text_field( (string) $input[ $key ] );
        }
        if ( is_object( $input ) && isset( $input->{$key} ) ) {
            return sanitize_text_field( (string) $input->{$key} );
        }
        return '';
    }
}

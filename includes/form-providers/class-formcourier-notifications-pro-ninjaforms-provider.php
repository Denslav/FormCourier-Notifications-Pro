<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_NinjaForms_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'ninja_forms_after_submission', [ $this, 'handle' ], 20, 1 );
    }

    public function handle( $form_data ): void {
        if ( ! is_array( $form_data ) ) {
            return;
        }

        $fields  = [];
        $aliases = [];

        if ( ! empty( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
            foreach ( $form_data['fields'] as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $value = $field['value'] ?? '';
                $value = $this->normalize_value( $value );

                if ( '' === $value ) {
                    continue;
                }

                $id    = ! empty( $field['id'] ) ? (string) absint( $field['id'] ) : '';
                $key   = ! empty( $field['key'] ) ? sanitize_text_field( (string) $field['key'] ) : '';
                $name  = ! empty( $field['name'] ) ? sanitize_text_field( (string) $field['name'] ) : '';
                $label = ! empty( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : '';

                if ( '' === $label && ! empty( $field['settings']['label'] ) ) {
                    $label = sanitize_text_field( (string) $field['settings']['label'] );
                }

                if ( '' === $key && ! empty( $field['settings']['key'] ) ) {
                    $key = sanitize_text_field( (string) $field['settings']['key'] );
                }

                $display_key = $label ?: ( $name ?: ( $key ?: ( $id ? 'Field ' . $id : '' ) ) );

                if ( '' === $display_key ) {
                    continue;
                }

                // Only one human-readable entry is used by {all_fields}.
                $fields[ $display_key ] = $value;

                // Technical identifiers remain available for {field:...} placeholders.
                foreach ( [ $key, $name, $id, $id ? 'field_' . $id : '' ] as $alias ) {
                    if ( '' !== $alias ) {
                        $aliases[ $alias ] = $value;
                    }
                }
            }
        }

        $form_id = ! empty( $form_data['form_id'] )
            ? absint( $form_data['form_id'] )
            : ( ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0 );

        $form_name = '';
        if ( ! empty( $form_data['settings']['title'] ) ) {
            $form_name = sanitize_text_field( (string) $form_data['settings']['title'] );
        } elseif ( ! empty( $form_data['settings']['form_title'] ) ) {
            $form_name = sanitize_text_field( (string) $form_data['settings']['form_title'] );
        } elseif ( ! empty( $form_data['title'] ) ) {
            $form_name = sanitize_text_field( (string) $form_data['title'] );
        }

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'ninja_forms',
                'Ninja Forms',
                $form_id,
                $form_name,
                $fields,
                $aliases,
                $form_data['actions']['save']['sub_id'] ?? ( $form_data['sub_id'] ?? '' )
            )
        );
    }

    private function normalize_value( $value ): string {
        return FormCourier_Notifications_Pro_Sanitizer::value( $value, ', ' );
    }
}

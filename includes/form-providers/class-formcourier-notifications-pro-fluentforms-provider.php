<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_FluentForms_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'fluentform/submission_inserted', [ $this, 'handle' ], 20, 3 );
    }

    public function handle( $submission_id, $form_data, $form ): void {
        if ( ! is_array( $form_data ) || empty( $form_data ) ) {
            return;
        }

        $fields  = [];
        $aliases = [];

        foreach ( $form_data as $key => $value ) {
            $key = FormCourier_Notifications_Pro_Sanitizer::key( $key );
            if ( '' === $key || 0 === strpos( $key, '_' ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $flat = $this->flatten_compound_field( $key, $value );
                foreach ( $flat as $flat_key => $flat_value ) {
                    $fields[ $flat_key ]  = $flat_value;
                    $aliases[ $flat_key ] = $flat_value;
                }

                // Keep the parent value only as a backwards-compatible alias.
                // It must not be added to $fields, otherwise {all_fields} prints
                // both names[first_name] / names[last_name] and the combined names value.
                $parent_value = FormCourier_Notifications_Pro_Sanitizer::value( $value );
                if ( '' !== $parent_value ) {
                    $aliases[ $key ] = $parent_value;
                }
                continue;
            }

            $value = FormCourier_Notifications_Pro_Sanitizer::value( $value );
            if ( '' === $value ) {
                continue;
            }

            $fields[ $key ]  = $value;
            $aliases[ $key ] = $value;
        }

        if ( empty( $fields ) ) {
            return;
        }

        $form_id = is_object( $form ) && isset( $form->id )
            ? absint( $form->id )
            : ( is_array( $form ) && isset( $form['id'] ) ? absint( $form['id'] ) : 0 );

        $form_name = is_object( $form ) && isset( $form->title )
            ? sanitize_text_field( (string) $form->title )
            : ( is_array( $form ) && isset( $form['title'] ) ? sanitize_text_field( (string) $form['title'] ) : '' );

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'fluent_forms',
                'Fluent Forms',
                $form_id,
                $form_name,
                $fields,
                $aliases,
                $submission_id
            )
        );
    }
    /** @return array<string,string> */
    private function flatten_compound_field( string $parent, array $value ): array {
        $out = [];
        foreach ( $value as $child_key => $child_value ) {
            $child_key = FormCourier_Notifications_Pro_Sanitizer::key( $child_key );
            if ( '' === $child_key ) { continue; }
            $technical_key = $parent . '[' . $child_key . ']';

            if ( is_array( $child_value ) ) {
                foreach ( $this->flatten_compound_field( $technical_key, $child_value ) as $nested_key => $nested_value ) {
                    $out[ $nested_key ] = $nested_value;
                }
                continue;
            }

            $child_value = FormCourier_Notifications_Pro_Sanitizer::value( $child_value );
            if ( '' !== $child_value ) { $out[ $technical_key ] = $child_value; }
        }
        return $out;
    }

}

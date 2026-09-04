<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_CF7_Provider {
    private FormCourier_Notifications_Pro_Notification_Manager $manager;

    public function __construct( FormCourier_Notifications_Pro_Notification_Manager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'wpcf7_before_send_mail', [ $this, 'handle' ] );
    }

    public function handle( $form ): void {
        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        $posted = $submission->get_posted_data();
        if ( ! is_array( $posted ) || empty( $posted ) ) {
            return;
        }

        $fields  = [];
        $aliases = [];

        foreach ( $posted as $key => $value ) {
            $key = FormCourier_Notifications_Pro_Sanitizer::key( $key );
            if ( '' === $key || 0 === strpos( $key, '_' ) || 'g-recaptcha-response' === $key ) {
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

        $form_id   = is_object( $form ) && method_exists( $form, 'id' ) ? (int) $form->id() : 0;
        $form_name = is_object( $form ) && method_exists( $form, 'title' ) ? (string) $form->title() : '';

        $this->manager->handle(
            new FormCourier_Notifications_Pro_Submission(
                'contact_form_7',
                'Contact Form 7',
                $form_id,
                $form_name,
                $fields,
                $aliases
            )
        );
    }
}

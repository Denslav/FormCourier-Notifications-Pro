<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Plugin {
    private static ?self $instance = null;
    private bool $initialized = false;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate(): void {
        if ( false === get_option( 'formcourier_notifications_pro_settings', false ) ) {
            add_option( 'formcourier_notifications_pro_settings', FormCourier_Notifications_Pro_Settings::defaults(), '', false );
        }
    }

    public function init(): void {
        if ( $this->initialized ) {
            return;
        }
        $this->initialized = true;

        FormCourier_Notifications_Pro_Retry_Queue::init();
        FormCourier_Notifications_Pro_Log_Cleanup::init();

        $settings = new FormCourier_Notifications_Pro_Settings();
        $routing  = new FormCourier_Notifications_Pro_Routing_Engine( $settings );
        $manager  = new FormCourier_Notifications_Pro_Notification_Manager( $settings, $routing );

        $manager->register_provider( new FormCourier_Notifications_Pro_Telegram_Provider( $settings ) );
        $manager->register_provider( new FormCourier_Notifications_Pro_Slack_Provider( $settings ) );
        $settings->init();

        $form_providers = [
            new FormCourier_Notifications_Pro_CF7_Provider( $manager ),
            new FormCourier_Notifications_Pro_WPForms_Provider( $manager ),
            new FormCourier_Notifications_Pro_FluentForms_Provider( $manager ),
            new FormCourier_Notifications_Pro_Forminator_Provider( $manager ),
            new FormCourier_Notifications_Pro_NinjaForms_Provider( $manager ),
            new FormCourier_Notifications_Pro_GravityForms_Provider( $manager ),
        ];

        foreach ( $form_providers as $provider ) {
            $provider->init();
        }
    }
}

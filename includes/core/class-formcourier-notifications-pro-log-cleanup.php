<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Keeps delivery logs within the optional 30-day retention window.
 */
final class FormCourier_Notifications_Pro_Log_Cleanup {
    private const HOOK = 'formcourier_notifications_pro_cleanup_logs';

    public static function init(): void {
        add_action( self::HOOK, [ __CLASS__, 'run' ] );
        self::sync_schedule();
    }

    public static function run(): void {
        if ( ! self::is_enabled() ) {
            self::unschedule();
            return;
        }

        FormCourier_Notifications_Pro_Logger::prune_older_than_days( 30 );
    }

    public static function sync_schedule(): void {
        if ( self::is_enabled() ) {
            if ( false === wp_next_scheduled( self::HOOK ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
            }
            return;
        }

        self::unschedule();
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    private static function is_enabled(): bool {
        $settings = get_option( 'formcourier_notifications_pro_settings', [] );
        return is_array( $settings ) && '1' === (string) ( $settings['auto_cleanup_logs_30_days'] ?? '0' );
    }
}

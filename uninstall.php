<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Remove pending automatic retry cron events before deleting logs.
$logs = get_option( 'formcourier_notifications_pro_logs', [] );
if ( is_array( $logs ) ) {
    foreach ( $logs as $log ) {
        if ( is_array( $log ) && ! empty( $log['id'] ) ) {
            wp_clear_scheduled_hook( 'formcourier_notifications_pro_retry_delivery', [ sanitize_text_field( (string) $log['id'] ) ] );
        }
    }
}

$formcourier_notifications_pro_settings = get_option( 'formcourier_notifications_pro_settings', [] );
$formcourier_notifications_pro_delete_data = is_array( $formcourier_notifications_pro_settings ) && ! empty( $formcourier_notifications_pro_settings['delete_data_on_uninstall'] ) && '1' === (string) $formcourier_notifications_pro_settings['delete_data_on_uninstall'];

if ( $formcourier_notifications_pro_delete_data ) {
    delete_option( 'formcourier_notifications_pro_settings' );
    delete_option( 'formcourier_notifications_pro_logs' );
    delete_option( 'formcourier_notifications_pro_known_forms' );
    delete_option( 'formcourier_notifications_pro_known_fields' );
}

delete_transient( 'formcourier_notifications_pro_test_error' );

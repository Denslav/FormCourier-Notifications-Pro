<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Logger {
    private const OPTION = 'formcourier_notifications_pro_logs';

    public static function add( array $entry ): string {
        $logs = get_option( self::OPTION, [] );
        if ( ! is_array( $logs ) ) { $logs = []; }

        $id = isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '';
        if ( '' === $id ) {
            $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'fcnp_', true );
        }

        $entry = array_merge(
            [
                'id'       => $id,
                'time'     => current_time( 'mysql' ),
                'attempts' => 1,
            ],
            $entry
        );

        array_unshift( $logs, $entry );
        update_option( self::OPTION, array_slice( $logs, 0, 100 ), false );

        return $id;
    }

    public static function all(): array {
        $logs = get_option( self::OPTION, [] );
        return is_array( $logs ) ? $logs : [];
    }

    public static function get( string $id ): array {
        foreach ( self::all() as $entry ) {
            if ( is_array( $entry ) && hash_equals( (string) ( $entry['id'] ?? '' ), $id ) ) {
                return $entry;
            }
        }
        return [];
    }

    public static function update( string $id, array $changes ): bool {
        $logs = self::all();
        $updated = false;

        foreach ( $logs as $index => $entry ) {
            if ( ! is_array( $entry ) || ! hash_equals( (string) ( $entry['id'] ?? '' ), $id ) ) {
                continue;
            }
            $logs[ $index ] = array_merge( $entry, $changes );
            $updated = true;
            break;
        }

        if ( $updated ) {
            update_option( self::OPTION, array_slice( $logs, 0, 100 ), false );
        }

        return $updated;
    }

    public static function clear(): void { delete_option( self::OPTION ); }
}

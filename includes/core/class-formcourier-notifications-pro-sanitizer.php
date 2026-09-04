<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Sanitizer {
    public static function key( $value ): string {
        $value = sanitize_text_field( (string) $value );
        return trim( preg_replace( '/[\r\n\t]+/', ' ', $value ) );
    }

    public static function value( $value, string $separator = ', ' ): string {
        if ( is_array( $value ) ) {
            $items = [];
            foreach ( $value as $item ) {
                $item = self::value( $item, $separator );
                if ( '' !== $item ) { $items[] = $item; }
            }
            return implode( $separator, $items );
        }
        if ( is_object( $value ) ) {
            $value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
        }
        $value = sanitize_textarea_field( (string) $value );
        return trim( preg_replace( "/\r\n|\r/", "\n", $value ) );
    }

    public static function fields( array $data ): array {
        $clean = [];
        foreach ( $data as $key => $value ) {
            $key = self::key( $key );
            if ( '' === $key || 0 === strpos( $key, '_' ) || in_array( strtolower( $key ), [ 'g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response' ], true ) ) { continue; }
            $value = self::value( $value );
            if ( '' !== $value ) { $clean[ $key ] = $value; }
        }
        return $clean;
    }
}

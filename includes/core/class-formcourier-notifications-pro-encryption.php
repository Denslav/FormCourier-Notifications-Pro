<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Encryption {
    public static function encrypt( string $plain ): string {
        if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) { return $plain; }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return false === $enc ? $plain : 'enc:' . base64_encode( $iv . $enc );
    }

    public static function decrypt( string $value ): string {
        if ( 0 !== strpos( $value, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) { return $value; }
        $raw = base64_decode( substr( $value, 4 ), true );
        if ( false === $raw || strlen( $raw ) <= 16 ) { return ''; }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $dec = openssl_decrypt( substr( $raw, 16 ), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
        return false === $dec ? '' : $dec;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Message_Builder {
    public static function build( FormCourier_Notifications_Pro_Submission $submission, string $template, array $context = [] ): string {
        if ( '' === trim( $template ) ) {
            $template = "🆕 <b>New form submission</b>\n\n<b>Form:</b> {form_name}\n\n{all_fields}";
        }

        $variables = [
            'form_provider' => self::escape( $submission->provider_label ),
            'provider'      => self::escape( $submission->provider_label ),
            'form_id'       => self::escape( $submission->form_id ),
            'form_name'     => self::escape( $submission->form_name ),
            'destination'   => self::escape( (string) ( $context['destination_name'] ?? '' ) ),
            'submitted_at'  => self::escape( $submission->submitted_at ),
            'all_fields'    => self::format_fields( $submission ),
            'page_url'      => self::escape( $submission->page_url ),
            'site_name'     => self::escape( get_bloginfo( 'name' ) ),
            'site_url'      => self::escape( home_url( '/' ) ),
            'date'          => self::escape( current_time( 'Y-m-d' ) ),
            'time'          => self::escape( current_time( 'H:i:s' ) ),
        ];

        foreach ( $variables as $key => $value ) {
            $template = str_replace( '{' . $key . '}', (string) $value, $template );
        }

        $template = preg_replace_callback(
            '/\{field:([^}]+)\}/',
            static function ( array $match ) use ( $submission ): string {
                $key = sanitize_text_field( $match[1] );

                if ( isset( $submission->fields[ $key ] ) ) {
                    return self::escape( $submission->fields[ $key ] );
                }

                if ( isset( $submission->field_aliases[ $key ] ) ) {
                    return self::escape( $submission->field_aliases[ $key ] );
                }

                return '';
            },
            $template
        );

        $template = preg_replace( '/\{[a-zA-Z0-9_.:-]+\}/', '', (string) $template );
        $template = trim( preg_replace( "/\n{3,}/", "\n\n", (string) $template ) );

        return $template;
    }


    public static function split_for_telegram( string $message, int $limit = 3000 ): array {
        if ( self::length( $message ) <= $limit ) {
            return [ $message ];
        }

        // Strip only the formatting tags added by the message template first,
        // then decode entities. Reversing this order could turn escaped user
        // input into tag-like text and accidentally remove it.
        $plain = wp_strip_all_tags( $message );
        $plain = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $plain = preg_replace( "/\r\n?|\r/", "\n", (string) $plain );
        $plain = (string) $plain;

        if ( '' === $plain ) {
            return [ '' ];
        }

        $raw_chunks = [];
        $remaining  = $plain;

        while ( self::length( $remaining ) > $limit ) {
            $candidate = self::substring( $remaining, 0, $limit );
            $break_at  = self::safe_break_position( $candidate, $limit );

            if ( $break_at <= 0 ) {
                $break_at = $limit;
            }

            $chunk = self::substring( $remaining, 0, $break_at );
            if ( '' === $chunk ) {
                // Defensive fallback: always make forward progress.
                $chunk    = self::substring( $remaining, 0, $limit );
                $break_at = self::length( $chunk );
            }

            $raw_chunks[] = $chunk;
            $remaining    = self::substring( $remaining, $break_at );
        }

        if ( '' !== $remaining ) {
            $raw_chunks[] = $remaining;
        }

        // Never silently lose form data. If a future PHP/environment edge case
        // causes the split result to differ, fall back to fixed Unicode chunks.
        if ( implode( '', $raw_chunks ) !== $plain ) {
            $raw_chunks = self::fixed_chunks( $plain, $limit );
        }

        $chunks = [];
        foreach ( $raw_chunks as $chunk ) {
            $chunks[] = self::escape( $chunk );
        }

        return $chunks ?: [ '' ];
    }

    private static function safe_break_position( string $candidate, int $limit ): int {
        // Prefer a natural break near the end of the chunk, but never discard
        // the separator itself: it stays at the beginning of the next chunk.
        $minimum = max( 1, $limit - 500 );
        $best    = 0;

        foreach ( [ "\n", ' ', '.', ',', ';', ':', '!', '?' ] as $separator ) {
            $position = self::strrpos( $candidate, $separator );
            if ( false !== $position && (int) $position >= $minimum ) {
                $best = max( $best, (int) $position + ( in_array( $separator, [ '.', ',', ';', ':', '!', '?' ], true ) ? 1 : 0 ) );
            }
        }

        return $best > 0 ? $best : $limit;
    }

    private static function fixed_chunks( string $text, int $limit ): array {
        $chunks = [];
        $offset = 0;
        $length = self::length( $text );

        while ( $offset < $length ) {
            $chunk = self::substring( $text, $offset, $limit );
            if ( '' === $chunk ) {
                break;
            }
            $chunks[] = $chunk;
            $offset  += self::length( $chunk );
        }

        return $chunks;
    }

    private static function length( string $text ): int {
        if ( function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $text, 'UTF-8' );
        }

        if ( preg_match_all( '/./us', $text, $matches ) ) {
            return count( $matches[0] );
        }

        return strlen( $text );
    }

    private static function substring( string $text, int $start, ?int $length = null ): string {
        if ( function_exists( 'mb_substr' ) ) {
            return null === $length
                ? (string) mb_substr( $text, $start, null, 'UTF-8' )
                : (string) mb_substr( $text, $start, $length, 'UTF-8' );
        }

        if ( preg_match_all( '/./us', $text, $matches ) ) {
            $slice = null === $length
                ? array_slice( $matches[0], $start )
                : array_slice( $matches[0], $start, $length );

            return implode( '', $slice );
        }

        return null === $length ? (string) substr( $text, $start ) : (string) substr( $text, $start, $length );
    }

    private static function strrpos( string $haystack, string $needle ) {
        return function_exists( 'mb_strrpos' )
            ? mb_strrpos( $haystack, $needle, 0, 'UTF-8' )
            : strrpos( $haystack, $needle );
    }

    public static function sanitize_template( string $template ): string {
        $allowed = [
            'b'          => [],
            'strong'     => [],
            'i'          => [],
            'em'         => [],
            'u'          => [],
            'ins'        => [],
            's'          => [],
            'strike'     => [],
            'del'        => [],
            'code'       => [],
            'pre'        => [],
            'blockquote' => [ 'expandable' => true ],
            'a'          => [ 'href' => true ],
            'span'       => [ 'class' => true ],
        ];

        return trim( wp_kses( $template, $allowed ) );
    }

    private static function format_fields( FormCourier_Notifications_Pro_Submission $submission ): string {
        $lines  = [];
        $fields = $submission->fields;
        $labels = [];

        // Query metadata only for the form that has just been submitted. This
        // avoids scanning every supported form builder on front-end requests.
        if ( class_exists( 'FormCourier_Notifications_Pro_Form_Discovery' ) ) {
            try {
                $labels = FormCourier_Notifications_Pro_Form_Discovery::fields_for(
                    $submission->provider_key,
                    $submission->form_id
                );
            } catch ( Throwable $exception ) {
                $labels = [];
            }
        }

        foreach ( $fields as $key => $value ) {
            $value = FormCourier_Notifications_Pro_Sanitizer::value( $value );
            if ( '' === $value || self::is_technical_field( (string) $key ) ) {
                continue;
            }

            $label = isset( $labels[ (string) $key ] )
                ? self::clean_discovered_label( (string) $labels[ (string) $key ] )
                : self::humanize_label( (string) $key );
            if ( '' === $label ) {
                continue;
            }

            $lines[] = '<b>' . self::escape( $label ) . ':</b> ' . self::escape( $value );
        }

        return implode( "\n", $lines );
    }


    private static function clean_discovered_label( string $label ): string {
        $label = trim( wp_strip_all_tags( $label ) );
        if ( '' === $label ) {
            return '';
        }

        // Contact Form 7 discovery adds the field type for the admin UI,
        // e.g. "Name [text]". Telegram should display the actual field label.
        $label = preg_replace( '/\s*\[[a-z0-9_-]+\]\s*$/i', '', $label );
        $label = trim( (string) $label );

        return '' !== $label ? $label : '';
    }

    private static function is_technical_field( string $key ): bool {
        $normalized = strtolower( trim( $key ) );

        if ( '' === $normalized || 0 === strpos( $normalized, '_' ) ) {
            return true;
        }

        $technical = [
            'g-recaptcha-response',
            'h-captcha-response',
            'cf-turnstile-response',
            'form_id',
            'formid',
            'entry_id',
            'submission_id',
            'action',
            'nonce',
        ];

        return in_array( $normalized, $technical, true );
    }

    private static function humanize_label( string $label ): string {
        $label = trim( wp_strip_all_tags( $label ) );
        if ( '' === $label ) {
            return '';
        }

        // Forminator commonly uses names such as name-1, email-1 and phone-1.
        $forminator_map = [
            'name'     => 'Name',
            'email'    => 'Email',
            'phone'    => 'Phone',
            'textarea' => 'Message',
            'message'  => 'Message',
            'address'  => 'Address',
            'website'  => 'Website',
            'url'      => 'Website',
        ];

        if ( preg_match( '/^([a-z_]+)-\d+$/i', $label, $match ) ) {
            $base = strtolower( str_replace( '_', '', $match[1] ) );
            if ( isset( $forminator_map[ $base ] ) ) {
                return $forminator_map[ $base ];
            }
        }

        $label = preg_replace( '/^(your[-_]+)/i', '', $label );
        $label = str_replace( [ '_', '-' ], ' ', (string) $label );
        $label = trim( preg_replace( '/\s+/', ' ', (string) $label ) );

        if ( '' === $label ) {
            return '';
        }

        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( $label, MB_CASE_TITLE, 'UTF-8' );
        }

        return ucwords( $label );
    }

    private static function escape( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}

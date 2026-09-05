<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Telegram_Provider implements FormCourier_Notifications_Pro_Provider_Interface {
    private const API_BASE = 'https://api.telegram.org/bot';
    private FormCourier_Notifications_Pro_Settings $settings;

    public function __construct( FormCourier_Notifications_Pro_Settings $settings ) {
        $this->settings = $settings;
    }

    public function get_id(): string {
        return 'telegram';
    }

    public function get_name(): string {
        return 'Telegram';
    }

    public function send( FormCourier_Notifications_Pro_Submission $submission, array $context = [] ): array {
        $destination_id = sanitize_key( (string) ( $context['destination'] ?? $this->settings->get_default_destination_id() ) );
        $destination = $this->settings->get_destination( $destination_id );
        $token   = $this->settings->get_destination_bot_token( $destination_id );
        $chat_id = trim( (string) ( $destination['chat_id'] ?? '' ) );

        if ( '' === $token || '' === $chat_id ) {
            return [
                'status'            => 'error',
                'message'           => 'Telegram Bot Token or Chat ID is empty for the selected destination.',
                'http_status'       => 0,
                'provider_response' => 'Local configuration error.',
                'retryable'         => false,
            ];
        }

        $message = FormCourier_Notifications_Pro_Message_Builder::build(
            $submission,
            $this->settings->get_message_template_for_submission( $submission ),
            [
                'destination_id'   => $destination_id,
                'destination_name' => (string) ( $destination['name'] ?? $destination_id ),
                'channel'          => $this->get_name(),
            ]
        );

        $parts = FormCourier_Notifications_Pro_Message_Builder::split_for_telegram( $message );
        $total = count( $parts );
        $last_result = [];

        foreach ( $parts as $index => $part ) {
            if ( $total > 1 ) {
                $part = '<b>Part ' . ( $index + 1 ) . ' of ' . $total . "</b>\n\n" . $part;
            }

            $result = $this->request( $token, $chat_id, $part );
            $last_result = $result;
            if ( 'success' !== ( $result['status'] ?? 'error' ) ) {
                if ( $total > 1 ) {
                    $result['message'] = 'Part ' . ( $index + 1 ) . ' of ' . $total . ' failed. ' . ( $result['message'] ?? '' );
                }
                return $result;
            }
        }

        return [
            'status'            => 'success',
            'message'           => $total > 1 ? 'Telegram message sent successfully in ' . $total . ' parts.' : 'Telegram message sent successfully.',
            'parts'             => max( 1, $total ),
            'http_status'       => absint( $last_result['http_status'] ?? 200 ),
            'provider_response' => (string) ( $last_result['provider_response'] ?? 'ok' ),
            'retryable'         => false,
        ];
    }

    public function send_test( string $destination_id = '' ): array {
        $submission = new FormCourier_Notifications_Pro_Submission(
            'test',
            'Test Form Provider',
            '123',
            'Test Form',
            [
                'Name'    => 'Test User',
                'Email'   => 'test@example.com',
                'Phone'   => '+380 00 000 00 00',
                'Message' => 'Test message from FormCourier Notifications Pro.',
            ]
        );
        return $this->send( $submission, [ 'destination' => $destination_id ?: $this->settings->get_default_destination_id() ] );
    }

    private function request( string $token, string $chat_id, string $message ): array {
        $response = wp_remote_post(
            self::API_BASE . rawurlencode( $token ) . '/sendMessage',
            [
                'timeout'            => 15,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
                'headers'            => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'               => wp_json_encode(
                    [
                        'chat_id'                  => $chat_id,
                        'text'                     => $message,
                        'parse_mode'               => 'HTML',
                        'disable_web_page_preview' => true,
                    ]
                ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            $network_error = sanitize_text_field( $response->get_error_message() );
            return [
                'status'            => 'error',
                'message'           => 'Network error: ' . $network_error,
                'http_status'       => 0,
                'provider_response' => $network_error,
                'retryable'         => true,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['ok'] ) ) {
            return [
                'status'            => 'success',
                'message'           => 'Telegram message sent successfully.',
                'http_status'       => $code,
                'provider_response' => 'ok',
                'retryable'         => false,
            ];
        }

        $description = is_array( $body ) && ! empty( $body['description'] )
            ? sanitize_text_field( (string) $body['description'] )
            : 'Telegram API request failed.';

        $error_code = is_array( $body ) && ! empty( $body['error_code'] ) ? absint( $body['error_code'] ) : $code;
        $retry_after = is_array( $body ) && ! empty( $body['parameters']['retry_after'] ) ? absint( $body['parameters']['retry_after'] ) : 0;

        $friendly = $this->friendly_error( $error_code, $description );
        if ( $retry_after > 0 ) {
            $friendly .= ' Retry after ' . $retry_after . ' seconds.';
        }

        $retryable = ( 429 === $error_code || $error_code >= 500 );

        return [
            'status'            => 'error',
            'message'           => $friendly,
            'http_status'       => $code,
            'error_code'        => $error_code,
            'provider_response' => $description,
            'retryable'         => $retryable,
            'retry_after'       => $retry_after,
        ];
    }

    private function friendly_error( int $code, string $description ): string {
        $lower = strtolower( $description );

        if ( 401 === $code ) {
            return 'Telegram authentication failed. Check the Bot Token.';
        }
        if ( false !== strpos( $lower, 'chat not found' ) ) {
            return 'Telegram chat not found. Check the Chat ID and make sure the bot has access to the chat.';
        }
        if ( 403 === $code || false !== strpos( $lower, 'bot was blocked' ) || false !== strpos( $lower, 'forbidden' ) ) {
            return 'Telegram access denied. The bot may be blocked or may not have permission to post in this chat.';
        }
        if ( 429 === $code ) {
            return 'Telegram rate limit reached.';
        }
        if ( $code >= 500 ) {
            return 'Telegram API is temporarily unavailable.';
        }

        return 'Telegram API error' . ( $code ? ' ' . $code : '' ) . ': ' . $description;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Routing_Engine {
    private FormCourier_Notifications_Pro_Settings $settings;

    public function __construct( FormCourier_Notifications_Pro_Settings $settings ) {
        $this->settings = $settings;
    }

    /** @return array<int,array{provider:string,destination:string}> */
    public function resolve( FormCourier_Notifications_Pro_Submission $submission ): array {
        $routes = [];

        if ( '1' === $this->settings->get( 'enabled', '0' ) ) {
            $telegram_destination_ids = $this->settings->get_route_destinations( $submission );

            foreach ( $this->settings->get_matching_conditional_rules( $submission ) as $rule ) {
                $rule_destinations = array_values( array_filter( array_map( 'sanitize_key', (array) ( $rule['destinations'] ?? [] ) ) ) );
                if ( empty( $rule_destinations ) ) { continue; }
                if ( 'add' === ( $rule['mode'] ?? 'replace' ) ) {
                    $telegram_destination_ids = array_values( array_unique( array_merge( $telegram_destination_ids, $rule_destinations ) ) );
                } else {
                    $telegram_destination_ids = $rule_destinations;
                }
            }

            foreach ( $telegram_destination_ids as $destination_id ) {
                $routes[] = [
                    'provider'    => 'telegram',
                    'destination' => sanitize_key( (string) $destination_id ),
                ];
            }
        }

        if ( '1' === $this->settings->get( 'slack_enabled', '0' ) ) {
            foreach ( $this->settings->get_slack_route_destinations( $submission ) as $destination_id ) {
                $routes[] = [
                    'provider'    => 'slack',
                    'destination' => sanitize_key( (string) $destination_id ),
                ];
            }
        }

        $routes = array_values(
            array_filter(
                $routes,
                static function ( array $route ): bool {
                    return '' !== ( $route['provider'] ?? '' ) && '' !== ( $route['destination'] ?? '' );
                }
            )
        );

        $routes = apply_filters( 'formcourier_notifications_pro_routes', $routes, $submission );
        return is_array( $routes ) ? $routes : [];
    }
}

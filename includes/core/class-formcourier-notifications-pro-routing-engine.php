<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Routing_Engine {
    private FormCourier_Notifications_Pro_Settings $settings;

    public function __construct( FormCourier_Notifications_Pro_Settings $settings ) {
        $this->settings = $settings;
    }

    /** @return array<int,array{provider:string,destination:string}> */
    public function resolve( FormCourier_Notifications_Pro_Submission $submission ): array {
        if ( '1' !== $this->settings->get( 'enabled', '0' ) ) {
            return [];
        }

        $destination_ids = $this->settings->get_route_destinations( $submission );

        foreach ( $this->settings->get_matching_conditional_rules( $submission ) as $rule ) {
            $rule_destinations = array_values( array_filter( array_map( 'sanitize_key', (array) ( $rule['destinations'] ?? [] ) ) ) );
            if ( empty( $rule_destinations ) ) { continue; }
            if ( 'add' === ( $rule['mode'] ?? 'replace' ) ) {
                $destination_ids = array_values( array_unique( array_merge( $destination_ids, $rule_destinations ) ) );
            } else {
                $destination_ids = $rule_destinations;
            }
        }

        if ( empty( $destination_ids ) ) {
            return [];
        }

        $routes = [];
        foreach ( $destination_ids as $destination_id ) {
            $routes[] = [
                'provider'    => 'telegram',
                'destination' => $destination_id,
            ];
        }

        $routes = apply_filters( 'formcourier_notifications_pro_routes', $routes, $submission );
        return is_array( $routes ) ? $routes : [];
    }
}

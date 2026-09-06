<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Settings {
    private const OPTION = 'formcourier_notifications_pro_settings';
    private array $settings = [];

    public function __construct() {
        $stored = get_option( self::OPTION, [] );
        $this->settings = wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
    }

    public static function defaults(): array {
        return [
            'enabled'          => '0',
            'bot_token'        => '',
            'chat_id'          => '',
            'destinations'     => [],
            'default_destination' => '',
            'form_routes'      => [],
            'conditional_rules' => [],
            'slack_enabled'          => '0',
            'slack_destinations'     => [],
            'slack_default_destination' => '',
            'slack_form_routes'      => [],
            'providers'        => [ 'contact_form_7', 'wpforms', 'fluent_forms', 'forminator', 'ninja_forms', 'gravity_forms' ],
            'message_template'          => "🆕 <b>New form submission</b>\n\n<b>Form:</b> {form_name}\n\n{all_fields}",
            'form_message_templates'    => [],
            'delete_data_on_uninstall' => '0',
            'auto_cleanup_logs_30_days' => '0',
        ];
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_post_formcourier_notifications_pro_test', [ $this, 'handle_test' ] );
        add_action( 'admin_post_formcourier_notifications_pro_slack_test', [ $this, 'handle_slack_test' ] );
        add_action( 'admin_post_formcourier_notifications_pro_clear_logs', [ $this, 'handle_clear_logs' ] );
        add_action( 'admin_post_formcourier_notifications_pro_retry_log', [ $this, 'handle_retry_log' ] );
        add_action( 'admin_post_formcourier_notifications_pro_export_logs', [ $this, 'handle_export_logs' ] );
        add_filter( 'plugin_action_links_' . FORMCOURIER_NOTIFICATIONS_PRO_BASENAME, [ $this, 'action_links' ] );
    }

    public function get( string $key, $default = '' ) {
        return $this->settings[ $key ] ?? $default;
    }

    public function get_bot_token(): string {
        return FormCourier_Notifications_Pro_Encryption::decrypt( (string) $this->get( 'bot_token', '' ) );
    }

    public function get_destinations(): array {
        $destinations = $this->get( 'destinations', [] );
        if ( ! is_array( $destinations ) || empty( $destinations ) ) {
            $legacy_token = (string) $this->get( 'bot_token', '' );
            $legacy_chat  = (string) $this->get( 'chat_id', '' );
            if ( '' !== $legacy_token || '' !== $legacy_chat ) {
                return [ 'default' => [ 'name' => 'Default', 'bot_token' => $legacy_token, 'chat_id' => $legacy_chat, 'enabled' => '1' ] ];
            }
        }
        return is_array( $destinations ) ? $destinations : [];
    }

    public function get_destination( string $id ): array {
        $destinations = $this->get_destinations();
        return isset( $destinations[ $id ] ) && is_array( $destinations[ $id ] ) ? $destinations[ $id ] : [];
    }

    public function get_destination_bot_token( string $id ): string {
        $destination = $this->get_destination( $id );
        return FormCourier_Notifications_Pro_Encryption::decrypt( (string) ( $destination['bot_token'] ?? '' ) );
    }

    public function get_default_destination_id(): string {
        $destinations = $this->get_destinations();
        $default = sanitize_key( (string) $this->get( 'default_destination', '' ) );
        if ( $default && isset( $destinations[ $default ] ) && '1' === ( $destinations[ $default ]['enabled'] ?? '1' ) ) {
            return $default;
        }
        foreach ( $destinations as $id => $destination ) {
            if ( '1' === ( $destination['enabled'] ?? '1' ) ) { return sanitize_key( (string) $id ); }
        }
        return '';
    }

    public function get_slack_destinations(): array {
        $destinations = $this->get( 'slack_destinations', [] );
        return is_array( $destinations ) ? $destinations : [];
    }

    public function get_slack_destination( string $id ): array {
        $destinations = $this->get_slack_destinations();
        return isset( $destinations[ $id ] ) && is_array( $destinations[ $id ] ) ? $destinations[ $id ] : [];
    }

    public function get_slack_destination_webhook_url( string $id ): string {
        $destination = $this->get_slack_destination( $id );
        return FormCourier_Notifications_Pro_Encryption::decrypt( (string) ( $destination['webhook_url'] ?? '' ) );
    }

    public function get_slack_default_destination_id(): string {
        $destinations = $this->get_slack_destinations();
        $default = sanitize_key( (string) $this->get( 'slack_default_destination', '' ) );
        if ( $default && isset( $destinations[ $default ] ) && '1' === ( $destinations[ $default ]['enabled'] ?? '1' ) ) {
            return $default;
        }
        foreach ( $destinations as $id => $destination ) {
            if ( '1' === ( $destination['enabled'] ?? '1' ) ) { return sanitize_key( (string) $id ); }
        }
        return '';
    }

    /** @return array<int,string> */
    public function get_slack_route_destinations( FormCourier_Notifications_Pro_Submission $submission ): array {
        $routes = $this->get( 'slack_form_routes', [] );
        $route_key = sanitize_key( $submission->provider_key ) . ':' . sanitize_key( (string) $submission->form_id );
        $configured = is_array( $routes ) && array_key_exists( $route_key, $routes ) ? $routes[ $route_key ] : [];
        if ( is_string( $configured ) && '' !== $configured ) { $configured = [ $configured ]; }

        $resolved = [];
        if ( is_array( $configured ) ) {
            foreach ( $configured as $destination_id ) {
                $destination_id = sanitize_key( (string) $destination_id );
                if ( '' === $destination_id || in_array( $destination_id, $resolved, true ) ) { continue; }
                $destination = $this->get_slack_destination( $destination_id );
                if ( ! empty( $destination ) && '1' === ( $destination['enabled'] ?? '1' ) ) { $resolved[] = $destination_id; }
            }
        }

        if ( empty( $resolved ) ) {
            $default = $this->get_slack_default_destination_id();
            if ( '' !== $default ) { $resolved[] = $default; }
        }
        return $resolved;
    }

    /** @return array<int,string> */
    public function get_route_destinations( FormCourier_Notifications_Pro_Submission $submission ): array {
        $routes = $this->get( 'form_routes', [] );
        $route_key = sanitize_key( $submission->provider_key ) . ':' . sanitize_key( (string) $submission->form_id );
        $configured = is_array( $routes ) && array_key_exists( $route_key, $routes ) ? $routes[ $route_key ] : [];

        // Backward compatibility with 1.1.0, where each form stored one destination ID.
        if ( is_string( $configured ) && '' !== $configured ) {
            $configured = [ $configured ];
        }

        $resolved = [];
        if ( is_array( $configured ) ) {
            foreach ( $configured as $destination_id ) {
                $destination_id = sanitize_key( (string) $destination_id );
                if ( '' === $destination_id || in_array( $destination_id, $resolved, true ) ) {
                    continue;
                }
                $destination = $this->get_destination( $destination_id );
                if ( ! empty( $destination ) && '1' === ( $destination['enabled'] ?? '1' ) ) {
                    $resolved[] = $destination_id;
                }
            }
        }

        if ( empty( $resolved ) ) {
            $default = $this->get_default_destination_id();
            if ( '' !== $default ) {
                $resolved[] = $default;
            }
        }

        return $resolved;
    }

    public function get_route_destination( FormCourier_Notifications_Pro_Submission $submission ): string {
        $destinations = $this->get_route_destinations( $submission );
        return $destinations[0] ?? '';
    }


    /** @return array<int,array<string,mixed>> */
    public function get_matching_conditional_rules( FormCourier_Notifications_Pro_Submission $submission ): array {
        $rules = $this->get( 'conditional_rules', [] );
        if ( ! is_array( $rules ) || empty( $rules ) ) { return []; }

        $route_key = sanitize_key( $submission->provider_key ) . ':' . sanitize_key( (string) $submission->form_id );
        $candidates = [];
        foreach ( $rules as $order => $rule ) {
            if ( ! is_array( $rule ) || '1' !== ( $rule['enabled'] ?? '1' ) ) { continue; }
            if ( $route_key !== (string) ( $rule['form_key'] ?? '' ) ) { continue; }
            $candidates[] = [
                'rule'     => $rule,
                'priority' => max( 0, min( 999, absint( $rule['priority'] ?? 0 ) ) ),
                'order'    => (int) $order,
            ];
        }

        // Higher priority rules are evaluated first. Equal priorities keep their saved order.
        usort(
            $candidates,
            static function ( array $a, array $b ): int {
                if ( $a['priority'] === $b['priority'] ) { return $a['order'] <=> $b['order']; }
                return $b['priority'] <=> $a['priority'];
            }
        );

        $matched = [];
        foreach ( $candidates as $candidate ) {
            $rule = $candidate['rule'];
            if ( ! $this->conditional_rule_matches( $rule, $submission ) ) { continue; }
            $matched[] = $candidate;
            if ( '1' === (string) ( $rule['stop_processing'] ?? '0' ) ) { break; }
        }

        // Apply lower priorities first so a higher-priority Replace action wins when rules overlap.
        usort(
            $matched,
            static function ( array $a, array $b ): int {
                if ( $a['priority'] === $b['priority'] ) { return $a['order'] <=> $b['order']; }
                return $a['priority'] <=> $b['priority'];
            }
        );

        return array_values( array_map( static fn( array $item ): array => $item['rule'], $matched ) );
    }

    private function conditional_rule_matches( array $rule, FormCourier_Notifications_Pro_Submission $submission ): bool {
        $conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : [];

        // Backward compatibility with 1.8.0 and earlier single-condition rules.
        if ( empty( $conditions ) && '' !== trim( (string) ( $rule['field'] ?? '' ) ) ) {
            $conditions[] = [
                'field'    => (string) ( $rule['field'] ?? '' ),
                'operator' => (string) ( $rule['operator'] ?? 'equals' ),
                'value'    => (string) ( $rule['value'] ?? '' ),
            ];
        }

        if ( empty( $conditions ) ) { return false; }

        $match_mode = 'any' === sanitize_key( (string) ( $rule['match_mode'] ?? 'all' ) ) ? 'any' : 'all';
        $results = [];
        foreach ( $conditions as $condition ) {
            if ( ! is_array( $condition ) || '' === trim( (string) ( $condition['field'] ?? '' ) ) ) { continue; }
            $results[] = $this->conditional_condition_matches( $condition, $submission );
        }

        if ( empty( $results ) ) { return false; }
        return 'any' === $match_mode ? in_array( true, $results, true ) : ! in_array( false, $results, true );
    }

    private function conditional_condition_matches( array $condition, FormCourier_Notifications_Pro_Submission $submission ): bool {
        $field = trim( (string) ( $condition['field'] ?? '' ) );
        $operator = sanitize_key( (string) ( $condition['operator'] ?? 'equals' ) );
        $expected = (string) ( $condition['value'] ?? '' );
        $actual = '';

        if ( '' !== $field ) {
            $aliases = is_array( $submission->field_aliases ) ? $submission->field_aliases : [];
            $fields  = is_array( $submission->fields ) ? $submission->fields : [];
            if ( array_key_exists( $field, $aliases ) ) { $actual = (string) $aliases[ $field ]; }
            elseif ( array_key_exists( $field, $fields ) ) { $actual = (string) $fields[ $field ]; }
            else {
                $needle = strtolower( $field );
                foreach ( array_merge( $aliases, $fields ) as $key => $value ) {
                    if ( strtolower( (string) $key ) === $needle ) { $actual = (string) $value; break; }
                }
            }
        }

        $actual_trim = trim( $actual );
        $expected_trim = trim( $expected );
        switch ( $operator ) {
            case 'not_equals': return 0 !== strcasecmp( $actual_trim, $expected_trim );
            case 'contains': return '' !== $expected_trim && false !== stripos( $actual, $expected_trim );
            case 'not_contains': return '' === $expected_trim || false === stripos( $actual, $expected_trim );
            case 'greater_than': return is_numeric( $actual_trim ) && is_numeric( $expected_trim ) && (float) $actual_trim > (float) $expected_trim;
            case 'less_than': return is_numeric( $actual_trim ) && is_numeric( $expected_trim ) && (float) $actual_trim < (float) $expected_trim;
            case 'is_empty': return '' === $actual_trim;
            case 'is_not_empty': return '' !== $actual_trim;
            case 'equals':
            default: return 0 === strcasecmp( $actual_trim, $expected_trim );
        }
    }

    public function get_message_template_for_submission( FormCourier_Notifications_Pro_Submission $submission ): string {
        $templates = $this->get( 'form_message_templates', [] );
        $route_key = sanitize_key( $submission->provider_key ) . ':' . sanitize_key( (string) $submission->form_id );

        if ( is_array( $templates ) && isset( $templates[ $route_key ] ) && is_string( $templates[ $route_key ] ) && '' !== trim( $templates[ $route_key ] ) ) {
            return (string) $templates[ $route_key ];
        }

        return (string) $this->get( 'message_template', '' );
    }

    public function remember_form( FormCourier_Notifications_Pro_Submission $submission ): void {
        $known = get_option( 'formcourier_notifications_pro_known_forms', [] );
        $known = is_array( $known ) ? $known : [];
        $key = sanitize_key( $submission->provider_key ) . ':' . sanitize_key( (string) $submission->form_id );
        $item = [ 'provider_key' => $submission->provider_key, 'provider_label' => $submission->provider_label, 'form_id' => $submission->form_id, 'form_name' => $submission->form_name ];
        if ( ! isset( $known[ $key ] ) || $known[ $key ] !== $item ) {
            $known[ $key ] = $item;
            update_option( 'formcourier_notifications_pro_known_forms', $known, false );
        }

        // Remember technical field identifiers observed in real submissions as a fallback
        // when a form builder changes its internal discovery API.
        $known_fields = get_option( 'formcourier_notifications_pro_known_fields', [] );
        $known_fields = is_array( $known_fields ) ? $known_fields : [];
        $fields_for_form = isset( $known_fields[ $key ] ) && is_array( $known_fields[ $key ] ) ? $known_fields[ $key ] : [];
        foreach ( array_keys( (array) $submission->field_aliases ) as $field_key ) {
            $field_key = sanitize_text_field( (string) $field_key );
            if ( '' !== $field_key ) { $fields_for_form[ $field_key ] = $this->humanize_field_key( $field_key ); }
        }
        foreach ( array_keys( (array) $submission->fields ) as $field_key ) {
            $field_key = sanitize_text_field( (string) $field_key );
            if ( '' !== $field_key && ! isset( $fields_for_form[ $field_key ] ) ) { $fields_for_form[ $field_key ] = $this->humanize_field_key( $field_key ); }
        }
        if ( ! empty( $fields_for_form ) ) {
            $known_fields[ $key ] = $fields_for_form;
            update_option( 'formcourier_notifications_pro_known_fields', $known_fields, false );
        }
    }

    private function humanize_field_key( string $key ): string {
        $label = preg_replace( '/[_-]+/', ' ', $key );
        $label = preg_replace( '/\s+/', ' ', (string) $label );
        return ucwords( trim( (string) $label ) );
    }

    public function is_form_provider_enabled( string $provider ): bool {
        $providers = $this->get( 'providers', [] );
        return is_array( $providers ) && in_array( $provider, $providers, true );
    }

    public function register(): void {
        register_setting( 'formcourier_notifications_pro_group', self::OPTION, [ $this, 'sanitize' ] );
    }

    public function sanitize( $input ): array {
        $old = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $input = is_array( $input ) ? $input : [];
        $clean = $old;

        if ( array_key_exists( 'enabled', $input ) || isset( $input['_section'] ) && 'telegram' === $input['_section'] ) {
            $clean['enabled'] = ! empty( $input['enabled'] ) ? '1' : '0';
        }

        if ( array_key_exists( 'bot_token', $input ) ) {
            $token = trim( sanitize_text_field( (string) $input['bot_token'] ) );
            if ( '' !== $token ) {
                $clean['bot_token'] = FormCourier_Notifications_Pro_Encryption::encrypt( $token );
            }
        }

        if ( array_key_exists( 'chat_id', $input ) ) {
            $clean['chat_id'] = sanitize_text_field( (string) $input['chat_id'] );
        }

        if ( isset( $input['_section'] ) && 'telegram' === $input['_section'] && isset( $input['destinations'] ) && is_array( $input['destinations'] ) ) {
            $old_destinations = isset( $old['destinations'] ) && is_array( $old['destinations'] ) ? $old['destinations'] : [];
            $destinations = [];
            foreach ( $input['destinations'] as $raw_id => $raw_destination ) {
                if ( ! is_array( $raw_destination ) ) { continue; }
                $id = sanitize_key( (string) $raw_id );
                if ( '' === $id ) { continue; }
                $name = sanitize_text_field( (string) ( $raw_destination['name'] ?? '' ) );
                $chat_id = sanitize_text_field( (string) ( $raw_destination['chat_id'] ?? '' ) );
                if ( '' === $name && '' === $chat_id ) { continue; }
                $token = trim( sanitize_text_field( (string) ( $raw_destination['bot_token'] ?? '' ) ) );
                $encrypted = '';
                if ( '' !== $token ) {
                    $encrypted = FormCourier_Notifications_Pro_Encryption::encrypt( $token );
                } elseif ( isset( $old_destinations[ $id ]['bot_token'] ) ) {
                    $encrypted = (string) $old_destinations[ $id ]['bot_token'];
                } elseif ( 'default' === $id && ! empty( $old['bot_token'] ) ) {
                    $encrypted = (string) $old['bot_token'];
                }
                $destinations[ $id ] = [ 'name' => $name ?: ucfirst( str_replace( '-', ' ', $id ) ), 'bot_token' => $encrypted, 'chat_id' => $chat_id, 'enabled' => ! empty( $raw_destination['enabled'] ) ? '1' : '0' ];
            }
            $clean['destinations'] = $destinations;
            $default = sanitize_key( (string) ( $input['default_destination'] ?? '' ) );
            $clean['default_destination'] = isset( $destinations[ $default ] ) ? $default : ( $destinations ? array_key_first( $destinations ) : '' );
        }

        if ( isset( $input['_section'] ) && 'slack' === $input['_section'] ) {
            $clean['slack_enabled'] = ! empty( $input['slack_enabled'] ) ? '1' : '0';
            $old_destinations = isset( $old['slack_destinations'] ) && is_array( $old['slack_destinations'] ) ? $old['slack_destinations'] : [];
            $destinations = [];
            if ( isset( $input['slack_destinations'] ) && is_array( $input['slack_destinations'] ) ) {
                foreach ( $input['slack_destinations'] as $raw_id => $raw_destination ) {
                    if ( ! is_array( $raw_destination ) ) { continue; }
                    $id = sanitize_key( (string) $raw_id );
                    if ( '' === $id ) { continue; }
                    $name = sanitize_text_field( (string) ( $raw_destination['name'] ?? '' ) );
                    $webhook = trim( esc_url_raw( (string) ( $raw_destination['webhook_url'] ?? '' ), [ 'https' ] ) );
                    if ( '' === $name && '' === $webhook && empty( $old_destinations[ $id ]['webhook_url'] ) ) { continue; }
                    $encrypted = '';
                    if ( '' !== $webhook ) {
                        $encrypted = FormCourier_Notifications_Pro_Encryption::encrypt( $webhook );
                    } elseif ( isset( $old_destinations[ $id ]['webhook_url'] ) ) {
                        $encrypted = (string) $old_destinations[ $id ]['webhook_url'];
                    }
                    $destinations[ $id ] = [
                        'name'        => $name ?: ucfirst( str_replace( '-', ' ', $id ) ),
                        'webhook_url' => $encrypted,
                        'enabled'     => ! empty( $raw_destination['enabled'] ) ? '1' : '0',
                    ];
                }
            }
            $clean['slack_destinations'] = $destinations;
            $default = sanitize_key( (string) ( $input['slack_default_destination'] ?? '' ) );
            $clean['slack_default_destination'] = isset( $destinations[ $default ] ) ? $default : ( $destinations ? array_key_first( $destinations ) : '' );
        }

        if ( isset( $input['_section'] ) && 'forms' === $input['_section'] ) {
            $allowed = [ 'contact_form_7', 'wpforms', 'fluent_forms', 'forminator', 'ninja_forms', 'gravity_forms' ];
            $providers = isset( $input['providers'] ) && is_array( $input['providers'] )
                ? array_values( array_intersect( $allowed, array_map( 'sanitize_key', $input['providers'] ) ) )
                : [];
            $clean['providers'] = $providers;
            $destinations = $this->get_destinations();
            $routes = [];
            if ( isset( $input['form_routes'] ) && is_array( $input['form_routes'] ) ) {
                foreach ( $input['form_routes'] as $route_key => $destination_ids ) {
                    $route_key = sanitize_text_field( (string) $route_key );
                    if ( '' === $route_key ) { continue; }

                    // Accept both the new checkbox array and the old single select value.
                    if ( ! is_array( $destination_ids ) ) {
                        $destination_ids = [ $destination_ids ];
                    }

                    $selected = [];
                    foreach ( $destination_ids as $destination_id ) {
                        $destination_id = sanitize_key( (string) $destination_id );
                        if ( '' !== $destination_id && isset( $destinations[ $destination_id ] ) && ! in_array( $destination_id, $selected, true ) ) {
                            $selected[] = $destination_id;
                        }
                    }

                    if ( ! empty( $selected ) ) {
                        $routes[ $route_key ] = $selected;
                    }
                }
            }
            $clean['form_routes'] = $routes;

            $slack_destinations = $this->get_slack_destinations();
            $slack_routes = [];
            if ( isset( $input['slack_form_routes'] ) && is_array( $input['slack_form_routes'] ) ) {
                foreach ( $input['slack_form_routes'] as $route_key => $destination_ids ) {
                    $route_key = sanitize_text_field( (string) $route_key );
                    if ( '' === $route_key ) { continue; }
                    if ( ! is_array( $destination_ids ) ) { $destination_ids = [ $destination_ids ]; }
                    $selected = [];
                    foreach ( $destination_ids as $destination_id ) {
                        $destination_id = sanitize_key( (string) $destination_id );
                        if ( '' !== $destination_id && isset( $slack_destinations[ $destination_id ] ) && ! in_array( $destination_id, $selected, true ) ) {
                            $selected[] = $destination_id;
                        }
                    }
                    if ( ! empty( $selected ) ) { $slack_routes[ $route_key ] = $selected; }
                }
            }
            $clean['slack_form_routes'] = $slack_routes;
            $rules = [];
            if ( isset( $input['conditional_rules'] ) && is_array( $input['conditional_rules'] ) ) {
                $allowed_operators = [ 'equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty' ];
                foreach ( $input['conditional_rules'] as $rule ) {
                    if ( ! is_array( $rule ) ) { continue; }
                    $form_key = sanitize_text_field( (string) ( $rule['form_key'] ?? '' ) );
                    $match_mode = 'any' === sanitize_key( (string) ( $rule['match_mode'] ?? 'all' ) ) ? 'any' : 'all';
                    $mode = 'add' === sanitize_key( (string) ( $rule['mode'] ?? 'replace' ) ) ? 'add' : 'replace';
                    $priority = max( 0, min( 999, absint( $rule['priority'] ?? 0 ) ) );
                    $stop_processing = ! empty( $rule['stop_processing'] ) ? '1' : '0';

                    $raw_conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : [];
                    // Preserve rules posted by older admin UIs or stored by 1.8.0.
                    if ( empty( $raw_conditions ) && isset( $rule['field'] ) ) {
                        $raw_conditions[] = [
                            'field'    => $rule['field'] ?? '',
                            'operator' => $rule['operator'] ?? 'equals',
                            'value'    => $rule['value'] ?? '',
                        ];
                    }

                    $conditions = [];
                    foreach ( $raw_conditions as $condition ) {
                        if ( ! is_array( $condition ) ) { continue; }
                        $field = sanitize_text_field( (string) ( $condition['field'] ?? '' ) );
                        $operator = sanitize_key( (string) ( $condition['operator'] ?? 'equals' ) );
                        if ( ! in_array( $operator, $allowed_operators, true ) ) { $operator = 'equals'; }
                        if ( '' === $field ) { continue; }
                        $conditions[] = [
                            'field'    => $field,
                            'operator' => $operator,
                            'value'    => sanitize_text_field( (string) ( $condition['value'] ?? '' ) ),
                        ];
                    }

                    $selected = [];
                    foreach ( (array) ( $rule['destinations'] ?? [] ) as $destination_id ) {
                        $destination_id = sanitize_key( (string) $destination_id );
                        if ( isset( $destinations[ $destination_id ] ) && ! in_array( $destination_id, $selected, true ) ) { $selected[] = $destination_id; }
                    }

                    $selected_slack = [];
                    foreach ( (array) ( $rule['slack_destinations'] ?? [] ) as $destination_id ) {
                        $destination_id = sanitize_key( (string) $destination_id );
                        if ( isset( $slack_destinations[ $destination_id ] ) && ! in_array( $destination_id, $selected_slack, true ) ) { $selected_slack[] = $destination_id; }
                    }

                    if ( '' === $form_key || empty( $conditions ) || ( empty( $selected ) && empty( $selected_slack ) ) ) { continue; }

                    $first_condition = $conditions[0];
                    $rules[] = [
                        'enabled'            => ! empty( $rule['enabled'] ) ? '1' : '0',
                        'form_key'           => $form_key,
                        'match_mode'         => $match_mode,
                        'priority'           => $priority,
                        'stop_processing'    => $stop_processing,
                        'conditions'         => $conditions,
                        // Legacy mirror keeps older code and downgrade scenarios usable.
                        'field'              => $first_condition['field'],
                        'operator'           => $first_condition['operator'],
                        'value'              => $first_condition['value'],
                        'mode'               => $mode,
                        'destinations'       => $selected,
                        'slack_destinations' => $selected_slack,
                    ];
                }
            }
            $clean['conditional_rules'] = $rules;
        }

        if ( isset( $input['_section'] ) && 'message' === $input['_section'] ) {
            if ( array_key_exists( 'message_template', $input ) ) {
                $clean['message_template'] = FormCourier_Notifications_Pro_Message_Builder::sanitize_template( (string) $input['message_template'] );
            }

            $form_templates = [];
            $enabled_templates = isset( $input['form_template_enabled'] ) && is_array( $input['form_template_enabled'] )
                ? array_map( 'sanitize_text_field', array_keys( $input['form_template_enabled'] ) )
                : [];
            $raw_templates = isset( $input['form_message_templates'] ) && is_array( $input['form_message_templates'] )
                ? $input['form_message_templates']
                : [];

            foreach ( $raw_templates as $route_key => $template ) {
                $route_key = sanitize_text_field( (string) $route_key );
                if ( '' === $route_key || ! in_array( $route_key, $enabled_templates, true ) ) {
                    continue;
                }

                $template = FormCourier_Notifications_Pro_Message_Builder::sanitize_template( (string) $template );
                if ( '' !== trim( $template ) ) {
                    $form_templates[ $route_key ] = $template;
                }
            }

            $clean['form_message_templates'] = $form_templates;
        }

        if ( isset( $input['_section'] ) && 'telegram' === $input['_section'] ) {
            $clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? '1' : '0';
        }

        if ( isset( $input['_section'] ) && 'logs' === $input['_section'] ) {
            $clean['auto_cleanup_logs_30_days'] = ! empty( $input['auto_cleanup_logs_30_days'] ) ? '1' : '0';
        }

        unset( $clean['_section'] );
        $this->settings = wp_parse_args( $clean, self::defaults() );
        return $clean;
    }

    public function admin_menu(): void {
        add_menu_page(
            __( 'FormCourier Notifications Pro', 'formcourier-notifications-pro' ),
            __( 'FormCourier Notifications Pro', 'formcourier-notifications-pro' ),
            'manage_options',
            'formcourier-notifications-pro',
            [ $this, 'render_page' ],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( 'toplevel_page_formcourier-notifications-pro' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'formcourier-notifications-pro-admin',
            FORMCOURIER_NOTIFICATIONS_PRO_URL . 'assets/admin.css',
            [],
            FORMCOURIER_NOTIFICATIONS_PRO_VERSION
        );
        wp_enqueue_script(
            'formcourier-notifications-pro-admin',
            FORMCOURIER_NOTIFICATIONS_PRO_URL . 'assets/admin.js',
            [],
            FORMCOURIER_NOTIFICATIONS_PRO_VERSION,
            true
        );

        wp_localize_script(
            'formcourier-notifications-pro-admin',
            'FormCourierNotificationsProAdmin',
            [
                'formFields' => FormCourier_Notifications_Pro_Form_Discovery::fields(),
                'fieldPlaceholder' => __( 'Select a field', 'formcourier-notifications-pro' ),
                'customFieldLabel' => __( 'Custom / previously saved field', 'formcourier-notifications-pro' ),
            ]
        );
    }

    public function action_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro' ) ) . '">' . esc_html__( 'Settings', 'formcourier-notifications-pro' ) . '</a>' );
        return $links;
    }

    private function tabs(): array {
        return [
            'dashboard' => __( 'Dashboard', 'formcourier-notifications-pro' ),
            'telegram'  => __( 'Telegram', 'formcourier-notifications-pro' ),
            'slack'     => __( 'Slack', 'formcourier-notifications-pro' ),
            'forms'     => __( 'Forms', 'formcourier-notifications-pro' ),
            'message'   => __( 'Message', 'formcourier-notifications-pro' ),
            'logs'      => __( 'Logs', 'formcourier-notifications-pro' ),
        ];
    }

    private function providers(): array {
        return [
            'contact_form_7' => [ 'label' => 'Contact Form 7', 'active' => defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' ) ],
            'wpforms'        => [ 'label' => 'WPForms', 'active' => function_exists( 'wpforms' ) || defined( 'WPFORMS_VERSION' ) ],
            'fluent_forms'   => [ 'label' => 'Fluent Forms', 'active' => defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluentForm' ) ],
            'forminator'     => [ 'label' => 'Forminator', 'active' => defined( 'FORMINATOR_VERSION' ) || class_exists( 'Forminator' ) ],
            'ninja_forms'    => [ 'label' => 'Ninja Forms', 'active' => function_exists( 'Ninja_Forms' ) || defined( 'NINJA_FORMS_VERSION' ) ],
            'gravity_forms'  => [ 'label' => 'Gravity Forms', 'active' => class_exists( 'GFForms' ) || defined( 'GF_VERSION' ) ],
        ];
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        $tabs = $this->tabs();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter; no data is changed.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'dashboard';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice parameter set after a nonce-protected admin action.
        $notice = isset( $_GET['fct_notice'] ) ? sanitize_key( wp_unslash( $_GET['fct_notice'] ) ) : '';
        ?>
        <div class="wrap fct-admin">
            <div class="fct-header">
                <div>
                    <h1><?php esc_html_e( 'FormCourier Notifications Pro', 'formcourier-notifications-pro' ); ?></h1>
                    <p><?php esc_html_e( 'Route WordPress form submissions to Telegram and Slack with routing, templates, logs and retries.', 'formcourier-notifications-pro' ); ?></p>
                </div>
                <span class="fct-version">v<?php echo esc_html( FORMCOURIER_NOTIFICATIONS_PRO_VERSION ); ?></span>
            </div>

            <nav class="nav-tab-wrapper fct-tabs" aria-label="<?php esc_attr_e( 'FormCourier Notifications Pro sections', 'formcourier-notifications-pro' ); ?>">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php $this->render_notice( $notice ); ?>

            <div class="fct-content">
                <?php
                switch ( $tab ) {
                    case 'telegram':
                        $this->render_telegram_tab();
                        break;
                    case 'slack':
                        $this->render_slack_tab();
                        break;
                    case 'forms':
                        $this->render_forms_tab();
                        break;
                    case 'message':
                        $this->render_message_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    default:
                        $this->render_dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_notice( string $notice ): void {
        if ( 'test_success' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test message sent successfully.', 'formcourier-notifications-pro' ) . '</p></div>';
        } elseif ( 'test_error' === $notice ) {
            $error = get_transient( 'formcourier_notifications_pro_test_error' ) ?: __( 'Notification test failed.', 'formcourier-notifications-pro' );
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
        } elseif ( 'logs_cleared' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Logs cleared.', 'formcourier-notifications-pro' ) . '</p></div>';
        } elseif ( 'retry_success' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Message sent successfully on retry.', 'formcourier-notifications-pro' ) . '</p></div>';
        } elseif ( 'retry_error' === $notice ) {
            $error = get_transient( 'formcourier_notifications_pro_retry_error' ) ?: __( 'Retry failed.', 'formcourier-notifications-pro' );
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
        }
    }

    private function render_dashboard_tab(): void {
        $settings = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $providers = $this->providers();
        $enabled_providers = (array) $settings['providers'];
        $token_ready = ! empty( $settings['bot_token'] );
        $chat_ready = '' !== trim( (string) $settings['chat_id'] );
        $integration_ready = '1' === $settings['enabled'] && $token_ready && $chat_ready;
        $slack_destinations = isset( $settings['slack_destinations'] ) && is_array( $settings['slack_destinations'] ) ? $settings['slack_destinations'] : [];
        $slack_configured = false;
        foreach ( $slack_destinations as $slack_destination ) {
            if ( is_array( $slack_destination ) && '1' === ( $slack_destination['enabled'] ?? '1' ) && ! empty( $slack_destination['webhook_url'] ) ) { $slack_configured = true; break; }
        }
        $slack_ready = '1' === $settings['slack_enabled'] && $slack_configured;
        $active_forms = 0;
        foreach ( $providers as $provider ) {
            if ( $provider['active'] ) { $active_forms++; }
        }
        ?>
        <div class="fct-grid fct-grid-3">
            <?php $this->status_card( __( 'Telegram integration', 'formcourier-notifications-pro' ), $integration_ready ? __( 'Ready', 'formcourier-notifications-pro' ) : __( 'Needs setup', 'formcourier-notifications-pro' ), $integration_ready ); ?>
            <?php $this->status_card( __( 'Bot token', 'formcourier-notifications-pro' ), $token_ready ? __( 'Configured', 'formcourier-notifications-pro' ) : __( 'Not configured', 'formcourier-notifications-pro' ), $token_ready ); ?>
            <?php $this->status_card( __( 'Chat ID', 'formcourier-notifications-pro' ), $chat_ready ? __( 'Configured', 'formcourier-notifications-pro' ) : __( 'Not configured', 'formcourier-notifications-pro' ), $chat_ready ); ?>
            <?php $this->status_card( __( 'Slack integration', 'formcourier-notifications-pro' ), $slack_ready ? __( 'Ready', 'formcourier-notifications-pro' ) : __( 'Needs setup', 'formcourier-notifications-pro' ), $slack_ready ); ?>
        </div>

        <div class="fct-card">
            <div class="fct-card-heading">
                <div>
                    <h2><?php esc_html_e( 'Supported forms', 'formcourier-notifications-pro' ); ?></h2>
                    <?php /* translators: 1: Number of active supported form plugins, 2: Total number of supported form plugins. */ ?>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d supported form plugins are active on this site.', 'formcourier-notifications-pro' ), $active_forms, count( $providers ) ) ); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=forms' ) ); ?>"><?php esc_html_e( 'Manage Forms', 'formcourier-notifications-pro' ); ?></a>
            </div>
            <div class="fct-provider-list">
                <?php foreach ( $providers as $key => $provider ) :
                    $receives = in_array( $key, $enabled_providers, true );
                    ?>
                    <div class="fct-provider-row">
                        <strong><?php echo esc_html( $provider['label'] ); ?></strong>
                        <div class="fct-provider-statuses">
                            <span class="fct-badge <?php echo $provider['active'] ? 'is-success' : 'is-muted'; ?>"><?php echo esc_html( $provider['active'] ? __( 'Active', 'formcourier-notifications-pro' ) : __( 'Not active', 'formcourier-notifications-pro' ) ); ?></span>
                            <span class="fct-badge <?php echo $receives ? 'is-info' : 'is-muted'; ?>"><?php echo esc_html( $receives ? __( 'Enabled', 'formcourier-notifications-pro' ) : __( 'Disabled', 'formcourier-notifications-pro' ) ); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fct-grid fct-grid-2">
            <div class="fct-card">
                <h2><?php esc_html_e( 'Quick actions', 'formcourier-notifications-pro' ); ?></h2>
                <p><?php esc_html_e( 'Configure Telegram or Slack, customize message templates, or review recent deliveries.', 'formcourier-notifications-pro' ); ?></p>
                <div class="fct-actions">
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=telegram' ) ); ?>"><?php esc_html_e( 'Telegram Settings', 'formcourier-notifications-pro' ); ?></a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=slack' ) ); ?>"><?php esc_html_e( 'Slack Settings', 'formcourier-notifications-pro' ); ?></a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=message' ) ); ?>"><?php esc_html_e( 'Edit Message', 'formcourier-notifications-pro' ); ?></a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs' ) ); ?>"><?php esc_html_e( 'View Logs', 'formcourier-notifications-pro' ); ?></a>
                </div>
            </div>
            <div class="fct-card">
                <h2><?php esc_html_e( 'Recent activity', 'formcourier-notifications-pro' ); ?></h2>
                <?php $logs = FormCourier_Notifications_Pro_Logger::all(); ?>
                <?php if ( empty( $logs ) ) : ?>
                    <p class="fct-muted"><?php esc_html_e( 'No messages have been logged yet.', 'formcourier-notifications-pro' ); ?></p>
                <?php else : $latest = $logs[0]; ?>
                    <p><strong><?php echo esc_html( $latest['provider'] ?? '' ); ?></strong><br><?php echo esc_html( $latest['form_name'] ?? '' ); ?></p>
                    <p><span class="fct-badge <?php echo 'success' === ( $latest['status'] ?? '' ) ? 'is-success' : 'is-error'; ?>"><?php echo esc_html( ucfirst( (string) ( $latest['status'] ?? '' ) ) ); ?></span> <span class="fct-muted"><?php echo esc_html( $latest['time'] ?? '' ); ?></span></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function status_card( string $title, string $value, bool $ok ): void {
        ?>
        <div class="fct-card fct-status-card">
            <span class="fct-status-dot <?php echo $ok ? 'is-success' : 'is-warning'; ?>"></span>
            <div><span class="fct-card-label"><?php echo esc_html( $title ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div>
        </div>
        <?php
    }

    private function render_telegram_tab(): void {
        $settings = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $destinations = $this->get_destinations();
        if ( empty( $destinations ) ) {
            $destinations = [ 'sales' => [ 'name' => 'Sales', 'bot_token' => '', 'chat_id' => '', 'enabled' => '1' ] ];
        }
        ?>
        <div class="fct-card fct-card-form">
            <h2><?php esc_html_e( 'Telegram destinations', 'formcourier-notifications-pro' ); ?></h2>
            <p><?php esc_html_e( 'Create multiple Telegram destinations for Sales, Support, HR or any other team.', 'formcourier-notifications-pro' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'formcourier_notifications_pro_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="telegram">
                <p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>> <?php esc_html_e( 'Enable Telegram notifications', 'formcourier-notifications-pro' ); ?></label></p>
                <div id="fcnp-destinations">
                    <?php foreach ( $destinations as $id => $destination ) : ?>
                        <div class="fct-destination" data-id="<?php echo esc_attr( $id ); ?>">
                            <div class="fct-card-heading"><h3><?php echo esc_html( $destination['name'] ?? $id ); ?></h3><button type="button" class="button-link-delete fcnp-remove-destination"><?php esc_html_e( 'Remove', 'formcourier-notifications-pro' ); ?></button></div>
                            <div class="fct-destination-grid">
                                <p><label><?php esc_html_e( 'Name', 'formcourier-notifications-pro' ); ?><br><input class="regular-text fcnp-destination-name" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[destinations][<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $destination['name'] ?? '' ); ?>"></label></p>
                                <p><label><?php esc_html_e( 'Bot Token', 'formcourier-notifications-pro' ); ?><br><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[destinations][<?php echo esc_attr( $id ); ?>][bot_token]" value="" placeholder="<?php echo ! empty( $destination['bot_token'] ) ? esc_attr__( 'Saved - enter a new token to replace it', 'formcourier-notifications-pro' ) : ''; ?>"></label></p>
                                <p><label><?php esc_html_e( 'Chat ID', 'formcourier-notifications-pro' ); ?><br><input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[destinations][<?php echo esc_attr( $id ); ?>][chat_id]" value="<?php echo esc_attr( $destination['chat_id'] ?? '' ); ?>"></label></p>
                                <p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[destinations][<?php echo esc_attr( $id ); ?>][enabled]" value="1" <?php checked( $destination['enabled'] ?? '1', '1' ); ?>> <?php esc_html_e( 'Enabled', 'formcourier-notifications-pro' ); ?></label></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="fcnp-add-destination"><?php esc_html_e( 'Add Destination', 'formcourier-notifications-pro' ); ?></button></p>
                <table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Default destination', 'formcourier-notifications-pro' ); ?></th><td><select name="<?php echo esc_attr( self::OPTION ); ?>[default_destination]" id="fcnp-default-destination"><?php foreach ( $destinations as $id => $destination ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $this->get_default_destination_id(), $id ); ?>><?php echo esc_html( $destination['name'] ?? $id ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Forms without a custom route are sent here.', 'formcourier-notifications-pro' ); ?></p></td></tr></table>
                <p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'], '1' ); ?>> <?php esc_html_e( 'Delete plugin settings and logs when uninstalled', 'formcourier-notifications-pro' ); ?></label></p>
                <?php submit_button( __( 'Save Telegram Destinations', 'formcourier-notifications-pro' ) ); ?>
            </form>
        </div>
        <div class="fct-card"><h2><?php esc_html_e( 'Connection test', 'formcourier-notifications-pro' ); ?></h2><p><?php esc_html_e( 'Choose a saved destination and send a test message.', 'formcourier-notifications-pro' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="formcourier_notifications_pro_test"><?php wp_nonce_field( 'formcourier_notifications_pro_test' ); ?><select name="destination_id"><?php foreach ( $this->get_destinations() as $id => $destination ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $destination['name'] ?? $id ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'Send Test Message', 'formcourier-notifications-pro' ), 'secondary', 'submit', false ); ?></form></div>
        <?php
    }

    private function render_slack_tab(): void {
        $settings = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $destinations = $this->get_slack_destinations();
        if ( empty( $destinations ) ) {
            $destinations = [ 'sales' => [ 'name' => 'Sales', 'webhook_url' => '', 'enabled' => '1' ] ];
        }
        ?>
        <div class="fct-card fct-card-form">
            <h2><?php esc_html_e( 'Slack destinations', 'formcourier-notifications-pro' ); ?></h2>
            <p><?php esc_html_e( 'Create Slack destinations using Incoming Webhook URLs for Sales, Support, HR or other channels.', 'formcourier-notifications-pro' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'formcourier_notifications_pro_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="slack">
                <p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[slack_enabled]" value="1" <?php checked( $settings['slack_enabled'], '1' ); ?>> <?php esc_html_e( 'Enable Slack notifications', 'formcourier-notifications-pro' ); ?></label></p>
                <div id="fcnp-slack-destinations">
                    <?php foreach ( $destinations as $id => $destination ) : ?>
                        <div class="fct-destination fct-slack-destination" data-id="<?php echo esc_attr( $id ); ?>">
                            <div class="fct-card-heading"><h3><?php echo esc_html( $destination['name'] ?? $id ); ?></h3><button type="button" class="button-link-delete fcnp-remove-slack-destination"><?php esc_html_e( 'Remove', 'formcourier-notifications-pro' ); ?></button></div>
                            <div class="fct-destination-grid">
                                <p><label><?php esc_html_e( 'Name', 'formcourier-notifications-pro' ); ?><br><input class="regular-text fcnp-slack-destination-name" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[slack_destinations][<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $destination['name'] ?? '' ); ?>"></label></p>
                                <p><label><?php esc_html_e( 'Incoming Webhook URL', 'formcourier-notifications-pro' ); ?><br><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[slack_destinations][<?php echo esc_attr( $id ); ?>][webhook_url]" value="" placeholder="<?php echo ! empty( $destination['webhook_url'] ) ? esc_attr__( 'Saved - enter a new URL to replace it', 'formcourier-notifications-pro' ) : ''; ?>"></label></p>
                                <p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[slack_destinations][<?php echo esc_attr( $id ); ?>][enabled]" value="1" <?php checked( $destination['enabled'] ?? '1', '1' ); ?>> <?php esc_html_e( 'Enabled', 'formcourier-notifications-pro' ); ?></label></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="fcnp-add-slack-destination"><?php esc_html_e( 'Add Destination', 'formcourier-notifications-pro' ); ?></button></p>
                <table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Default destination', 'formcourier-notifications-pro' ); ?></th><td><select name="<?php echo esc_attr( self::OPTION ); ?>[slack_default_destination]" id="fcnp-slack-default-destination"><?php foreach ( $destinations as $id => $destination ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $this->get_slack_default_destination_id(), $id ); ?>><?php echo esc_html( $destination['name'] ?? $id ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Forms without a custom Slack route are sent here.', 'formcourier-notifications-pro' ); ?></p></td></tr></table>
                <?php submit_button( __( 'Save Slack Destinations', 'formcourier-notifications-pro' ) ); ?>
            </form>
        </div>
        <div class="fct-card"><h2><?php esc_html_e( 'Connection test', 'formcourier-notifications-pro' ); ?></h2><p><?php esc_html_e( 'Choose a saved Slack destination and send a test message.', 'formcourier-notifications-pro' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="formcourier_notifications_pro_slack_test"><?php wp_nonce_field( 'formcourier_notifications_pro_slack_test' ); ?><select name="destination_id"><?php foreach ( $this->get_slack_destinations() as $id => $destination ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $destination['name'] ?? $id ); ?></option><?php endforeach; ?></select> <?php submit_button( __( 'Send Test Message', 'formcourier-notifications-pro' ), 'secondary', 'submit', false ); ?></form></div>
        <?php
    }

    private function render_forms_tab(): void {
        $settings = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $providers = $this->providers();
        ?>
        <div class="fct-card fct-card-form">
            <h2><?php esc_html_e( 'Form providers', 'formcourier-notifications-pro' ); ?></h2>
            <p><?php esc_html_e( 'Choose which supported form plugins are allowed to send submissions to notification channels.', 'formcourier-notifications-pro' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'formcourier_notifications_pro_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="forms">
                <div class="fct-provider-cards">
                    <?php foreach ( $providers as $key => $provider ) : ?>
                        <label class="fct-provider-card">
                            <span class="fct-provider-check"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[providers][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $settings['providers'], true ) ); ?>></span>
                            <span class="fct-provider-copy"><strong><?php echo esc_html( $provider['label'] ); ?></strong><small><?php echo esc_html( $provider['active'] ? __( 'Plugin detected and active', 'formcourier-notifications-pro' ) : __( 'Plugin not detected', 'formcourier-notifications-pro' ) ); ?></small></span>
                            <span class="fct-badge <?php echo $provider['active'] ? 'is-success' : 'is-muted'; ?>"><?php echo esc_html( $provider['active'] ? __( 'Active', 'formcourier-notifications-pro' ) : __( 'Inactive', 'formcourier-notifications-pro' ) ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php $known_forms = FormCourier_Notifications_Pro_Form_Discovery::all(); $destinations = $this->get_destinations(); $slack_destinations = $this->get_slack_destinations(); ?>
                <?php if ( ! empty( $known_forms ) && ! empty( $destinations ) ) : ?>
                    <hr>
                    <h2><?php esc_html_e( 'Form routing', 'formcourier-notifications-pro' ); ?></h2>
                    <p><?php esc_html_e( 'Choose one or more Telegram destinations for each form. If none are selected, the form uses the default destination.', 'formcourier-notifications-pro' ); ?></p>
                    <table class="widefat striped fct-routing-table">
                        <thead><tr><th><?php esc_html_e( 'Provider', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Form', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Destinations', 'formcourier-notifications-pro' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $known_forms as $route_key => $form ) :
                            $saved_route = $settings['form_routes'][ $route_key ] ?? [];
                            if ( is_string( $saved_route ) && '' !== $saved_route ) { $saved_route = [ $saved_route ]; }
                            $saved_route = is_array( $saved_route ) ? array_map( 'sanitize_key', $saved_route ) : [];
                            ?>
                            <tr>
                                <td><?php echo esc_html( $form['provider_label'] ?? '' ); ?></td>
                                <td><?php echo esc_html( ( $form['form_name'] ?? '' ) . ' (#' . ( $form['form_id'] ?? '' ) . ')' ); ?></td>
                                <td>
                                    <div class="fct-route-destinations">
                                        <?php foreach ( $destinations as $destination_id => $destination ) : ?>
                                            <label class="fct-route-option">
                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[form_routes][<?php echo esc_attr( $route_key ); ?>][]" value="<?php echo esc_attr( $destination_id ); ?>" <?php checked( in_array( $destination_id, $saved_route, true ) ); ?>>
                                                <span><?php echo esc_html( $destination['name'] ?? $destination_id ); ?></span>
                                                <?php if ( $destination_id === $this->get_default_destination_id() ) : ?><small><?php esc_html_e( 'Default', 'formcourier-notifications-pro' ); ?></small><?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php esc_html_e( 'No selection = use the default destination.', 'formcourier-notifications-pro' ); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <hr><h2><?php esc_html_e( 'Form routing', 'formcourier-notifications-pro' ); ?></h2><p class="fct-muted"><?php esc_html_e( 'No forms were found in the active supported form plugins.', 'formcourier-notifications-pro' ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $known_forms ) && ! empty( $slack_destinations ) ) : ?>
                    <hr>
                    <h2><?php esc_html_e( 'Slack form routing', 'formcourier-notifications-pro' ); ?></h2>
                    <p><?php esc_html_e( 'Choose one or more Slack destinations for each form. If none are selected, the form uses the default Slack destination.', 'formcourier-notifications-pro' ); ?></p>
                    <table class="widefat striped fct-routing-table">
                        <thead><tr><th><?php esc_html_e( 'Provider', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Form', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Slack destinations', 'formcourier-notifications-pro' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $known_forms as $route_key => $form ) :
                            $saved_slack_route = $settings['slack_form_routes'][ $route_key ] ?? [];
                            if ( is_string( $saved_slack_route ) && '' !== $saved_slack_route ) { $saved_slack_route = [ $saved_slack_route ]; }
                            $saved_slack_route = is_array( $saved_slack_route ) ? array_map( 'sanitize_key', $saved_slack_route ) : [];
                            ?>
                            <tr>
                                <td><?php echo esc_html( $form['provider_label'] ?? '' ); ?></td>
                                <td><?php echo esc_html( ( $form['form_name'] ?? '' ) . ' (#' . ( $form['form_id'] ?? '' ) . ')' ); ?></td>
                                <td><div class="fct-route-destinations">
                                    <?php foreach ( $slack_destinations as $destination_id => $destination ) : ?>
                                        <label class="fct-route-option"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[slack_form_routes][<?php echo esc_attr( $route_key ); ?>][]" value="<?php echo esc_attr( $destination_id ); ?>" <?php checked( in_array( $destination_id, $saved_slack_route, true ) ); ?>><span><?php echo esc_html( $destination['name'] ?? $destination_id ); ?></span><?php if ( $destination_id === $this->get_slack_default_destination_id() ) : ?><small><?php esc_html_e( 'Default', 'formcourier-notifications-pro' ); ?></small><?php endif; ?></label>
                                    <?php endforeach; ?>
                                </div><p class="description"><?php esc_html_e( 'No selection = use the default Slack destination.', 'formcourier-notifications-pro' ); ?></p></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php if ( ! empty( $known_forms ) && ( ! empty( $destinations ) || ! empty( $slack_destinations ) ) ) : ?>
                    <hr>
                    <?php $has_advanced_rules = ! empty( $settings['conditional_rules'] ); ?>
                    <details class="fcnp-advanced-routing" <?php echo $has_advanced_rules ? 'open' : ''; ?>>
                        <summary>
                            <strong><?php esc_html_e( 'Advanced Routing (Optional)', 'formcourier-notifications-pro' ); ?></strong>
                            <span><?php esc_html_e( 'Use multiple conditions only when you need them.', 'formcourier-notifications-pro' ); ?></span>
                        </summary>
                        <div class="fcnp-advanced-routing-body">
                            <div class="fct-card-heading fct-rules-heading">
                                <div>
                                    <h2><?php esc_html_e( 'Advanced routing', 'formcourier-notifications-pro' ); ?></h2>
                                    <p><?php esc_html_e( 'Leave this section empty to keep normal form routing and default destinations. Advanced rules are never required for sending messages.', 'formcourier-notifications-pro' ); ?></p>
                                    <p class="description"><strong><?php esc_html_e( 'Automatic fallback:', 'formcourier-notifications-pro' ); ?></strong> <?php esc_html_e( 'If no Advanced Routing rule matches a submission, the normal Form Routes and default destinations are used automatically.', 'formcourier-notifications-pro' ); ?></p>
                                </div>
                                <button type="button" class="button" id="fcnp-add-rule"><?php esc_html_e( 'Add Rule', 'formcourier-notifications-pro' ); ?></button>
                            </div>
                            <div id="fcnp-rules">
                                <?php foreach ( (array) ( $settings['conditional_rules'] ?? [] ) as $index => $rule ) : ?>
                                    <?php $this->render_rule_row( (int) $index, $rule, $known_forms, $destinations, $slack_destinations ); ?>
                                <?php endforeach; ?>
                            </div>
                            <template id="fcnp-rule-template"><?php $this->render_rule_row( '__INDEX__', [], $known_forms, $destinations, $slack_destinations ); ?></template>
                            <p class="description"><?php esc_html_e( 'ALL means every condition must match. ANY means at least one condition must match. Higher priority rules are evaluated first; when Replace actions overlap, the higher priority rule takes precedence. Stop processing skips lower-priority rules after a match. A matching rule can route Telegram, Slack, or both. If no rule matches, normal Form Routes and default destinations are used automatically. Existing 1.8.0 single-condition rules remain compatible.', 'formcourier-notifications-pro' ); ?></p>
                        </div>
                    </details>
                <?php endif; ?>
                <?php submit_button( __( 'Save Form Settings', 'formcourier-notifications-pro' ) ); ?>
            </form>
        </div>
        <?php
    }

    /** @param int|string $index @param array<string,mixed> $rule @param array<string,array<string,mixed>> $forms @param array<string,array<string,mixed>> $destinations @param array<string,array<string,mixed>> $slack_destinations */
    private function render_rule_row( $index, array $rule, array $forms, array $destinations, array $slack_destinations ): void {
        $prefix = self::OPTION . '[conditional_rules][' . $index . ']';
        $mode = (string) ( $rule['mode'] ?? 'replace' );
        $match_mode = 'any' === (string) ( $rule['match_mode'] ?? 'all' ) ? 'any' : 'all';
        $priority = max( 0, min( 999, absint( $rule['priority'] ?? 0 ) ) );
        $stop_processing = '1' === (string) ( $rule['stop_processing'] ?? '0' );
        $selected_destinations = array_map( 'sanitize_key', (array) ( $rule['destinations'] ?? [] ) );
        $selected_slack_destinations = array_map( 'sanitize_key', (array) ( $rule['slack_destinations'] ?? [] ) );
        $selected_form_key = (string) ( $rule['form_key'] ?? '' );
        if ( '' === $selected_form_key && ! empty( $forms ) ) { $selected_form_key = (string) array_key_first( $forms ); }
        $conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : [];
        if ( empty( $conditions ) && '' !== trim( (string) ( $rule['field'] ?? '' ) ) ) {
            $conditions[] = [ 'field' => $rule['field'], 'operator' => $rule['operator'] ?? 'equals', 'value' => $rule['value'] ?? '' ];
        }
        if ( empty( $conditions ) ) { $conditions[] = []; }
        ?>
        <div class="fct-rule-row" data-rule-index="<?php echo esc_attr( (string) $index ); ?>">
            <div class="fct-rule-top">
                <label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( $rule['enabled'] ?? '1', '1' ); ?>> <?php esc_html_e( 'Enabled', 'formcourier-notifications-pro' ); ?></label>
                <button type="button" class="button-link-delete fcnp-remove-rule"><?php esc_html_e( 'Remove rule', 'formcourier-notifications-pro' ); ?></button>
            </div>
            <div class="fct-rule-meta-grid">
                <label><?php esc_html_e( 'Form', 'formcourier-notifications-pro' ); ?><select class="fcnp-rule-form" name="<?php echo esc_attr( $prefix ); ?>[form_key]">
                    <?php foreach ( $forms as $form_key => $form ) : ?><option value="<?php echo esc_attr( $form_key ); ?>" <?php selected( $selected_form_key, $form_key ); ?>><?php echo esc_html( ( $form['provider_label'] ?? '' ) . ' - ' . ( $form['form_name'] ?? '' ) . ' (#' . ( $form['form_id'] ?? '' ) . ')' ); ?></option><?php endforeach; ?>
                </select></label>
                <label><?php esc_html_e( 'Match', 'formcourier-notifications-pro' ); ?><select name="<?php echo esc_attr( $prefix ); ?>[match_mode]">
                    <option value="all" <?php selected( $match_mode, 'all' ); ?>><?php esc_html_e( 'ALL conditions (AND)', 'formcourier-notifications-pro' ); ?></option>
                    <option value="any" <?php selected( $match_mode, 'any' ); ?>><?php esc_html_e( 'ANY condition (OR)', 'formcourier-notifications-pro' ); ?></option>
                </select></label>
                <label><?php esc_html_e( 'Action', 'formcourier-notifications-pro' ); ?><select name="<?php echo esc_attr( $prefix ); ?>[mode]"><option value="replace" <?php selected( $mode, 'replace' ); ?>><?php esc_html_e( 'Replace form destinations', 'formcourier-notifications-pro' ); ?></option><option value="add" <?php selected( $mode, 'add' ); ?>><?php esc_html_e( 'Add destinations', 'formcourier-notifications-pro' ); ?></option></select></label>
            </div>
            <div class="fcnp-rule-flow-controls">
                <label class="fcnp-rule-priority"><?php esc_html_e( 'Priority', 'formcourier-notifications-pro' ); ?><input type="number" min="0" max="999" step="1" name="<?php echo esc_attr( $prefix ); ?>[priority]" value="<?php echo esc_attr( (string) $priority ); ?>"><span><?php esc_html_e( 'Higher numbers run first.', 'formcourier-notifications-pro' ); ?></span></label>
                <label class="fcnp-stop-processing"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[stop_processing]" value="1" <?php checked( $stop_processing ); ?>><span><strong><?php esc_html_e( 'Stop processing lower-priority rules', 'formcourier-notifications-pro' ); ?></strong><small><?php esc_html_e( 'When this rule matches, lower-priority Advanced Routing rules are skipped.', 'formcourier-notifications-pro' ); ?></small></span></label>
            </div>
            <div class="fcnp-rule-conditions">
                <div class="fcnp-rule-conditions-head"><strong><?php esc_html_e( 'Conditions', 'formcourier-notifications-pro' ); ?></strong><button type="button" class="button button-small fcnp-add-condition"><?php esc_html_e( 'Add Condition', 'formcourier-notifications-pro' ); ?></button></div>
                <div class="fcnp-condition-list">
                    <?php foreach ( $conditions as $condition_index => $condition ) : $this->render_condition_row( $index, $condition_index, is_array( $condition ) ? $condition : [], $selected_form_key ); endforeach; ?>
                </div>
                <template class="fcnp-condition-template"><?php $this->render_condition_row( $index, '__COND__', [], $selected_form_key ); ?></template>
            </div>
            <?php if ( ! empty( $destinations ) ) : ?>
                <div class="fct-rule-destinations"><strong><?php esc_html_e( 'Telegram destinations', 'formcourier-notifications-pro' ); ?></strong><div class="fct-route-destinations">
                    <?php foreach ( $destinations as $destination_id => $destination ) : ?><label class="fct-route-option"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[destinations][]" value="<?php echo esc_attr( $destination_id ); ?>" <?php checked( in_array( $destination_id, $selected_destinations, true ) ); ?>><span><?php echo esc_html( $destination['name'] ?? $destination_id ); ?></span></label><?php endforeach; ?>
                </div></div>
            <?php endif; ?>
            <?php if ( ! empty( $slack_destinations ) ) : ?>
                <div class="fct-rule-destinations"><strong><?php esc_html_e( 'Slack destinations', 'formcourier-notifications-pro' ); ?></strong><div class="fct-route-destinations">
                    <?php foreach ( $slack_destinations as $destination_id => $destination ) : ?><label class="fct-route-option"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[slack_destinations][]" value="<?php echo esc_attr( $destination_id ); ?>" <?php checked( in_array( $destination_id, $selected_slack_destinations, true ) ); ?>><span><?php echo esc_html( $destination['name'] ?? $destination_id ); ?></span></label><?php endforeach; ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @param int|string $rule_index @param int|string $condition_index @param array<string,mixed> $condition */
    private function render_condition_row( $rule_index, $condition_index, array $condition, string $selected_form_key ): void {
        $prefix = self::OPTION . '[conditional_rules][' . $rule_index . '][conditions][' . $condition_index . ']';
        $operator = (string) ( $condition['operator'] ?? 'equals' );
        $selected_field = sanitize_text_field( (string) ( $condition['field'] ?? '' ) );
        $all_form_fields = FormCourier_Notifications_Pro_Form_Discovery::fields();
        $available_fields = isset( $all_form_fields[ $selected_form_key ] ) && is_array( $all_form_fields[ $selected_form_key ] ) ? $all_form_fields[ $selected_form_key ] : [];
        ?>
        <div class="fcnp-condition-row">
            <label><?php esc_html_e( 'Field', 'formcourier-notifications-pro' ); ?><select class="fcnp-rule-field" name="<?php echo esc_attr( $prefix ); ?>[field]" data-selected="<?php echo esc_attr( $selected_field ); ?>">
                <option value=""><?php esc_html_e( 'Select a field', 'formcourier-notifications-pro' ); ?></option>
                <?php foreach ( $available_fields as $field_key => $field_label ) : ?><option value="<?php echo esc_attr( $field_key ); ?>" <?php selected( $selected_field, $field_key ); ?>><?php echo esc_html( $field_label . ' (' . $field_key . ')' ); ?></option><?php endforeach; ?>
                <?php if ( '' !== $selected_field && ! isset( $available_fields[ $selected_field ] ) ) : ?><option value="<?php echo esc_attr( $selected_field ); ?>" selected><?php echo esc_html( $selected_field . ' - ' . __( 'Custom / previously saved field', 'formcourier-notifications-pro' ) ); ?></option><?php endif; ?>
            </select></label>
            <label><?php esc_html_e( 'Operator', 'formcourier-notifications-pro' ); ?><select class="fcnp-rule-operator" name="<?php echo esc_attr( $prefix ); ?>[operator]">
                <?php foreach ( [ 'equals' => 'Equals', 'not_equals' => 'Does not equal', 'contains' => 'Contains', 'not_contains' => 'Does not contain', 'greater_than' => 'Greater than', 'less_than' => 'Less than', 'is_empty' => 'Is empty', 'is_not_empty' => 'Is not empty' ] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $operator, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
            </select></label>
            <label class="fcnp-rule-value-wrap"><?php esc_html_e( 'Value', 'formcourier-notifications-pro' ); ?><input type="text" name="<?php echo esc_attr( $prefix ); ?>[value]" value="<?php echo esc_attr( $condition['value'] ?? '' ); ?>"></label>
            <button type="button" class="button-link-delete fcnp-remove-condition"><?php esc_html_e( 'Remove', 'formcourier-notifications-pro' ); ?></button>
        </div>
        <?php
    }

    private function render_message_tab(): void {
        $settings       = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $forms          = FormCourier_Notifications_Pro_Form_Discovery::all();
        $form_fields    = FormCourier_Notifications_Pro_Form_Discovery::fields();
        $form_templates = isset( $settings['form_message_templates'] ) && is_array( $settings['form_message_templates'] ) ? $settings['form_message_templates'] : [];
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'formcourier_notifications_pro_group' ); ?>
            <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="message">

            <div class="fct-grid fct-grid-message">
                <div class="fct-card fct-card-form">
                    <h2><?php esc_html_e( 'Default message template', 'formcourier-notifications-pro' ); ?></h2>
                    <p><?php esc_html_e( 'This template is used by every form that does not have its own custom template.', 'formcourier-notifications-pro' ); ?></p>
                    <textarea id="fct-template" class="large-text code fct-template" rows="14" name="<?php echo esc_attr( self::OPTION ); ?>[message_template]"><?php echo esc_textarea( $settings['message_template'] ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Telegram HTML is supported. Slack receives a clean plain-text version of the same template.', 'formcourier-notifications-pro' ); ?></p>
                </div>
                <div class="fct-card">
                    <h2><?php esc_html_e( 'Global placeholders', 'formcourier-notifications-pro' ); ?></h2>
                    <div class="fct-placeholders">
                        <?php foreach ( [ '{form_provider}', '{provider}', '{form_id}', '{form_name}', '{destination}', '{submitted_at}', '{all_fields}', '{page_url}', '{site_name}', '{site_url}', '{date}', '{time}', '{field:FIELD_NAME}' ] as $placeholder ) : ?>
                            <code><?php echo esc_html( $placeholder ); ?></code>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php esc_html_e( 'Use {field:FIELD_NAME} for a specific form field. Form-specific field placeholders are shown below.', 'formcourier-notifications-pro' ); ?></p>
                    <div class="fct-preview">
                        <span class="fct-preview-label"><?php esc_html_e( 'Example', 'formcourier-notifications-pro' ); ?></span>
                        <div class="fct-telegram-bubble">
                            <strong>🆕 New form submission</strong><br><br>
                            <strong>Form:</strong> Contact Form 7<br><br>
                            <strong>Name:</strong> John Smith<br>
                            <strong>Email:</strong> john@example.com<br>
                            <strong>Phone:</strong> +44 7700 900123<br>
                            <strong>Message:</strong> I would like more information.
                        </div>
                    </div>
                </div>
            </div>

            <div class="fct-card">
                <div class="fct-card-heading">
                    <div>
                        <h2><?php esc_html_e( 'Form-specific templates', 'formcourier-notifications-pro' ); ?></h2>
                        <p><?php esc_html_e( 'Enable a custom template only for forms that need a different notification message.', 'formcourier-notifications-pro' ); ?></p>
                    </div>
                </div>

                <?php if ( empty( $forms ) ) : ?>
                    <div class="fct-empty"><span class="dashicons dashicons-feedback"></span><p><?php esc_html_e( 'No supported forms were discovered yet.', 'formcourier-notifications-pro' ); ?></p></div>
                <?php else : ?>
                    <div class="fct-form-template-list">
                        <?php foreach ( $forms as $route_key => $form ) :
                            $custom_template = isset( $form_templates[ $route_key ] ) ? (string) $form_templates[ $route_key ] : '';
                            $is_enabled      = '' !== trim( $custom_template );
                            $fields          = isset( $form_fields[ $route_key ] ) && is_array( $form_fields[ $route_key ] ) ? $form_fields[ $route_key ] : [];
                            ?>
                            <div class="fct-form-template-card <?php echo $is_enabled ? 'is-enabled' : ''; ?>">
                                <div class="fct-form-template-head">
                                    <div>
                                        <strong><?php echo esc_html( (string) ( $form['form_name'] ?? '' ) ); ?></strong>
                                        <span><?php echo esc_html( (string) ( $form['provider_label'] ?? '' ) . ' #' . (string) ( $form['form_id'] ?? '' ) ); ?></span>
                                    </div>
                                    <label class="fct-template-toggle">
                                        <input class="fcnp-template-enabled" type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[form_template_enabled][<?php echo esc_attr( $route_key ); ?>]" value="1" <?php checked( $is_enabled ); ?>>
                                        <?php esc_html_e( 'Use custom template', 'formcourier-notifications-pro' ); ?>
                                    </label>
                                </div>
                                <div class="fct-form-template-body" <?php echo $is_enabled ? '' : 'hidden'; ?>>
                                    <?php if ( ! empty( $fields ) ) : ?>
                                        <div class="fct-form-field-placeholders">
                                            <span><?php esc_html_e( 'Fields:', 'formcourier-notifications-pro' ); ?></span>
                                            <?php foreach ( $fields as $field_key => $field_label ) : ?>
                                                <code title="<?php echo esc_attr( (string) $field_label ); ?>"><?php echo esc_html( '{field:' . $field_key . '}' ); ?></code>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <textarea class="large-text code fct-form-template-textarea" rows="10" name="<?php echo esc_attr( self::OPTION ); ?>[form_message_templates][<?php echo esc_attr( $route_key ); ?>]" placeholder="<?php esc_attr_e( 'Enter a custom template for this form.', 'formcourier-notifications-pro' ); ?>"><?php echo esc_textarea( $custom_template ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'When disabled, this form automatically uses the Default message template above.', 'formcourier-notifications-pro' ); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php submit_button( __( 'Save Message Templates', 'formcourier-notifications-pro' ) ); ?>
            </div>
        </form>
        <?php
    }

    private function render_logs_tab(): void {
        $all_logs = FormCourier_Notifications_Pro_Logger::all();

        $channel_filter     = isset( $_GET['log_channel'] ) ? sanitize_key( wp_unslash( $_GET['log_channel'] ) ) : '';
        $provider_filter    = isset( $_GET['log_provider'] ) ? sanitize_text_field( wp_unslash( $_GET['log_provider'] ) ) : '';
        $status_filter      = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( $_GET['log_status'] ) ) : '';
        $destination_filter = isset( $_GET['log_destination'] ) ? sanitize_text_field( wp_unslash( $_GET['log_destination'] ) ) : '';
        $search_filter      = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : '';
        $date_from_filter   = isset( $_GET['log_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date_from'] ) ) : '';
        $date_to_filter     = isset( $_GET['log_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date_to'] ) ) : '';
        $per_page            = isset( $_GET['log_per_page'] ) ? absint( $_GET['log_per_page'] ) : 20;
        $current_page        = isset( $_GET['log_paged'] ) ? max( 1, absint( $_GET['log_paged'] ) ) : 1;
        if ( ! in_array( $per_page, [ 20, 50, 100 ], true ) ) { $per_page = 20; }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from_filter ) ) { $date_from_filter = ''; }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to_filter ) ) { $date_to_filter = ''; }

        $channels     = [];
        $providers    = [];
        $destinations = [];
        foreach ( $all_logs as $log ) {
            if ( ! is_array( $log ) ) { continue; }
            $channel_id = sanitize_key( (string) ( $log['channel_id'] ?? '' ) );
            $channel_label = sanitize_text_field( (string) ( $log['channel'] ?? ucfirst( $channel_id ) ) );
            if ( '' !== $channel_id ) { $channels[ $channel_id ] = $channel_label; }

            $provider = sanitize_text_field( (string) ( $log['provider'] ?? '' ) );
            if ( '' !== $provider ) { $providers[ $provider ] = $provider; }

            $destination = sanitize_text_field( (string) ( $log['destination'] ?? ( $log['destination_id'] ?? '' ) ) );
            if ( '' !== $destination ) { $destinations[ $destination ] = $destination; }
        }
        natcasesort( $providers );
        natcasesort( $destinations );

        $logs = array_values(
            array_filter(
                $all_logs,
                static function ( $log ) use ( $channel_filter, $provider_filter, $status_filter, $destination_filter, $search_filter, $date_from_filter, $date_to_filter ): bool {
                    if ( ! is_array( $log ) ) { return false; }
                    if ( '' !== $channel_filter && $channel_filter !== sanitize_key( (string) ( $log['channel_id'] ?? '' ) ) ) { return false; }
                    if ( '' !== $provider_filter && $provider_filter !== (string) ( $log['provider'] ?? '' ) ) { return false; }
                    if ( '' !== $status_filter && $status_filter !== sanitize_key( (string) ( $log['status'] ?? '' ) ) ) { return false; }
                    $destination = (string) ( $log['destination'] ?? ( $log['destination_id'] ?? '' ) );
                    if ( '' !== $destination_filter && $destination_filter !== $destination ) { return false; }

                    $log_time = (string) ( $log['time'] ?? '' );
                    $log_date = substr( $log_time, 0, 10 );
                    if ( '' !== $date_from_filter && ( '' === $log_date || $log_date < $date_from_filter ) ) { return false; }
                    if ( '' !== $date_to_filter && ( '' === $log_date || $log_date > $date_to_filter ) ) { return false; }

                    if ( '' !== $search_filter ) {
                        $haystack = implode( ' ', [
                            (string) ( $log['channel'] ?? '' ),
                            (string) ( $log['provider'] ?? '' ),
                            (string) ( $log['provider_key'] ?? '' ),
                            (string) ( $log['form_id'] ?? '' ),
                            (string) ( $log['form_name'] ?? '' ),
                            $destination,
                            (string) ( $log['status'] ?? '' ),
                            (string) ( $log['message'] ?? '' ),
                            (string) ( $log['last_error'] ?? '' ),
                            (string) ( $log['provider_response'] ?? '' ),
                            (string) ( $log['submission_id'] ?? '' ),
                        ] );
                        if ( false === stripos( $haystack, $search_filter ) ) { return false; }
                    }
                    return true;
                }
            )
        );

        $filtered_count = count( $logs );
        $log_stats = [
            'success'  => 0,
            'error'    => 0,
            'telegram' => 0,
            'slack'    => 0,
        ];
        foreach ( $logs as $log ) {
            if ( ! is_array( $log ) ) { continue; }
            $log_status  = sanitize_key( (string) ( $log['status'] ?? '' ) );
            $log_channel = sanitize_key( (string) ( $log['channel_id'] ?? '' ) );
            if ( isset( $log_stats[ $log_status ] ) ) { $log_stats[ $log_status ]++; }
            if ( isset( $log_stats[ $log_channel ] ) ) { $log_stats[ $log_channel ]++; }
        }
        $total_pages    = max( 1, (int) ceil( $filtered_count / $per_page ) );
        if ( $current_page > $total_pages ) { $current_page = $total_pages; }
        $page_offset = ( $current_page - 1 ) * $per_page;
        $paged_logs  = array_slice( $logs, $page_offset, $per_page );
        $showing_from = $filtered_count > 0 ? $page_offset + 1 : 0;
        $showing_to   = $filtered_count > 0 ? min( $page_offset + count( $paged_logs ), $filtered_count ) : 0;

        $filters_active = '' !== $channel_filter || '' !== $provider_filter || '' !== $status_filter || '' !== $destination_filter || '' !== $search_filter || '' !== $date_from_filter || '' !== $date_to_filter;
        ?>
        <div class="fct-card">
            <div class="fct-card-heading">
                <div><h2><?php esc_html_e( 'Recent logs', 'formcourier-notifications-pro' ); ?></h2><p><?php esc_html_e( 'The latest 100 notification delivery attempts are stored locally.', 'formcourier-notifications-pro' ); ?></p></div>
                <?php if ( ! empty( $all_logs ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="formcourier_notifications_pro_clear_logs">
                        <?php wp_nonce_field( 'formcourier_notifications_pro_clear_logs' ); ?>
                        <?php submit_button( __( 'Clear Logs', 'formcourier-notifications-pro' ), 'delete', 'submit', false ); ?>
                    </form>
                <?php endif; ?>
            </div>
            <form method="post" action="options.php" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 16px;">
                <?php settings_fields( 'formcourier_notifications_pro_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_section]" value="logs">
                <label style="display:flex;align-items:center;gap:7px;">
                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_cleanup_logs_30_days]" value="1" <?php checked( (string) $this->get( 'auto_cleanup_logs_30_days', '0' ), '1' ); ?>>
                    <strong><?php esc_html_e( 'Automatically delete logs older than 30 days', 'formcourier-notifications-pro' ); ?></strong>
                </label>
                <?php submit_button( __( 'Save', 'formcourier-notifications-pro' ), 'secondary', 'submit', false ); ?>
                <span style="color:#646970;"><?php esc_html_e( 'Cleanup runs automatically with WP-Cron. Disable this option to keep logs until they are removed manually.', 'formcourier-notifications-pro' ); ?></span>
            </form>
            <?php if ( empty( $all_logs ) ) : ?>
                <div class="fct-empty"><span class="dashicons dashicons-list-view"></span><p><?php esc_html_e( 'No logs yet.', 'formcourier-notifications-pro' ); ?></p></div>
            <?php else : ?>
                <div class="fcnp-log-summary" aria-label="<?php esc_attr_e( 'Log summary', 'formcourier-notifications-pro' ); ?>">
                    <div class="fcnp-log-summary-card"><span><?php esc_html_e( 'Filtered', 'formcourier-notifications-pro' ); ?></span><strong><?php echo esc_html( (string) $filtered_count ); ?></strong></div>
                    <div class="fcnp-log-summary-card is-success"><span><?php esc_html_e( 'Success', 'formcourier-notifications-pro' ); ?></span><strong><?php echo esc_html( (string) $log_stats['success'] ); ?></strong></div>
                    <div class="fcnp-log-summary-card is-error"><span><?php esc_html_e( 'Errors', 'formcourier-notifications-pro' ); ?></span><strong><?php echo esc_html( (string) $log_stats['error'] ); ?></strong></div>
                    <div class="fcnp-log-summary-card"><span><?php esc_html_e( 'Telegram', 'formcourier-notifications-pro' ); ?></span><strong><?php echo esc_html( (string) $log_stats['telegram'] ); ?></strong></div>
                    <div class="fcnp-log-summary-card"><span><?php esc_html_e( 'Slack', 'formcourier-notifications-pro' ); ?></span><strong><?php echo esc_html( (string) $log_stats['slack'] ); ?></strong></div>
                </div>
                <form method="get" class="fcnp-log-filters">
                    <input type="hidden" name="page" value="formcourier-notifications-pro">
                    <input type="hidden" name="tab" value="logs">
                    <input type="hidden" name="log_paged" value="1">
                    <label><?php esc_html_e( 'Search', 'formcourier-notifications-pro' ); ?><br>
                        <input type="search" name="log_search" value="<?php echo esc_attr( $search_filter ); ?>" placeholder="<?php esc_attr_e( 'Form, destination, error...', 'formcourier-notifications-pro' ); ?>">
                    </label>
                    <label><?php esc_html_e( 'Date from', 'formcourier-notifications-pro' ); ?><br>
                        <input type="date" name="log_date_from" value="<?php echo esc_attr( $date_from_filter ); ?>">
                    </label>
                    <label><?php esc_html_e( 'Date to', 'formcourier-notifications-pro' ); ?><br>
                        <input type="date" name="log_date_to" value="<?php echo esc_attr( $date_to_filter ); ?>">
                    </label>
                    <label><?php esc_html_e( 'Channel', 'formcourier-notifications-pro' ); ?><br>
                        <select name="log_channel">
                            <option value=""><?php esc_html_e( 'All channels', 'formcourier-notifications-pro' ); ?></option>
                            <?php foreach ( $channels as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $channel_filter, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Provider', 'formcourier-notifications-pro' ); ?><br>
                        <select name="log_provider">
                            <option value=""><?php esc_html_e( 'All providers', 'formcourier-notifications-pro' ); ?></option>
                            <?php foreach ( $providers as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $provider_filter, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Status', 'formcourier-notifications-pro' ); ?><br>
                        <select name="log_status">
                            <option value=""><?php esc_html_e( 'All statuses', 'formcourier-notifications-pro' ); ?></option>
                            <option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'formcourier-notifications-pro' ); ?></option>
                            <option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'formcourier-notifications-pro' ); ?></option>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Destination', 'formcourier-notifications-pro' ); ?><br>
                        <select name="log_destination">
                            <option value=""><?php esc_html_e( 'All destinations', 'formcourier-notifications-pro' ); ?></option>
                            <?php foreach ( $destinations as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $destination_filter, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Per page', 'formcourier-notifications-pro' ); ?><br>
                        <select name="log_per_page">
                            <?php foreach ( [ 20, 50, 100 ] as $size ) : ?><option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( (string) $size ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <?php submit_button( __( 'Filter', 'formcourier-notifications-pro' ), 'secondary', 'submit', false ); ?>
                    <?php if ( $filters_active ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs' ) ); ?>"><?php esc_html_e( 'Reset', 'formcourier-notifications-pro' ); ?></a><?php endif; ?>
                    <span class="fcnp-log-filter-count"><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d filtered (%4$d total)', 'formcourier-notifications-pro' ), $showing_from, $showing_to, $filtered_count, count( $all_logs ) ) ); ?></span>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fcnp-log-export">
                    <input type="hidden" name="action" value="formcourier_notifications_pro_export_logs">
                    <input type="hidden" name="log_search" value="<?php echo esc_attr( $search_filter ); ?>">
                    <input type="hidden" name="log_date_from" value="<?php echo esc_attr( $date_from_filter ); ?>">
                    <input type="hidden" name="log_date_to" value="<?php echo esc_attr( $date_to_filter ); ?>">
                    <input type="hidden" name="log_channel" value="<?php echo esc_attr( $channel_filter ); ?>">
                    <input type="hidden" name="log_provider" value="<?php echo esc_attr( $provider_filter ); ?>">
                    <input type="hidden" name="log_status" value="<?php echo esc_attr( $status_filter ); ?>">
                    <input type="hidden" name="log_destination" value="<?php echo esc_attr( $destination_filter ); ?>">
                    <?php wp_nonce_field( 'formcourier_notifications_pro_export_logs' ); ?>
                    <?php if ( empty( $logs ) ) : ?>
                        <?php submit_button( __( 'Export CSV', 'formcourier-notifications-pro' ), 'secondary', 'submit', false, [ 'disabled' => 'disabled' ] ); ?>
                    <?php else : ?>
                        <?php submit_button( __( 'Export CSV', 'formcourier-notifications-pro' ), 'secondary', 'submit', false ); ?>
                    <?php endif; ?>
                    <span class="fcnp-log-export-note"><?php esc_html_e( 'Exports the currently filtered log entries.', 'formcourier-notifications-pro' ); ?></span>
                </form>

                <?php if ( empty( $logs ) ) : ?>
                    <div class="fct-empty"><span class="dashicons dashicons-filter"></span><p><?php esc_html_e( 'No log entries match the selected filters.', 'formcourier-notifications-pro' ); ?></p></div>
                <?php else : ?>
                <div class="fct-table-wrap">
                    <table class="widefat striped fct-logs-table">
                        <thead><tr><th><?php esc_html_e( 'Date', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Channel', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Provider', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Form', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Destination', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Status', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Attempts', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Details', 'formcourier-notifications-pro' ); ?></th><th><?php esc_html_e( 'Actions', 'formcourier-notifications-pro' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $paged_logs as $log ) : ?>
                            <tr data-log-id="<?php echo esc_attr( (string) ( $log['id'] ?? '' ) ); ?>">
                                <td class="fct-nowrap"><?php echo esc_html( $log['time'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $log['channel'] ?? 'Telegram' ); ?></td>
                                <td><?php echo esc_html( $log['provider'] ?? '' ); ?></td>
                                <td><?php echo esc_html( trim( '#' . ( $log['form_id'] ?? '' ) . ' ' . ( $log['form_name'] ?? '' ) ) ); ?></td>
                                <td><strong><?php echo esc_html( $log['destination'] ?? ( $log['destination_id'] ?? '' ) ); ?></strong></td>
                                <td><span class="fct-badge <?php echo 'success' === ( $log['status'] ?? '' ) ? 'is-success' : 'is-error'; ?>"><?php echo esc_html( ucfirst( (string) ( $log['status'] ?? '' ) ) ); ?></span></td>
                                <td><?php echo esc_html( (string) max( 1, absint( $log['attempts'] ?? 1 ) ) ); ?></td>
                                <td>
                                    <?php echo esc_html( $log['message'] ?? '' ); ?>
                                    <?php if ( ! empty( $log['next_retry_at'] ) ) : ?>
                                        <br><small><strong><?php esc_html_e( 'Automatic retry:', 'formcourier-notifications-pro' ); ?></strong> <?php echo esc_html( (string) $log['next_retry_at'] ); ?></small>
                                    <?php elseif ( 'exhausted' === ( $log['auto_retry_state'] ?? '' ) ) : ?>
                                        <br><small><?php esc_html_e( 'Automatic retries exhausted. Manual retry is still available.', 'formcourier-notifications-pro' ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( 'success' !== ( $log['status'] ?? '' ) && ! empty( $log['retry_payload'] ) && ! empty( $log['id'] ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <input type="hidden" name="action" value="formcourier_notifications_pro_retry_log">
                                            <input type="hidden" name="log_id" value="<?php echo esc_attr( (string) $log['id'] ); ?>">
                                            <?php wp_nonce_field( 'formcourier_notifications_pro_retry_log_' . (string) $log['id'] ); ?>
                                            <?php submit_button( __( 'Retry', 'formcourier-notifications-pro' ), 'secondary small', 'submit', false ); ?>
                                        </form>
                                    <?php else : ?>
                                        <span aria-hidden="true">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom" style="margin-top:14px;">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = [
                                'page'            => 'formcourier-notifications-pro',
                                'tab'             => 'logs',
                                'log_search'      => $search_filter,
                                'log_date_from'   => $date_from_filter,
                                'log_date_to'     => $date_to_filter,
                                'log_channel'     => $channel_filter,
                                'log_provider'    => $provider_filter,
                                'log_status'      => $status_filter,
                                'log_destination' => $destination_filter,
                                'log_per_page'    => $per_page,
                            ];
                            $pagination_args = array_filter( $pagination_args, static fn( $value ) => '' !== (string) $value );
                            $base_url = add_query_arg( $pagination_args, admin_url( 'admin.php' ) );
                            echo wp_kses_post(
                                paginate_links(
                                    [
                                        'base'      => add_query_arg( 'log_paged', '%#%', $base_url ),
                                        'format'    => '',
                                        'current'   => $current_page,
                                        'total'     => $total_pages,
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                        'type'      => 'list',
                                    ]
                                )
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_test(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'formcourier-notifications-pro' ) ); }
        check_admin_referer( 'formcourier_notifications_pro_test' );
        $provider = new FormCourier_Notifications_Pro_Telegram_Provider( new self() );
        $destination_id = isset( $_POST['destination_id'] ) ? sanitize_key( wp_unslash( $_POST['destination_id'] ) ) : '';
        $result = $provider->send_test( $destination_id );
        if ( 'success' !== ( $result['status'] ?? '' ) ) {
            set_transient( 'formcourier_notifications_pro_test_error', $result['message'] ?? 'Unknown error', 60 );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=telegram&fct_notice=' . ( 'success' === ( $result['status'] ?? '' ) ? 'test_success' : 'test_error' ) ) );
        exit;
    }

    public function handle_slack_test(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'formcourier-notifications-pro' ) ); }
        check_admin_referer( 'formcourier_notifications_pro_slack_test' );
        $provider = new FormCourier_Notifications_Pro_Slack_Provider( new self() );
        $destination_id = isset( $_POST['destination_id'] ) ? sanitize_key( wp_unslash( $_POST['destination_id'] ) ) : '';
        $result = $provider->send_test( $destination_id );
        if ( 'success' !== ( $result['status'] ?? '' ) ) {
            set_transient( 'formcourier_notifications_pro_test_error', $result['message'] ?? 'Unknown error', 60 );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=slack&fct_notice=' . ( 'success' === ( $result['status'] ?? '' ) ? 'test_success' : 'test_error' ) ) );
        exit;
    }

    public function handle_retry_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'formcourier-notifications-pro' ) ); }

        $log_id = isset( $_POST['log_id'] ) ? sanitize_text_field( wp_unslash( $_POST['log_id'] ) ) : '';
        if ( '' === $log_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs&fct_notice=retry_error' ) );
            exit;
        }

        check_admin_referer( 'formcourier_notifications_pro_retry_log_' . $log_id );
        $log = FormCourier_Notifications_Pro_Logger::get( $log_id );
        $payload = isset( $log['retry_payload'] ) && is_array( $log['retry_payload'] ) ? $log['retry_payload'] : [];

        if ( empty( $log ) || empty( $payload ) || ! in_array( sanitize_key( (string) ( $log['channel_id'] ?? '' ) ), [ 'telegram', 'slack' ], true ) ) {
            set_transient( 'formcourier_notifications_pro_retry_error', __( 'This log entry cannot be retried.', 'formcourier-notifications-pro' ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs&fct_notice=retry_error' ) );
            exit;
        }

        $result = FormCourier_Notifications_Pro_Retry_Queue::retry_log( $log_id, false );
        $status = (string) ( $result['status'] ?? 'error' );

        if ( 'success' !== $status ) {
            set_transient( 'formcourier_notifications_pro_retry_error', (string) ( $result['message'] ?? __( 'Retry failed.', 'formcourier-notifications-pro' ) ), 60 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs&fct_notice=' . ( 'success' === $status ? 'retry_success' : 'retry_error' ) ) );
        exit;
    }

    public function handle_export_logs(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'formcourier-notifications-pro' ) ); }
        check_admin_referer( 'formcourier_notifications_pro_export_logs' );

        $channel_filter     = isset( $_POST['log_channel'] ) ? sanitize_key( wp_unslash( $_POST['log_channel'] ) ) : '';
        $provider_filter    = isset( $_POST['log_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['log_provider'] ) ) : '';
        $status_filter      = isset( $_POST['log_status'] ) ? sanitize_key( wp_unslash( $_POST['log_status'] ) ) : '';
        $destination_filter = isset( $_POST['log_destination'] ) ? sanitize_text_field( wp_unslash( $_POST['log_destination'] ) ) : '';
        $search_filter      = isset( $_POST['log_search'] ) ? sanitize_text_field( wp_unslash( $_POST['log_search'] ) ) : '';
        $date_from_filter   = isset( $_POST['log_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date_from'] ) ) : '';
        $date_to_filter     = isset( $_POST['log_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date_to'] ) ) : '';
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from_filter ) ) { $date_from_filter = ''; }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to_filter ) ) { $date_to_filter = ''; }

        $logs = array_values(
            array_filter(
                FormCourier_Notifications_Pro_Logger::all(),
                static function ( $log ) use ( $channel_filter, $provider_filter, $status_filter, $destination_filter, $search_filter, $date_from_filter, $date_to_filter ): bool {
                    if ( ! is_array( $log ) ) { return false; }
                    if ( '' !== $channel_filter && $channel_filter !== sanitize_key( (string) ( $log['channel_id'] ?? '' ) ) ) { return false; }
                    if ( '' !== $provider_filter && $provider_filter !== (string) ( $log['provider'] ?? '' ) ) { return false; }
                    if ( '' !== $status_filter && $status_filter !== sanitize_key( (string) ( $log['status'] ?? '' ) ) ) { return false; }
                    $destination = (string) ( $log['destination'] ?? ( $log['destination_id'] ?? '' ) );
                    if ( '' !== $destination_filter && $destination_filter !== $destination ) { return false; }
                    $log_date = substr( (string) ( $log['time'] ?? '' ), 0, 10 );
                    if ( '' !== $date_from_filter && ( '' === $log_date || $log_date < $date_from_filter ) ) { return false; }
                    if ( '' !== $date_to_filter && ( '' === $log_date || $log_date > $date_to_filter ) ) { return false; }
                    if ( '' !== $search_filter ) {
                        $haystack = implode( ' ', [
                            (string) ( $log['channel'] ?? '' ), (string) ( $log['provider'] ?? '' ),
                            (string) ( $log['provider_key'] ?? '' ), (string) ( $log['form_id'] ?? '' ),
                            (string) ( $log['form_name'] ?? '' ), $destination, (string) ( $log['status'] ?? '' ),
                            (string) ( $log['message'] ?? '' ), (string) ( $log['last_error'] ?? '' ),
                            (string) ( $log['provider_response'] ?? '' ), (string) ( $log['submission_id'] ?? '' ),
                        ] );
                        if ( false === stripos( $haystack, $search_filter ) ) { return false; }
                    }
                    return true;
                }
            )
        );

        $filename = 'formcourier-logs-' . wp_date( 'Y-m-d-His', time(), wp_timezone() ) . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) { exit; }
        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, [ 'Date', 'Channel', 'Provider', 'Form ID', 'Form', 'Destination', 'Status', 'Attempts', 'Message', 'HTTP Status', 'Last Error', 'Provider Response', 'Retryable', 'Retry After', 'Retry State', 'Next Retry', 'Submission ID', 'Submitted At' ] );
        foreach ( $logs as $log ) {
            fputcsv( $output, [
                (string) ( $log['time'] ?? '' ),
                (string) ( $log['channel'] ?? '' ),
                (string) ( $log['provider'] ?? '' ),
                (string) ( $log['form_id'] ?? '' ),
                (string) ( $log['form_name'] ?? '' ),
                (string) ( $log['destination'] ?? ( $log['destination_id'] ?? '' ) ),
                (string) ( $log['status'] ?? '' ),
                (string) max( 1, absint( $log['attempts'] ?? 1 ) ),
                (string) ( $log['message'] ?? '' ),
                (string) absint( $log['http_status'] ?? 0 ),
                (string) ( $log['last_error'] ?? '' ),
                (string) ( $log['provider_response'] ?? '' ),
                ! empty( $log['retryable'] ) ? 'Yes' : 'No',
                (string) absint( $log['retry_after'] ?? 0 ),
                (string) ( $log['auto_retry_state'] ?? '' ),
                (string) ( $log['next_retry_at'] ?? '' ),
                (string) ( $log['submission_id'] ?? '' ),
                (string) ( $log['submitted_at'] ?? '' ),
            ] );
        }
        fclose( $output );
        exit;
    }

    public function handle_clear_logs(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'formcourier-notifications-pro' ) ); }
        check_admin_referer( 'formcourier_notifications_pro_clear_logs' );
        foreach ( FormCourier_Notifications_Pro_Logger::all() as $log ) {
            if ( is_array( $log ) && ! empty( $log['id'] ) ) {
                FormCourier_Notifications_Pro_Retry_Queue::unschedule( (string) $log['id'] );
            }
        }
        FormCourier_Notifications_Pro_Logger::clear();
        wp_safe_redirect( admin_url( 'admin.php?page=formcourier-notifications-pro&tab=logs&fct_notice=logs_cleared' ) );
        exit;
    }
}

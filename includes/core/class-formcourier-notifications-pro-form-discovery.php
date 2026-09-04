<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FormCourier_Notifications_Pro_Form_Discovery {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        $remembered = get_option( 'formcourier_notifications_pro_known_forms', [] );
        $forms = is_array( $remembered ) ? self::merge( [], $remembered ) : [];

        // Live form-builder data is authoritative; remembered submissions are only a fallback.
        $forms = self::merge( $forms, self::contact_form_7() );
        $forms = self::merge( $forms, self::wpforms() );
        $forms = self::merge( $forms, self::fluent_forms() );
        $forms = self::merge( $forms, self::forminator() );
        $forms = self::merge( $forms, self::ninja_forms() );
        $forms = self::merge( $forms, self::gravity_forms() );

        uasort(
            $forms,
            static function ( array $a, array $b ): int {
                $provider = strcasecmp( (string) ( $a['provider_label'] ?? '' ), (string) ( $b['provider_label'] ?? '' ) );
                if ( 0 !== $provider ) { return $provider; }
                return strcasecmp( (string) ( $a['form_name'] ?? '' ), (string) ( $b['form_name'] ?? '' ) );
            }
        );

        return $forms;
    }

    /** @param array<string,array<string,mixed>> $base @param array<string,array<string,mixed>> $incoming */
    private static function merge( array $base, array $incoming ): array {
        foreach ( $incoming as $key => $form ) {
            if ( ! is_array( $form ) ) { continue; }
            $provider_key = sanitize_key( (string) ( $form['provider_key'] ?? '' ) );
            $form_id      = sanitize_text_field( (string) ( $form['form_id'] ?? '' ) );
            if ( '' === $provider_key || '' === $form_id ) { continue; }
            $route_key = $provider_key . ':' . sanitize_key( $form_id );
            $base[ $route_key ] = [
                'provider_key'   => $provider_key,
                'provider_label' => sanitize_text_field( (string) ( $form['provider_label'] ?? $provider_key ) ),
                'form_id'        => $form_id,
                'form_name'      => sanitize_text_field( (string) ( $form['form_name'] ?? '' ) ),
            ];
        }
        return $base;
    }

    private static function contact_form_7(): array {
        if ( ! class_exists( 'WPCF7_ContactForm' ) || ! method_exists( 'WPCF7_ContactForm', 'find' ) ) { return []; }
        $out = [];
        try {
            $items = WPCF7_ContactForm::find( [ 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
            foreach ( (array) $items as $form ) {
                if ( ! is_object( $form ) || ! method_exists( $form, 'id' ) ) { continue; }
                $id = (string) absint( $form->id() );
                if ( '0' === $id ) { continue; }
                $title = method_exists( $form, 'title' ) ? (string) $form->title() : '';
                $out['contact_form_7:' . sanitize_key( $id )] = self::item( 'contact_form_7', 'Contact Form 7', $id, $title );
            }
        } catch ( Throwable $exception ) { return []; }
        return $out;
    }

    private static function wpforms(): array {
        if ( ! function_exists( 'wpforms' ) && ! defined( 'WPFORMS_VERSION' ) ) { return []; }
        $out = [];
        $posts = get_posts( [ 'post_type' => 'wpforms', 'post_status' => [ 'publish', 'draft', 'private' ], 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        foreach ( $posts as $post ) {
            $id = (string) absint( $post->ID );
            $out['wpforms:' . sanitize_key( $id )] = self::item( 'wpforms', 'WPForms', $id, get_the_title( $post ) );
        }
        return $out;
    }

    private static function fluent_forms(): array {
        if ( ! defined( 'FLUENTFORM' ) && ! defined( 'FLUENTFORM_VERSION' ) && ! function_exists( 'wpFluentForm' ) ) { return []; }
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_forms';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ( $table !== $exists ) { return []; }
        $rows = $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
        $out = [];
        foreach ( (array) $rows as $row ) {
            $id = isset( $row->id ) ? (string) absint( $row->id ) : '';
            if ( '' === $id || '0' === $id ) { continue; }
            $out['fluent_forms:' . sanitize_key( $id )] = self::item( 'fluent_forms', 'Fluent Forms', $id, (string) ( $row->title ?? '' ) );
        }
        return $out;
    }

    private static function forminator(): array {
        if ( ! defined( 'FORMINATOR_VERSION' ) && ! class_exists( 'Forminator' ) ) { return []; }
        $out = [];
        $posts = get_posts( [ 'post_type' => 'forminator_forms', 'post_status' => [ 'publish', 'draft', 'private' ], 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        foreach ( $posts as $post ) {
            $id = (string) absint( $post->ID );
            $title = get_the_title( $post );
            if ( class_exists( 'Forminator_API' ) && method_exists( 'Forminator_API', 'get_form' ) ) {
                try {
                    $form = Forminator_API::get_form( (int) $id );
                    if ( is_object( $form ) && ! empty( $form->settings['formName'] ) ) { $title = (string) $form->settings['formName']; }
                    elseif ( is_object( $form ) && ! empty( $form->name ) ) { $title = (string) $form->name; }
                } catch ( Throwable $exception ) {}
            }
            $out['forminator:' . sanitize_key( $id )] = self::item( 'forminator', 'Forminator', $id, $title );
        }
        return $out;
    }

    private static function ninja_forms(): array {
        if ( ! function_exists( 'Ninja_Forms' ) && ! defined( 'NINJA_FORMS_VERSION' ) ) { return []; }
        $out = [];
        try {
            if ( function_exists( 'Ninja_Forms' ) ) {
                $models = Ninja_Forms()->form()->get_forms();
                foreach ( (array) $models as $model ) {
                    if ( ! is_object( $model ) || ! method_exists( $model, 'get_id' ) ) { continue; }
                    $id = (string) absint( $model->get_id() );
                    if ( '0' === $id ) { continue; }
                    $title = method_exists( $model, 'get_setting' ) ? (string) $model->get_setting( 'title' ) : '';
                    $out['ninja_forms:' . sanitize_key( $id )] = self::item( 'ninja_forms', 'Ninja Forms', $id, $title );
                }
            }
        } catch ( Throwable $exception ) { return []; }
        return $out;
    }

    private static function gravity_forms(): array {
        if ( ! class_exists( 'GFAPI' ) ) { return []; }
        $out = [];
        try {
            $items = GFAPI::get_forms();
            foreach ( (array) $items as $form ) {
                if ( ! is_array( $form ) || empty( $form['id'] ) ) { continue; }
                $id = (string) absint( $form['id'] );
                $out['gravity_forms:' . sanitize_key( $id )] = self::item( 'gravity_forms', 'Gravity Forms', $id, (string) ( $form['title'] ?? '' ) );
            }
        } catch ( Throwable $exception ) { return []; }
        return $out;
    }



    /** @return array<string,array<string,string>> Route key => field key => human label. */
    public static function fields(): array {
        $out = [];
        $remembered = get_option( 'formcourier_notifications_pro_known_fields', [] );
        if ( is_array( $remembered ) ) {
            foreach ( $remembered as $route_key => $fields ) {
                if ( is_array( $fields ) ) { $out[ (string) $route_key ] = self::clean_fields( $fields ); }
            }
        }

        foreach ( self::all() as $route_key => $form ) {
            $provider = (string) ( $form['provider_key'] ?? '' );
            $form_id  = (string) ( $form['form_id'] ?? '' );
            $live = self::fields_for( $provider, $form_id );
            if ( ! empty( $live ) ) {
                // Live builder metadata is authoritative. Remembered field aliases are
                // only a fallback when live discovery is unavailable. Keeping both
                // caused duplicate placeholders such as 1 / field_1 in the UI.
                $out[ $route_key ] = $live;
            }
        }
        return $out;
    }

    /** @return array<string,string> */
    public static function fields_for( string $provider, string $form_id ): array {
        switch ( sanitize_key( $provider ) ) {
            case 'contact_form_7': return self::cf7_fields( $form_id );
            case 'wpforms': return self::wpforms_fields( $form_id );
            case 'fluent_forms': return self::fluent_fields( $form_id );
            case 'forminator': return self::forminator_fields( $form_id );
            case 'ninja_forms': return self::ninja_fields( $form_id );
            case 'gravity_forms': return self::gravity_fields( $form_id );
            default: return [];
        }
    }

    /** @param array<string,mixed> $fields @return array<string,string> */
    private static function clean_fields( array $fields ): array {
        $clean = [];
        foreach ( $fields as $key => $label ) {
            $key = sanitize_text_field( (string) $key );
            $label = sanitize_text_field( (string) $label );
            if ( '' !== $key ) { $clean[ $key ] = '' !== $label ? $label : self::humanize( $key ); }
        }
        return $clean;
    }

    private static function humanize( string $key ): string {
        $label = preg_replace( '/[_-]+/', ' ', $key );
        $label = preg_replace( '/\\s+/', ' ', (string) $label );
        return ucwords( trim( (string) $label ) );
    }

    /** @return array<string,string> */
    private static function cf7_fields( string $form_id ): array {
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) { return []; }
        try {
            $form = WPCF7_ContactForm::get_instance( absint( $form_id ) );
            if ( ! $form || ! method_exists( $form, 'scan_form_tags' ) ) { return []; }
            $out = [];
            foreach ( (array) $form->scan_form_tags() as $tag ) {
                $name = is_object( $tag ) && isset( $tag->name ) ? sanitize_text_field( (string) $tag->name ) : '';
                if ( '' === $name || 'g-recaptcha-response' === $name ) { continue; }
                $type = is_object( $tag ) && isset( $tag->basetype ) ? sanitize_text_field( (string) $tag->basetype ) : '';
                $label = self::humanize( preg_replace( '/^your-/', '', $name ) ?: $name );
                if ( '' !== $type ) { $label .= ' [' . $type . ']'; }
                $out[ $name ] = $label;
            }
            return $out;
        } catch ( Throwable $exception ) { return []; }
    }

    /** @return array<string,string> */
    private static function wpforms_fields( string $form_id ): array {
        $data = [];
        try {
            if ( function_exists( 'wpforms' ) && is_object( wpforms() ) && isset( wpforms()->form ) && method_exists( wpforms()->form, 'get' ) ) {
                $post = wpforms()->form->get( absint( $form_id ) );
                if ( is_object( $post ) && isset( $post->post_content ) ) {
                    if ( function_exists( 'wpforms_decode' ) ) { $data = wpforms_decode( $post->post_content ); }
                    if ( ! is_array( $data ) ) { $data = json_decode( (string) $post->post_content, true ); }
                }
            }
            if ( ! is_array( $data ) || empty( $data ) ) {
                $post = get_post( absint( $form_id ) );
                if ( $post ) {
                    $decoded = json_decode( (string) $post->post_content, true );
                    if ( is_array( $decoded ) ) { $data = $decoded; }
                }
            }
        } catch ( Throwable $exception ) { return []; }
        $out = [];
        foreach ( (array) ( $data['fields'] ?? [] ) as $field ) {
            if ( ! is_array( $field ) || empty( $field['id'] ) ) { continue; }
            $id = (string) absint( $field['id'] );
            $label = sanitize_text_field( (string) ( $field['label'] ?? $field['name'] ?? ( 'Field ' . $id ) ) );
            // Expose one canonical placeholder per WPForms field. Legacy aliases
            // remain supported by the submission provider, but are intentionally
            // hidden from the UI to avoid duplicate placeholders.
            $out[ $id ] = $label;
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function fluent_fields( string $form_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_forms';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT form_fields FROM {$table} WHERE id = %d", absint( $form_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
        if ( ! $row || empty( $row->form_fields ) ) { return []; }

        $decoded = json_decode( (string) $row->form_fields, true );
        if ( ! is_array( $decoded ) ) { return []; }

        $out = [];
        $root_fields = isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ? $decoded['fields'] : $decoded;

        $walk = static function ( $items, string $parent_name = '' ) use ( &$out, &$walk ): void {
            foreach ( (array) $items as $field ) {
                if ( ! is_array( $field ) ) { continue; }

                $attributes = is_array( $field['attributes'] ?? null ) ? $field['attributes'] : [];
                $settings   = is_array( $field['settings'] ?? null ) ? $field['settings'] : [];
                $element    = sanitize_key( (string) ( $field['element'] ?? '' ) );
                $name       = sanitize_text_field( (string) ( $attributes['name'] ?? $field['name'] ?? '' ) );
                $label      = sanitize_text_field( (string) ( $settings['label'] ?? $settings['admin_field_label'] ?? $field['label'] ?? '' ) );

                // Fluent Forms compound fields (most notably Name) store child
                // controls under `fields` with names such as first_name and last_name.
                // Submitted data uses bracket notation: names[first_name].
                $child_fields = isset( $field['fields'] ) && is_array( $field['fields'] ) ? $field['fields'] : [];
                if ( ! empty( $child_fields ) ) {
                    $compound_label = '' !== $label ? $label : self::humanize( $name );
                    foreach ( $child_fields as $child_key => $child ) {
                        if ( ! is_array( $child ) ) { continue; }
                        $child_attributes = is_array( $child['attributes'] ?? null ) ? $child['attributes'] : [];
                        $child_settings   = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];

                        // Do not expose disabled parts of a compound field.
                        if ( array_key_exists( 'visible', $child_settings ) && false === (bool) $child_settings['visible'] ) { continue; }

                        $child_name = sanitize_text_field( (string) ( $child_attributes['name'] ?? $child_key ) );
                        if ( '' === $child_name ) { continue; }

                        $technical_key = '' !== $name ? $name . '[' . $child_name . ']' : $child_name;
                        $child_label = sanitize_text_field( (string) ( $child_settings['label'] ?? $child['label'] ?? '' ) );
                        if ( '' === $child_label ) { $child_label = self::humanize( $child_name ); }
                        // Use the actual child label (First Name, Last Name, etc.).
                        // Prefixing it with the compound label produced technical-looking
                        // Telegram output such as "Names - First Name".
                        $out[ $technical_key ] = $child_label;
                    }
                } elseif ( '' !== $name && 'button' !== $element ) {
                    $out[ $name ] = '' !== $label ? $label : self::humanize( $name );
                }

                // Layout/container elements can contain controls inside columns.
                if ( ! empty( $field['columns'] ) && is_array( $field['columns'] ) ) {
                    $walk( $field['columns'], $parent_name );
                }
            }
        };

        $walk( $root_fields );
        return $out;
    }

    /** @return array<string,string> */
    private static function forminator_fields( string $form_id ): array {
        if ( ! class_exists( 'Forminator_API' ) || ! method_exists( 'Forminator_API', 'get_form' ) ) { return []; }
        try { $form = Forminator_API::get_form( absint( $form_id ) ); } catch ( Throwable $exception ) { return []; }
        if ( ! is_object( $form ) ) { return []; }
        $raw_fields = [];
        if ( isset( $form->fields ) && is_array( $form->fields ) ) { $raw_fields = $form->fields; }
        elseif ( method_exists( $form, 'get_fields' ) ) { try { $raw_fields = (array) $form->get_fields(); } catch ( Throwable $exception ) {} }
        $out = [];
        foreach ( $raw_fields as $field ) {
            if ( is_object( $field ) ) {
                $key = (string) ( $field->slug ?? $field->element_id ?? $field->name ?? '' );
                $label = (string) ( $field->field_label ?? $field->label ?? $field->name ?? '' );
            } elseif ( is_array( $field ) ) {
                $key = (string) ( $field['slug'] ?? $field['element_id'] ?? $field['name'] ?? '' );
                $label = (string) ( $field['field_label'] ?? $field['label'] ?? $field['name'] ?? '' );
            } else { continue; }
            $key = sanitize_text_field( $key ); $label = sanitize_text_field( $label );
            if ( '' !== $key ) { $out[ $key ] = '' !== $label ? $label : self::humanize( $key ); }
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function ninja_fields( string $form_id ): array {
        if ( ! function_exists( 'Ninja_Forms' ) ) { return []; }
        try { $fields = Ninja_Forms()->form( absint( $form_id ) )->get_fields(); } catch ( Throwable $exception ) { return []; }
        $out = [];
        foreach ( (array) $fields as $field ) {
            if ( ! is_object( $field ) ) { continue; }

            $id    = method_exists( $field, 'get_id' ) ? (string) absint( $field->get_id() ) : '';
            $key   = method_exists( $field, 'get_setting' ) ? sanitize_text_field( (string) $field->get_setting( 'key' ) ) : '';
            $label = method_exists( $field, 'get_setting' ) ? sanitize_text_field( (string) $field->get_setting( 'label' ) ) : '';
            $type  = method_exists( $field, 'get_setting' ) ? sanitize_key( (string) $field->get_setting( 'type' ) ) : '';

            // Non-data controls must never become message placeholders.
            if ( in_array( $type, [ 'submit', 'html', 'hr', 'recaptcha', 'spam' ], true ) || 'submit' === $key ) {
                continue;
            }

            $display = '' !== $label ? $label : ( '' !== $key ? self::humanize( $key ) : ( $id ? 'Field ' . $id : '' ) );

            // Prefer Ninja Forms' stable field key. Numeric IDs and field_ID aliases
            // are still accepted internally for backwards compatibility, but they
            // are not shown as duplicate placeholders in the admin UI.
            if ( '' !== $key ) {
                $out[ $key ] = $display;
            } elseif ( '' !== $id ) {
                $out[ $id ] = $display;
            }
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function gravity_fields( string $form_id ): array {
        if ( ! class_exists( 'GFAPI' ) ) { return []; }
        try { $form = GFAPI::get_form( absint( $form_id ) ); } catch ( Throwable $exception ) { return []; }
        if ( ! is_array( $form ) ) { return []; }
        $out = [];
        foreach ( (array) ( $form['fields'] ?? [] ) as $field ) {
            $id = is_object( $field ) && isset( $field->id ) ? (string) $field->id : ( is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : '' );
            if ( '' === $id ) { continue; }
            $label = is_object( $field ) && isset( $field->label ) ? (string) $field->label : ( is_array( $field ) && isset( $field['label'] ) ? (string) $field['label'] : 'Field ' . $id );
            $label = sanitize_text_field( $label );
            $out[ $id ] = $label;
            $inputs = is_object( $field ) && isset( $field->inputs ) ? $field->inputs : ( is_array( $field ) && isset( $field['inputs'] ) ? $field['inputs'] : [] );
            foreach ( (array) $inputs as $input ) {
                $input_id = is_object( $input ) && isset( $input->id ) ? (string) $input->id : ( is_array( $input ) && isset( $input['id'] ) ? (string) $input['id'] : '' );
                $input_label = is_object( $input ) && isset( $input->label ) ? (string) $input->label : ( is_array( $input ) && isset( $input['label'] ) ? (string) $input['label'] : '' );
                if ( '' !== $input_id ) { $out[ $input_id ] = $label . ( $input_label ? ' - ' . sanitize_text_field( $input_label ) : '' ); }
            }
        }
        return $out;
    }

    private static function item( string $provider_key, string $provider_label, string $id, string $title ): array {
        return [
            'provider_key'   => $provider_key,
            'provider_label' => $provider_label,
            'form_id'        => $id,
            'form_name'      => '' !== trim( $title ) ? $title : $provider_label,
        ];
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Adds expandable delivery diagnostics to the existing Logs table without
 * changing the stored log format or the main settings screen renderer.
 */
final class FormCourier_Notifications_Pro_Log_Details_UI {
    public static function init(): void {
        add_action( 'admin_footer', [ __CLASS__, 'render' ] );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        if ( 'formcourier-notifications-pro' !== $page || 'logs' !== $tab ) {
            return;
        }

        $diagnostics = [];
        foreach ( FormCourier_Notifications_Pro_Logger::all() as $log ) {
            if ( ! is_array( $log ) ) {
                $diagnostics[] = [];
                continue;
            }

            $diagnostics[] = [
                'http_status'       => absint( $log['http_status'] ?? 0 ),
                'last_error'        => sanitize_text_field( (string) ( $log['last_error'] ?? '' ) ),
                'provider_response' => sanitize_text_field( (string) ( $log['provider_response'] ?? '' ) ),
                'retryable'         => ! empty( $log['retryable'] ),
                'retry_after'       => absint( $log['retry_after'] ?? 0 ),
                'next_retry_at'     => sanitize_text_field( (string) ( $log['next_retry_at'] ?? '' ) ),
                'retry_state'       => sanitize_key( (string) ( $log['auto_retry_state'] ?? '' ) ),
                'submission_id'     => sanitize_text_field( (string) ( $log['submission_id'] ?? '' ) ),
                'submitted_at'      => sanitize_text_field( (string) ( $log['submitted_at'] ?? '' ) ),
            ];
        }
        ?>
        <style>
            .fcnp-delivery-details { margin-top: 8px; }
            .fcnp-delivery-details summary { cursor: pointer; font-weight: 600; color: #2271b1; }
            .fcnp-delivery-details-grid { display: grid; grid-template-columns: max-content 1fr; gap: 4px 10px; margin: 8px 0 0; font-size: 12px; }
            .fcnp-delivery-details-grid dt { font-weight: 600; color: #50575e; }
            .fcnp-delivery-details-grid dd { margin: 0; overflow-wrap: anywhere; }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            let diagnostics = <?php echo wp_json_encode( $diagnostics ); ?>;
            let rows = document.querySelectorAll('.fct-logs-table tbody tr');

            rows.forEach(function (row, index) {
                let data = diagnostics[index] || {};
                let cell = row.cells && row.cells.length > 7 ? row.cells[7] : null;
                if (!cell || cell.querySelector('.fcnp-delivery-details')) {
                    return;
                }

                let details = document.createElement('details');
                details.className = 'fcnp-delivery-details';

                let summary = document.createElement('summary');
                summary.textContent = 'Delivery details';
                details.appendChild(summary);

                let list = document.createElement('dl');
                list.className = 'fcnp-delivery-details-grid';

                let fields = [
                    ['HTTP status', data.http_status ? String(data.http_status) : '—'],
                    ['Last error', data.last_error || '—'],
                    ['Provider response', data.provider_response || '—'],
                    ['Retryable', data.retryable ? 'Yes' : 'No'],
                    ['Retry after', data.retry_after ? String(data.retry_after) + ' sec' : '—'],
                    ['Retry state', data.retry_state || '—'],
                    ['Next retry', data.next_retry_at || '—'],
                    ['Submission ID', data.submission_id || '—'],
                    ['Submitted at', data.submitted_at || '—']
                ];

                fields.forEach(function (field) {
                    let dt = document.createElement('dt');
                    let dd = document.createElement('dd');
                    dt.textContent = field[0];
                    dd.textContent = field[1];
                    list.appendChild(dt);
                    list.appendChild(dd);
                });

                details.appendChild(list);
                cell.appendChild(details);
            });
        });
        </script>
        <?php
    }
}

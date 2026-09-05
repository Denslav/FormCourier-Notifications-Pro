<?php
/**
 * Plugin Name: FormCourier Notifications Pro
 * Description: Route submissions from popular WordPress form plugins to notification channels. Telegram provider included.
 * Version: 1.8.0-dev
 * Author: Den Slav
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: formcourier-notifications-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FORMCOURIER_NOTIFICATIONS_PRO_VERSION', '1.8.0-dev' );
define( 'FORMCOURIER_NOTIFICATIONS_PRO_FILE', __FILE__ );
define( 'FORMCOURIER_NOTIFICATIONS_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORMCOURIER_NOTIFICATIONS_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'FORMCOURIER_NOTIFICATIONS_PRO_BASENAME', plugin_basename( __FILE__ ) );

require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-sanitizer.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-submission.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-encryption.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-settings.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-form-discovery.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-logger.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-message-builder.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-provider-interface.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-routing-engine.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-notification-manager.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-retry-queue.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/providers/telegram/class-formcourier-notifications-pro-telegram-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/providers/slack/class-formcourier-notifications-pro-slack-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-cf7-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-wpforms-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-fluentforms-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-forminator-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-ninjaforms-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/form-providers/class-formcourier-notifications-pro-gravityforms-provider.php';
require_once FORMCOURIER_NOTIFICATIONS_PRO_PATH . 'includes/core/class-formcourier-notifications-pro-plugin.php';

register_activation_hook( __FILE__, [ 'FormCourier_Notifications_Pro_Plugin', 'activate' ] );

add_action(
    'plugins_loaded',
    static function (): void {
        FormCourier_Notifications_Pro_Plugin::instance()->init();
    }
);

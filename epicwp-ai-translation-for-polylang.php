<?php
/**
 * EPICWP AI Translation for Polylang
 *
 * Plugin Name:       EPICWP AI Translation for Polylang
 * Plugin URI:        https://github.com/epicwp/Polylang-AI-Automatic-Translation-Plugin
 * Description:       Single post/page AI translation with OpenAI for Polylang. Translate content with one click using OpenAI's powerful models. Free version with OpenAI support only.
 * Author:            EPICWP
 * Author URI:        https://www.epicwpsolutions.com
 * Version:           0.0.0
 * Requires PHP:      8.1
 * Requires at least: 5.8
 * Tested up to:      6.8
 * Text Domain:       epicwp-ai-translation-for-polylang
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package EPICWP AI Translation for Polylang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'PLLAT_PLUGIN_VERSION', '0.0.0' );
define( 'PLLAT_DB_VERSION', '2.3.0' );
define( 'PLLAT_PLUGIN_FILE', __FILE__ );
define( 'PLLAT_PLUGIN_BASE', plugin_basename( PLLAT_PLUGIN_FILE ) );
define( 'PLLAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLLAT_PLUGIN_URL', plugins_url( '/', PLLAT_PLUGIN_BASE ) );
define( 'PLLAT_PLUGIN_SETTINGS_PAGE', admin_url( 'admin.php?page=epicwp-ai-translation-for-polylang' ) );

/**
 * Get plugin log directory path.
 * Uses wp_upload_dir() to comply with WordPress.org guidelines.
 *
 * @return string Log directory path.
 */
function pllat_get_log_dir() {
	$upload_dir = wp_upload_dir();
	return $upload_dir['basedir'] . '/epicwp-ai-translation-for-polylang/logs';
}

require_once __DIR__ . '/vendor/autoload_packages.php';
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
require_once __DIR__ . '/src/Common/Utils/bootstrap.php';

// Register activation hook.
register_activation_hook(
    __FILE__,
    static function () {
        \PLLAT\Common\Installer\Installer::install();
    },
);

xwp_load_app(
    app: array(
        'app_file'       => __FILE__,
        'app_id'         => 'pllat',
        'app_module'     => \PLLAT\App::class,
        'app_version'    => PLLAT_PLUGIN_VERSION,
        'cache_app'      => false,
        'cache_defs'     => false,
        'cache_dir'      => __DIR__ . '/cache',
        'cache_hooks'    => false,
        'public'         => true,
        'use_attributes' => true,
        'use_autowiring' => true,
    ),
    hook: 'plugins_loaded',
    priority: 0,
);

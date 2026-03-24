<?php
/**
 * Plugin Name:       Blog Lead Magnet
 * Plugin URI:        https://github.com/lukelocksmith/important-cta
 * Description:       CTA blocks, content gate (email paywall) & analytics for WordPress blog posts.
 * Version:           3.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Łukasz Ślusarski
 * Author URI:        https://important.is
 * License:           GPL-2.0-or-later
 * Text Domain:       important-cta
 */

defined('ABSPATH') || exit;

define('ICTA_VERSION', '3.0.0');
define('ICTA_DIR',     plugin_dir_path(__FILE__));
define('ICTA_URL',     plugin_dir_url(__FILE__));

// GitHub auto-update
require_once ICTA_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$updater = PucFactory::buildUpdateChecker(
    'https://github.com/lukelocksmith/important-cta/',
    __FILE__,
    'important-cta'
);
$updater->setBranch('main');
$updater->getVcsApi()->enableReleaseAssets();

// Load modules
require_once ICTA_DIR . 'includes/class-cta-settings.php';
require_once ICTA_DIR . 'includes/class-cta-injector.php';
require_once ICTA_DIR . 'includes/class-content-gate.php';
require_once ICTA_DIR . 'includes/class-analytics.php';

new ICTA_Settings();
new ICTA_Injector();
new ICTA_Content_Gate();
new ICTA_Analytics();

// DB table creation on activation
register_activation_hook(__FILE__, ['ICTA_Analytics', 'create_table']);

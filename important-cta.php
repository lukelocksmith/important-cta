<?php
/**
 * Plugin Name:       Blog Lead Magnet
 * Plugin URI:        https://github.com/lukelocksmith/important-cta
 * Description:       Auto-injects configurable CTA & lead magnet blocks into blog posts — per category, 3 positions.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Łukasz Ślusarski
 * Author URI:        https://important.is
 * License:           GPL-2.0-or-later
 * Text Domain:       important-cta
 */

defined('ABSPATH') || exit;

define('ICTA_VERSION', '2.0.1');
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

new ICTA_Settings();
new ICTA_Injector();

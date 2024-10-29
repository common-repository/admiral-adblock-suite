<?php
/*
Plugin Name: Admiral Ad Block Analytics
Plugin URI: http://getadmiral.com/wordpress
Description: Admiral is an advanced adblock analytics and revenue recovery platform.
Author: Admiral <support@getadmiral.com>
Version: 1.9.6
Author URI: http://getadmiral.com/
*/

if (!function_exists('add_action')) {
    exit;
}
require_once("AdmiralAdBlockAnalytics.php");

// Find the property ID on the install page of https://app.getadmiral.com
// Then do one of the following:
// - Add to wp-config.php as `ADMIRAL_PROPERTY_ID`
//   https://wordpress.org/documentation/article/editing-wp-config-php/
//   e.g. `define('ADMIRAL_PROPERTY_ID', '');`
//
// - Setup in WordPress VIP Environment as `ADMIRAL_PROPERTY_ID`
//   https://docs.wpvip.com/how-tos/manage-environment-variables/#managing-environment-variables-with-vip-cli
//   e.g. `vip [@site] config envvar set ADMIRAL_PROPERTY_ID`
//
// - Configure the property id in the WordPress admin UI.

\wp\AdmiralAdBlockAnalytics::setClientIDSecret("41c528e5f0d2c6b4cc93", "2y41c528e5f0d2c6b4cc930001912b9ea79d22eaadaeed70b7183d99ff0cfc2f7f652c1336");

function admiraladblock_load_settings()
{
    try {
        $env = (defined('VIP_GO_APP_ENVIRONMENT') && VIP_GO_APP_ENVIRONMENT) || 'production';
        $didInitialize = \wp\AdmiralAdBlockAnalytics::initialize("wp", "1.9.4", $env);
        $isLogin = strpos($_SERVER['SCRIPT_NAME'], '/wp-login.php') !== false;
        if ($didInitialize && (!function_exists('is_admin') || !is_admin()) && !$isLogin) {
            add_action('wp_print_scripts', function() {
                $embed = \wp\AdmiralAdBlockAnalytics::getEmbed();
                if (!empty($embed)) {
                    echo $embed;
                }
            });
        }
    } catch (Exception $e) {
        error_log("Error loading settings: " . $e->getMessage());
    }
}

// Set `Activated_Admiral` when the plugin is activated so that we can check this
// later to redirect to the plugin settings page.
register_activation_hook( __FILE__, function() {
    if (!empty($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/plugins.php') !== false) {
        add_option('Activated_Admiral', true);
    }
});

admiraladblock_load_settings();

// always include the admin section
require_once('adminHooks.php');

/* EOF */

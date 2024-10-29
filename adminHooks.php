<?php

require_once("AdmiralAdBlockAnalytics.php");

if (!function_exists('add_action')) {
    exit;
}

add_action("admin_init", function() {
    register_setting("admiral_property_settings", wp\AdmiralAdBlockAnalytics::PROPERTY_OPTION_ID_KEY);
    register_setting("admiral_property_settings", wp\AdmiralAdBlockAnalytics::EMBED_OPTION_KEY);
    register_setting("admiral_property_settings", wp\AdmiralAdBlockAnalytics::APPEND_PHP_OPTION_KEY);
});

add_action("admin_menu", function() {
    add_options_page("Admiral Options", "Admiral", "manage_options", "admiral-adblock-analytics", function() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        $embedOptionKey = wp\AdmiralAdBlockAnalytics::EMBED_OPTION_KEY;
        $propertyOptionIDKey = wp\AdmiralAdBlockAnalytics::PROPERTY_OPTION_ID_KEY;
        if (array_key_exists($propertyOptionIDKey, $_POST)) {
            $pidValue = wp\AdmiralAdBlockAnalytics::getPropertyID();
            if ($pidValue !== $_POST[$propertyOptionIDKey]) {
                wp\AdmiralAdBlockAnalytics::updatePropertyIDByPOST();
            }
        }

        $pidValue = wp\AdmiralAdBlockAnalytics::getPropertyID();
        $pidPromiseValue = wp\AdmiralAdBlockAnalytics::getPropertyPromiseID();
        $pluginCode = wp\AdmiralAdBlockAnalytics::getPluginCode();
        $pidOrphan = wp\AdmiralAdBlockAnalytics::isPropertyOrphanProperty($pidValue);
        $pidValueSpecial = htmlspecialchars($pidValue);

        echo <<<END
<div class="wrap" style="margin-top: 30px;">
    <div class="logo-container">
        <img src="//cdn.getadmiral.com/logo-horz.svg" height="40" />
    </div>
    <p>The Admiral WordPress Plugin allows you to easily measure how much revenue you are losing to adblock. After measuring, you can use the Admiral platform to recover and engage with adblock visitors.</p>
    <p><a href="https://learn.getadmiral.com" target="_blank">Learn more about Admiral</a></p>
END;
        if (wp\AdmiralAdBlockAnalytics::getEnvConfiguredPropertyID()) {
            echo <<<END
            <table class="form-table">
            <tbody>
                <tr>
                    <th>Property ID</th>
                    <td>$pidValueSpecial (configured via <strong>ADMIRAL_PROPERTY_ID</strong> environment variable)</td>
                </tr>
            </tbody>
            </table>
END;
        } else {
            $promiseError = false;
            $signupLink = wp\AdmiralAdBlockAnalytics::getClaimPropertyLink();
            if (empty($signupLink)) {
                $signupLink = wp\AdmiralAdBlockAnalytics::getBaseSignupLink();
                if ($pidPromiseValue) {
                    $promiseError = true;
                }
            }

            if (empty($pidValue) || $pidOrphan) {
                echo <<<END
    <div class="sub-cta" style="max-width:800px;padding: 40px;border-radius: 6px;box-shadow: 0 1px 2px 0 rgba(0,0,0,.1);background: linear-gradient(125deg,transparent 60%,rgba(41,98,255,.8)),#0c2c5b;color: #fff; overflow:auto;">
        <h3 style="font-size: 20px;margin-bottom: 10px;margin-top:0;color: #fff;">Free Adblock Analytics with Admiral.</h3>
        <p style="color:white;">Admiral is the industry's leading adblock revenue recovery specialists, serving over 12,000 customers worldwide. We build products that empower publishers to grow visitor relationships, protect copyrighted content, and recover advertising revenue.</p>
END;
                if (empty($pidValue)) {
                    echo <<<END
        <div>
            <p>Click <strong>Create Property</strong> to start collecting data and discover how much revenue you're losing to adblockers. Afterwards, create an account to view your analytics.</p>
            <form action="admin-post.php" method="POST">
                <input type="hidden" name="action" value="activate_admiral_adblocks_analytics_$pluginCode" />
                <button class="button button-primary" style="margin-right: 15px;display: block;height: 40px;padding: 0 16px;float: left;font-size: 14px;line-height: 40px;border: 0; outline: 0;border-radius: 3px;cursor: pointer;overflow: hidden;box-sizing: border-box;transition: .25s;" name="accept" type="submit">Create Property</button>
            </form>
        </div>
END;
                } else {
                    echo <<<END
        <div>
            <p>Click <strong>Create Account</strong> to view analytics about your traffic and visitors.</p>
        </div>
END;
                }
                echo <<<END
        <a href="$signupLink">
            <button style="background-color: #f44336;position: relative;display: block;height: 40px;padding: 0 16px;float: left;font-size: 14px;line-height:40px;border: 0;outline: 0;border-radius: 3px;cursor: pointer;overflow: hidden;box-sizing: border-box;transition: .25s; color:white;">Create Account</button>
        </a>
    </div>
END;
            } else {
                echo <<<END
    <form action="https://app.getadmiral.com" method="GET" target="_blank">
        <input type="hidden" name="redirect" value="wp-view">
        <input type="submit" value="View your Analytics">
    </form>
END;
            }
            settings_fields('admiral_property_settings');
            do_settings_sections('admiral_property_settings');

            if ($promiseError) {
                echo <<<HTML
    <div class="error notice">
        <p>Unable to generate claim token for your property. Please contact us at <a href="https://www.getadmiral.com/contact-us">https://www.getadmiral.com/contact-us</a> to resolve.</p>
    </div>
HTML;
            }

            $args = array('page' => 'admiral-adblock-analytics');
            $url = add_query_arg($args, class_exists('Jetpack') ? admin_url('admin.php') : admin_url('options-general.php'));

            echo <<<END
    <form method="POST" action="$url">
        <table class="form-table">
            <tbody>
                <tr>
                    <th>Property ID</th>
                    <td>
                        <input type="text" size="35" name="$propertyOptionIDKey" value="$pidValueSpecial" />
END;
            if (empty($pidValue)) {
                echo <<<END
                        <p class="description">The property ID is a unique identifier
                            for your site. If you already have an account at
                            getadmiral.com, then enter the property ID found
                            on the property settings page here. If you
                            don't, no worries!
                        </p>
                        <p class="description">Creating an
                            account is quick and free
                            <a href="$signupLink">via our dashboard</a>.
                        </p>
END;
            }
            echo <<<END
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="hidden" name="$embedOptionKey" value="" />
END;

            submit_button();
            echo '</form></div>';
        }
    });
});

function admiraladblock_auto_update($update, $item) {
    if (!empty($item) && $item->slug == 'admiral-adblock-suite') {
        return true;
    }
    // fallback to whatever it was going to do instead
    return $update;
}

// This checks for `Activated_Admiral` and redirects to the plugin settings page.
if (is_admin()) {
    add_action('init', function() {
        if (get_option('Activated_Admiral')) {
            delete_option('Activated_Admiral');
            if (!headers_sent()) {
                wp_redirect(add_query_arg(array('page' => 'admiral-adblock-analytics'), class_exists('Jetpack') ? admin_url('admin.php') : admin_url('options-general.php')));
            }
        }
    });
}

if (function_exists('add_filter')) {
    add_filter('auto_update_plugin', 'admiraladblock_auto_update', 10, 2);
}

add_action('admin_notices', function() {
    global $hook_suffix;

    $pidValue = wp\AdmiralAdBlockAnalytics::getPropertyID();

    if (('plugins.php' === $hook_suffix || 'index.php' === $hook_suffix) && empty($pidValue)) {
        $title = esc_html__('Admiral is not configured.', 'admiral');
        $desc = esc_html__('A Property ID must be configured for Admiral to measure visitors with adblock installed and provide analytics. Visit the plugin Settings to complete setup.', 'admiral');
        echo <<<END
<div class="notice notice-warning">
    <p><strong>$title</strong></p>
    <p>$desc</p>
</div>
END;
    }
});

/* EOF */

<?php
namespace wp;

class AdmiralAdBlockAnalytics
{
    /**
     * Default duration (1 day) (24 * 60 * 60) to store the embed for before requesting another script
     * @var int
     */
    const DEFAULT_EMBED_EXPIRATION = 86400;

    /**
     * Option keys for admiral plugin settings and other configuration data
     */
    const PROPERTY_OPTION_ID_KEY = "admiral_property_id";
    const PROPERTY_PROMISE_OPTION_ID_KEY = "admiral_property_promise_id";
    const PROPERTY_PROMISE_PROPERTY_OPTION_ID_KEY = "admiral_property_promise_property_id";
    const EMBED_OPTION_KEY = "admiral_embed";
    // ends in "2" to ignore old expiration option set before the fix for multiplciation vs addition of expiration
    const EMBED_EXPIRATION_OPTION_KEY = "admiral_embed_expiration2";
    const APPEND_PHP_OPTION_KEY = "admiral_append_php";

    /**
     * Suffix to append to the user-agent when proxying requests
     * @var string
     */
    public static $UASuffix = "ADMIRALWP/uninit";

    /**
     * Admiral PropertyID you get when signing up for Admiral
     * @var string
     */
    private static $propertyID = "";

    /**
     * Whether the propertyID was configured via an environment variable
     * @var bool
     */
    private static $envConfiguredPropertyID = false;

    /**
     * Admiral PropertyPromiseID you get when signing up for Admiral
     * @var string
     */
    private static $propertyPromiseID = "";

    /**
     * Admiral Embed script you get when signing up for Admiral
     * @var string
     */
    private static $embed = "";

    /**
     * Endpoint to partner API
     */
    private static $partnerApiEndpoint = "//partner.api.getadmiral.com/";


    /**
     * List of headers to copy over when proxying the script
     * @var array
     */
    public static $headersToCopy = array(
        "Vary",
        "Content-Encoding",
        "Expires",
        "Last-Modified",
        "Content-Type",
        "X-Hostname",
        "Date",
        "Cache-Control",
    );

    private static $clientID = "";

    private static $clientSecret = "";

    private static $pluginCode = "";

    private static $environment = "production";

    private static function setPropertyID($propertyID)
    {
        if (empty($propertyID)) {
            throw new Exception("PropertyID cannot be empty");
        }
        self::$propertyID = $propertyID;
    }

    /**
     * Get the configured propertyID from either an environment variable
     * or from the WordPress option.
     *
     * @return string the configured propertyID (or empty string)
     */
    public static function getPropertyID()
    {
        if (empty(self::$propertyID) && defined("ADMIRAL_PROPERTY_ID")) {
            self::$propertyID = ADMIRAL_PROPERTY_ID;
            if (!empty(self::$propertyID)) {
                self::$envConfiguredPropertyID = true;
            }
        }
        if (empty(self::$propertyID)){
            self::$propertyID = get_option(self::PROPERTY_OPTION_ID_KEY, "");
        }
        return self::$propertyID;
    }

    /**
     * Getter for self::$envConfiguredPropertyID
     *
     * @return bool whether an environment var configured the PROPERTY_ID
     */
    public static function getEnvConfiguredPropertyID() {
        return self::$envConfiguredPropertyID;
    }

    /**
     * Completes a POST submission by reading from $_POST for the
     * PROPERTY_OPTION_ID_KEY and saving it in the WordPress options.
     *
     * @return bool whether the new value was saved
     */
    public static function updatePropertyIDByPOST()
    {
        $propertyID = trim($_POST[self::PROPERTY_OPTION_ID_KEY]);
        if ($propertyID !== self::getPropertyID()) {
            if (strpos($propertyID, 'A-') !== 0) {
                return false;
            }
            self::$propertyID = $propertyID;
            update_option(self::PROPERTY_OPTION_ID_KEY, $propertyID);
            // delete the promise since this is a user provided propertyID
            delete_option(self::PROPERTY_PROMISE_OPTION_ID_KEY);
            delete_option(self::PROPERTY_PROMISE_PROPERTY_OPTION_ID_KEY);

            // if the propertyID updated, the embed should too
            $script = self::requestEmbedScript();
            if (!empty($script)) {
                self::setEmbed($script);
            }

            return true;
        }
        return false;
    }

    private static function setPropertyPromiseID($propertyPromiseID)
    {
        if (empty($propertyPromiseID)) {
            throw new Exception("propertyPromiseID cannot be empty");
        }
        self::$propertyPromiseID = $propertyPromiseID;
    }

    public static function getPropertyPromiseID()
    {
        if(empty(self::$propertyPromiseID)){
            self::$propertyPromiseID = get_option(self::PROPERTY_PROMISE_OPTION_ID_KEY, "");
        }
        return self::$propertyPromiseID;
    }

    public static function isPropertyOrphanProperty($propertyID){
        $promiseProperty = get_option(self::PROPERTY_PROMISE_PROPERTY_OPTION_ID_KEY, "");
        return $propertyID === $promiseProperty;
    }

    private static function setEmbed($embed)
    {
        if (empty($embed)) {
            throw new Exception("Embed cannot be empty");
        }
        self::$embed = $embed;
        update_option(self::EMBED_OPTION_KEY, $embed);
        update_option(self::EMBED_EXPIRATION_OPTION_KEY, time() + self::DEFAULT_EMBED_EXPIRATION);
    }

    /**
     * Retrieves the embed from the DB. If it doesn't exist or
     * has expired, retrieves an embed from kikis and returns it
     *
     * @return string the embed or empty string
     */
    public static function getEmbed()
    {
        if (empty(self::$embed)) {
            self::$embed = get_option(self::EMBED_OPTION_KEY, "");
            $embedTimeout = get_option(self::EMBED_EXPIRATION_OPTION_KEY, 0);

            // If the no embed was stored in the DB, or the current embed is timed out
            // we should request a new embed script
            $isExpired = $embedTimeout < time();
            $isEmpty = empty(self::$embed) && !empty(self::getPropertyID());
            if ($isExpired || $isEmpty) {
                $script = self::requestEmbedScript();
                if (!empty($script)) {
                    self::setEmbed($script);
                }
            }
        }
        return self::$embed;
    }

    public static function setClientIDSecret($clientID, $clientSecret)
    {
        self::$clientID = $clientID;
        self::$clientSecret = $clientSecret;
    }

    /**
     * Retrieves the property ID and returns true or false respectively if it is set or not
     * Also allows for setting up the plugin code/version
     */
    public static function initialize($pluginCode, $pluginVersion, $env)
    {
        self::$pluginCode = $pluginCode;
        self::$environment = $env;
        self::$UASuffix = "ADMIRALWP/" . $pluginVersion . " " . $pluginCode;
        add_action('admin_post_activate_admiral_adblocks_analytics_' . $pluginCode, function() {
            // Call redirect before all so that the user isn't sent to a blank page in case of handled error.
            $referer = wp_get_referer();
            if (array_key_exists("accept", $_POST)) {
                AdmiralAdBlockAnalytics::createNewProperty("");
            }
            if (!empty($referer)){
                wp_redirect($referer);
            } else {
                wp_redirect('index.php');
            }
        });

        $propertyID = self::getPropertyID();
        if (empty($propertyID)) {
            return false;
        }
        return true;
    }

    public static function getPluginCode() {
        return self::$pluginCode;
    }

    public static function getBaseSignupLink() {
        return 'https://app.getadmiral.com/signup';
    }

    public static function getClaimPropertyLink() {
        $link = self::getBaseSignupLink();
        $token = self::getPropertyClaimToken();
        $url = get_site_url();
        $pid = self::getPropertyID();
        if (empty($token)) {
            return '';
        }
        $qs = http_build_query(array(
            'i' => 'claim-property',
            't' => $token,
            'd' => $url,
            'p' => $pid,
            'aid' => self::$clientID,
        ));
        return $link . '?' . $qs;
    }

    private static function httpCall($url, $headers = array(), $postBody = "")
    {
        $res = array("source" => "",
                     "error" => null,
                     "headers" => array(),
                     );
        $urlWithScheme = "https:" . $url;
        $ua = self::$UASuffix;
        $foundUA = false;
        foreach ($headers as $key => $header) {
            if (stripos($header, "user-agent") !== false) {
                $parts = explode(":", $header, 2);
                $ua = (isset($parts[1]) ? trim($parts[1]) . " " : "") . $ua;
                $headers[$key] = "User-Agent: $ua";
                $foundUA = true;
            }
        }
        if (!$foundUA) {
            $headers[] = "User-Agent: $ua";
        }
        if (function_exists("wp_remote_retrieve_body") && function_exists("wp_remote_get") && function_exists("wp_remote_post")) {
            $args = array(
                "headers" => $headers,
                "user-agent" => $ua,
            );
            if (!empty($postBody)) {
                $args["body"] = $postBody;
                $resp = wp_remote_post($urlWithScheme, $args);
            } else {
                $resp = wp_remote_get($urlWithScheme, $args);
            }
            if (function_exists('is_wp_error') && is_wp_error($resp)) {
                $res["error"] = array("code" => $resp->get_error_code(),
                                      "str" => $resp->get_error_message(),
                                      "type" => "wp",
                                      );
            } else {
                $body = wp_remote_retrieve_body($resp);
                if (empty($body)) {
                    $res["error"] = array("code" => 0,
                                          "str" => "Unknown error but empty body",
                                          "type" => "wp",
                                          );
                } else {
                    $res["source"] = $body;
                    $headers = wp_remote_retrieve_headers($resp);
                    foreach ($headers as $key => $val) {
                        $res["headers"][] = "$key: $val";
                    }
                }
            }
        }
        return $res;
    }

    private static function getSecretPromiseCall()
    {
        $postData = array(
            "method" => "Partner.CreateSecretPromise",
            "jsonrpc" => "2.0",
            "params" => array(
                "clientID" => self::$clientID,
                "clientSecret" => self::$clientSecret
            )
        );
        $res = self::httpCall(self::$partnerApiEndpoint, array(), json_encode($postData));
        if (empty($res["source"])) {
            return "";
        }
        $body = json_decode($res["source"]);
        if (empty($body->result)) {
            return "";
        }
        return $body->result->secretPromise;
    }

    private static function createPropertyCall($secretPromise, $domain)
    {
        $postData = array(
            "method" => "Partner.NewOrphanProperty",
            "jsonrpc" => "2.0",
            "params" => array(
                "clientID" => self::$clientID,
                "clientSecretPromise" => $secretPromise,
                "domain" => $domain,
                "withEmbed" => true
            )
        );
        $res = self::httpCall(self::$partnerApiEndpoint, array(), json_encode($postData));
        if (empty($res["source"])) {
            return "";
        }
        $body = json_decode($res["source"]);
        if (empty($body->result)) {
            return "";
        }
        return $body->result;
    }

    /**
     * Function to create new anonymous property
     *
     * @return bool whether the property was created and setup
     */
    public static function createNewProperty($domain)
    {
        $secretPromise = self::getSecretPromiseCall();
        if (empty($domain)) {
            $domain = get_site_url();
        }
        if (!empty($secretPromise)) {
            $property = self::createPropertyCall($secretPromise, $domain);
            if (!empty($property)) {
                self::setPropertyID($property->propertyID);
                self::setPropertyPromiseID($property->propertyPromiseID);
                update_option(self::PROPERTY_OPTION_ID_KEY, self::getPropertyID());
                update_option(self::PROPERTY_PROMISE_OPTION_ID_KEY, self::getPropertyPromiseID());
                update_option(self::PROPERTY_PROMISE_PROPERTY_OPTION_ID_KEY, self::getPropertyID());
                // Get embed will handle any potential fetching of embed code at this point
                self::setEmbed($property->embed);
                return true;
            }

            return false;
        }
    }

    /**
     * Make a request to the delivery service to get a script for this property
     *
     * @return string the token or an empty string
     */
    private static function getPropertyClaimToken()
    {
        $secretPromise = self::getSecretPromiseCall();
        $postData = array(
            "method" => "Partner.GetClaimPropertyToken",
            "jsonrpc" => "2.0",
            "params" => array(
                "clientID" => self::$clientID,
                "clientSecretPromise" => $secretPromise,
                "propertyID" => self::getPropertyID(),
                "propertyPromiseID" => self::getPropertyPromiseID()
            )
        );
        $res = self::httpCall(self::$partnerApiEndpoint, array(), json_encode($postData));
        if (empty($res["source"])) {
            return "";
        }
        $body = json_decode($res["source"]);
        if (empty($body->result)) {
            return "";
        }
        if (empty($body->result->claimPropertyToken)) {
            return "";
        }
        return $body->result->claimPropertyToken;
    }

    /**
     * Make a request to the delivery service to get a script for this property
     *
     * @return string the embed or an empty string
     */
    private static function requestEmbedScript()
    {
        $propertyID = self::getPropertyID();
        if (!self::isPropertyOrphanProperty($propertyID)) {
            $res = self::httpCall("//delivery.api.getadmiral.com/script/" . $propertyID . "/bootstrap?cacheable=1&environment=" . self::$environment, array());
            return '<script>' . $res["source"] . '</script>';
        }
        $secretPromise = self::getSecretPromiseCall();
        $postData = array(
            "method" => "Partner.GetEmbedCode",
            "jsonrpc" => "2.0",
            "params" => array(
                "clientID" => self::$clientID,
                "clientSecretPromise" => $secretPromise,
                "propertyID" => $propertyID,
                "propertyPromiseID" => self::getPropertyPromiseID(),
                "environment" => self::$environment,
            )
        );
        $res = self::httpCall(self::$partnerApiEndpoint, array(), json_encode($postData));
        if (empty($res["source"])) {
            return "";
        }
        $body = json_decode($res["source"]);
        if (empty($body->result)) {
            return "";
        }
        if (empty($body->result->embed)) {
            return "";
        }
        return $body->result->embed;
    }
}

/* EOF */

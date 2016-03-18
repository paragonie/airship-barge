<?php
declare(strict_types=1);
namespace Airship\Barge;

/**
 * Class HTTP
 *
 * Handles the network requests
 *
 * @package Airship\Barge
 */
abstract class HTTP
{
    public static $last_ch; // Store the last curl handle here
    
    /**
     * Do a simple GET request
     * 
     * @param string $url
     * @param array $args
     * @param array $options
     * @return string
     */
    public static function get(
        string $url,
        array $args = [],
        array $options = []
    ): string {
        if (!empty($args)) {
            $url .= \strpos($url, '?') === false ? '?' : '&';
            $url .= \http_build_query($args);
        }
        self::$last_ch = \curl_init($url);
        \curl_setopt(self::$last_ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt_array(self::$last_ch, $options);
        return \curl_exec(self::$last_ch);
    }
    
    /**
     * Do a simple POST request
     * 
     * @param string $url
     * @param array $args
     * @param array $options
     * @return string
     */
    public static function post(
        string $url,
        array $args = [],
        array $options = []
    ): string {
        self::$last_ch = \curl_init($url);
        \curl_setopt(self::$last_ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt(self::$last_ch, CURLOPT_POST, true);
        \curl_setopt(self::$last_ch, CURLOPT_POSTFIELDS, $args);
        \curl_setopt_array(self::$last_ch, $options);
        return \curl_exec(self::$last_ch);
    }
}
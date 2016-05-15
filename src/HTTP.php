<?php
declare(strict_types=1);
namespace Airship\Barge;

use \ParagonIE\ConstantTime\{
    Base64UrlSafe,
    Binary
};
use \ParagonIE\Halite\Asymmetric\{
    Crypto as Asymmetric,
    SignaturePublicKey
};

/**
 * Class HTTP
 *
 * Handles the network requests
 *
 * @package Airship\Barge
 */
abstract class HTTP
{
    const ENCODED_SIGNATURE_LENGTH = 88;

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
     * Get/verify/parse a JSON response
     *
     * @param string $url
     * @param SignaturePublicKey $publicKey
     * @param array $args
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public static function getSignedJSON(
        string $url,
        SignaturePublicKey $publicKey,
        array $args = [],
        array $options = []
    ): array {
        $body = self::get($url, $args, $options);
        $firstNewLine = \strpos($body, "\n");
        // There should be a newline immediately after the base64urlsafe-encoded signature
        if ($firstNewLine !== self::ENCODED_SIGNATURE_LENGTH) {
            throw new \Exception('Invalid Signature');
        }
        $sig = Base64UrlSafe::decode(
            Binary::safeSubstr($body, 0, 88)
        );
        $msg = Binary::safeSubstr($body, 89);
        if (!Asymmetric::verify($msg, $publicKey, $sig, true)) {
            throw new \Exception('Invalid Signature');
        }
        return \json_decode($msg, true);
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
        \curl_setopt(self::$last_ch, CURLOPT_POSTFIELDS, \http_build_query($args));
        \curl_setopt_array(self::$last_ch, $options);
        return \curl_exec(self::$last_ch);
    }

    /**
     * Get/verify/parse a JSON response
     *
     * @param string $url
     * @param SignaturePublicKey $publicKey
     * @param array $args
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public static function postSignedJSON(
        string $url,
        SignaturePublicKey $publicKey,
        array $args = [],
        array $options = []
    ): array {
        $body = self::post($url, $args, $options);
        if (empty($body)) {
            throw new \Exception('Empty response');
        }
        $firstNewLine = \strpos($body, "\n");
        // There should be a newline immediately after the base64urlsafe-encoded signature
        if ($firstNewLine !== self::ENCODED_SIGNATURE_LENGTH) {
            throw new \Exception('Invalid Signature');
        }
        $sig = Base64UrlSafe::decode(
            Binary::safeSubstr($body, 0, 88)
        );
        $msg = Binary::safeSubstr($body, 89);
        if (!Asymmetric::verify($msg, $publicKey, $sig, true)) {
            throw new \Exception('Invalid Signature');
        }
        return \json_decode($msg, true);
    }
}
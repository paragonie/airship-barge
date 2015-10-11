<?php
namespace ParagonIE\Halite;

abstract class Util
{
    /**
     * Safe string length
     *
     * @staticvar boolean $exists
     * @param string $str
     * @return int
     */
    public static function safeStrlen($str)
    {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_strlen');
        }
        if ($exists) {
            $length = \mb_strlen($str, '8bit');
            if ($length === false) {
                
            }
            return $length;
        } else {
            return \strlen($str);
        }
    }
    /**
     * Safe substring
     *
     * @staticvar boolean $exists
     * @param string $str
     * @param int $start
     * @param int $length
     * @return string
     */
    public static function safeSubstr($str, $start, $length = null)
    {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_substr');
        }
        if ($exists) {
            // mb_substr($str, 0, NULL, '8bit') returns an empty string on PHP
            // 5.3, so we have to find the length ourselves.
            if (!isset($length)) {
                if ($start >= 0) {
                    $length = self::safeStrlen($str) - $start;
                } else {
                    $length = -$start;
                }
            }
            return \mb_substr($str, $start, $length, '8bit');
        }
        // Unlike mb_substr(), substr() doesn't accept NULL for length
        if ($length !== null) {
            return \substr($str, $start, $length);
        } else {
            return \substr($str, $start);
        }
    }
    
    /**
     * Use a derivative of HKDF to derive multiple keys from one.
     * http://tools.ietf.org/html/rfc5869
     * 
     * This is a variant from hash_hkdf() and instead uses BLAKE2b provided by
     * libsodium.
     * 
     * Important: instead of a true HKDF (from HMAC) construct, this uses the 
     * \Sodium\crypto_generichash() key parameter. This is *probably* okay.
     * 
     * @param string $ikm Initial Keying Material
     * @param int $length How many bytes?
     * @param string $info What sort of key are we deriving?
     * @param string $salt
     * @return string
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     */
    public static function hkdfBlake2b($ikm, $length, $info = '', $salt = null)
    {
        // Sanity-check the desired output length.
        if (empty($length)
            || !\is_int($length)
            || $length < 0
            || $length > 255 * \Sodium\CRYPTO_GENERICHASH_KEYBYTES
        ) {
            throw new \ParagonIE\Halite\Alerts\InvalidDigestLength(
                'Bad HKDF Digest Length'
            );
        }
        // "if [salt] not provided, is set to a string of HashLen zeroes."
        if (\is_null($salt)) {
            $salt = \str_repeat("\x00", \Sodium\CRYPTO_GENERICHASH_KEYBYTES);
        }

        // HKDF-Extract:
        // PRK = HMAC-Hash(salt, IKM)
        // The salt is the HMAC key.
        $prk = \Sodium\crypto_generichash($ikm, $salt);

        // HKDF-Expand:
        // This check is useless, but it serves as a reminder to the spec.
        if (self::safeStrlen($prk) < \Sodium\CRYPTO_GENERICHASH_KEYBYTES) {
            throw new \ParagonIE\Halite\Alerts\CannotPerformOperation(
                'An unknown error has occurred'
            );
        }
        // T(0) = ''
        $t = '';
        $last_block = '';
        for ($block_index = 1; self::safeStrlen($t) < $length; ++$block_index) {
            // T(i) = HMAC-Hash(PRK, T(i-1) | info | 0x??)
            $last_block = \Sodium\crypto_generichash(
                $last_block . $info . \chr($block_index),
                $prk
            );
            // T = T(1) | T(2) | T(3) | ... | T(N)
            $t .= $last_block;
        }
        // ORM = first L octets of T
        $orm = self::safeSubstr($t, 0, $length);
        if ($orm === false) {
            throw new \ParagonIE\Halite\Alerts\CannotPerformOperation(
                'An unknown error has occurred'
            );
        }
        return $orm;
    }
}

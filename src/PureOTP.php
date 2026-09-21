<?php
// src/PureOTP.php

/**
 * Pure PHP 8 RFC 6238 TOTP implementation.
 */
class PureOTP {
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32 secret key (16 characters = 80 bits).
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a secret key at a specific timestamp.
     */
    public static function getCode(string $secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $timeSlice = floor($timestamp / 30);
        
        $secretBin = self::base32Decode($secret);
        // pack 'J' is 64-bit unsigned integer (big-endian), available in PHP 7.0+
        $timeBin = pack('J', $timeSlice);

        // HMAC-SHA1 computation
        $hmac = hash_hmac('sha1', $timeBin, $secretBin, true);

        // Dynamic truncation
        $offset = ord($hmac[19]) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        $modulo = 10 ** 6;

        return str_pad((string)($value % $modulo), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-provided 6-digit code against secret (with ±3 time slices = 90s tolerance).
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 3): bool {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTime = time();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $checkTime = $currentTime + ($i * 30);
            if (hash_equals(self::getCode($secret, $checkTime), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Custom Base32 decoder.
     */
    private static function base32Decode(string $secret): string {
        $secret = strtoupper($secret);
        $secret = preg_replace('/[^A-Z2-7]/', '', $secret);
        
        $binaryString = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            $position = strpos(self::$base32Chars, $char);
            if ($position !== false) {
                $binaryString .= sprintf('%05b', $position);
            }
        }

        $bytes = '';
        $binaryLength = strlen($binaryString);
        for ($i = 0; $i + 8 <= $binaryLength; $i += 8) {
            $bytes .= chr(bindec(substr($binaryString, $i, 8)));
        }

        return $bytes;
    }
}




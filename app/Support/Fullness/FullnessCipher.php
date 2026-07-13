<?php

declare(strict_types=1);

namespace App\Support\Fullness;

/**
 * Mirrors the Fullness CRM's `App\Helpers\AesEncryptionHelper`.
 *
 * The CRM's API is wrapped by an EncryptDecryptMiddleware that AES-256-CBC
 * encrypts every v1 request and response body as `{"data": "<base64>"}`. The
 * connector must speak the same scheme or the server decrypts our payload into
 * garbage (a correct email/password then fails as "invalid credentials").
 *
 * Production uses these shared default key/IV (they are baked into the mobile
 * app too, so they are not a real secret); override via
 * `config('services.fullness.aes_key')` / `aes_iv` if Fullness ever rotates them.
 */
final class FullnessCipher
{
    private const CIPHER = 'AES-256-CBC';

    private const DEFAULT_KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90'; // 32 bytes

    private const DEFAULT_IV = '1234567890abcdef'; // 16 bytes

    public static function encrypt(string $plaintext): string
    {
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, self::iv());

        return base64_encode((string) $encrypted);
    }

    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }

        $plaintext = openssl_decrypt($raw, self::CIPHER, self::key(), OPENSSL_RAW_DATA, self::iv());

        return $plaintext === false ? null : $plaintext;
    }

    private static function key(): string
    {
        $key = config('services.fullness.aes_key');

        return is_string($key) && $key !== '' ? $key : self::DEFAULT_KEY;
    }

    private static function iv(): string
    {
        $iv = config('services.fullness.aes_iv');

        return is_string($iv) && $iv !== '' ? $iv : self::DEFAULT_IV;
    }
}

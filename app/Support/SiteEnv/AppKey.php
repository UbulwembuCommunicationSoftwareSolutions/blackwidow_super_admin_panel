<?php

namespace App\Support\SiteEnv;

use Illuminate\Encryption\Encrypter;

/**
 * A Laravel APP_KEY the encrypter will accept. The template placeholder
 * base64:CHANGE_ME is not one of these, and pushing it replaces the key
 * key:generate wrote on the site.
 */
final class AppKey
{
    public static function isUsable(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $key = $value;
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                return false;
            }
            $key = $decoded;
        }

        return Encrypter::supported($key, 'AES-256-CBC')
            || Encrypter::supported($key, 'AES-128-CBC');
    }
}

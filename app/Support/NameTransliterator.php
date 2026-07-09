<?php

declare(strict_types=1);

namespace App\Support;

use Transliterator;

/**
 * ZKTeco devices store the user name in a fixed 24-byte ASCII field.
 * Non-ASCII names (e.g. Hebrew/Arabic) are corrupted on the terminal, so we
 * transliterate to ASCII and hard-cap the byte length before pushing.
 */
final class NameTransliterator
{
    public static function toAscii(string $value, int $maxLength = 24): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $ascii = null;

        if (class_exists(Transliterator::class)) {
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; [^\x00-\x7F] Remove');

            if ($transliterator !== null) {
                $result = $transliterator->transliterate($value);

                if ($result !== false) {
                    $ascii = $result;
                }
            }
        }

        if ($ascii === null) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            $ascii = $converted !== false ? $converted : $value;
        }

        // Drop anything still outside printable ASCII and collapse whitespace.
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '', $ascii);
        $ascii = trim((string) preg_replace('/\s+/', ' ', $ascii));

        return mb_strcut($ascii, 0, $maxLength);
    }
}

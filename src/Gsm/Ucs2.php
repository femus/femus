<?php

declare(strict_types=1);

namespace Femus\Gsm;

/**
 * Text-mode SMS carries anything beyond the GSM alphabet — Cyrillic, Greek, emoji —
 * as UCS-2 hex, so a message typed in Russian arrives as "0421044204...". The modem
 * does not unwrap it and neither did we, which is how a question reached the AI as a
 * wall of hex digits. Both directions are handled here.
 */
final class Ucs2
{
    /** Hex UCS-2 in, UTF-8 out; anything else is passed through untouched. */
    public static function decode(string $text): string
    {
        if (!self::looksLikeHex($text)) {
            return $text;
        }

        $utf8 = @mb_convert_encoding((string) hex2bin($text), 'UTF-8', 'UTF-16BE');

        return $utf8 === false || $utf8 === '' ? $text : $utf8;
    }

    /** UTF-8 in, upper-case hex UCS-2 out. */
    public static function encode(string $text): string
    {
        return strtoupper(bin2hex((string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')));
    }

    /** True when the text needs UCS-2 — i.e. it is not plain ASCII. */
    public static function isNeeded(string $text): bool
    {
        return preg_match('/^[\x20-\x7E\r\n\t]*$/', $text) !== 1;
    }

    /**
     * Telling UCS-2 hex from a short ASCII word made of hex letters is guesswork —
     * "FACE" is both. The rules below keep the guess conservative: at least two
     * characters' worth of hex, and every code unit must be a usable character, which
     * rules out "DEADBEEF" (U+DEAD is an unpaired surrogate) and anything holding
     * control characters.
     */
    private static function looksLikeHex(string $text): bool
    {
        $text = trim($text);

        if (strlen($text) < 8 || strlen($text) % 4 !== 0 || preg_match('/^[0-9A-Fa-f]+$/', $text) !== 1) {
            return false;
        }

        foreach (str_split($text, 4) as $unit) {
            $code = (int) hexdec($unit);
            if (($code >= 0xD800 && $code <= 0xDFFF) || $code < 0x20 && !in_array($code, [0x09, 0x0A, 0x0D], true)) {
                return false;
            }
        }

        return true;
    }
}

<?php

namespace App\Services\Broadcasting;

/**
 * How many SMS a text costs. Plain GSM text fits 160 characters (153 per
 * part when split); anything outside the GSM alphabet, such as Igbo's
 * ị, ọ, ụ, switches the whole message to Unicode: 70 (67 per part).
 */
class SmsText
{
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Count as two characters (an escape plus the character). */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    public static function isGsm(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM, $char) && ! str_contains(self::GSM_EXTENDED, $char)) {
                return false;
            }
        }

        return true;
    }

    public static function length(string $text): int
    {
        if (! self::isGsm($text)) {
            return mb_strlen($text);
        }

        $length = 0;
        foreach (mb_str_split($text) as $char) {
            $length += str_contains(self::GSM_EXTENDED, $char) ? 2 : 1;
        }

        return $length;
    }

    public static function parts(string $text): int
    {
        $length = self::length($text);
        [$single, $multi] = self::isGsm($text) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    /**
     * @return array{length: int, parts: int, unicode: bool}
     */
    public static function describe(string $text): array
    {
        return ['length' => self::length($text), 'parts' => self::parts($text), 'unicode' => ! self::isGsm($text)];
    }
}

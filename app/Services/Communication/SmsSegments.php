<?php

namespace App\Services\Communication;

/**
 * How many billable SMS parts a message is split into. Plain text (the GSM-7 alphabet) fits
 * 160 characters in one SMS, or 153 per part when it's longer; a single emoji or non-Latin
 * character switches the whole message to Unicode, which fits only 70 (67 per part).
 */
final class SmsSegments
{
    private const string GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Count as two characters (an escape plus the character). */
    private const string GSM_EXTENDED = "^{}\\[~]|€\f";

    public static function count(string $message): int
    {
        if ($message === '') {
            return 0;
        }

        $units = 0;

        foreach (mb_str_split($message) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $units++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $units += 2;
            } else {
                $length = mb_strlen($message);

                return $length <= 70 ? 1 : (int) ceil($length / 67);
            }
        }

        return $units <= 160 ? 1 : (int) ceil($units / 153);
    }

    public static function isUnicode(string $message): bool
    {
        foreach (mb_str_split($message) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) === false && mb_strpos(self::GSM_EXTENDED, $char) === false) {
                return true;
            }
        }

        return false;
    }
}

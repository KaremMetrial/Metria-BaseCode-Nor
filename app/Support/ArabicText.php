<?php

namespace App\Support;

final class ArabicText
{
    public const LETTERS = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ی' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ک' => 'ك'];

    public static function digits(string $value): string
    {
        return strtr($value, array_combine(mb_str_split('٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹'), str_split('01234567890123456789')));
    }

    public static function normalize(string $value, bool $relaxedEnding = false): string
    {
        $value = str_replace(self::ignoredCharacters(), '', strtr($value, self::LETTERS));

        return $relaxedEnding ? str_replace('ة', 'ه', $value) : $value;
    }

    public static function hasArabicLetters(string $value): bool
    {
        return preg_match('/(?=\p{Arabic})\p{L}/u', $value) === 1;
    }

    /** @return list<string> */
    public static function ignoredCharacters(): array
    {
        static $characters;

        return $characters ??= array_map(fn (int $code): string => mb_chr($code, 'UTF-8'), array_merge(
            range(0x09, 0x0D), [0x20, 0x85, 0xA0, 0x1680], range(0x2000, 0x200F),
            [0x2028, 0x2029, 0x202F, 0x205F, 0x2060, 0x3000, 0xFEFF, 0x061C],
            [0x0640, 0x0670], range(0x0610, 0x061A), range(0x064B, 0x065F),
            range(0x06D6, 0x06DC), range(0x06DF, 0x06E4), range(0x06E7, 0x06E8), range(0x06EA, 0x06ED),
        ));
    }
}

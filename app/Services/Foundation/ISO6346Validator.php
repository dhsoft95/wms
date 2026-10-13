<?php

declare(strict_types=1);

namespace App\Services\Foundation;

final class ISO6346Validator
{
    private const LETTER_VALUES = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15, 'F' => 16,
        'G' => 17, 'H' => 18, 'I' => 19, 'J' => 20, 'K' => 21, 'L' => 23,
        'M' => 24, 'N' => 25, 'O' => 26, 'P' => 27, 'Q' => 28, 'R' => 29,
        'S' => 30, 'T' => 31, 'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36,
        'Y' => 37, 'Z' => 38,
    ];

    public static function normalize(string $number): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', $number));
    }

    public static function isValid(string $number): bool
    {
        $n = self::normalize($number);
        if (strlen($n) !== 11) {
            return false;
        }
        if (!preg_match('/^[A-Z]{3}[UJZ]\d{6}\d$/', $n)) {
            return false;
        }
        return self::checkDigit(substr($n, 0, 10)) === (int) $n[10];
    }

    public static function checkDigit(string $prefix): int
    {
        $prefix = strtoupper($prefix);
        $sum    = 0;
        for ($i = 0; $i < 10; $i++) {
            $char  = $prefix[$i];
            $value = ctype_alpha($char) ? (self::LETTER_VALUES[$char] ?? 0) : (int) $char;
            $sum  += $value * (2 ** $i);
        }
        $remainder = $sum % 11;
        return $remainder === 10 ? 0 : $remainder;
    }
}

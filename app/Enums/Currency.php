<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case TZS = 'TZS';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case KES = 'KES';
    case UGX = 'UGX';
    case RWF = 'RWF';
    case ZAR = 'ZAR';
    case CNY = 'CNY';
    case INR = 'INR';
    case AED = 'AED';
    case JPY = 'JPY';

    public function label(): string
    {
        return match($this) {
            self::TZS => 'TZS — Tanzanian Shilling',
            self::USD => 'USD — US Dollar',
            self::EUR => 'EUR — Euro',
            self::GBP => 'GBP — British Pound',
            self::KES => 'KES — Kenyan Shilling',
            self::UGX => 'UGX — Ugandan Shilling',
            self::RWF => 'RWF — Rwandan Franc',
            self::ZAR => 'ZAR — South African Rand',
            self::CNY => 'CNY — Chinese Yuan',
            self::INR => 'INR — Indian Rupee',
            self::AED => 'AED — UAE Dirham',
            self::JPY => 'JPY — Japanese Yen',
        };
    }
}

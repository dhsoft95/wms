<?php

namespace App\Enums;

enum DocumentSequenceType: string
{
    case Grn = 'GRN';
    case Do  = 'DO';
    case Adj = 'ADJ';
    case Trf = 'TRF';
    case Cnt = 'CNT';
    case Lpn = 'LPN';
    case Ctr = 'CTR';
    case Stk = 'STK';

    public function label(): string
    {
        return match($this) {
            self::Grn => 'Goods Received Note',
            self::Do  => 'Delivery Order',
            self::Adj => 'Stock Adjustment',
            self::Trf => 'Transfer',
            self::Cnt => 'Cycle Count',
            self::Lpn => 'License Plate Number',
            self::Ctr => 'Container',
            self::Stk => 'Stock Transaction',
        };
    }
}

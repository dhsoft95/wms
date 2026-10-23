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
    case Pck = 'PCK';
    case Dsp  = 'DSP';
    case Gp   = 'GP';
    case Mov  = 'MOV';
    case Po   = 'PO';
    case Mrn  = 'MRN';
    case Rcn  = 'RCN';
    case Dn   = 'DN';
    case Wtr  = 'WTR';
    case Odn  = 'ODN';
    case Disp = 'DISP';
    case Ssr  = 'SSR';
    case Stn  = 'STN';

    public function label(): string
    {
        return match($this) {
            self::Grn  => 'Goods Received Note',
            self::Do   => 'Delivery Order',
            self::Adj  => 'Stock Adjustment',
            self::Trf  => 'Transfer',
            self::Cnt  => 'Cycle Count',
            self::Lpn  => 'License Plate Number',
            self::Ctr  => 'Container',
            self::Stk  => 'Stock Transaction',
            self::Pck  => 'Pick List',
            self::Dsp  => 'Dispatch',
            self::Gp   => 'Gate Pass',
            self::Mov  => 'Internal Movement',
            self::Po   => 'Purchase Order',
            self::Mrn  => 'Material Receipt Note',
            self::Rcn  => 'Reconciliation Note',
            self::Dn   => 'Delivery Note',
            self::Wtr  => 'Warehouse Transfer',
            self::Odn  => 'Outbound Delivery Note',
            self::Disp => 'Disposal Request',
            self::Ssr  => 'Stock Status Request',
            self::Stn  => 'Stock Transfer Note',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum ContainerDocumentType: string
{
    case BillOfLading      = 'bill_of_lading';
    case PackingList       = 'packing_list';
    case Photo             = 'photo';
    case InspectionReport  = 'inspection_report';
    case Other             = 'other';

    public function label(): string
    {
        return match($this) {
            self::BillOfLading     => 'Bill of Lading',
            self::PackingList      => 'Packing List',
            self::Photo            => 'Photo',
            self::InspectionReport => 'Inspection Report',
            self::Other            => 'Other',
        };
    }
}

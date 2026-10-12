<?php

namespace App\Enums;

enum PartnerType: string
{
    case Supplier     = 'supplier';
    case Shipper      = 'shipper';
    case Consignee    = 'consignee';
    case Customer     = 'customer';
    case ShippingLine = 'shipping_line';
    case Transporter  = 'transporter';

    public function label(): string
    {
        return match($this) {
            self::Supplier     => 'Supplier',
            self::Shipper      => 'Shipper',
            self::Consignee    => 'Consignee',
            self::Customer     => 'Customer',
            self::ShippingLine => 'Shipping Line',
            self::Transporter  => 'Transporter',
        };
    }
}

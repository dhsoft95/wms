<?php

namespace App\Actions\Foundation;

use App\Models\Partner;

class CreatePartnerAction
{
    public static function execute(array $data): Partner
    {
        return Partner::create([
            'code'           => strtoupper($data['code']),
            'name'           => $data['name'],
            'type'           => $data['types'],
            'tin'            => $data['tin'] ?: null,
            'vrn'            => $data['vrn'] ?: null,
            'contact_person' => $data['contact_person'] ?: null,
            'phone'          => $data['phone'] ?: null,
            'email'          => $data['email'] ?: null,
            'address'        => $data['address'] ?: null,
            'city'           => $data['city'] ?: null,
            'country'        => $data['country'] ?: null,
            'is_active'      => true,
        ]);
    }
}

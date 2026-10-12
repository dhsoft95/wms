<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\PartnerType;
use App\Models\Partner;

class PartnerImport extends BaseImport
{
    public function headers(): array
    {
        return ['code', 'name', 'types', 'tin', 'vrn', 'contact_person', 'phone', 'email', 'address', 'city', 'country'];
    }

    public function templateRows(): array
    {
        return [
            ['code' => 'CUST-001', 'name' => 'ACME Corp', 'types' => 'customer', 'tin' => '', 'vrn' => '', 'contact_person' => 'John Doe', 'phone' => '+255700000000', 'email' => 'john@acme.com', 'address' => '123 Main St', 'city' => 'Dar es Salaam', 'country' => 'Tanzania'],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'code'           => 'Required. Unique partner code.',
            'name'           => 'Required. Partner display name.',
            'types'          => 'Required. Comma-separated: customer, supplier, transporter.',
            'tin'            => 'Optional.',
            'vrn'            => 'Optional.',
            'contact_person' => 'Optional.',
            'phone'          => 'Optional.',
            'email'          => 'Optional. Valid email address.',
            'address'        => 'Optional.',
            'city'           => 'Optional.',
            'country'        => 'Optional.',
        ];
    }

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];
        if ($this->str($row, 'code') === '') {
            $errors[] = $this->err('code', "'code' is required.");
        }
        if ($this->str($row, 'name') === '') {
            $errors[] = $this->err('name', "'name' is required.");
        }
        $types = $this->parseTypes($row);
        if (empty($types)) {
            $errors[] = $this->err('types', "'types' must contain at least one of: customer, supplier, transporter.");
        }
        $email = $this->str($row, 'email');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->err('email', "Invalid email address.");
        }
        return $errors;
    }

    protected function importRow(array $row): string
    {
        $partner = Partner::firstOrNew(['code' => $this->str($row, 'code')]);
        $isNew   = ! $partner->exists;

        if ($this->import->mode->value === 'create_only' && ! $isNew) {
            return 'updated';
        }

        $types = $this->parseTypes($row);

        $partner->fill([
            'name'           => $this->str($row, 'name'),
            'type'           => $types,
            'tin'            => $this->str($row, 'tin'),
            'vrn'            => $this->str($row, 'vrn'),
            'contact_person' => $this->str($row, 'contact_person'),
            'phone'          => $this->str($row, 'phone'),
            'email'          => $this->str($row, 'email') ?: null,
            'address'        => $this->str($row, 'address'),
            'city'           => $this->str($row, 'city'),
            'country'        => $this->str($row, 'country'),
            'is_active'      => true,
        ]);
        $partner->save();

        return $isNew ? 'created' : 'updated';
    }

    private function parseTypes(array $row): array
    {
        $raw   = $this->str($row, 'types');
        $valid = array_map(fn ($t) => $t->value, PartnerType::cases());
        return array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($t) => in_array($t, $valid, true)
        );
    }
}

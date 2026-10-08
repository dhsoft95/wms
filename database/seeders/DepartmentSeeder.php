<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'General Warehouse',  'code' => 'GW'],
            ['name' => 'Cold Chain',          'code' => 'CC'],
            ['name' => 'Technology Vault',    'code' => 'TV'],
            ['name' => 'Procurement',         'code' => 'PR'],
            ['name' => 'Logistics',           'code' => 'LG'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept + ['is_active' => true]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\VirtualWarehouse;
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

        // Seed virtual warehouses: General Warehouse gets two VWs so the VW switcher
        // is visible for any demo dept-user assigned to both.
        $gw = Department::where('code', 'GW')->first();
        if ($gw) {
            VirtualWarehouse::firstOrCreate(
                ['code' => 'GW-MAIN'],
                ['name' => 'GW Main Store', 'department_id' => $gw->id, 'is_active' => true]
            );
            VirtualWarehouse::firstOrCreate(
                ['code' => 'GW-BULK'],
                ['name' => 'GW Bulk Store', 'department_id' => $gw->id, 'is_active' => true]
            );
        }

        foreach (['CC', 'TV', 'PR', 'LG'] as $code) {
            $dept = Department::where('code', $code)->first();
            if ($dept) {
                VirtualWarehouse::firstOrCreate(
                    ['code' => $code . '-VW'],
                    ['name' => $dept->name . ' VW', 'department_id' => $dept->id, 'is_active' => true]
                );
            }
        }
    }
}

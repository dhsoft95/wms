<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one demo user per role.
 * Default password for all demo users: Password1!
 *
 * | Role               | Email                    | Name                      |
 * |--------------------|--------------------------|---------------------------|
 * | system-admin       | admin@wms.local          | System Administrator      |
 * | warehouse-manager  | wm@wms.local             | Warehouse Manager         |
 * | warehouse-supervisor| ws@wms.local            | Warehouse Supervisor      |
 * | department-user    | dept@wms.local           | Department User           |
 * | department-manager | deptmgr@wms.local        | Department Manager        |
 * | department-head    | depthead@wms.local       | Department Head           |
 * | storekeeper        | clerk@wms.local          | Storekeeper               |
 * | scanner-operator   | scanner@wms.local        | Scanner Operator          |
 * | gate-security      | gate@wms.local           | Gate Security             |
 * | viewer-auditor     | auditor@wms.local        | Viewer / Auditor          |
 * | data-analyst       | analyst@wms.local        | Data Analyst              |
 */
class DemoUsersSeeder extends Seeder
{
    private const PASSWORD = 'Password1!';
    private const USERS = [
        ['email' => 'david.haule@simbalogistic.co.tz',    'name' => 'System Administrator', 'role' => 'system-admin'],
        ['email' => 'wm@wms.local',       'name' => 'Warehouse Manager',    'role' => 'warehouse-manager'],
        ['email' => 'ws@wms.local',       'name' => 'Warehouse Supervisor', 'role' => 'warehouse-supervisor'],
        ['email' => 'dept@wms.local',     'name' => 'Department User',      'role' => 'department-user'],
        ['email' => 'deptmgr@wms.local',  'name' => 'Department Manager',   'role' => 'department-manager'],
        ['email' => 'depthead@wms.local', 'name' => 'Department Head',      'role' => 'department-head'],
        ['email' => 'clerk@wms.local',    'name' => 'Storekeeper',          'role' => 'storekeeper'],
        ['email' => 'scanner@wms.local',  'name' => 'Scanner Operator',     'role' => 'scanner-operator'],
        ['email' => 'gate@wms.local',     'name' => 'Gate Security',        'role' => 'gate-security'],
        ['email' => 'auditor@wms.local',  'name' => 'Viewer / Auditor',     'role' => 'viewer-auditor'],
        ['email' => 'analyst@wms.local',  'name' => 'Data Analyst',         'role' => 'data-analyst'],
    ];

    public function run(): void
    {
        $hash = Hash::make(self::PASSWORD);

        foreach (self::USERS as $row) {
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name'              => $row['name'],
                    'password'          => $hash,
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            // Sync to exactly one role (idempotent)
            $user->syncRoles([$row['role']]);
        }
    }
}

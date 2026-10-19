<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            FoundationDefaultsSeeder::class,
            DepartmentSeeder::class,
            AdminUserSeeder::class,
            DemoUsersSeeder::class,
            TestMasterDataSeeder::class,
//            FoundationDemoSeeder::class,
//            StockDemoSeeder::class,
//            ContainerDemoSeeder::class,
//            ReceivingDemoSeeder::class,
//            LabelsSeeder::class,
//            OutboundDemoSeeder::class,
        ]);

        // Ensure the developer account is active and has system-admin role
        $dev = \App\Models\User::updateOrCreate(
            ['email' => 'davidhaule3@gmail.com'],
            [
                'name'              => 'David Haule',
                'password'          => \Illuminate\Support\Facades\Hash::make('@Dmin2021!'),
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );
        $dev->syncRoles('system-admin');
    }
}

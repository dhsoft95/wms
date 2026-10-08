<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@wms.local')],
            [
                'name'              => env('ADMIN_NAME', 'System Administrator'),
                'password'          => Hash::make(env('ADMIN_PASSWORD', 'Password1!')),
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('system-admin');
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\Department;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Notifications\UserInvitationNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class YasNetworkUsersSeeder extends Seeder
{
    public function run(): void
    {
        $vwNet   = VirtualWarehouse::where('code', 'YAS-NET')->firstOrFail();
        $deptNet = Department::where('name', 'Yas Network')->firstOrFail();

        $definitions = [
            [
                'name'    => 'Raphael Luhelo',
                'email'   => 'raphael.luhelo@simbalogistic.co.tz',
                'role'    => 'department-user',
                'vw'      => $vwNet,
                'dept_id' => $deptNet->id,
                'is_head' => true,
            ],
            [
                'name'    => 'David Haule',
                'email'   => 'davidhaule95@gmail.com',
                'role'    => 'department-user',
                'vw'      => $vwNet,
                'dept_id' => $deptNet->id,
                'is_head' => false,
            ],
            [
                'name'    => 'Omary Haggan',
                'email'   => 'omary.haggan@simbacourier.co.tz',
                'role'    => 'warehouse-manager',
                'vw'      => null,
                'dept_id' => null,
                'is_head' => false,
            ],
        ];

        foreach ($definitions as $def) {
            $isNew = ! User::where('email', $def['email'])->exists();

            $user = User::firstOrCreate(
                ['email' => $def['email']],
                [
                    'name'              => $def['name'],
                    'password'          => Hash::make(Str::random(32)),
                    'status'            => 'invited',
                    'department_id'     => $def['dept_id'],
                    'email_verified_at' => null,
                ],
            );

            // Always keep name/dept current (never touch password)
            $updates = ['name' => $def['name']];
            if ($def['dept_id'] !== null) {
                $updates['department_id'] = $def['dept_id'];
            }
            $user->update($updates);

            // Exactly one role
            $user->syncRoles($def['role']);

            // Attach VW without removing others
            if ($def['vw'] !== null) {
                $user->virtualWarehouses()->syncWithoutDetaching([$def['vw']->id]);
            }

            // Invitation email only for brand-new users
            if ($isNew) {
                $token = $user->generateInvitationToken();
                $user->notify(new UserInvitationNotification($token));
            }

            // Set as department head
            if ($def['is_head']) {
                $deptNet->update(['head_user_id' => $user->id]);
            }

            $action = $isNew ? 'CREATED' : 'UPDATED';
            $vwTag  = $def['vw']?->code ?? '—';
            $invite = $isNew ? ' · invitation queued' : '';
            $head   = $def['is_head'] ? ' · set as Yas Network head' : '';
            $this->command->line("  [{$action}] {$user->name} | role: {$def['role']} | VW: {$vwTag}{$invite}{$head}");
        }

        // PO and DO approval chains: Level 1 = HOD (Raphael) only.
        $raphael = User::where('email', 'raphael.luhelo@simbalogistic.co.tz')->first();
        if ($raphael) {
            foreach (['po', 'do'] as $docType) {
                $chain = \App\Models\ApprovalChain::firstOrCreate([
                    'department_id' => $deptNet->id,
                    'document_type' => $docType,
                    'level'         => 1,
                ]);
                \App\Models\ApprovalChainUser::firstOrCreate([
                    'approval_chain_id' => $chain->id,
                    'user_id'           => $raphael->id,
                ]);
                \App\Models\ApprovalChain::where('department_id', $deptNet->id)
                    ->where('document_type', $docType)
                    ->where('level', '>', 1)
                    ->delete();
            }
            $this->command->line('  Approval chains (PO + DO): Level 1 → Raphael Luhelo (HOD)');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Done — department: Yas Network (ID ' . $deptNet->id . ')');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: for every existing department create one VW, then attach
 * every user who belongs to that department.
 *
 * Idempotent: skips departments whose code already has a matching VW.
 * down() removes only the VWs this migration created.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now         = now();
        $departments = DB::table('departments')->whereNull('deleted_at')->get();

        foreach ($departments as $dept) {
            $code = $this->resolveCode($dept);

            // Idempotent — skip if already created
            if (DB::table('virtual_warehouses')->where('code', $code)->exists()) {
                continue;
            }

            $vwId = DB::table('virtual_warehouses')->insertGetId([
                'code'          => $code,
                'name'          => $dept->name,
                'department_id' => $dept->id,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            // Attach every user in this department
            $userIds = DB::table('users')
                ->where('department_id', $dept->id)
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($userIds as $userId) {
                $alreadyLinked = DB::table('user_virtual_warehouse')
                    ->where('user_id', $userId)
                    ->where('virtual_warehouse_id', $vwId)
                    ->exists();

                if (! $alreadyLinked) {
                    DB::table('user_virtual_warehouse')->insert([
                        'user_id'               => $userId,
                        'virtual_warehouse_id'  => $vwId,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Collect codes that match existing departments (only rows this migration could have created)
        $deptCodes = DB::table('departments')
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($d) => $this->resolveCode($d));

        $vwIds = DB::table('virtual_warehouses')
            ->whereIn('code', $deptCodes)
            ->pluck('id');

        DB::table('user_virtual_warehouse')
            ->whereIn('virtual_warehouse_id', $vwIds)
            ->delete();

        DB::table('virtual_warehouses')
            ->whereIn('id', $vwIds)
            ->delete();
    }

    private function resolveCode(object $dept): string
    {
        if (! empty($dept->code)) {
            return strtoupper(trim($dept->code));
        }

        // Generate from name: uppercase alphanumeric slug, max 10 chars, guaranteed unique
        $base   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $dept->name));
        $base   = substr($base, 0, 10) ?: 'DEPT';
        $code   = $base;
        $suffix = 1;

        while (DB::table('virtual_warehouses')->where('code', $code)->exists()) {
            $code = substr($base, 0, 9) . $suffix++;
        }

        return $code;
    }
};

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use Illuminate\Database\Seeder;

/**
 * Seeds DO approval chains by copying the existing PO chain structure.
 * For each department that has a PO approval chain, a matching DO chain
 * is created with the same approvers at each level.
 *
 * Safe to run multiple times (idempotent via firstOrCreate).
 */
class DoApprovalChainSeeder extends Seeder
{
    public function run(): void
    {
        $poChains = ApprovalChain::where('document_type', ApprovalChainDocumentType::Po->value)
            ->with('chainUsers')
            ->get();

        if ($poChains->isEmpty()) {
            $this->command->warn('No PO approval chains found — nothing to copy.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($poChains as $poChain) {
            $doChain = ApprovalChain::firstOrCreate([
                'department_id' => $poChain->department_id,
                'document_type' => ApprovalChainDocumentType::Do->value,
                'level'         => $poChain->level,
            ]);

            $isNew = $doChain->wasRecentlyCreated;
            $isNew ? $created++ : $skipped++;

            foreach ($poChain->chainUsers as $chainUser) {
                ApprovalChainUser::firstOrCreate([
                    'approval_chain_id' => $doChain->id,
                    'user_id'           => $chainUser->user_id,
                ]);
            }
        }

        $this->command->info("DO approval chains: {$created} created, {$skipped} already existed.");
    }
}

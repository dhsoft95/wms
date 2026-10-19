<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\User;
use App\Notifications\Inbound\PoStatusChangedNotification;
use Illuminate\Console\Command;

class RemindPoApproversCommand extends Command
{
    protected $signature   = 'wms:po:remind-approvers';
    protected $description = 'Send reminder notifications to approvers on POs awaiting verification (every N days from settings).';

    public function handle(): int
    {
        $intervalDays = (int) (config('wms.po_approver_reminder_days', 2));

        $pos = PurchaseOrder::whereIn('status', [
            PurchaseOrderStatus::CompletedPendingVerification->value,
            PurchaseOrderStatus::IssueResolved->value,
        ])->get();

        $count = 0;

        foreach ($pos as $po) {
            // Only remind if it has been at least $intervalDays since last reminder or validation
            $since = $po->putaway_validated_at ?? $po->updated_at;
            if (! $since || $since->diffInDays(now()) < $intervalDays) {
                continue;
            }

            $approverIds = PurchaseOrderApproval::where('purchase_order_id', $po->id)
                ->where('action', 'approved')
                ->pluck('user_id')
                ->unique();

            $approvers = User::whereIn('id', $approverIds)->get();
            foreach ($approvers as $approver) {
                $approver->notify(new PoStatusChangedNotification(
                    $po, 'verification_reminder',
                    "Reminder: PO {$po->number} is awaiting your verification.",
                ));
            }

            $count += $approvers->count();
        }

        $this->info("Sent {$count} reminder notification(s) to approvers.");

        return self::SUCCESS;
    }
}

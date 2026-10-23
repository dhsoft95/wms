<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

use App\Exceptions\InvalidTransitionException;

enum PurchaseOrderStatus: string
{
    case Draft                            = 'draft';
    case PendingApproval                  = 'pending_approval';                   // awaiting dept-head
    case ReturnedForCorrection            = 'returned_for_correction';
    case Rejected                         = 'rejected';
    case ApprovedPendingAcceptance        = 'approved_pending_acceptance';
    case AcceptedPendingAssignment        = 'accepted_pending_assignment';
    case AssignedPendingReceiving         = 'assigned_pending_receiving';
    case ReceivingInProgress              = 'receiving_in_progress';
    case PartiallyReceived                = 'partially_received';
    case PendingPutaway                   = 'pending_putaway';
    case PutawayInProgress                = 'putaway_in_progress';
    case PendingSupervisorValidation      = 'pending_supervisor_validation';
    case CompletedPendingVerification     = 'completed_pending_verification';
    case IssueRaised                      = 'issue_raised';
    case IssueResolved                    = 'issue_resolved';
    case VerifiedClosed                   = 'verified_closed';
    case Cancelled                        = 'cancelled';

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Draft =>
                in_array($new, [self::PendingApproval, self::Cancelled], true),

            self::PendingApproval =>
                in_array($new, [
                    self::ApprovedPendingAcceptance,
                    self::ReturnedForCorrection,
                    self::Rejected,
                ], true),

            self::ReturnedForCorrection =>
                in_array($new, [self::PendingApproval, self::Cancelled], true),

            self::ApprovedPendingAcceptance =>
                in_array($new, [self::AcceptedPendingAssignment, self::ReturnedForCorrection, self::Rejected, self::Cancelled], true),

            self::AcceptedPendingAssignment =>
                in_array($new, [self::AssignedPendingReceiving, self::Cancelled], true),

            self::AssignedPendingReceiving =>
                in_array($new, [self::ReceivingInProgress, self::Cancelled], true),

            self::ReceivingInProgress =>
                in_array($new, [self::PartiallyReceived, self::PendingPutaway], true),

            self::PartiallyReceived =>
                in_array($new, [self::ReceivingInProgress, self::PendingPutaway], true),

            self::PendingPutaway =>
                $new === self::PutawayInProgress,

            self::PutawayInProgress =>
                $new === self::PendingSupervisorValidation,

            self::PendingSupervisorValidation =>
                in_array($new, [self::PutawayInProgress, self::CompletedPendingVerification], true),

            self::CompletedPendingVerification =>
                in_array($new, [self::IssueRaised, self::VerifiedClosed], true),

            self::IssueRaised =>
                $new === self::IssueResolved,

            self::IssueResolved =>
                $new === self::CompletedPendingVerification,

            default => false,
        };
    }

    public function transitionTo(self $new): void
    {
        if (! $this->canTransitionTo($new)) {
            throw new InvalidTransitionException($this->value, $new->value);
        }
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ReturnedForCorrection], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::PendingApproval,
            self::ReturnedForCorrection,
            self::ApprovedPendingAcceptance,
            self::AcceptedPendingAssignment,
            self::AssignedPendingReceiving,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::VerifiedClosed, self::Cancelled], true);
    }

    /** Returns true if the PO is awaiting dept-head approval. */
    public function isAwaitingApproval(): bool
    {
        return $this === self::PendingApproval;
    }

    public function label(): string
    {
        return match($this) {
            self::Draft                           => 'Draft',
            self::PendingApproval                 => 'Pending Dept Head Approval',
            self::ReturnedForCorrection           => 'Returned for Correction',
            self::Rejected                        => 'Rejected',
            self::ApprovedPendingAcceptance       => 'Approved — Pending Acceptance',
            self::AcceptedPendingAssignment       => 'Accepted — Pending Assignment',
            self::AssignedPendingReceiving        => 'Assigned — Pending Receiving',
            self::ReceivingInProgress             => 'Receiving in Progress',
            self::PartiallyReceived               => 'Partially Received',
            self::PendingPutaway                  => 'Pending Putaway',
            self::PutawayInProgress               => 'Putaway in Progress',
            self::PendingSupervisorValidation     => 'Pending Supervisor Validation',
            self::CompletedPendingVerification    => 'Completed — Pending Verification',
            self::IssueRaised                     => 'Issue Raised',
            self::IssueResolved                   => 'Issue Resolved',
            self::VerifiedClosed                  => 'Verified / Closed',
            self::Cancelled                       => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft                           => 'badge-slate',
            self::PendingApproval                 => 'badge-amber',
            self::ReturnedForCorrection           => 'badge-amber',
            self::Rejected                        => 'badge-red',
            self::ApprovedPendingAcceptance       => 'badge-green',
            self::AcceptedPendingAssignment       => 'badge-green',
            self::AssignedPendingReceiving        => 'badge-brand',
            self::ReceivingInProgress             => 'badge-brand',
            self::PartiallyReceived               => 'badge-amber',
            self::PendingPutaway                  => 'badge-amber',
            self::PutawayInProgress               => 'badge-brand',
            self::PendingSupervisorValidation     => 'badge-amber',
            self::CompletedPendingVerification    => 'badge-green',
            self::IssueRaised                     => 'badge-red',
            self::IssueResolved                   => 'badge-amber',
            self::VerifiedClosed                  => 'badge-neutral',
            self::Cancelled                       => 'badge-neutral',
        };
    }
}

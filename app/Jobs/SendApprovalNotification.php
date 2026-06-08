<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ApprovalRequest $approvalRequest)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $approvers = User::where('role', UserRole::Approver->value)
            ->orWhere('role', UserRole::Admin->value)
            ->get();

        foreach ($approvers as $approver) {
            $approver->notify(new ApprovalRequestedNotification($this->approvalRequest));
        }
    }
}

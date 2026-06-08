<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ApprovalRequest $approvalRequest)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('New Approval Request Pending')
                    ->line('A new document (UUID: ' . $this->approvalRequest->document->uuid . ') requires your approval.')
                    ->action('View Request', url('/api/v1/approvals/' . $this->approvalRequest->id))
                    ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approvalRequest->id,
            'document_uuid' => $this->approvalRequest->document->uuid,
            'message' => 'New document requires approval.',
        ];
    }
}

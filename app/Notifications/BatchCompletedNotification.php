<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BatchCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Batch $batch) {}

    public function via(object $notifiable): array
    {
        return ['webhook'];
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $webhookUrl = env('SLACK_WEBHOOK_URL');

        if ($webhookUrl) {
            Log::info("Sending Batch Completed notification to webhook for Batch: {$this->batch->uuid}");
            Http::post($webhookUrl, ['text' => "Batch {$this->batch->uuid} completed with status {$this->batch->status->value}"]);
        } else {
            Log::info("BatchCompletedNotification triggered for Batch: {$this->batch->uuid}, but SLACK_WEBHOOK_URL is not set.");
        }
    }
}

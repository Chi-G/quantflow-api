<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessBatch;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BatchService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function createBatch(Request $request, User $user): Batch
    {
        $filePath = null;
        $fileName = null;
        $fileType = null;
        $totalRecords = 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getClientOriginalExtension() === 'xlsx' ? 'xlsx' : 'csv';

            $path = $file->storeAs('batches', Str::random(40).'.'.$fileType);
            $filePath = $path;

            $totalRecords = 0;
        } elseif ($request->has('payload')) {
            $payload = $request->input('payload');
            $fileName = 'payload_'.now()->timestamp.'.json';
            $fileType = 'json';

            $filePath = 'batches/'.Str::random(40).'.json';
            Storage::put($filePath, json_encode($payload));

            $totalRecords = count($payload);
        }

        $batch = Batch::create([
            'tenant_id' => $user->tenant_id,
            'uploaded_by' => $user->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'total_records' => $totalRecords,
            'status' => BatchStatus::Pending->value,
        ]);

        $this->auditService->log('created', $batch, $user, [], $batch->toArray());

        return $batch;
    }

    public function submitBatch(Batch $batch, User $user): void
    {
        if ($batch->status !== BatchStatus::Pending && $batch->status !== BatchStatus::Failed) {
            throw new \Exception('Batch is not in a submittable state.');
        }

        $lock = Cache::lock("batch_{$batch->id}_processing", 10);

        if (! $lock->get()) {
            throw new \Exception('Batch submission is already in progress.');
        }

        try {
            $batch->update([
                'status' => BatchStatus::Processing->value,
                'submitted_at' => now(),
            ]);

            $this->auditService->log('batch_submitted', $batch, $user, ['status' => 'pending'], ['status' => 'processing']);

            ProcessBatch::dispatch($batch);
        } finally {
            $lock->release();
        }
    }

    public function retryBatch(Batch $batch, User $user): void
    {
        if (! in_array($batch->status, [BatchStatus::PartiallyFailed, BatchStatus::Failed, BatchStatus::Completed])) {
            throw new \Exception('Batch cannot be retried in its current state.');
        }

        $this->auditService->log('retried', $batch, $user);

        ProcessBatch::dispatch($batch, true);
    }

    public function getBatchStats(Batch $batch): array
    {
        return [
            'total' => $batch->total_records,
            'processed' => $batch->processed_records,
            'status' => $batch->status->value,
            'documents' => [
                'pending' => $batch->documents()->where('status', DocumentStatus::Pending->value)->count(),
                'validated' => $batch->documents()->where('status', DocumentStatus::Validated->value)->count(),
                'failed' => $batch->documents()->where('status', DocumentStatus::Failed->value)->count(),
                'approved' => $batch->documents()->where('status', DocumentStatus::Approved->value)->count(),
                'rejected' => $batch->documents()->where('status', DocumentStatus::Rejected->value)->count(),
            ],
        ];
    }
}

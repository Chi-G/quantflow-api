<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\User;
use App\Notifications\BatchCompletedNotification;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ProcessBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Batch $batch,
        public readonly bool $isRetry = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentService $documentService, AuditService $auditService): void
    {
        try {
            $filePath = Storage::path($this->batch->file_path);
            $rows = [];

            if ($this->batch->file_type === 'csv') {
                $csv = Reader::createFromPath($filePath, 'r');
                $csv->setHeaderOffset(0);
                $rows = $csv->getRecords();
            } elseif ($this->batch->file_type === 'xlsx') {
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $header = [];
                foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                    if ($rowIndex === 1) {
                        $header = $rowData;
                    } else {
                        $rows[] = array_combine($header, $rowData);
                    }
                }
            } elseif ($this->batch->file_type === 'json') {
                $content = file_get_contents($filePath);
                $rows = json_decode($content, true);
            }

            if ($this->batch->total_records === 0) {
                if (is_array($rows)) {
                    $this->batch->total_records = count($rows);
                } else {
                    $this->batch->total_records = iterator_count($rows);
                    $csv = Reader::createFromPath($filePath, 'r');
                    $csv->setHeaderOffset(0);
                    $rows = $csv->getRecords();
                }
                $this->batch->save();
            }

            $failedCount = 0;
            $processedCount = 0;

            foreach ($rows as $row) {
                DB::transaction(function () use ($row, $documentService, &$failedCount) {
                    $document = $documentService->createFromRow($row, $this->batch);

                    $validation = $documentService->validateRow($row);

                    if (! $validation['valid']) {
                        $documentService->markFailed($document, implode(', ', $validation['errors']));
                        $failedCount++;
                    } else {
                        $documentService->markValidated($document, $this->batch->uploader);
                    }
                });

                $processedCount++;

                if ($processedCount % 100 === 0) {
                    $this->batch->update(['processed_records' => $processedCount]);
                }
            }

            $finalStatus = $failedCount > 0 ? BatchStatus::PartiallyFailed : BatchStatus::Completed;

            $this->batch->update([
                'processed_records' => $processedCount,
                'status' => $finalStatus->value,
                'completed_at' => now(),
            ]);

            $auditService->log('batch_processed', $this->batch, null, [], ['status' => $finalStatus->value]);

            $user = User::find($this->batch->uploaded_by);
            if ($user) {
                $user->notify(new BatchCompletedNotification($this->batch));
            }

        } catch (\Throwable $e) {
            $this->batch->update([
                'status' => BatchStatus::Failed->value,
                'metadata' => array_merge((array) $this->batch->metadata, ['error' => $e->getMessage()]),
            ]);
            $auditService->log('failed', $this->batch, null, [], ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Batch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class DocumentService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly AuditService $auditService
    ) {}

    public function createFromRow(array $row, Batch $batch): Document
    {
        $document = Document::where('batch_id', $batch->id)
            ->where('reference_number', $row['reference_number'] ?? '')
            ->first();

        if ($document) {
            $document->update([
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency'] ?? 'NGN',
                'recipient_name' => $row['recipient_name'] ?? '',
                'recipient_account' => $row['recipient_account'] ?? '',
                'bank_code' => $row['bank_code'] ?? '',
                'status' => DocumentStatus::Pending->value,
                'failure_reason' => null,
            ]);

            return $document;
        }

        return Document::create([
            'tenant_id' => $batch->tenant_id,
            'batch_id' => $batch->id,
            'reference_number' => $row['reference_number'] ?? '',
            'amount' => $row['amount'] ?? 0,
            'currency' => $row['currency'] ?? 'NGN',
            'recipient_name' => $row['recipient_name'] ?? '',
            'recipient_account' => $row['recipient_account'] ?? '',
            'bank_code' => $row['bank_code'] ?? '',
            'status' => DocumentStatus::Pending->value,
        ]);
    }

    public function validateRow(array $row): array
    {
        $validator = Validator::make($row, [
            'reference_number' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'recipient_name' => ['required', 'string'],
            'recipient_account' => ['required', 'string', 'size:10'],
            'bank_code' => ['required', 'string', 'min:3', 'max:6'],
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->all(),
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    public function markFailed(Document $doc, string $reason): void
    {
        $doc->update([
            'status' => DocumentStatus::Failed->value,
            'failure_reason' => $reason,
        ]);

        $this->auditService->log('failed', $doc, null, [], ['failure_reason' => $reason]);
    }

    public function markValidated(Document $doc, User $uploadedBy): void
    {
        $doc->update([
            'status' => DocumentStatus::Validated->value,
        ]);

        $this->auditService->log('validated', $doc, null);

        $this->approvalService->createApprovalRequest($doc, $uploadedBy);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\User;

final class ApprovalService
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function createApprovalRequest(Document $doc, User $requestedBy): ApprovalRequest
    {
        $request = ApprovalRequest::create([
            'tenant_id' => $doc->tenant_id,
            'document_id' => $doc->id,
            'batch_id' => $doc->batch_id,
            'requested_by' => $requestedBy->id,
            'status' => ApprovalStatus::Pending->value,
        ]);

        \App\Jobs\SendApprovalNotification::dispatch($request);

        return $request;
    }

    public function approve(ApprovalRequest $request, User $approver, ?string $comment): void
    {
        $request->update([
            'status' => ApprovalStatus::Approved->value,
            'approved_by' => $approver->id,
            'comment' => $comment,
            'responded_at' => now(),
        ]);

        $request->document->update([
            'status' => DocumentStatus::Approved->value,
        ]);

        $this->auditService->log('approved', $request->document, $approver, [], ['comment' => $comment]);
    }

    public function reject(ApprovalRequest $request, User $approver, string $comment): void
    {
        $request->update([
            'status' => ApprovalStatus::Rejected->value,
            'approved_by' => $approver->id,
            'comment' => $comment,
            'responded_at' => now(),
        ]);

        $request->document->update([
            'status' => DocumentStatus::Rejected->value,
        ]);

        $this->auditService->log('rejected', $request->document, $approver, [], ['comment' => $comment]);
    }
}

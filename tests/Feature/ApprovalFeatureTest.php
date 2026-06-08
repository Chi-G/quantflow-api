<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Batch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_can_approve_document()
    {
        $tenantId = Str::uuid();
        $operator = User::factory()->create(['tenant_id' => $tenantId, 'role' => \App\Enums\UserRole::Operator->value]);
        $approver = User::factory()->create(['tenant_id' => $tenantId, 'role' => \App\Enums\UserRole::Approver->value]);

        $batch = Batch::factory()->create(['tenant_id' => $tenantId, 'uploaded_by' => $operator->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenantId, 
            'batch_id' => $batch->id, 
            'status' => \App\Enums\DocumentStatus::Validated->value,
            'reference_number' => 'REF001',
            'amount' => 1000,
            'recipient_name' => 'John',
            'recipient_account' => '1234567890',
            'bank_code' => '058'
        ]);
        $approval = ApprovalRequest::factory()->create([
            'tenant_id' => $tenantId,
            'batch_id' => $batch->id,
            'document_id' => $document->id,
            'requested_by' => $operator->id,
            'status' => \App\Enums\ApprovalStatus::Pending->value,
        ]);

        $response = $this->actingAs($approver)->postJson("/api/v1/approvals/{$approval->id}/approve", [
            'comment' => 'Looks good',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'status' => \App\Enums\ApprovalStatus::Approved->value,
            'approved_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => \App\Enums\DocumentStatus::Approved->value,
        ]);
    }

    public function test_operator_cannot_approve_document()
    {
        $tenantId = Str::uuid();
        $operator = User::factory()->create(['tenant_id' => $tenantId, 'role' => \App\Enums\UserRole::Operator->value]);

        $batch = Batch::factory()->create(['tenant_id' => $tenantId, 'uploaded_by' => $operator->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenantId, 
            'batch_id' => $batch->id, 
            'status' => \App\Enums\DocumentStatus::Validated->value,
            'reference_number' => 'REF002',
            'amount' => 1000,
            'recipient_name' => 'John',
            'recipient_account' => '1234567890',
            'bank_code' => '058'
        ]);
        $approval = ApprovalRequest::factory()->create([
            'tenant_id' => $tenantId,
            'batch_id' => $batch->id,
            'document_id' => $document->id,
            'requested_by' => $operator->id,
            'status' => \App\Enums\ApprovalStatus::Pending->value,
        ]);

        $response = $this->actingAs($operator)->postJson("/api/v1/approvals/{$approval->id}/approve", [
            'comment' => 'I approve my own',
        ]);

        $response->assertStatus(403);
    }
}

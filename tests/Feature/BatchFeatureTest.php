<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BatchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_batch_via_json_payload()
    {
        $user = User::factory()->create(['tenant_id' => Str::uuid()]);

        $response = $this->actingAs($user)->postJson('/api/v1/batches', [
            'payload' => [
                [
                    'reference_number' => 'REF001',
                    'amount' => 500,
                    'currency' => 'NGN',
                    'recipient_name' => 'John Doe',
                    'recipient_account' => '1234567890',
                    'bank_code' => '058',
                ]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('batches', [
            'total_records' => 1,
            'file_type' => 'json'
        ]);
    }

    public function test_can_upload_batch_via_csv_file()
    {
        Storage::fake('local');
        $user = User::factory()->create(['tenant_id' => Str::uuid()]);

        $file = UploadedFile::fake()->createWithContent('test.csv', "reference_number,amount,currency,recipient_name,recipient_account,bank_code\nREF002,1000,NGN,Jane Doe,0987654321,058");

        $response = $this->actingAs($user)->postJson('/api/v1/batches', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('batches', [
            'file_type' => 'csv'
        ]);
    }

    public function test_can_submit_batch_for_processing()
    {
        Queue::fake();
        $user = User::factory()->create(['tenant_id' => Str::uuid()]);
        
        $batch = Batch::factory()->create([
            'tenant_id' => $user->tenant_id,
            'uploaded_by' => $user->id,
            'status' => \App\Enums\BatchStatus::Pending->value,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/batches/{$batch->uuid}/submit");

        $response->assertStatus(200);
        
        Queue::assertPushed(\App\Jobs\ProcessBatch::class);
        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => \App\Enums\BatchStatus::Processing->value
        ]);
    }
}

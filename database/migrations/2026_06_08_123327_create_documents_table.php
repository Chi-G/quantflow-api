<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id')->index();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('reference_number');
            $table->decimal('amount', 18, 2);
            $table->string('currency')->default('NGN');
            $table->string('recipient_name');
            $table->string('recipient_account');
            $table->string('bank_code');
            $table->string('status')->default(\App\Enums\DocumentStatus::Pending->value);
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulse_provisioning_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('integration', 100);
            $table->string('integration_hash', 64);
            $table->string('external_id_hash', 64);
            $table->string('payload_hash', 64);
            $table->uuid('idempotency_key')->unique();
            $table->longText('snapshot');
            $table->longText('result')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->uuid('lease')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamps();
            $table->unique(['integration_hash', 'external_id_hash'], 'pulse_provisioning_customer_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_provisioning_intents');
    }
};

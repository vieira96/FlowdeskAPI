<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_ai_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('classification', 20)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('suggestion')->nullable();
            $table->string('model', 100);
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['classification', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ai_suggestions');
    }
};

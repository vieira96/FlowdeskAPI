<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla_escalations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('metadata')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->unique(['ticket_id', 'type']);
            $table->index(['type', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_escalations');
    }
};

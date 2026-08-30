<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('team_id')->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique('ticket_id');
            $table->index(['agent_id', 'assigned_at']);
            $table->index(['team_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};

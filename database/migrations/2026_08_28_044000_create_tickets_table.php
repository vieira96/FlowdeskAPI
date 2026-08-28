<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('medium');
            $table->foreignUuid('category_id')->constrained('team_categories')->restrictOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['requester_id', 'created_at']);
            $table->index(['category_id', 'created_at']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

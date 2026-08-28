<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};

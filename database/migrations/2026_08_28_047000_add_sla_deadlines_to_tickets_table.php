<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('first_response_due_at')->nullable()->after('assignee_id');
            $table->timestamp('first_responded_at')->nullable()->after('first_response_due_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_responded_at');
            $table->timestamp('resolved_at')->nullable()->after('resolution_due_at');

            $table->index(['status', 'first_response_due_at']);
            $table->index(['status', 'resolution_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['status', 'first_response_due_at']);
            $table->dropIndex(['status', 'resolution_due_at']);
            $table->dropColumn([
                'first_response_due_at',
                'first_responded_at',
                'resolution_due_at',
                'resolved_at',
            ]);
        });
    }
};

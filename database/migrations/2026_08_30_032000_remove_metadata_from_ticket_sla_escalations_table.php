<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ticket_sla_escalations', 'metadata')) {
            Schema::table('ticket_sla_escalations', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ticket_sla_escalations', 'metadata')) {
            Schema::table('ticket_sla_escalations', function (Blueprint $table): void {
                $table->json('metadata')->nullable();
            });
        }
    }
};

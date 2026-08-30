<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'human_assistance_requested_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('human_assistance_requested_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'human_assistance_requested_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('human_assistance_requested_at');
        });
    }
};

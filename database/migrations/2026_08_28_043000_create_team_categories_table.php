<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'is_active']);
            $table->index('name');
        });

        $administratorId = DB::table('users')->where('email', 'admin@admin.com')->value('id');
        $now = now();
        $defaults = [
            'Suporte de TI' => ['Acesso a sistemas', 'E-mail', 'Impressora'],
            'Infraestrutura' => ['Computador', 'Rede', 'Servidor'],
        ];

        foreach ($defaults as $teamName => $categories) {
            $teamId = DB::table('teams')->where('name', $teamName)->value('id');

            if ($teamId === null) {
                $teamId = (string) Str::uuid();

                DB::table('teams')->insert([
                    'id' => $teamId,
                    'name' => $teamName,
                    'created_by' => $administratorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($categories as $categoryName) {
                if (! DB::table('team_categories')
                    ->where('team_id', $teamId)
                    ->where('name', $categoryName)
                    ->exists()) {
                    DB::table('team_categories')->insert([
                        'id' => (string) Str::uuid(),
                        'team_id' => $teamId,
                        'name' => $categoryName,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $now = now();
        $roles = [
            'admin' => 'Admin',
            'agent' => 'Agent',
            'requester' => 'Requester',
        ];
        $roleIds = [];

        foreach ($roles as $slug => $name) {
            $roleIds[$slug] = (string) Str::uuid();

            DB::table('roles')->insert([
                'id' => $roleIds[$slug],
                'name' => $name,
                'slug' => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Administrador',
            'email' => 'admin@admin.com',
            'email_verified_at' => $now,
            'password' => Hash::make('abcd1234'),
            'role_id' => $roleIds['admin'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('email', 'admin@admin.com')->delete();
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists('roles');
    }
};

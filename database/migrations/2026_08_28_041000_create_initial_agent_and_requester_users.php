<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roles = DB::table('roles')->pluck('id', 'slug');

        foreach ([
            ['name' => 'Agente Inicial', 'email' => 'agent@agent.com', 'role' => 'agent'],
            ['name' => 'Solicitante Inicial', 'email' => 'requester@requester.com', 'role' => 'requester'],
        ] as $user) {
            if (! DB::table('users')->where('email', $user['email'])->exists()) {
                DB::table('users')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'email_verified_at' => $now,
                    'password' => Hash::make('abcd1234'),
                    'role_id' => $roles[$user['role']],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('email', ['agent@agent.com', 'requester@requester.com'])
            ->delete();
    }
};

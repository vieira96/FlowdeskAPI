<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_initial_roles_and_administrator_are_created(): void
    {
        $administrator = User::query()->with('role')->where('email', 'admin@admin.com')->firstOrFail();

        $this->assertSame('admin', $administrator->role?->slug);
        $this->assertTrue(Hash::check('abcd1234', $administrator->password));
        $this->assertDatabaseCount('roles', 3);
    }
}

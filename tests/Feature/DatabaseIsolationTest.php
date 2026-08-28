<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_the_test_suite_uses_the_dedicated_testing_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('flowdesk_testing', config('database.connections.mysql.database'));
    }
}

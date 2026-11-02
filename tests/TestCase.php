<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on `npm run build` having been run.
        $this->withoutVite();
    }

    protected function owner(): User
    {
        return User::factory()->owner()->create();
    }

    protected function cashier(): User
    {
        return User::factory()->cashier()->create();
    }
}

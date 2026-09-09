<?php

namespace Tests;

use App\Support\Toast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function assertToastSuccess(): void
    {
        $this->assertTrue(
            session()->has(Toast::KEY_SUCCESS),
            'Expected success toast in session but none found.'
        );
    }

    protected function assertToastError(): void
    {
        $this->assertTrue(
            session()->has(Toast::KEY_ERROR),
            'Expected error toast in session but none found.'
        );
    }
}

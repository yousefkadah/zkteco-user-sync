<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Home/Index'));
    }

    public function test_imports_page_renders(): void
    {
        $this->get('/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Import/Index'));
    }

    public function test_devices_page_renders(): void
    {
        $this->get('/devices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Devices/Index'));
    }

    public function test_template_download_returns_a_spreadsheet(): void
    {
        $response = $this->get('/template');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Import\UserSpreadsheetParser;
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

    /**
     * The window lands here first. NativePHP reveals the window on Electron's
     * `did-finish-load`, which waits for every subresource — so this page must
     * never pull the app bundle, or the window stays hidden until the whole
     * bundle downloads (which is what made startup look frozen).
     */
    public function test_splash_page_is_bundle_free_and_hands_off_to_the_app(): void
    {
        $content = (string) $this->get('/splash')->assertOk()->getContent();

        $this->assertStringNotContainsString('app.tsx', $content);
        $this->assertStringNotContainsString('@vite', $content);
        $this->assertStringContainsString('app-splash', $content);
        $this->assertStringContainsString('location.replace', $content);
    }

    public function test_devices_page_renders(): void
    {
        $this->get('/devices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Devices/Index'));
    }

    /**
     * The template is a CSV, not an XLSX: the bundled runtime PHP has no
     * xmlwriter extension, so PhpSpreadsheet's Xlsx writer threw
     * "Class \"XMLWriter\" not found" and this download 500'd in the packaged
     * app (CI's PHP has xmlwriter, which is why it went unnoticed here).
     */
    public function test_template_download_returns_a_csv(): void
    {
        $response = $this->get('/template');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('zkteco-users-template.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('user_id,name,password,card_number,privilege', (string) $response->getContent());
    }

    /**
     * Guards the regression directly: the template must not need any extension
     * the packaged runtime lacks. Parsing our own template back must round-trip.
     */
    public function test_the_downloaded_template_can_be_imported_back(): void
    {
        $csv = (string) $this->get('/template')->getContent();

        $path = tempnam(sys_get_temp_dir(), 'zk_tpl_').'.csv';
        file_put_contents($path, $csv);

        try {
            $rows = (new UserSpreadsheetParser)->parse($path);

            $this->assertCount(3, $rows);
            $this->assertSame('Dana Cohen', $rows[0]->name);
            $this->assertTrue($rows[0]->isValid());
        } finally {
            @unlink($path);
        }
    }
}

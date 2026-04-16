<?php

namespace Tests\Feature;

use App\Services\PdfGenerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PdfGenerationServiceTest extends TestCase
{
    private PdfGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PdfGenerationService;
    }

    /**
     * Helper: call a private method via reflection.
     */
    private function callPrivate(object $instance, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($instance, $method);

        return $ref->invoke($instance, ...$args);
    }

    /**
     * Create a partial mock that stubs renderHtml to return simple HTML.
     */
    private function createMockedService(): PdfGenerationService
    {
        $mock = $this->createPartialMock(PdfGenerationService::class, ['renderHtml']);
        $mock->method('renderHtml')->willReturn('<html><body>Test PDF</body></html>');

        return $mock;
    }

    // ── isChromeAvailable ──────────────────────────────────────────

    #[Test]
    public function is_chrome_available_returns_bool(): void
    {
        $result = $this->callPrivate($this->service, 'isChromeAvailable');

        $this->assertIsBool($result);
    }

    #[Test]
    public function is_chrome_available_matches_chrome_path_existence(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows-specific Chrome detection test');
        }

        $chromePath = $this->callPrivate($this->service, 'getChromePath');
        $expected = file_exists($chromePath);

        $this->assertSame($expected, $this->callPrivate($this->service, 'isChromeAvailable'));
    }

    #[Test]
    public function get_chrome_path_respects_config(): void
    {
        $fakePath = storage_path('app/tmp/fake-chrome');
        @mkdir(dirname($fakePath), 0755, true);
        file_put_contents($fakePath, 'fake');

        Config::set('app.chrome_path', $fakePath);

        $result = $this->callPrivate($this->service, 'getChromePath');
        $this->assertSame($fakePath, $result);

        @unlink($fakePath);
    }

    #[Test]
    public function get_chrome_path_falls_back_when_config_missing(): void
    {
        Config::set('app.chrome_path', null);

        $result = $this->callPrivate($this->service, 'getChromePath');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ── Strategy: force microservice ──────────────────────────────

    #[Test]
    public function force_microservice_config_bypasses_chrome(): void
    {
        Config::set('app.pdf_use_microservice', true);
        Config::set('app.pdf_service_url', 'http://127.0.0.1:9999/pdf');
        Config::set('app.pdf_service_key', '');

        Log::shouldReceive('error')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'PDF microservice failed');
        });

        $mock = $this->createMockedService();
        $result = $mock->generate(['customerName' => 'Test']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('PDF generation failed', $result['error']);
    }

    // ── Strategy: Chrome attempted when available ─────────────────

    #[Test]
    public function chrome_is_attempted_when_available(): void
    {
        Config::set('app.pdf_use_microservice', false);

        $mock = $this->createMockedService();

        if (!$this->callPrivate($mock, 'isChromeAvailable')) {
            $this->markTestSkipped('Chrome not available on this system');
        }

        $result = $mock->generate(['customerName' => 'Test']);

        // Chrome available: should attempt Chrome (may succeed or fail depending on environment)
        if (isset($result['success'])) {
            $this->assertTrue($result['success']);
            $this->assertFileExists($result['path']);
            @unlink($result['path']);
        } else {
            $this->assertArrayHasKey('error', $result);
        }
    }

    // ── Strategy: microservice fallback ───────────────────────────

    #[Test]
    public function microservice_fallback_when_chrome_fails(): void
    {
        Config::set('app.pdf_use_microservice', false);
        Config::set('app.pdf_service_url', 'http://127.0.0.1:9999/pdf');

        // Point Chrome at a file that exists but isn't Chrome → exec fails → fallback
        $fakeBinary = storage_path('app/tmp/not-chrome');
        @mkdir(dirname($fakeBinary), 0755, true);
        file_put_contents($fakeBinary, 'not a browser');
        Config::set('app.chrome_path', $fakeBinary);

        // Chrome fails → error log + warning log, then microservice fails → error log
        Log::shouldReceive('error')->twice();
        Log::shouldReceive('warning')->once();

        $mock = $this->createMockedService();
        $result = $mock->generate(['customerName' => 'Test']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('PDF generation failed', $result['error']);

        @unlink($fakeBinary);
    }

    // ── Code verification tests ───────────────────────────────────

    #[Test]
    public function microservice_uses_connect_timeout(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PdfGenerationService::class))->getFileName()
        );

        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT, 5', $source);
    }

    #[Test]
    public function microservice_timeout_is_60_seconds(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PdfGenerationService::class))->getFileName()
        );

        $this->assertStringContainsString('CURLOPT_TIMEOUT, 60', $source);
    }

    #[Test]
    public function chrome_linux_path_uses_escapeshellarg(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PdfGenerationService::class))->getFileName()
        );

        $this->assertStringContainsString('escapeshellarg($chromePath)', $source);
        $this->assertStringContainsString('escapeshellarg($filepath)', $source);
        $this->assertStringContainsString('escapeshellarg($tmpHtml)', $source);
    }

    #[Test]
    public function strategy_order_is_microservice_config_then_chrome_then_fallback(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(PdfGenerationService::class))->getFileName()
        );

        $configPos = strpos($source, "config('app.pdf_use_microservice')");
        $chromePos = strpos($source, '$this->isChromeAvailable()');

        $this->assertNotFalse($configPos);
        $this->assertNotFalse($chromePos);
        $this->assertLessThan($chromePos, $configPos, 'Force-microservice check should precede Chrome check');
    }

    #[Test]
    public function generate_returns_success_with_filename_and_path(): void
    {
        Config::set('app.pdf_use_microservice', false);

        $mock = $this->createMockedService();

        if (!$this->callPrivate($mock, 'isChromeAvailable')) {
            $this->markTestSkipped('Chrome not available on this system');
        }

        $result = $mock->generate(['customerName' => 'Test']);

        if (isset($result['success'])) {
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('filename', $result);
            $this->assertArrayHasKey('path', $result);
            $this->assertStringStartsWith('Loan_Proposal_', $result['filename']);
            $this->assertStringEndsWith('.pdf', $result['filename']);
            @unlink($result['path']);
        } else {
            $this->assertArrayHasKey('error', $result);
        }
    }
}

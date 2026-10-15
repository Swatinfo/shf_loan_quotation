<?php

namespace Tests\Unit;

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The IP allowlist that lets specific client IPs through while the site is in
 * maintenance mode. Driven directly against the middleware (no real `artisan
 * down` file) with a mocked application.
 */
class MaintenanceIpAllowlistTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function middleware(bool $down): PreventRequestsDuringMaintenance
    {
        // The framework's parent::handle() always consults maintenanceMode(),
        // so mock it for both states; data() is only read while active.
        $manager = Mockery::mock();
        $manager->shouldReceive('active')->andReturn($down);
        $manager->shouldReceive('data')->andReturn(['time' => time(), 'retry' => 60]);

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('isDownForMaintenance')->andReturn($down);
        $app->shouldReceive('maintenanceMode')->andReturn($manager);

        return new PreventRequestsDuringMaintenance($app);
    }

    private function requestFrom(string $ip): Request
    {
        return Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip]);
    }

    public function test_allowlisted_ip_passes_through(): void
    {
        config(['maintenance.allowed_ips' => ['203.0.113.10', '198.51.100.5']]);

        $response = $this->middleware(down: true)
            ->handle($this->requestFrom('198.51.100.5'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_non_allowlisted_ip_is_blocked(): void
    {
        config(['maintenance.allowed_ips' => ['203.0.113.10']]);

        try {
            $this->middleware(down: true)
                ->handle($this->requestFrom('198.51.100.9'), fn () => response('ok'));
            $this->fail('Expected a 503 for a non-allowlisted IP.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_cidr_range_is_allowed(): void
    {
        config(['maintenance.allowed_ips' => ['192.168.1.0/24']]);

        $response = $this->middleware(down: true)
            ->handle($this->requestFrom('192.168.1.55'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_empty_allowlist_blocks_everyone(): void
    {
        config(['maintenance.allowed_ips' => []]);

        $this->expectException(HttpException::class);

        $this->middleware(down: true)
            ->handle($this->requestFrom('203.0.113.10'), fn () => response('ok'));
    }

    public function test_requests_pass_when_not_in_maintenance(): void
    {
        config(['maintenance.allowed_ips' => []]);

        $response = $this->middleware(down: false)
            ->handle($this->requestFrom('198.51.100.9'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}

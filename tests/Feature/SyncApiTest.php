<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PdfGenerationService;
use App\Services\QuotationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function createUser(string $role = 'staff'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customerName' => 'Test Customer',
            'customerType' => 'proprietor',
            'loanAmount' => 5000000,
            'preparedByName' => 'Agent',
            'preparedByMobile' => '9999999999',
            'selectedTenures' => [5, 10],
            'banks' => [[
                'name' => 'SBI',
                'roiMin' => 8.5,
                'roiMax' => 9.0,
                'emiByTenure' => [
                    5 => ['emi' => 102000, 'totalPayment' => 6120000, 'totalInterest' => 1120000],
                    10 => ['emi' => 61000, 'totalPayment' => 7320000, 'totalInterest' => 2320000],
                ],
                'charges' => [
                    'pf' => 25000, 'pfPercent' => 0.5, 'admin' => 5000, 'adminBase' => 5000,
                    'stamp_notary' => 500, 'registration_fee' => 0, 'advocate' => 3000,
                    'iom' => 0, 'tc' => 1000,
                    'extra1Name' => '', 'extra1Amt' => 0, 'extra2Name' => '', 'extra2Amt' => 0,
                    'total' => 34500,
                ],
            ]],
            'documents' => [['en' => 'PAN Card', 'gu' => 'પાન કાર્ડ']],
            'additionalNotes' => '',
            'ourServices' => '',
        ], $overrides);
    }

    public function test_unauthenticated_user_cannot_sync(): void
    {
        $response = $this->postJson('/api/sync', ['quotations' => [$this->validPayload()]]);
        $response->assertStatus(401);
    }

    public function test_empty_quotations_returns_400(): void
    {
        $user = $this->createUser();
        $response = $this->actingAs($user)->postJson('/api/sync', ['quotations' => []]);
        $response->assertStatus(400)->assertJson(['error' => 'No quotations to sync']);
    }

    public function test_successful_sync_saves_to_database(): void
    {
        $user = $this->createUser();

        $this->mock(PdfGenerationService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'success' => true,
                'filename' => 'test_sync.pdf',
                'path' => storage_path('app/pdfs/test_sync.pdf'),
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [$this->validPayload()],
        ]);

        $response->assertOk()->assertJsonPath('results.0.success', true);

        $this->assertDatabaseHas('quotations', [
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'loan_amount' => 5000000,
        ]);
    }

    public function test_sync_returns_failure_when_db_save_fails(): void
    {
        $user = $this->createUser();

        $this->mock(QuotationService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'success' => false,
                'error' => 'Database save failed: SQLSTATE[HY000]: General error: 5 database is locked',
                'filename' => 'orphan.pdf',
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [$this->validPayload()],
        ]);

        $response->assertOk();
        $result = $response->json('results.0');
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertDatabaseMissing('quotations', ['customer_name' => 'Test Customer']);
    }

    public function test_sync_auto_fills_prepared_by_from_auth_user(): void
    {
        $user = $this->createUser();
        $user->update(['name' => 'Syncer', 'phone' => '1234567890']);

        $this->mock(PdfGenerationService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'success' => true,
                'filename' => 'autofill.pdf',
                'path' => storage_path('app/pdfs/autofill.pdf'),
            ]);
        });

        $payload = $this->validPayload();
        unset($payload['preparedByName'], $payload['preparedByMobile']);

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [$payload],
        ]);

        $response->assertOk()->assertJsonPath('results.0.success', true);

        $this->assertDatabaseHas('quotations', [
            'prepared_by_name' => 'Syncer',
            'prepared_by_mobile' => '1234567890',
        ]);
    }

    public function test_sync_logs_activity_on_success(): void
    {
        $user = $this->createUser();

        $this->mock(PdfGenerationService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'success' => true,
                'filename' => 'activity.pdf',
                'path' => storage_path('app/pdfs/activity.pdf'),
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [$this->validPayload()],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'create_quotation',
        ]);
    }

    public function test_sync_does_not_log_activity_on_failure(): void
    {
        $user = $this->createUser();

        $this->mock(QuotationService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn([
                'success' => false,
                'error' => 'Database save failed: test error',
                'filename' => 'fail.pdf',
            ]);
        });

        $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [$this->validPayload()],
        ]);

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $user->id,
            'action' => 'create_quotation',
        ]);
    }

    public function test_batch_sync_handles_partial_failures(): void
    {
        $user = $this->createUser();
        $callCount = 0;

        $this->mock(QuotationService::class, function ($mock) use (&$callCount) {
            $mock->shouldReceive('generate')->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    return [
                        'success' => false,
                        'error' => 'Database save failed: database is locked',
                        'filename' => 'orphan.pdf',
                    ];
                }

                return [
                    'success' => true,
                    'quotation' => new \App\Models\Quotation([
                        'id' => $callCount,
                        'pdf_filename' => "sync_{$callCount}.pdf",
                    ]),
                ];
            });
        });

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [
                $this->validPayload(['customerName' => 'First']),
                $this->validPayload(['customerName' => 'Second']),
                $this->validPayload(['customerName' => 'Third']),
            ],
        ]);

        $response->assertOk();
        $results = $response->json('results');

        $this->assertTrue($results[0]['success']);
        $this->assertFalse($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    public function test_validation_error_returns_failure(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/sync', [
            'quotations' => [['customerName' => '', 'banks' => []]],
        ]);

        $response->assertOk();
        $result = $response->json('results.0');
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }
}

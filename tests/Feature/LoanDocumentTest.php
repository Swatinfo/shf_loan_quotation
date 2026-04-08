<?php

namespace Tests\Feature;

use App\Models\LoanDetail;
use App\Models\LoanDocument;
use App\Models\User;
use App\Services\LoanDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
    }

    private function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'task_role' => 'loan_advisor',
        ], $overrides));
    }

    private function createLoan(User $user): LoanDetail
    {
        return LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'status' => LoanDetail::STATUS_ACTIVE,
            'current_stage' => 'document_collection',
            'created_by' => $user->id,
        ]);
    }

    private function createDocument(LoanDetail $loan, array $overrides = []): LoanDocument
    {
        return LoanDocument::create(array_merge([
            'loan_id' => $loan->id,
            'document_name_en' => 'PAN Card',
            'document_name_gu' => 'પાન કાર્ડ',
            'is_required' => true,
            'status' => 'pending',
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_can_view_loan_documents(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $this->createDocument($loan);

        $response = $this->actingAs($user)->get(route('loans.documents', $loan));
        $response->assertOk();
    }

    public function test_can_update_document_status_to_received(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.status', [$loan, $doc]),
            ['status' => 'received']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $doc->refresh();
        $this->assertEquals('received', $doc->status);
        $this->assertNotNull($doc->received_date);
        $this->assertEquals($user->id, $doc->received_by);
    }

    public function test_can_update_document_status_to_rejected_with_reason(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.status', [$loan, $doc]),
            ['status' => 'rejected', 'rejected_reason' => 'Document expired']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $doc->refresh();
        $this->assertEquals('rejected', $doc->status);
        $this->assertEquals('Document expired', $doc->rejected_reason);
    }

    public function test_can_waive_document(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.status', [$loan, $doc]),
            ['status' => 'waived']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $doc->refresh();
        $this->assertEquals('waived', $doc->status);
    }

    public function test_can_add_custom_document(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.store', $loan),
            [
                'document_name_en' => 'Salary Slip',
                'document_name_gu' => 'પગાર સ્લિપ',
                'is_required' => true,
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('loan_documents', [
            'loan_id' => $loan->id,
            'document_name_en' => 'Salary Slip',
            'status' => 'pending',
        ]);
    }

    public function test_can_remove_document(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $response = $this->actingAs($user)->deleteJson(
            route('loans.documents.destroy', [$loan, $doc])
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('loan_documents', ['id' => $doc->id]);
    }

    public function test_document_progress_calculation(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);

        $this->createDocument($loan, ['document_name_en' => 'Doc 1', 'sort_order' => 0]);
        $this->createDocument($loan, ['document_name_en' => 'Doc 2', 'sort_order' => 1, 'status' => 'received']);
        $this->createDocument($loan, ['document_name_en' => 'Doc 3', 'sort_order' => 2, 'status' => 'waived']);
        $this->createDocument($loan, ['document_name_en' => 'Doc 4', 'sort_order' => 3, 'status' => 'rejected']);

        $service = app(LoanDocumentService::class);
        $progress = $service->getProgress($loan);

        $this->assertEquals(4, $progress['total']);
        $this->assertEquals(2, $progress['resolved']); // received + waived
        $this->assertEquals(1, $progress['received']);
        $this->assertEquals(1, $progress['rejected']);
        $this->assertEquals(1, $progress['pending']);
        $this->assertEquals(50.0, $progress['percentage']); // 2/4 = 50%
    }

    public function test_all_required_resolved_check(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);

        $this->createDocument($loan, ['document_name_en' => 'Doc 1', 'sort_order' => 0, 'status' => 'received']);
        $this->createDocument($loan, ['document_name_en' => 'Doc 2', 'sort_order' => 1, 'status' => 'waived']);

        $service = app(LoanDocumentService::class);
        $this->assertTrue($service->allRequiredResolved($loan));
    }

    public function test_all_required_not_resolved_when_pending(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);

        $this->createDocument($loan, ['document_name_en' => 'Doc 1', 'sort_order' => 0, 'status' => 'received']);
        $this->createDocument($loan, ['document_name_en' => 'Doc 2', 'sort_order' => 1, 'status' => 'pending']);

        $service = app(LoanDocumentService::class);
        $this->assertFalse($service->allRequiredResolved($loan));
    }

    public function test_status_transition_clears_fields_correctly(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $service = app(LoanDocumentService::class);

        // Mark as received
        $service->updateStatus($doc, 'received', $user->id);
        $doc->refresh();
        $this->assertNotNull($doc->received_date);
        $this->assertEquals($user->id, $doc->received_by);

        // Change to rejected — should clear received fields
        $service->updateStatus($doc, 'rejected', $user->id, 'Bad scan');
        $doc->refresh();
        $this->assertNull($doc->received_date);
        $this->assertNull($doc->received_by);
        $this->assertEquals('Bad scan', $doc->rejected_reason);

        // Change to pending — should clear everything
        $service->updateStatus($doc, 'pending', $user->id);
        $doc->refresh();
        $this->assertNull($doc->received_date);
        $this->assertNull($doc->received_by);
        $this->assertNull($doc->rejected_reason);
    }

    public function test_optional_documents_not_counted_in_progress(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);

        $this->createDocument($loan, ['document_name_en' => 'Required', 'sort_order' => 0, 'is_required' => true, 'status' => 'received']);
        $this->createDocument($loan, ['document_name_en' => 'Optional', 'sort_order' => 1, 'is_required' => false, 'status' => 'pending']);

        $service = app(LoanDocumentService::class);
        $progress = $service->getProgress($loan);

        $this->assertEquals(1, $progress['total']);
        $this->assertEquals(100.0, $progress['percentage']);
    }
}

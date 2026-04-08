<?php

namespace Tests\Feature;

use App\Models\LoanDetail;
use App\Models\LoanDocument;
use App\Models\User;
use App\Services\LoanDocumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
        Storage::fake('local');
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

    // ── Upload Tests ──

    public function test_can_upload_file_to_document(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->create('pan_card.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response->assertOk()->assertJson([
            'success' => true,
            'document' => [
                'id' => $doc->id,
                'has_file' => true,
            ],
        ]);

        $doc->refresh();
        $this->assertNotNull($doc->file_path);
        $this->assertEquals('pan_card.pdf', $doc->file_name);
        $this->assertNotNull($doc->file_size);
        $this->assertEquals($user->id, $doc->uploaded_by);
        $this->assertNotNull($doc->uploaded_at);

        // File should exist in storage
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_upload_auto_marks_document_as_received(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan); // starts as 'pending'

        $file = UploadedFile::fake()->create('aadhar.pdf', 200, 'application/pdf');

        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $doc->refresh();
        $this->assertEquals('received', $doc->status);
        $this->assertNotNull($doc->received_date);
    }

    public function test_upload_does_not_change_status_if_already_received(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan, ['status' => 'received', 'received_date' => now(), 'received_by' => $user->id]);

        $file = UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf');

        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $doc->refresh();
        $this->assertEquals('received', $doc->status);
        $this->assertTrue($doc->hasFile());
    }

    public function test_replace_file_deletes_old_one(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        // Upload first file
        $file1 = UploadedFile::fake()->create('old.pdf', 100, 'application/pdf');
        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file1]
        );

        $doc->refresh();
        $oldPath = $doc->file_path;
        Storage::disk('local')->assertExists($oldPath);

        // Upload replacement
        $file2 = UploadedFile::fake()->create('new.pdf', 200, 'application/pdf');
        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file2]
        );

        $doc->refresh();
        $this->assertEquals('new.pdf', $doc->file_name);
        $this->assertTrue($doc->hasFile());

        // New file should exist in storage
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        // 11MB — over the 10MB limit
        $file = UploadedFile::fake()->create('huge.pdf', 11264, 'application/pdf');

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_invalid_mime_type(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_accepts_image_files(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        $response = $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    // ── Download Tests ──

    public function test_can_download_uploaded_file(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        // Upload a file first
        $file = UploadedFile::fake()->create('pan_card.pdf', 500, 'application/pdf');
        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response = $this->actingAs($user)->get(
            route('loans.documents.download', [$loan, $doc->fresh()])
        );

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_cannot_download_when_no_file(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan); // No file uploaded

        $response = $this->actingAs($user)->get(
            route('loans.documents.download', [$loan, $doc])
        );

        $response->assertNotFound();
    }

    // ── Delete File Tests ──

    public function test_can_delete_file_keeping_document(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        // Upload first
        $file = UploadedFile::fake()->create('to_delete.pdf', 300, 'application/pdf');
        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $doc->refresh();
        $filePath = $doc->file_path;
        Storage::disk('local')->assertExists($filePath);

        // Delete file
        $response = $this->actingAs($user)->deleteJson(
            route('loans.documents.deleteFile', [$loan, $doc])
        );

        $response->assertOk()->assertJson(['success' => true]);

        $doc->refresh();
        $this->assertFalse($doc->hasFile());
        $this->assertNull($doc->file_path);
        $this->assertNull($doc->file_name);

        // File removed from storage
        Storage::disk('local')->assertMissing($filePath);

        // Document record still exists
        $this->assertDatabaseHas('loan_documents', ['id' => $doc->id]);
    }

    public function test_cannot_delete_file_when_none_exists(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $response = $this->actingAs($user)->deleteJson(
            route('loans.documents.deleteFile', [$loan, $doc])
        );

        $response->assertNotFound();
    }

    // ── Permission Tests ──

    public function test_staff_can_upload(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($staff)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response->assertOk();
    }

    public function test_staff_can_download(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->actingAs($staff)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $response = $this->actingAs($staff)->get(
            route('loans.documents.download', [$loan, $doc->fresh()])
        );

        $response->assertOk();
    }

    public function test_staff_cannot_delete_files(): void
    {
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($admin);
        $doc = $this->createDocument($loan);

        // Admin uploads
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->actingAs($admin)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        // Staff tries to delete file
        $response = $this->actingAs($staff)->deleteJson(
            route('loans.documents.deleteFile', [$loan, $doc->fresh()])
        );

        $response->assertForbidden();
    }

    // ── Removing document also removes file ──

    public function test_removing_document_deletes_file_from_storage(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoan($user);
        $doc = $this->createDocument($loan);

        $file = UploadedFile::fake()->create('to_remove.pdf', 200, 'application/pdf');
        $this->actingAs($user)->postJson(
            route('loans.documents.upload', [$loan, $doc]),
            ['file' => $file]
        );

        $doc->refresh();
        $filePath = $doc->file_path;
        Storage::disk('local')->assertExists($filePath);

        // Remove entire document
        $this->actingAs($user)->deleteJson(
            route('loans.documents.destroy', [$loan, $doc])
        );

        Storage::disk('local')->assertMissing($filePath);
        $this->assertDatabaseMissing('loan_documents', ['id' => $doc->id]);
    }

    // ── Model Helpers ──

    public function test_has_file_helper(): void
    {
        $doc = new LoanDocument(['file_path' => null]);
        $this->assertFalse($doc->hasFile());

        $doc = new LoanDocument(['file_path' => 'loan-documents/1/doc.pdf']);
        $this->assertTrue($doc->hasFile());
    }

    public function test_formatted_file_size(): void
    {
        $doc = new LoanDocument(['file_size' => 500]);
        $this->assertEquals('500 B', $doc->formattedFileSize());

        $doc = new LoanDocument(['file_size' => 2048]);
        $this->assertEquals('2 KB', $doc->formattedFileSize());

        $doc = new LoanDocument(['file_size' => 1572864]);
        $this->assertEquals('1.5 MB', $doc->formattedFileSize());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for `Quotation::isEditableBy()` — gates the edit form, the update
 * endpoint, and the per-document strike toggle.
 *
 * Rule: editable iff (a) not converted, (b) not cancelled, (c) the user
 * passes one of: super_admin role, ownership + edit_quotation,
 * branch_manager/bdh of the quotation's branch + edit_quotation, or
 * view_all_quotations + edit_quotation (admin convenience).
 */
class QuotationEditPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function seedRolesAndPermissions(): void
    {
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee'] as $slug) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
        }

        foreach ([
            ['slug' => 'edit_quotation', 'name' => 'Edit Quotation'],
            ['slug' => 'view_all_quotations', 'name' => 'View All Quotations'],
        ] as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['name' => $perm['name'], 'group' => 'Quotations'],
            );
        }

        // Default-grant edit_quotation to the roles that should have it.
        $editPermId = Permission::where('slug', 'edit_quotation')->value('id');
        $viewAllPermId = Permission::where('slug', 'view_all_quotations')->value('id');
        foreach (['admin', 'branch_manager', 'bdh', 'loan_advisor'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            \DB::table('role_permission')->insertOrIgnore([['role_id' => $roleId, 'permission_id' => $editPermId]]);
        }
        foreach (['admin'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            \DB::table('role_permission')->insertOrIgnore([['role_id' => $roleId, 'permission_id' => $viewAllPermId]]);
        }
    }

    private function makeUser(string $slug, ?Branch $branch = null): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $slug)->pluck('id'));
        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        return $user->fresh(['roles']);
    }

    private function makeBranch(): Branch
    {
        return Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
    }

    private function makeQuotation(User $creator, Branch $branch, array $overrides = []): Quotation
    {
        return Quotation::create(array_merge([
            'user_id' => $creator->id,
            'customer_name' => 'Test Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1500000,
            'pdf_filename' => 'cached.pdf',
            'pdf_path' => 'storage/app/pdfs/cached.pdf',
            'selected_tenures' => [10, 15],
            'branch_id' => $branch->id,
            'status' => Quotation::STATUS_ACTIVE,
        ], $overrides));
    }

    /**
     * Create a real LoanDetail (with its bank + product) and return its id —
     * tests that simulate "this quotation has been converted" need a valid
     * FK target on `quotations.loan_id`.
     */
    private function makeLoanId(Branch $branch, User $creator): int
    {
        $bank = Bank::create(['name' => 'TestBank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'TestProduct-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Converted Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1500000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $creator->id,
            'assigned_advisor' => $creator->id,
        ])->id;
    }

    public function test_creator_can_edit_active_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $q = $this->makeQuotation($creator, $branch);

        $this->assertTrue($q->isEditableBy($creator));
    }

    public function test_creator_cannot_edit_converted_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $loanId = $this->makeLoanId($branch, $creator);
        $q = $this->makeQuotation($creator, $branch, ['loan_id' => $loanId]);

        $this->assertFalse($q->isEditableBy($creator));
    }

    public function test_creator_cannot_edit_cancelled_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $q = $this->makeQuotation($creator, $branch, ['status' => Quotation::STATUS_CANCELLED]);

        $this->assertFalse($q->isEditableBy($creator));
    }

    public function test_branch_manager_in_quotation_branch_can_edit(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $bm = $this->makeUser('branch_manager', $branch);
        $q = $this->makeQuotation($creator, $branch);

        $this->assertTrue($q->isEditableBy($bm));
    }

    public function test_branch_manager_in_other_branch_cannot_edit(): void
    {
        $quotationBranch = $this->makeBranch();
        $otherBranch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $bm = $this->makeUser('branch_manager', $otherBranch);
        $q = $this->makeQuotation($creator, $quotationBranch);

        $this->assertFalse($q->isEditableBy($bm));
    }

    public function test_bdh_in_quotation_branch_can_edit(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $bdh = $this->makeUser('bdh', $branch);
        $q = $this->makeQuotation($creator, $branch);

        $this->assertTrue($q->isEditableBy($bdh));
    }

    public function test_bank_employee_cannot_edit(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $banker = $this->makeUser('bank_employee', $branch);
        $q = $this->makeQuotation($creator, $branch);

        $this->assertFalse($q->isEditableBy($banker));
    }

    public function test_super_admin_can_edit_any_active_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $sa = $this->makeUser('super_admin');
        $q = $this->makeQuotation($creator, $branch);

        $this->assertTrue($q->isEditableBy($sa));
    }

    public function test_super_admin_cannot_edit_converted_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $sa = $this->makeUser('super_admin');
        $loanId = $this->makeLoanId($branch, $creator);
        $q = $this->makeQuotation($creator, $branch, ['loan_id' => $loanId]);

        // The conversion gate is absolute — even super_admin can't edit a
        // converted quotation, mirrors the destroy() and convert() blocks.
        $this->assertFalse($q->isEditableBy($sa));
    }

    public function test_admin_with_view_all_can_edit_other_branch_quotation(): void
    {
        $branch = $this->makeBranch();
        $creator = $this->makeUser('loan_advisor');
        $admin = $this->makeUser('admin');
        $q = $this->makeQuotation($creator, $branch);

        $this->assertTrue($q->isEditableBy($admin));
    }
}

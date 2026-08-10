<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Excel exports for the Pipeline + Loan Report pages:
 *   - Same view_reports gate and reportScope narrowing as the data endpoints;
 *     a forged branch_id cannot widen the export.
 *   - Same filters as the screen (status/tab/stage/stuck/date/bank/branch/user).
 *   - ALL matching records are exported — never a rendered page subset.
 *   - Real xlsx: well-formed parts, raw numeric amount cells (no ₹ strings),
 *     real date serials, totals footer, flattened stage lines.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        Stage::firstOrCreate(['stage_key' => 'legal_verification'], [
            'stage_name_en' => 'Legal Verification',
            'sequence_order' => 5,
            'is_parallel' => false,
            'parent_stage_key' => null,
            'stage_type' => 'sequential',
            'is_enabled' => true,
        ]);
    }

    private function makeUser(string $roleSlug): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(User $advisor, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);

        return LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 2000000,
            'status' => 'active',
            'current_stage' => 'legal_verification',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ], $attrs));
    }

    private function completeStage(LoanDetail $loan, string $stageKey, string $completedAt = '2026-03-10 10:00:00'): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'assigned_to' => $loan->assigned_advisor,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-03-01 10:00:00',
            'completed_at' => $completedAt,
        ]);
    }

    /** One disbursement tranche — "disbursed" is defined by these rows. */
    private function addDisbursementEntry(LoanDetail $loan, string $date, int $amount): void
    {
        $detailId = DB::table('disbursement_details')->where('loan_id', $loan->id)->value('id')
            ?? DB::table('disbursement_details')->insertGetId([
                'loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        DB::table('disbursement_entries')->insert([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detailId,
            'disbursement_date' => $date, 'method' => 'fund_transfer', 'amount' => $amount,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Unzip the downloaded workbook and return xl/worksheets/sheet1.xml,
     * asserting the part is present and well-formed on the way.
     */
    private function sheetXml(TestResponse $response): string
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'download is not a readable zip');
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertNotFalse($xml, 'sheet1.xml missing from workbook');
        $this->assertNotFalse(simplexml_load_string($xml), 'sheet1.xml is not well-formed');

        return $xml;
    }

    /** Excel 1900-system serial — same UTC-midnight formula as the writer. */
    private function dateSerial(string $ymd): int
    {
        return intdiv(strtotime($ymd.' 00:00:00 UTC'), 86400) + 25569;
    }

    public function test_exports_require_view_reports_permission(): void
    {
        $noRole = User::create([
            'name' => 'NoRole',
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);

        $this->actingAs($noRole)->get(route('reports.pipeline.export'))->assertForbidden();
        $this->actingAs($noRole)->get(route('reports.loans.export'))->assertForbidden();

        auth()->logout();
        $this->get(route('reports.pipeline.export'))->assertRedirect(route('login'));
        $this->get(route('reports.loans.export'))->assertRedirect(route('login'));
    }

    public function test_loan_report_export_downloads_an_xlsx(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $this->completeStage($this->makeLoan($advisor, ['sanctioned_amount' => 900000]), 'sanction');

        $response = $this->actingAs($admin)->get(route('reports.loans.export'))->assertOk();

        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertDownload('loan-report-sanctioned-'.now()->format('Y-m-d').'.xlsx');
        $this->sheetXml($response);
    }

    public function test_loan_report_export_writes_raw_numeric_amounts(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 900000]);
        $this->completeStage($loan, 'sanction');

        $xml = $this->sheetXml($this->actingAs($admin)->get(route('reports.loans.export'))->assertOk());

        $this->assertStringContainsString($loan->loan_number, $xml);
        $this->assertStringContainsString('<v>900000</v>', $xml);
        $this->assertStringNotContainsString('₹', $xml);
    }

    public function test_loan_report_export_appends_a_period_totals_footer(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $this->completeStage($this->makeLoan($advisor, ['sanctioned_amount' => 1000000]), 'sanction');
        $this->completeStage($this->makeLoan($advisor, ['sanctioned_amount' => 2500000]), 'sanction');
        // Disbursed elsewhere — no row in the sanctioned export, but the
        // period footer still counts it under the disbursed total.
        $this->addDisbursementEntry($this->makeLoan($advisor, ['disbursed_amount' => 700000]), '2026-03-12', 700000);

        $xml = $this->sheetXml($this->actingAs($admin)->get(route('reports.loans.export'))->assertOk());

        $this->assertStringContainsString('Period totals — sanctioned 2 / disbursed 1 loans', $xml);
        $this->assertStringContainsString('<v>3500000</v>', $xml);
        $this->assertStringContainsString('<v>700000</v>', $xml);
    }

    public function test_loan_report_export_respects_the_status_filter(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $sanctioned = $this->makeLoan($advisor, ['sanctioned_amount' => 1500000]);
        $disbursed = $this->makeLoan($advisor, ['disbursed_amount' => 900000]);
        $this->completeStage($sanctioned, 'sanction');
        $this->addDisbursementEntry($disbursed, '2026-03-10', 900000);

        $response = $this->actingAs($admin)
            ->get(route('reports.loans.export', ['status' => 'disbursed']))
            ->assertOk();
        $response->assertDownload('loan-report-disbursed-'.now()->format('Y-m-d').'.xlsx');
        $xml = $this->sheetXml($response);

        $this->assertStringContainsString($disbursed->loan_number, $xml);
        $this->assertStringNotContainsString($sanctioned->loan_number, $xml);
    }

    public function test_loan_report_export_writes_real_date_serials(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction',
            'assigned_to' => $advisor->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-02-01 10:00:00',
            'completed_at' => '2026-02-10 10:00:00',
        ]);

        $xml = $this->sheetXml($this->actingAs($admin)->get(route('reports.loans.export'))->assertOk());

        $this->assertStringContainsString('<v>'.$this->dateSerial('2026-02-10').'</v>', $xml);
    }

    public function test_branch_manager_export_scope_survives_a_forged_branch_id(): void
    {
        $bm = $this->makeUser('branch_manager');
        $advisor = $this->makeUser('loan_advisor');
        $own = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $foreign = $this->makeLoan($advisor, ['sanctioned_amount' => 200000]);
        $this->completeStage($own, 'sanction');
        $this->completeStage($foreign, 'sanction');
        $bm->branches()->attach($own->branch_id);

        $xml = $this->sheetXml($this->actingAs($bm)
            ->get(route('reports.loans.export', ['branch_id' => $foreign->branch_id]))
            ->assertOk());
        $this->assertStringNotContainsString($foreign->loan_number, $xml);
        $this->assertStringNotContainsString($own->loan_number, $xml); // scope ∩ forged branch = ∅

        $xml = $this->sheetXml($this->actingAs($bm)
            ->get(route('reports.pipeline.export', ['branch_id' => $foreign->branch_id]))
            ->assertOk());
        $this->assertStringNotContainsString($foreign->loan_number, $xml);
    }

    public function test_pipeline_export_flattens_stage_lines_into_one_cell(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $owner = $this->makeUser('office_employee');
        $loan = $this->makeLoan($advisor);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $owner->id,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
            'started_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.pipeline.export'))->assertOk();
        $response->assertDownload('loan-pipeline-active-'.now()->format('Y-m-d').'.xlsx');
        $xml = $this->sheetXml($response);

        $this->assertStringContainsString($loan->loan_number, $xml);
        $this->assertStringContainsString('Legal Verification — '.$owner->name.' — 5d', $xml);
    }

    public function test_pipeline_export_respects_status_and_stuck_filters(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $active = $this->makeLoan($advisor);
        $completed = $this->makeLoan($advisor, ['status' => 'completed']);

        $xml = $this->sheetXml($this->actingAs($admin)
            ->get(route('reports.pipeline.export', ['status' => 'completed']))
            ->assertOk());
        $this->assertStringContainsString($completed->loan_number, $xml);
        $this->assertStringNotContainsString($active->loan_number, $xml);

        $xml = $this->sheetXml($this->actingAs($admin)
            ->get(route('reports.pipeline.export', ['stuck_days' => 999]))
            ->assertOk());
        $this->assertStringNotContainsString($active->loan_number, $xml);
    }

    public function test_pipeline_export_workload_tab(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $holder = $this->makeUser('office_employee');
        $loan = $this->makeLoan($advisor);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $holder->id,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
            'started_at' => now()->subDays(9),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.pipeline.export', ['tab' => 'workload']))
            ->assertOk();
        $response->assertDownload('workload-by-user-'.now()->format('Y-m-d').'.xlsx');
        $xml = $this->sheetXml($response);

        $this->assertStringContainsString($holder->name, $xml);
        $this->assertStringContainsString('Legal Verification', $xml);
    }

    public function test_empty_result_still_yields_a_valid_workbook(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $this->completeStage($loan, 'sanction'); // sanctioned 2026 — outside the 2030 window

        $xml = $this->sheetXml($this->actingAs($admin)
            ->get(route('reports.loans.export', ['date_from' => '2030-01-01']))
            ->assertOk());

        $this->assertStringContainsString('Loan #', $xml); // header row survives
        $this->assertStringNotContainsString($loan->loan_number, $xml);
    }

    public function test_export_includes_all_matching_records_not_a_page(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $bank = Bank::create(['name' => 'Bank-bulk', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-bulk', 'is_active' => true]);
        $loans = collect(range(1, 60))->map(fn (int $i) => LoanDetail::create([
            'loan_number' => sprintf('BULK-%03d', $i),
            'customer_name' => 'Customer '.$i,
            'customer_type' => 'salaried',
            'loan_amount' => 100000 + $i,
            'sanctioned_amount' => 100000 + $i,
            'status' => 'active',
            'current_stage' => 'legal_verification',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ]));
        $loans->each(fn (LoanDetail $loan) => $this->completeStage($loan, 'sanction'));

        $loanXml = $this->sheetXml($this->actingAs($admin)->get(route('reports.loans.export'))->assertOk());
        $pipelineXml = $this->sheetXml($this->actingAs($admin)->get(route('reports.pipeline.export'))->assertOk());

        foreach ($loans as $loan) {
            $this->assertStringContainsString($loan->loan_number, $loanXml);
            $this->assertStringContainsString($loan->loan_number, $pipelineXml);
        }
    }
}

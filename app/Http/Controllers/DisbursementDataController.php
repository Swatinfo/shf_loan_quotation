<?php

namespace App\Http\Controllers;

use App\Services\DisbursementDataService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Illuminate\Http\Request;

/**
 * Super-admin only: export every disbursed tranche to Excel, correct the real
 * values, re-import to rewrite the disbursement + OTC data everywhere. Routes are
 * gated by `import_disbursement_data` (granted to no role); this hard-checks the
 * super_admin role on top so it can never be delegated via a permission grant.
 */
class DisbursementDataController extends Controller
{
    public function __construct(private DisbursementDataService $service) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('newtheme.loans.disbursement-data', [
            'result' => null,
            'preview' => null,
            'pageKey' => 'settings',
        ]);
    }

    public function exportXlsx(Request $request)
    {
        $this->guard($request);

        $data = $this->service->exportData();

        return app(XlsxExportService::class)->download(
            'disbursement-data-'.now()->format('Ymd_His').'.xlsx',
            $data['headers'],
            $data['rows'],
            $data['types'],
            [],
            'Disbursements',
            $data['section_rows'],
        );
    }

    public function preview(Request $request)
    {
        $this->guard($request);
        $request->validate(['file' => 'required|file|mimes:xlsx|max:10240']);

        // Stash the upload so "Confirm Import" doesn't need a re-select.
        $stored = $request->file('file')->store('tmp-disbursement-imports');
        $rows = app(XlsxImportService::class)->readAssoc($request->file('file')->getRealPath());

        return view('newtheme.loans.disbursement-data', [
            'result' => null,
            'preview' => $this->service->preview($rows),
            'token' => basename($stored),
            'pageKey' => 'settings',
        ]);
    }

    public function import(Request $request)
    {
        $this->guard($request);

        // Prefer the previewed temp file (token); otherwise a fresh upload.
        $path = null;
        $cleanup = null;
        if ($request->filled('token')) {
            $token = basename((string) $request->input('token'));
            $rel = 'tmp-disbursement-imports/'.$token;
            abort_unless(\Storage::exists($rel), 422, 'Preview file expired — please upload again.');
            $path = \Storage::path($rel);
            $cleanup = $rel;
        } else {
            $request->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
            $path = $request->file('file')->getRealPath();
        }

        $rows = app(XlsxImportService::class)->readAssoc($path);
        $result = $this->service->apply($rows, $request->user());

        if ($cleanup) {
            \Storage::delete($cleanup);
        }

        $msg = "{$result['updated']} loan(s) updated"
            .($result['skippedLoans'] ? ", {$result['skippedLoans']} skipped" : '')
            .($result['errors'] ? ', '.count($result['errors']).' issue(s)' : '').'.';

        return redirect()->route('loans.disbursement-data')
            ->with('success', $msg)
            ->with('importResult', $result);
    }
}

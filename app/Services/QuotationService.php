<?php

namespace App\Services;

use App\Models\BankCharge;
use App\Models\Quotation;
use App\Models\QuotationBank;
use App\Models\QuotationDocument;
use App\Models\QuotationEmi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quotation business logic — validation, PDF generation, DB persistence.
 * Ported from legacy includes/generate-quotation.php
 */
class QuotationService
{
    public function __construct(
        private ConfigService $configService,
        private PdfGenerationService $pdfService,
    ) {}

    /**
     * Generate a quotation PDF and save to database.
     *
     * @param  array  $input  Raw payload from client
     * @param  int  $userId  Authenticated user ID
     * @return array ['success' => true, 'quotation' => Quotation] or ['error' => '...']
     */
    public function generate(array $input, int $userId): array
    {
        $error = $this->validateInput($input);
        if ($error) {
            return ['error' => $error];
        }

        $templateData = $this->buildTemplateDataFromInput($input);

        // Generate PDF — unless the SKIP_PDF_GENERATION dev flag is on, in
        // which case we still save the quotation row but skip the costly
        // Chrome/microservice call. Useful on Windows local where headless
        // Chrome is flaky. The quotation's pdf_filename/pdf_path get
        // placeholder values so downstream lookups don't break.
        $pdfResult = $this->renderPdfOrSkip($templateData);

        if (isset($pdfResult['error'])) {
            return $pdfResult;
        }

        try {
            $quotation = DB::transaction(function () use ($input, $userId, $pdfResult, $templateData) {
                $branchId = $input['branch_id'] ?? User::find($userId)?->default_branch_id;

                $quotation = Quotation::create([
                    'user_id' => $userId,
                    'customer_name' => $templateData['customerName'],
                    'customer_type' => $templateData['customerType'],
                    'referral_name' => $templateData['referralName'] ?: null,
                    'referral_type' => $templateData['referralType'] ?: null,
                    'loan_amount' => $templateData['loanAmount'],
                    'pdf_filename' => $pdfResult['filename'],
                    'pdf_path' => $pdfResult['path'],
                    'additional_notes' => $templateData['additionalNotes'] ?: null,
                    'prepared_by_name' => $templateData['preparedByName'] ?: null,
                    'prepared_by_mobile' => $templateData['preparedByMobile'] ?: null,
                    'selected_tenures' => $templateData['tenures'],
                    'location_id' => $input['location_id'] ?? null,
                    'branch_id' => $branchId,
                ]);

                $this->persistBanksEmisDocuments($quotation, $templateData);

                return $quotation;
            });

            $this->updateBankCharges($templateData['banks']);

            return ['success' => true, 'quotation' => $quotation];
        } catch (\Exception $e) {
            Log::error('Quotation DB save failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Database save failed: '.$e->getMessage(),
                'filename' => $pdfResult['filename'],
            ];
        }
    }

    /**
     * Update an existing quotation. Replaces banks/emis/documents wholesale,
     * regenerates the PDF, and clears the previous cached file.
     *
     * Caller must enforce `Quotation::isEditableBy($user)` before invocation.
     */
    public function update(Quotation $quotation, array $input): array
    {
        $error = $this->validateInput($input);
        if ($error) {
            return ['error' => $error];
        }

        $templateData = $this->buildTemplateDataFromInput($input);

        $pdfResult = $this->renderPdfOrSkip($templateData);
        if (isset($pdfResult['error'])) {
            return $pdfResult;
        }

        $oldPdfPath = $quotation->pdf_path;
        $oldPdfFilename = $quotation->pdf_filename;

        try {
            DB::transaction(function () use ($quotation, $input, $pdfResult, $templateData) {
                if ($quotation->fresh()->is_converted) {
                    throw new \RuntimeException('This quotation has already been converted to a loan and cannot be edited.');
                }

                $quotation->update([
                    'customer_name' => $templateData['customerName'],
                    'customer_type' => $templateData['customerType'],
                    'referral_name' => $templateData['referralName'] ?: null,
                    'referral_type' => $templateData['referralType'] ?: null,
                    'loan_amount' => $templateData['loanAmount'],
                    'pdf_filename' => $pdfResult['filename'],
                    'pdf_path' => $pdfResult['path'],
                    'additional_notes' => $templateData['additionalNotes'] ?: null,
                    'prepared_by_name' => $templateData['preparedByName'] ?: null,
                    'prepared_by_mobile' => $templateData['preparedByMobile'] ?: null,
                    'selected_tenures' => $templateData['tenures'],
                    'location_id' => $input['location_id'] ?? $quotation->location_id,
                    'branch_id' => $input['branch_id'] ?? $quotation->branch_id,
                ]);

                $quotation->banks()->delete();
                $quotation->documents()->delete();

                $this->persistBanksEmisDocuments($quotation, $templateData);
            });

            $this->updateBankCharges($templateData['banks']);
            $this->cleanupOldPdf($oldPdfPath, $oldPdfFilename, $pdfResult['filename'] ?? null);

            return ['success' => true, 'quotation' => $quotation->fresh(['banks.emiEntries', 'documents'])];
        } catch (\Exception $e) {
            Log::error('Quotation update failed', ['quotation_id' => $quotation->id, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Update failed: '.$e->getMessage(),
                'filename' => $pdfResult['filename'] ?? null,
            ];
        }
    }

    /**
     * Validate inputs shared between generate() and update(). Returns the
     * first error message string, or null when valid.
     */
    private function validateInput(array $input): ?string
    {
        $customerName = trim($input['customerName'] ?? '');
        $customerType = trim($input['customerType'] ?? '');
        $loanAmount = (int) ($input['loanAmount'] ?? 0);
        $banks = $input['banks'] ?? [];

        if (! $customerName || ! $customerType || $loanAmount <= 0 || empty($banks)) {
            return 'Missing required fields (customerName, customerType, loanAmount, banks).';
        }

        if ($loanAmount > 1000000000000) {
            return 'Loan amount cannot exceed 1 lakh crore.';
        }

        foreach ($banks as $bank) {
            $roiMin = (float) ($bank['roiMin'] ?? 0);
            $roiMax = (float) ($bank['roiMax'] ?? 0);
            $bankName = $bank['name'] ?? 'Unknown';
            if ($roiMin <= 0 || $roiMax <= 0) {
                return "Min and Max ROI are required for {$bankName}.";
            }
            if ($roiMin > 30 || $roiMax > 30) {
                return "ROI cannot exceed 30% for {$bankName}.";
            }
            if ($roiMin > $roiMax) {
                return "Min ROI cannot be greater than Max ROI for {$bankName}.";
            }
        }

        return null;
    }

    /**
     * Build the PDF template payload from a raw form input array.
     */
    private function buildTemplateDataFromInput(array $input): array
    {
        $config = $this->configService->load();
        $companyPhone = $config['companyPhone'] ?? '+91 XXXXX XXXXX';
        $companyEmail = $config['companyEmail'] ?? 'info@shf.com';
        $tenures = $config['tenures'] ?? [5, 10, 15, 20];

        if (! empty($input['selectedTenures']) && is_array($input['selectedTenures'])) {
            $selectedTenures = array_map('intval', $input['selectedTenures']);
            $filtered = array_values(array_intersect($selectedTenures, $tenures));
            if (! empty($filtered)) {
                $tenures = $filtered;
            }
        }

        $templateData = [
            'customerName' => trim($input['customerName'] ?? ''),
            'customerType' => trim($input['customerType'] ?? ''),
            'referralName' => trim($input['referralName'] ?? ''),
            'referralType' => trim($input['referralType'] ?? ''),
            'loanAmount' => (int) ($input['loanAmount'] ?? 0),
            'date' => now()->format('d F Y'),
            'companyPhone' => $companyPhone,
            'companyEmail' => $companyEmail,
            'tenures' => $tenures,
            'banks' => [],
            'documentsAll' => $this->normaliseDocuments($input['documents'] ?? []),
            'additionalNotes' => trim($input['additionalNotes'] ?? ''),
            'ourServices' => trim($input['ourServices'] ?? ($config['ourServices'] ?? '')),
            'preparedByName' => trim($input['preparedByName'] ?? ''),
            'preparedByMobile' => trim($input['preparedByMobile'] ?? ''),
        ];

        // PDF rendering only sees included docs.
        $templateData['documents'] = array_values(array_filter(
            $templateData['documentsAll'],
            fn ($d) => empty($d['excluded'])
        ));

        foreach ($input['banks'] ?? [] as $bank) {
            $charges = $bank['charges'] ?? [];
            $emiByTenure = [];
            foreach ($bank['emiByTenure'] ?? [] as $tenure => $emiData) {
                $emiByTenure[(int) $tenure] = [
                    'emi' => (int) ($emiData['emi'] ?? 0),
                    'totalInterest' => (int) ($emiData['totalInterest'] ?? 0),
                    'totalPayment' => (int) ($emiData['totalPayment'] ?? 0),
                ];
            }

            $templateData['banks'][] = [
                'name' => $bank['name'] ?? '',
                'roiMin' => (float) ($bank['roiMin'] ?? 0),
                'roiMax' => (float) ($bank['roiMax'] ?? 0),
                'charges' => [
                    'pf' => (int) ($charges['pf'] ?? 0),
                    'pfPercent' => (float) ($charges['pfPercent'] ?? 0),
                    'admin' => (int) ($charges['admin'] ?? 0),
                    'adminBase' => (int) ($charges['adminBase'] ?? 0),
                    'stamp_notary' => (int) ($charges['stamp_notary'] ?? 0),
                    'registration_fee' => (int) ($charges['registration_fee'] ?? 0),
                    'advocate' => (int) ($charges['advocate'] ?? 0),
                    'iom' => (int) ($charges['iom'] ?? 0),
                    'tc' => (int) ($charges['tc'] ?? 0),
                    'extra1Name' => trim($charges['extra1Name'] ?? ''),
                    'extra1Amt' => (int) ($charges['extra1Amt'] ?? 0),
                    'extra2Name' => trim($charges['extra2Name'] ?? ''),
                    'extra2Amt' => (int) ($charges['extra2Amt'] ?? 0),
                    'total' => (int) ($charges['total'] ?? 0),
                ],
                'emiByTenure' => $emiByTenure,
            ];
        }

        return $templateData;
    }

    /**
     * Normalise the incoming documents payload into a stable array shape.
     * Accepts both the new shape `[{en, gu, excluded}]` and the legacy
     * `[{en, gu}]` (treated as included).
     *
     * @return array<int, array{en:string,gu:string,excluded:bool,sequence:int}>
     */
    private function normaliseDocuments(array $documents): array
    {
        $rows = [];
        foreach (array_values($documents) as $i => $doc) {
            $en = trim((string) ($doc['en'] ?? ''));
            if ($en === '') {
                continue;
            }
            $rows[] = [
                'en' => $en,
                'gu' => trim((string) ($doc['gu'] ?? '')),
                'excluded' => ! empty($doc['excluded']),
                'sequence' => (int) ($doc['sequence'] ?? $i),
            ];
        }

        return $rows;
    }

    private function renderPdfOrSkip(array $templateData): array
    {
        if (config('app.skip_pdf_generation')) {
            $safe = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $templateData['customerName']);

            return [
                'success' => true,
                'filename' => 'SKIPPED_'.$safe.'_'.now()->format('Ymd_His').'.pdf',
                'path' => null,
            ];
        }

        return $this->pdfService->generate($templateData);
    }

    private function persistBanksEmisDocuments(Quotation $quotation, array $templateData): void
    {
        foreach ($templateData['banks'] as $bankData) {
            $c = $bankData['charges'];
            $qBank = QuotationBank::create([
                'quotation_id' => $quotation->id,
                'bank_name' => $bankData['name'],
                'roi_min' => $bankData['roiMin'],
                'roi_max' => $bankData['roiMax'],
                'pf_charge' => $c['pfPercent'],
                'admin_charge' => $c['adminBase'],
                'stamp_notary' => $c['stamp_notary'],
                'registration_fee' => $c['registration_fee'],
                'advocate_fees' => $c['advocate'],
                'iom_charge' => $c['iom'],
                'tc_report' => $c['tc'],
                'extra1_name' => $c['extra1Name'] ?: null,
                'extra1_amount' => $c['extra1Amt'],
                'extra2_name' => $c['extra2Name'] ?: null,
                'extra2_amount' => $c['extra2Amt'],
                'total_charges' => $c['total'],
            ]);

            foreach ($bankData['emiByTenure'] as $tenure => $emi) {
                QuotationEmi::create([
                    'quotation_bank_id' => $qBank->id,
                    'tenure_years' => $tenure,
                    'monthly_emi' => $emi['emi'],
                    'total_interest' => $emi['totalInterest'],
                    'total_payment' => $emi['totalPayment'],
                ]);
            }
        }

        // Persist the FULL document list including excluded rows so the
        // operator can flip a row back on later from the show/edit page.
        foreach ($templateData['documentsAll'] as $doc) {
            QuotationDocument::create([
                'quotation_id' => $quotation->id,
                'document_name_en' => $doc['en'],
                'document_name_gu' => $doc['gu'] ?: null,
                'is_excluded' => $doc['excluded'],
                'sequence' => $doc['sequence'],
            ]);
        }
    }

    /**
     * Delete the previous cached PDF artifact when a quotation gets a new one.
     * Skips the active path so we don't unlink the file we just generated.
     */
    private function cleanupOldPdf(?string $oldPath, ?string $oldFilename, ?string $newFilename): void
    {
        if ($oldFilename && $oldFilename !== $newFilename) {
            $storage = storage_path('app/pdfs/'.$oldFilename);
            if (file_exists($storage)) {
                @unlink($storage);
            }
        }
        if ($oldPath && $oldPath !== ($newFilename ? storage_path('app/pdfs/'.$newFilename) : null) && file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }

    /**
     * Update bank_charges table with latest charges from quotation.
     */
    private function updateBankCharges(array $banks): void
    {
        foreach ($banks as $bank) {
            $c = $bank['charges'];
            BankCharge::updateOrCreate(
                ['bank_name' => $bank['name']],
                [
                    'pf' => $c['pfPercent'],
                    'admin' => $c['adminBase'],
                    'stamp_notary' => $c['stamp_notary'],
                    'registration_fee' => $c['registration_fee'],
                    'advocate' => $c['advocate'],
                    'tc' => $c['tc'],
                    'extra1_name' => $c['extra1Name'] ?: null,
                    'extra1_amt' => $c['extra1Amt'],
                    'extra2_name' => $c['extra2Name'] ?: null,
                    'extra2_amt' => $c['extra2Amt'],
                ]
            );
        }
    }
}

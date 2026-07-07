<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerKycDetail;
use App\Models\LoanDetail;

/**
 * Customer identity resolution by PAN + per-loan KYC capture.
 *
 * - The `customers` row is the identity anchor, keyed by PAN. It is created
 *   once per new PAN and NEVER updated afterwards.
 * - Every loan creation/edit records a `customer_kyc_details` snapshot, which
 *   is what the loan displays. Differing details across deals are preserved as
 *   separate KYC rows rather than overwriting the master.
 */
class CustomerService
{
    /**
     * Canonicalise a PAN for matching/storage (uppercase, trimmed).
     */
    public function normalizePan(?string $pan): ?string
    {
        $pan = strtoupper(trim((string) $pan));

        return $pan !== '' ? $pan : null;
    }

    /**
     * Find the master customer for this PAN, creating it once if absent.
     * Never updates an existing master.
     *
     * @param  array{customer_name?:string,mobile?:?string,email?:?string,date_of_birth?:?string,pan_number?:?string}  $kyc
     */
    public function resolveMasterByPan(array $kyc): Customer
    {
        $pan = $this->normalizePan($kyc['pan_number'] ?? null);

        if ($pan !== null) {
            $existing = Customer::whereRaw('UPPER(pan_number) = ?', [$pan])->first();
            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'customer_name' => $kyc['customer_name'] ?? '',
            'mobile' => $kyc['mobile'] ?? null,
            'email' => $kyc['email'] ?? null,
            'date_of_birth' => $kyc['date_of_birth'] ?? null,
            'pan_number' => $pan,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Write a KYC snapshot for a loan against its master customer.
     *
     * @param  array{customer_name?:string,mobile?:?string,email?:?string,date_of_birth?:?string,pan_number?:?string}  $kyc
     * @param  array{loan_id?:?int,quotation_id?:?int,source?:string}  $context
     */
    public function recordKyc(Customer $master, array $kyc, array $context = []): CustomerKycDetail
    {
        return CustomerKycDetail::create([
            'customer_id' => $master->id,
            'loan_id' => $context['loan_id'] ?? null,
            'quotation_id' => $context['quotation_id'] ?? null,
            'customer_name' => $kyc['customer_name'] ?? $master->customer_name,
            'mobile' => $kyc['mobile'] ?? null,
            'email' => $kyc['email'] ?? null,
            'date_of_birth' => $kyc['date_of_birth'] ?? null,
            'pan_number' => $this->normalizePan($kyc['pan_number'] ?? null),
            'source' => $context['source'] ?? 'conversion',
            'captured_by' => auth()->id(),
        ]);
    }

    /**
     * Resolve the master and record a KYC snapshot in one call.
     *
     * @param  array<string,mixed>  $kyc
     * @param  array<string,mixed>  $context
     */
    public function captureForLoan(array $kyc, array $context = []): CustomerKycDetail
    {
        $master = $this->resolveMasterByPan($kyc);

        return $this->recordKyc($master, $kyc, $context);
    }

    /**
     * Reconcile a loan's KYC after an edit. If the PAN is unchanged, the loan's
     * existing snapshot is updated in place; if the PAN changed (or there is no
     * snapshot yet), the master is re-resolved/created and a fresh snapshot is
     * linked. The master is never updated.
     *
     * @param  array<string,mixed>  $kyc
     */
    public function syncLoanKyc(LoanDetail $loan, array $kyc): CustomerKycDetail
    {
        $newPan = $this->normalizePan($kyc['pan_number'] ?? null);
        $existing = $loan->customerKycDetails;

        if ($existing && $this->normalizePan($existing->pan_number) === $newPan) {
            $existing->update([
                'customer_name' => $kyc['customer_name'] ?? $existing->customer_name,
                'mobile' => $kyc['mobile'] ?? null,
                'email' => $kyc['email'] ?? null,
                'date_of_birth' => $kyc['date_of_birth'] ?? null,
                'pan_number' => $newPan,
            ]);

            return $existing;
        }

        $master = $this->resolveMasterByPan($kyc);
        $kycRow = $this->recordKyc($master, $kyc, ['loan_id' => $loan->id, 'source' => 'edit']);
        $loan->update(['customer_id' => $master->id, 'customer_kyc_details_id' => $kycRow->id]);

        return $kycRow;
    }

    /**
     * Latest KYC snapshot recorded for a PAN (for autofill lookups), or null.
     */
    public function latestKycForPan(?string $pan): ?CustomerKycDetail
    {
        $pan = $this->normalizePan($pan);
        if ($pan === null) {
            return null;
        }

        return CustomerKycDetail::whereRaw('UPPER(pan_number) = ?', [$pan])
            ->latest('id')
            ->first();
    }
}

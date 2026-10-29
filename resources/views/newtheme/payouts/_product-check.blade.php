{{-- Reconcile product cell: our product, then the bank's product (Excel `product`
     column) with a check badge. A mismatch offers one-click "Change to X".
     Expects: $ourName, $check (PayoutController::productCheck), $loanId, $loanNumber,
     $canChange, $dbLine. --}}
<div>{{ $ourName ?: '—' }}</div>
@if (($check['status'] ?? 'blank') !== 'blank')
    <div style="{{ $dbLine }}">Bank: {{ $check['excel'] }}</div>
    @switch($check['status'])
        @case('ok')
            <span class="shf-badge shf-badge-green shf-text-2xs">✓ Product</span>
            @break
        @case('mismatch')
            <span class="shf-badge shf-badge-orange shf-text-2xs">⚠ Product mismatch</span>
            @if ($canChange && $loanId)
                <div style="margin-top:4px;">
                    <button type="button" class="btn sm rc-change-product"
                        data-url="{{ route('payouts.reconcile.change-product', $loanId) }}"
                        data-product-id="{{ $check['target_id'] }}"
                        data-product-name="{{ $check['target_name'] }}"
                        data-our-name="{{ $ourName }}"
                        data-loan-number="{{ $loanNumber }}">Change to {{ $check['target_name'] }}</button>
                </div>
            @endif
            @break
        @default
            <span class="shf-badge shf-badge-gray shf-text-2xs"
                title="Not a product of this bank — add it under Settings → Products → Bank names / aliases">? Unknown product</span>
    @endswitch
@endif

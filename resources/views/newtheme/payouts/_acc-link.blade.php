{{-- Loan A/c number(s) or loan # linking to the loan's Stages page (new tab, so the
     reconcile / payout-run result stays open). Expects: $accounts, $loanId; optional
     $isLoanNumber (true = render in the brand orange via .po-loan-no). --}}
@php $loanNoClass = !empty($isLoanNumber) ? 'po-loan-no' : ''; @endphp
@if (!empty($accounts) && !empty($loanId))
    <a href="{{ route('loans.stages', $loanId) }}" target="_blank" rel="noopener" class="{{ $loanNoClass }}"
        title="Open loan stages">{{ implode(', ', $accounts) }}</a>
@elseif (!empty($accounts))
    <span class="{{ $loanNoClass }}">{{ implode(', ', $accounts) }}</span>
@else
    —
@endif

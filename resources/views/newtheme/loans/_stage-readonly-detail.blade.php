{{--
    Read-only stage financial detail — VIEW ONLY.
    Rendered with $assignment (StageAssignment) and $loan (LoanDetail) in scope.
    Reproduces the owner-gated "saved data" figures from _stages-body.blade.php so
    non-assignee viewers can see the same numbers. Contains NO forms, inputs, buttons,
    edit/action links, or interactive controls of any kind.
--}}
@php $roNotes = $assignment->getNotesData(); @endphp
@switch($assignment->stage_key)
    @case('rate_pf')
        <div class="mt-2 border-top pt-2 shf-stage-saved-data">
            <div class="row g-2">
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Interest Rate:</span>
                        <strong>{{ $roNotes['interest_rate'] ?? '—' }}%</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Rate Offered Date:</span>
                        <strong>{{ $roNotes['rate_offered_date'] ?? '—' }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Valid Until:</span>
                        <strong>{{ $roNotes['rate_valid_until'] ?? '—' }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Bank Reference:</span>
                        <strong>{{ $roNotes['bank_reference'] ?? '—' }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Repo Rate:</span>
                        <strong>{{ $roNotes['repo_rate'] ?? '—' }}%</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Bank Margin:</span>
                        <strong>{{ $roNotes['bank_rate'] ?? '—' }}%</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Processing Fee:</span>
                        <strong>{{ $roNotes['processing_fee'] ?? '0' }}{{ ($roNotes['processing_fee_type'] ?? 'percent') === 'percent' ? '%' : '' }}</strong>
                        <small class="text-muted">({{ ($roNotes['processing_fee_type'] ?? 'percent') === 'percent' ? '% of loan' : 'Fixed' }})</small>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">PF Amount:</span>
                        <strong>₹ {{ number_format((float) ($roNotes['processing_fee_amount'] ?? 0)) }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">GST ({{ $roNotes['gst_percent'] ?? '18' }}%):</span>
                        <strong>₹ {{ number_format((float) ($roNotes['pf_gst_amount'] ?? 0)) }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Total PF:</span>
                        <strong>₹ {{ number_format((float) ($roNotes['total_pf'] ?? 0)) }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Admin Charges:</span>
                        <strong>₹ {{ number_format((float) ($roNotes['admin_charges'] ?? 0)) }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Admin GST ({{ $roNotes['admin_charges_gst_percent'] ?? '18' }}%):</span>
                        <strong>₹ {{ number_format((float) ($roNotes['admin_charges_gst_amount'] ?? 0)) }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Total Admin:</span>
                        <strong>₹ {{ number_format((float) ($roNotes['total_admin_charges'] ?? 0)) }}</strong>
                    </div>
                </div>
                @if (!empty($roNotes['special_conditions']))
                    <div class="col-12">
                        <div class="small"><span class="text-muted">Special Conditions:</span>
                            {{ $roNotes['special_conditions'] }}</div>
                    </div>
                @endif
                @if (!empty($roNotes['stageRemarks']))
                    <div class="col-12">
                        <div class="small text-muted">{{ $roNotes['stageRemarks'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @break

    @case('sanction')
        <div class="mt-2 border-top pt-2 shf-stage-saved-data">
            <div class="row g-2">
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Sanction Date:</span>
                        <strong>{{ $roNotes['sanction_date'] ?? '—' }}</strong>
                    </div>
                </div>
                @if (!empty($roNotes['conditions']))
                    <div class="col-12">
                        <div class="small"><span class="text-muted">Conditions:</span>
                            {{ $roNotes['conditions'] }}</div>
                    </div>
                @endif
                @if (!empty($roNotes['stageRemarks']))
                    <div class="col-12">
                        <div class="small text-muted">{{ $roNotes['stageRemarks'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @break

    @case('docket')
        @php
            $docketNotes = $roNotes;

            // Calculate expected docket date from sanction_date + app_number offset
            $appNumberAssignment = $loan
                ->stageAssignments()
                ->where('stage_key', 'app_number')
                ->first();
            $appNotes = $appNumberAssignment ? $appNumberAssignment->getNotesData() : [];
            $sanctionAssignment = $loan
                ->stageAssignments()
                ->where('stage_key', 'sanction')
                ->first();
            $sanctionNotesDocket = $sanctionAssignment ? $sanctionAssignment->getNotesData() : [];
            $expectedDocketDate = null;
            $expectedDocketCarbon = null;
            $docketOffset = $appNotes['docket_days_offset'] ?? null;

            if (
                $docketOffset &&
                $docketOffset !== '0' &&
                !empty($sanctionNotesDocket['sanction_date'])
            ) {
                $expectedDocketCarbon = \Carbon\Carbon::createFromFormat(
                    'd/m/Y',
                    $sanctionNotesDocket['sanction_date'],
                )->addDays((int) $docketOffset);
                $expectedDocketDate = $expectedDocketCarbon->format('d/m/Y');
            } elseif ($docketOffset === '0' && !empty($appNotes['custom_docket_date'])) {
                $expectedDocketCarbon = \Carbon\Carbon::createFromFormat(
                    'd/m/Y',
                    $appNotes['custom_docket_date'],
                );
                $expectedDocketDate = $appNotes['custom_docket_date'];
            }

            $docketDaysInfo = '';
            if ($expectedDocketCarbon) {
                $diffDays = now()
                    ->startOfDay()
                    ->diffInDays($expectedDocketCarbon->startOfDay(), false);
                if ($diffDays > 0) {
                    $docketDaysInfo =
                        '<span class="text-success fw-semibold">' .
                        $diffDays .
                        ' day' .
                        ($diffDays > 1 ? 's' : '') .
                        ' remaining</span>';
                } elseif ($diffDays === 0) {
                    $docketDaysInfo = '<span class="text-warning fw-semibold">Due today</span>';
                } else {
                    $docketDaysInfo =
                        '<span class="text-danger fw-semibold">' .
                        abs($diffDays) .
                        ' day' .
                        (abs($diffDays) > 1 ? 's' : '') .
                        ' overdue</span>';
                }
            }
        @endphp

        {{-- Expected docket date banner --}}
        @if ($expectedDocketDate)
            <div
                class="alert {{ $expectedDocketCarbon && $expectedDocketCarbon->isPast() ? 'alert-danger' : 'alert-info' }} py-2 mt-2 mb-2 shf-text-sm">
                <strong>Expected Docket Date:</strong> {{ $expectedDocketDate }}
                @if ($docketOffset && $docketOffset !== '0')
                    <small class="text-muted">(Sanction + {{ $docketOffset }}d)</small>
                @endif
                — {!! $docketDaysInfo !!}
            </div>
        @endif

        {{-- Completed docket financials --}}
        <div class="mt-2 border-top pt-2 shf-stage-saved-data">
            <div class="row g-2">
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Login Date:</span>
                        <strong>{{ $docketNotes['login_date'] ?? '—' }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Sanctioned Amount:</span>
                        <strong>₹ {{ $docketNotes['sanctioned_amount'] ?? '—' }}</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Sanctioned Rate:</span>
                        <strong>{{ $docketNotes['sanctioned_rate'] ?? '—' }}%</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">Tenure:</span>
                        <strong>{{ $docketNotes['tenure_months'] ?? '—' }} months</strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small"><span class="text-muted">EMI Amount:</span>
                        <strong>₹ {{ $docketNotes['emi_amount'] ?? '—' }}</strong>
                    </div>
                </div>
                @if (!empty($docketNotes['stageRemarks']))
                    <div class="col-12">
                        <div class="small text-muted">{{ $docketNotes['stageRemarks'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @break

    @case('disbursement')
        @php
            $disbData = $loan->disbursement;
            $disbEntries = $disbData?->entryList() ?? [];
        @endphp
        @if ($disbData)
            <div class="mt-2 border-top pt-2 shf-stage-saved-data">
                @if (!empty($disbEntries))
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-hover mb-0 shf-text-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Product</th>
                                    <th>Loan A/c No.</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($disbEntries as $entry)
                                    <tr>
                                        <td>{{ !empty($entry['disbursement_date']) ? \Carbon\Carbon::parse($entry['disbursement_date'])->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>{{ ($entry['method'] ?? '') === 'cheque' ? 'Cheque' : 'Fund Transfer' }}
                                        </td>
                                        <td>{{ $entry['product_name'] ?? '—' }}</td>
                                        <td>{{ $entry['loan_account_number'] ?? '—' }}</td>
                                        <td class="text-end">₹ {{ number_format($entry['amount'] ?? 0) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-end">₹ {{ number_format($disbData->entryTotal()) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
                @if ($disbData->notes)
                    <div class="small"><span class="text-muted">Notes:</span>
                        {{ $disbData->notes }}</div>
                @endif
            </div>
        @endif
    @break
@endswitch

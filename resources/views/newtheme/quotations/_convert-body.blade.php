<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5 shf-max-w-lg">

        <form method="POST" action="{{ route('quotations.convert.store', $quotation) }}">
            @csrf

            {{-- Section 1: Quotation Summary --}}
            <div class="shf-section mb-4">
                <div class="shf-section-header">
                    <div class="shf-section-number">1</div>
                    <span class="shf-section-title">Quotation Summary</span>
                </div>
                <div class="shf-section-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Customer</small>
                            <strong>{{ $quotation->customer_name }}</strong>
                            <span class="shf-badge shf-badge-gray ms-1 shf-text-2xs">{{ $quotation->getTypeLabel() }}</span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Loan Amount</small>
                            <strong>{{ $quotation->formatted_amount }}</strong>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Documents</small>
                            {{ $quotation->documents->count() }} required documents
                        </div>
                        <div class="col-sm-6">
                            <small class="text-muted d-block">Banks Compared</small>
                            {{ $quotation->banks->pluck('bank_name')->implode(', ') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 2: Select Bank --}}
            <div class="shf-section mb-4">
                <div class="shf-section-header">
                    <div class="shf-section-number">2</div>
                    <span class="shf-section-title">Select Bank for Loan</span>
                </div>
                <div class="shf-section-body">
                    @foreach($quotation->banks as $index => $bank)
                        @php $matchedBankId = $bankNameToId[$bank->bank_name] ?? null; @endphp
                        <div class="form-check p-3 border rounded mb-2 {{ $index === 0 ? 'border-primary' : '' }}" id="bankCard{{ $index }}">
                            <input class="form-check-input" type="radio" name="bank_index"
                                   value="{{ $index }}" id="bank{{ $index }}"
                                   data-bank-id="{{ $matchedBankId }}"
                                   {{ old('bank_index', 0) == $index ? 'checked' : '' }}>
                            <label class="form-check-label w-100" for="bank{{ $index }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ $bank->bank_name }}</strong>
                                    <span class="text-muted shf-text-sm">
                                        ROI: {{ $bank->roi_min }}% - {{ $bank->roi_max }}%
                                    </span>
                                </div>
                                @if($bank->total_charges)
                                    <small class="text-muted">Total Charges: ₹ {{ number_format($bank->total_charges) }}</small>
                                @endif
                            </label>
                        </div>
                    @endforeach
                    @error('bank_index')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Section 3: Additional Details --}}
            <div class="shf-section mb-4">
                <div class="shf-section-header">
                    <div class="shf-section-number">3</div>
                    <span class="shf-section-title">Additional Details</span>
                </div>
                <div class="shf-section-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="shf-form-label">Branch</label>
                            @if($lockedBranch)
                                <input type="hidden" name="branch_id" value="{{ $lockedBranch->id }}">
                                <div class="shf-input w-100" style="background: var(--light); cursor: default;">
                                    {{ $lockedBranch->name }}
                                    @if($lockedBranch->location)
                                        <small class="text-muted">({{ $lockedBranch->location->name }})</small>
                                    @endif
                                </div>
                                <small class="text-muted">Set during quotation creation</small>
                            @else
                                <input type="hidden" name="branch_id" value="{{ $defaultBranchId }}">
                                <div class="shf-input w-100" style="background: var(--light); cursor: default;">
                                    {{ \App\Models\Branch::find($defaultBranchId)?->name ?? '—' }}
                                </div>
                                <small class="text-muted">Using your default branch</small>
                            @endif
                        </div>

                        <div class="col-sm-6">
                            <label class="shf-form-label">Product <span class="text-danger">*</span></label>
                            <select name="product_id" class="shf-input w-100" id="productSelect" required>
                                <option value="">-- Select Product --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-bank-id="{{ $product->bank_id }}"
                                            {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-sm-6">
                            <label class="shf-form-label">Customer Phone <span class="text-danger">*</span></label>
                            <input type="text" name="customer_phone" class="shf-input w-100" required
                                   value="{{ old('customer_phone') }}" placeholder="+91 99999 99999" maxlength="20">
                            @error('customer_phone')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-sm-6">
                            <label class="shf-form-label">Customer Email</label>
                            <input type="email" name="customer_email" class="shf-input w-100"
                                   value="{{ old('customer_email') }}" placeholder="customer@example.com">
                            @error('customer_email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-sm-6">
                            <label class="shf-form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="text" name="date_of_birth" class="shf-input w-100 shf-datepicker" required
                                   value="{{ old('date_of_birth') }}" placeholder="dd/mm/yyyy" autocomplete="off">
                            @error('date_of_birth')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-sm-6">
                            <label class="shf-form-label">PAN Card Number <span class="text-danger">*</span></label>
                            <input type="text" name="pan_number" class="shf-input w-100" required
                                   value="{{ old('pan_number') }}" placeholder="ABCDE1234F" maxlength="10"
                                   style="text-transform: uppercase;">
                            @error('pan_number')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="shf-form-label">Assigned Advisor <span class="text-danger">*</span></label>
                            <select name="assigned_advisor" class="shf-input w-100" required>
                                <option value="">-- Select Advisor --</option>
                                @foreach($advisors as $advisor)
                                    <option value="{{ $advisor->id }}" {{ old('assigned_advisor', $defaultAdvisorId) == $advisor->id ? 'selected' : '' }}>
                                        {{ $advisor->name }} ({{ $advisor->workflow_role_label }})
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_advisor')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="shf-form-label">Notes</label>
                            <textarea name="notes" class="shf-input w-100" rows="3"
                                      placeholder="Additional notes...">{{ old('notes', $quotation->additional_notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="shf-form-actions d-flex justify-content-end gap-3 mb-4">
                <a href="{{ route('quotations.show', $quotation) }}" class="btn-accent-outline"><svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Cancel</a>
                <button type="submit" class="btn-accent fw-semibold" style="padding:10px 24px">
                    <svg style="width:16px;height:16px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    Convert to Loan Task
                </button>
            </div>
        </form>
    </div>
</div>

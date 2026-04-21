<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5">

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#banksTab">Banks & Products</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#branchesTab">Branches</a></li>
        </ul>

        <div class="tab-content">
            {{-- Banks Tab --}}
            <div class="tab-pane fade show active" id="banksTab">
                @foreach($banks as $bank)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>{{ $bank->name }}</strong>
                                    @if($bank->code) <small class="text-muted">({{ $bank->code }})</small> @endif
                                </div>
                                <button class="btn-accent-sm shf-btn-danger-alt shf-delete-bank" data-id="{{ $bank->id }}"><svg class="shf-btn-icon shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Delete</button>
                            </div>
                            @if($bank->products->isNotEmpty())
                                <div class="ms-3">
                                    <small class="text-muted">Products:</small>
                                    @foreach($bank->products as $product)
                                        <span class="shf-badge shf-badge-gray me-1 shf-text-2xs">
                                            {{ $product->name }}
                                            <a href="{{ route('settings.workflow.product-stages', $product) }}" class="text-decoration-none ms-1" title="Configure stages">⚙</a>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Add Bank --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6>Add Bank</h6>
                        <form method="POST" action="{{ route('settings.workflow.banks.store') }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-5"><input type="text" name="name" class="form-control form-control-sm" placeholder="Bank name" required></div>
                                <div class="col-sm-3"><input type="text" name="code" class="form-control form-control-sm" placeholder="Code (optional)"></div>
                                <div class="col-sm-2"><button type="submit" class="btn-accent-outline btn-accent-sm w-100"><svg class="shf-btn-icon shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Save</button></div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Add Product --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6>Add Product</h6>
                        <form method="POST" action="{{ route('settings.workflow.products.store') }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-4">
                                    <select name="bank_id" class="form-select form-select-sm" required>
                                        <option value="">Bank...</option>
                                        @foreach($banks as $b) <option value="{{ $b->id }}">{{ $b->name }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="Product name" required></div>
                                <div class="col-sm-2"><button type="submit" class="btn-accent-outline btn-accent-sm w-100"><svg class="shf-btn-icon shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Save</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Branches Tab --}}
            <div class="tab-pane fade" id="branchesTab">
                @foreach($branches as $branch)
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $branch->name }}</strong>
                                @if($branch->code) <small class="text-muted">({{ $branch->code }})</small> @endif
                                @if($branch->city) <small class="text-muted ms-2">{{ $branch->city }}</small> @endif
                            </div>
                            <button class="btn-accent-sm shf-btn-danger-alt shf-delete-branch" data-id="{{ $branch->id }}"><svg class="shf-btn-icon shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Delete</button>
                        </div>
                    </div>
                @endforeach

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body">
                        <h6>Add Branch</h6>
                        <form method="POST" action="{{ route('settings.workflow.branches.store') }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                                <div class="col-sm-2"><input type="text" name="code" class="form-control form-control-sm" placeholder="Code"></div>
                                <div class="col-sm-2"><input type="text" name="city" class="form-control form-control-sm" placeholder="City"></div>
                                <div class="col-sm-3"><input type="text" name="phone" class="form-control form-control-sm" placeholder="Phone"></div>
                                <div class="col-sm-2"><button type="submit" class="btn-accent-outline btn-accent-sm w-100"><svg class="shf-btn-icon shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Save</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

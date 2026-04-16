# Stage D: Document Collection Workflow

## Overview

Creates the document tracking system for loans. Documents are NOT uploaded — only their receipt status is tracked (received/pending). Documents come from either the quotation's document list (conversion) or config defaults (direct creation).

## Dependencies

- Stage A (foundation)
- Stage B (loan_details, LoanConversionService)
- Stage C (LoanController, loan views)

---

## Migration: `create_loan_documents_table`

**File**: `database/migrations/xxxx_xx_xx_create_loan_documents_table.php`

**Table**: `loan_documents`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete |
| document_name_en | string | no | | English name |
| document_name_gu | string | yes | null | Gujarati name |
| is_required | boolean | no | true | mandatory vs optional |
| status | string | no | 'pending' | values: pending, received, rejected, waived |
| received_date | date | yes | null | when status changed to received |
| received_by | FK → users | yes | null | set null on delete — who marked received |
| rejected_reason | text | yes | null | reason for rejection (when status = rejected) |
| notes | text | yes | null | any notes about this specific document |
| sort_order | integer | no | 0 | display ordering |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**:
- index on `loan_id`
- index on `is_received`
- composite index on `(loan_id, is_received)` — for progress queries

---

## Model: LoanDocument

**File**: `app/Models/LoanDocument.php`

**Table**: `loan_documents`

**Fillable**: `loan_id`, `document_name_en`, `document_name_gu`, `is_required`, `status`, `received_date`, `received_by`, `rejected_reason`, `notes`, `sort_order`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_required` | boolean |
| `received_date` | date |
| `sort_order` | integer |

**Status Constants**:
```php
const STATUS_PENDING = 'pending';
const STATUS_RECEIVED = 'received';
const STATUS_REJECTED = 'rejected';
const STATUS_WAIVED = 'waived';

const STATUSES = ['pending', 'received', 'rejected', 'waived'];

const STATUS_LABELS = [
    'pending' => ['label' => 'Pending', 'color' => 'secondary'],
    'received' => ['label' => 'Received', 'color' => 'success'],
    'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
    'waived' => ['label' => 'Waived', 'color' => 'warning'],
];
```

**Helper Methods**:
```php
public function isReceived(): bool { return $this->status === self::STATUS_RECEIVED; }
public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
public function isWaived(): bool { return $this->status === self::STATUS_WAIVED; }
public function isResolved(): bool { return in_array($this->status, ['received', 'waived']); }
```

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `receivedByUser()` | BelongsTo | User | `received_by` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeRequired($q)` | `$q->where('is_required', true)` |
| `scopeReceived($q)` | `$q->where('status', 'received')` |
| `scopePending($q)` | `$q->where('status', 'pending')` |
| `scopeRejected($q)` | `$q->where('status', 'rejected')` |
| `scopeWaived($q)` | `$q->where('status', 'waived')` |
| `scopeResolved($q)` | `$q->whereIn('status', ['received', 'waived'])` |
| `scopeUnresolved($q)` | `$q->whereIn('status', ['pending', 'rejected'])` |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getDisplayNameAttribute()` | string | Returns `document_name_en` (primary display) |
| `getBilingualNameAttribute()` | string | Returns `name_en / name_gu` if gu exists, else just en |

---

### LoanDetail Model Additions

**File**: `app/Models/LoanDetail.php` (modify)

**New Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `documents()` | HasMany | LoanDocument | `loan_id` |

**New Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getDocumentProgressAttribute()` | array | `['total' => X, 'received' => Y, 'pending' => Z, 'percentage' => N.N]` |

---

## Service: LoanDocumentService

**File**: `app/Services/LoanDocumentService.php`

### Constructor

```php
public function __construct(
    private ConfigService $configService,
) {}
```

### Methods

#### `populateFromQuotation(LoanDetail $loan, Quotation $quotation): void`

**Purpose**: Copy document checklist from quotation to loan.

**Logic**:
```php
public function populateFromQuotation(LoanDetail $loan, Quotation $quotation): void
{
    $quotation->loadMissing('documents');
    $order = 0;

    foreach ($quotation->documents as $doc) {
        LoanDocument::create([
            'loan_id' => $loan->id,
            'document_name_en' => $doc->document_name_en,
            'document_name_gu' => $doc->document_name_gu,
            'is_required' => true,
            'is_received' => false,
            'sort_order' => $order++,
        ]);
    }
}
```

**Called from**: `LoanConversionService::convertFromQuotation()` (update Stage B to call this)

---

#### `populateFromDefaults(LoanDetail $loan): void`

**Purpose**: Populate documents from `config/app-defaults.php` based on customer type.

**Logic**:
```php
public function populateFromDefaults(LoanDetail $loan): void
{
    $config = $this->configService->load();
    $type = $loan->customer_type;

    $docsEn = $config['documents_en'][$type] ?? [];
    $docsGu = $config['documents_gu'][$type] ?? [];

    foreach ($docsEn as $i => $nameEn) {
        LoanDocument::create([
            'loan_id' => $loan->id,
            'document_name_en' => $nameEn,
            'document_name_gu' => $docsGu[$i] ?? null,
            'is_required' => true,
            'is_received' => false,
            'sort_order' => $i,
        ]);
    }
}
```

**Called from**: `LoanConversionService::createDirectLoan()` (update Stage B to call this)

**Config source** (`config/app-defaults.php`):
- `documents_en.proprietor` → 10 documents
- `documents_en.partnership_llp` → 12 documents
- `documents_en.pvt_ltd` → 14 documents
- `documents_en.salaried` → 10 documents

---

#### `updateStatus(LoanDocument $document, string $status, int $userId, ?string $rejectedReason = null): void`

**Purpose**: Change document status. Handles all transitions: pending→received, pending→rejected, pending→waived, rejected→received, etc.

```php
public function updateStatus(LoanDocument $document, string $status, int $userId, ?string $rejectedReason = null): void
{
    $updateData = ['status' => $status];

    if ($status === 'received') {
        $updateData['received_date'] = now()->toDateString();
        $updateData['received_by'] = $userId;
        $updateData['rejected_reason'] = null;
    } elseif ($status === 'rejected') {
        $updateData['rejected_reason'] = $rejectedReason;
        $updateData['received_date'] = null;
        $updateData['received_by'] = null;
    } elseif ($status === 'waived') {
        $updateData['received_date'] = null;
        $updateData['received_by'] = null;
        $updateData['rejected_reason'] = null;
    } elseif ($status === 'pending') {
        $updateData['received_date'] = null;
        $updateData['received_by'] = null;
        $updateData['rejected_reason'] = null;
    }

    $document->update($updateData);

    ActivityLog::log('update_document_status', $document, [
        'document_name' => $document->document_name_en,
        'loan_number' => $document->loan->loan_number,
        'new_status' => $status,
    ]);
}
```

---

#### `getProgress(LoanDetail $loan): array`

```php
public function getProgress(LoanDetail $loan): array
{
    $total = $loan->documents()->required()->count();
    $resolved = $loan->documents()->required()->resolved()->count(); // received + waived
    $received = $loan->documents()->required()->received()->count();
    $rejected = $loan->documents()->required()->rejected()->count();
    $pending = $loan->documents()->required()->pending()->count();
    $waived = $loan->documents()->required()->waived()->count();
    $percentage = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

    return compact('total', 'resolved', 'received', 'rejected', 'pending', 'waived', 'percentage');
}
```

---

#### `addDocument(LoanDetail $loan, string $nameEn, ?string $nameGu, bool $required = true): LoanDocument`

```php
public function addDocument(LoanDetail $loan, string $nameEn, ?string $nameGu, bool $required = true): LoanDocument
{
    $maxOrder = $loan->documents()->max('sort_order') ?? -1;

    return LoanDocument::create([
        'loan_id' => $loan->id,
        'document_name_en' => trim($nameEn),
        'document_name_gu' => $nameGu ? trim($nameGu) : null,
        'is_required' => $required,
        'is_received' => false,
        'sort_order' => $maxOrder + 1,
    ]);
}
```

---

#### `removeDocument(LoanDocument $document): void`

```php
public function removeDocument(LoanDocument $document): void
{
    ActivityLog::log('remove_loan_document', $document, [
        'document_name' => $document->document_name_en,
        'loan_number' => $document->loan->loan_number,
    ]);

    $document->delete();
}
```

---

#### `allRequiredResolved(LoanDetail $loan): bool`

```php
public function allRequiredResolved(LoanDetail $loan): bool
{
    // All required docs must be received or waived (not pending or rejected)
    return $loan->documents()->required()->unresolved()->count() === 0;
}
```

**Purpose**: Used by Stage E workflow engine to determine if document collection stage can be completed. A document is "resolved" if its status is `received` or `waived`. Documents with status `pending` or `rejected` block completion.

---

## Controller: LoanDocumentController

**File**: `app/Http/Controllers/LoanDocumentController.php`

### Constructor

```php
public function __construct(
    private LoanDocumentService $documentService,
) {}
```

### Actions

#### `index(LoanDetail $loan)` — `GET /loans/{loan}/documents`

**Permission**: `view_loans`

**Logic**:
```php
public function index(LoanDetail $loan)
{
    $this->authorizeView($loan);

    $documents = $loan->documents()->orderBy('sort_order')->get();
    $progress = $this->documentService->getProgress($loan);

    return view('loans.documents', compact('loan', 'documents', 'progress'));
}
```

---

#### `toggle(Request $request, LoanDetail $loan, LoanDocument $document)` — `POST /loans/{loan}/documents/{document}/toggle`

**Permission**: `manage_loan_documents`

**Logic** (AJAX):
```php
public function toggle(Request $request, LoanDetail $loan, LoanDocument $document): JsonResponse
{
    // Ensure document belongs to this loan
    abort_unless($document->loan_id === $loan->id, 404);

    if ($document->is_received) {
        $this->documentService->markNotReceived($document);
    } else {
        $this->documentService->markReceived($document, auth()->id());
    }

    $document->refresh();
    $progress = $this->documentService->getProgress($loan);

    return response()->json([
        'success' => true,
        'document' => [
            'id' => $document->id,
            'is_received' => $document->is_received,
            'received_date' => $document->received_date?->format('d M Y'),
            'received_by' => $document->receivedByUser?->name,
        ],
        'progress' => $progress,
    ]);
}
```

---

#### `store(Request $request, LoanDetail $loan)` — `POST /loans/{loan}/documents`

**Permission**: `manage_loan_documents`

**Validation**:
```php
$validated = $request->validate([
    'document_name_en' => 'required|string|max:255',
    'document_name_gu' => 'nullable|string|max:255',
    'is_required' => 'boolean',
]);
```

**Logic** (AJAX):
```php
public function store(Request $request, LoanDetail $loan): JsonResponse
{
    $validated = $request->validate([...]);

    $document = $this->documentService->addDocument(
        $loan,
        $validated['document_name_en'],
        $validated['document_name_gu'] ?? null,
        $validated['is_required'] ?? true,
    );

    $progress = $this->documentService->getProgress($loan);

    return response()->json([
        'success' => true,
        'document' => $document,
        'progress' => $progress,
    ]);
}
```

---

#### `destroy(LoanDetail $loan, LoanDocument $document)` — `DELETE /loans/{loan}/documents/{document}`

**Permission**: `manage_loan_documents`

**Logic** (AJAX):
```php
public function destroy(LoanDetail $loan, LoanDocument $document): JsonResponse
{
    abort_unless($document->loan_id === $loan->id, 404);

    $this->documentService->removeDocument($document);
    $progress = $this->documentService->getProgress($loan);

    return response()->json([
        'success' => true,
        'progress' => $progress,
    ]);
}
```

---

## Routes

**File**: `routes/web.php` (add to existing loan group)

```php
// Document collection
Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('permission:view_loans')->group(function () {
        Route::get('/loans/{loan}/documents', [LoanDocumentController::class, 'index'])
            ->name('loans.documents');
    });

    Route::middleware('permission:manage_loan_documents')->group(function () {
        Route::post('/loans/{loan}/documents/{document}/toggle', [LoanDocumentController::class, 'toggle'])
            ->name('loans.documents.toggle');
        Route::post('/loans/{loan}/documents', [LoanDocumentController::class, 'store'])
            ->name('loans.documents.store');
        Route::delete('/loans/{loan}/documents/{document}', [LoanDocumentController::class, 'destroy'])
            ->name('loans.documents.destroy');
    });
});
```

---

## View: `resources/views/loans/documents.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌───────────────────────────────────────────────────────┐
│ Documents — Loan #SHF-202604-0001                     │
│ ← Back to Loan                                        │
├───────────────────────────────────────────────────────┤
│ Progress Bar:                                          │
│ ████████████░░░░░░░░ 7/10 (70%)                      │
│ 7 received · 3 pending                                │
├───────────────────────────────────────────────────────┤
│ Document List:                                         │
│ ┌───────────────────────────────────────────────┐     │
│ │ ☑ PAN Card of Proprietor                       │     │
│ │   માલિકનું PAN કાર્ડ                           │     │
│ │   Required · Received 05 Apr 2026 by Admin     │     │
│ │   [Notes: ...]                    [× Remove]   │     │
│ ├───────────────────────────���───────────────────┤     │
│ │ ☐ Bank Statement (12 months)                   │     │
│ │   બેંક સ્ટેટમેન્ટ (૧૨ મહિના)                   │     │
│ │   Required · Pending                           │     │
│ │   [Notes: ...]                    [× Remove]   │     │
│ ├───────────────────────────────────────────────┤     │
│ │ ... more documents ...                         │     │
│ └───────────────────────────────────────────────┘     │
├───────────────────────────────────────────────────────┤
│ Add Custom Document:                                   │
│ Name (EN): [________________]                          │
│ Name (GU): [________________] (optional)               │
│ Required: [✓]                                         │
│ [+ Add Document]                                       │
└───────────────────────────────────────────────────────┘
```

**CSS classes**:
- `.shf-doc-item` — individual document row/card
- `.shf-doc-toggle` — the checkbox toggle (custom styled switch)
- `.shf-doc-received` — green left border when received
- `.shf-doc-pending` — gray left border when pending
- `.shf-doc-progress` — the progress bar container

**JS behavior** (in `@push('scripts')` or `shf-loans.js`):

```javascript
// Toggle document received status
$(document).on('click', '.shf-doc-toggle', function() {
    const $toggle = $(this);
    const url = $toggle.data('url'); // /loans/{id}/documents/{docId}/toggle
    const $item = $toggle.closest('.shf-doc-item');

    $.post(url, { _token: csrfToken })
        .done(function(response) {
            if (response.success) {
                // Update checkbox visual
                if (response.document.is_received) {
                    $item.addClass('shf-doc-received').removeClass('shf-doc-pending');
                    $toggle.prop('checked', true);
                    $item.find('.shf-doc-status').html(
                        'Received ' + response.document.received_date + ' by ' + response.document.received_by
                    );
                } else {
                    $item.removeClass('shf-doc-received').addClass('shf-doc-pending');
                    $toggle.prop('checked', false);
                    $item.find('.shf-doc-status').html('Pending');
                }
                // Update progress bar
                updateDocProgressBar(response.progress);
            }
        });
});

// Add custom document
$('#addDocForm').on('submit', function(e) {
    e.preventDefault();
    $.post($(this).attr('action'), $(this).serialize())
        .done(function(response) {
            if (response.success) {
                // Append new document to list
                appendDocumentRow(response.document);
                updateDocProgressBar(response.progress);
                $('#addDocForm')[0].reset();
            }
        });
});
```

---

## Update LoanConversionService (Stage B Integration)

After Stage D is implemented, update `LoanConversionService`:

### In `convertFromQuotation()`:
```php
// After creating loan, before returning:
app(LoanDocumentService::class)->populateFromQuotation($loan, $quotation);
```

### In `createDirectLoan()`:
```php
// After creating loan, before returning:
app(LoanDocumentService::class)->populateFromDefaults($loan);
```

---

## Update Loan Show View (Stage C Integration)

In `resources/views/loans/show.blade.php`, add documents summary section:

```blade
{{-- Section: Documents --}}
<div class="shf-section">
    <div class="shf-section-header">
        <h6>Documents</h6>
        <a href="{{ route('loans.documents', $loan) }}" class="btn btn-sm btn-outline-primary">
            View All
        </a>
    </div>
    <div class="shf-section-body">
        @php $progress = app(\App\Services\LoanDocumentService::class)->getProgress($loan); @endphp
        <div class="progress mb-2" style="height: 8px;">
            <div class="progress-bar bg-success" style="width: {{ $progress['percentage'] }}%"></div>
        </div>
        <small class="text-muted">
            {{ $progress['received'] }}/{{ $progress['total'] }} documents collected ({{ $progress['percentage'] }}%)
        </small>
    </div>
</div>
```

---

## Permissions

**Add to `config/permissions.php`** under `'Loans'` group:

```php
['slug' => 'manage_loan_documents', 'name' => 'Manage Loan Documents', 'description' => 'Mark documents as received/pending, add/remove documents'],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `manage_loan_documents` | yes | yes |

---

## Verification

```bash
php artisan migrate                              # loan_documents table
php artisan db:seed --class=PermissionSeeder     # manage_loan_documents permission
php artisan serve

# Test flow:
# 1. Convert a quotation to loan → documents auto-populated from quotation
# 2. Create a direct loan → documents populated from config defaults
# 3. Go to /loans/{id}/documents → see document list with progress bar
# 4. Toggle a document → AJAX updates status, progress bar recalculates
# 5. Toggle back (undo) → reverts to pending
# 6. Add a custom document → appears in list
# 7. Remove a document → removed from list, progress recalculates
# 8. All required docs received → 100% progress
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_loan_documents_table.php` |
| Create | `app/Models/LoanDocument.php` |
| Create | `app/Services/LoanDocumentService.php` |
| Create | `app/Http/Controllers/LoanDocumentController.php` |
| Create | `resources/views/loans/documents.blade.php` |
| Modify | `app/Models/LoanDetail.php` (add documents relationship, accessor) |
| Modify | `app/Services/LoanConversionService.php` (call document population) |
| Modify | `resources/views/loans/show.blade.php` (add documents summary section) |
| Modify | `config/permissions.php` (add manage_loan_documents) |
| Modify | `routes/web.php` (add document routes) |

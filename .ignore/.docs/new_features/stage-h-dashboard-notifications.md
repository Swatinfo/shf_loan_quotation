# Stage H: Dashboard, Notifications, Remarks

## Overview

Adds the remarks system (one-way notes per loan/stage), in-app notification system with bell icon, and loan-related dashboard enhancements.

## Dependencies

- Stage C (LoanController, loan views)
- Stage E (stage_assignments)

---

## Migrations

### Migration 1: `create_remarks_table`

**File**: `database/migrations/xxxx_xx_xx_create_remarks_table.php`

**Table**: `remarks`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete |
| stage_key | string | yes | null | null = general remark, else refs stages.stage_key |
| user_id | FK → users | no | | cascade on delete |
| remark | text | no | | the remark text |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index on `loan_id`; index on `stage_key`; index on `user_id`; index on `created_at`

**Design note**: Remarks are one-way notes (not a query/reply system). Users add remarks, others can read them. No editing or deleting — immutable audit trail of communication.

---

### Migration 2: `create_notifications_table`

**File**: `database/migrations/xxxx_xx_xx_create_notifications_table.php`

**Table**: `notifications`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| user_id | FK → users | no | | cascade on delete |
| title | string | no | | short notification title |
| message | text | no | | notification body |
| type | string | no | 'info' | values: info, success, warning, error, stage_update, assignment |
| is_read | boolean | no | false | |
| loan_id | FK → loan_details | yes | null | set null on delete — related loan |
| stage_key | string | yes | null | related stage |
| link | string | yes | null | URL to navigate to on click |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index on `user_id`; index on `is_read`; index on `loan_id`; composite index on `(user_id, is_read)` — for unread count queries; index on `created_at`

---

## Models

### Remark

**File**: `app/Models/Remark.php`

**Table**: `remarks`

**Fillable**: `loan_id`, `stage_key`, `user_id`, `remark`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `user()` | BelongsTo | User | `user_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeForStage($q, $key)` | `$q->where('stage_key', $key)` |
| `scopeGeneral($q)` | `$q->whereNull('stage_key')` |
| `scopeRecent($q, $limit = 10)` | `$q->latest()->limit($limit)` |

---

### Notification (App-level, NOT Laravel's built-in)

**File**: `app/Models/Notification.php`

**Table**: `notifications`

**Fillable**: `user_id`, `title`, `message`, `type`, `is_read`, `loan_id`, `stage_key`, `link`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_read` | boolean |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `user()` | BelongsTo | User | `user_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeUnread($q)` | `$q->where('is_read', false)` |
| `scopeForUser($q, $userId)` | `$q->where('user_id', $userId)` |
| `scopeRecent($q, $limit = 50)` | `$q->latest()->limit($limit)` |

**Type Constants**:
```php
const TYPE_INFO = 'info';
const TYPE_SUCCESS = 'success';
const TYPE_WARNING = 'warning';
const TYPE_ERROR = 'error';
const TYPE_STAGE_UPDATE = 'stage_update';
const TYPE_ASSIGNMENT = 'assignment';

const TYPE_ICONS = [
    'info' => 'bi-info-circle',
    'success' => 'bi-check-circle',
    'warning' => 'bi-exclamation-triangle',
    'error' => 'bi-x-circle',
    'stage_update' => 'bi-arrow-right-circle',
    'assignment' => 'bi-person-plus',
];

const TYPE_COLORS = [
    'info' => 'primary',
    'success' => 'success',
    'warning' => 'warning',
    'error' => 'danger',
    'stage_update' => 'info',
    'assignment' => 'primary',
];
```

---

### LoanDetail Model Additions

**Add Relationship**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `remarks()` | HasMany | Remark | `loan_id` |

### User Model Additions

**Add Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `notifications()` | HasMany | Notification | `user_id` |
| `unreadNotifications()` | HasMany | Notification | `user_id` + scope unread |

```php
public function unreadNotifications()
{
    return $this->hasMany(Notification::class)->unread();
}
```

---

## Service: NotificationService

**File**: `app/Services/NotificationService.php`

### Methods

#### `notify(int $userId, string $title, string $message, string $type = 'info', ?int $loanId = null, ?string $stageKey = null, ?string $link = null): Notification`

```php
public function notify(int $userId, string $title, string $message, string $type = 'info', ?int $loanId = null, ?string $stageKey = null, ?string $link = null): Notification
{
    return Notification::create([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'loan_id' => $loanId,
        'stage_key' => $stageKey,
        'link' => $link,
    ]);
}
```

---

#### `notifyStageAssignment(LoanDetail $loan, string $stageKey, int $assignedUserId): Notification`

```php
public function notifyStageAssignment(LoanDetail $loan, string $stageKey, int $assignedUserId): Notification
{
    $stageName = Stage::where('stage_key', $stageKey)->value('stage_name_en') ?? $stageKey;

    return $this->notify(
        $assignedUserId,
        'Stage Assigned',
        "You have been assigned to '{$stageName}' for Loan #{$loan->loan_number} ({$loan->customer_name})",
        'assignment',
        $loan->id,
        $stageKey,
        route('loans.stages', $loan),
    );
}
```

---

#### `notifyStageCompleted(LoanDetail $loan, string $stageKey): void`

Notifies the loan creator and assigned advisor:

```php
public function notifyStageCompleted(LoanDetail $loan, string $stageKey): void
{
    $stageName = Stage::where('stage_key', $stageKey)->value('stage_name_en') ?? $stageKey;
    $message = "Stage '{$stageName}' completed for Loan #{$loan->loan_number}";
    $link = route('loans.stages', $loan);

    $notifyUsers = collect([$loan->created_by, $loan->assigned_advisor])
        ->filter()
        ->unique()
        ->reject(fn($id) => $id === auth()->id()); // Don't notify self

    foreach ($notifyUsers as $userId) {
        $this->notify($userId, 'Stage Completed', $message, 'stage_update', $loan->id, $stageKey, $link);
    }
}
```

---

#### `notifyLoanCompleted(LoanDetail $loan): void`

```php
public function notifyLoanCompleted(LoanDetail $loan): void
{
    $message = "Loan #{$loan->loan_number} ({$loan->customer_name}) has been completed!";
    $link = route('loans.show', $loan);

    $notifyUsers = collect([$loan->created_by, $loan->assigned_advisor])
        ->filter()->unique()->reject(fn($id) => $id === auth()->id());

    foreach ($notifyUsers as $userId) {
        $this->notify($userId, 'Loan Completed', $message, 'success', $loan->id, null, $link);
    }
}
```

---

#### `markRead(Notification $notification): void`

```php
public function markRead(Notification $notification): void
{
    $notification->update(['is_read' => true]);
}
```

#### `markAllRead(int $userId): void`

```php
public function markAllRead(int $userId): void
{
    Notification::forUser($userId)->unread()->update(['is_read' => true]);
}
```

#### `getUnreadCount(int $userId): int`

```php
public function getUnreadCount(int $userId): int
{
    return Notification::forUser($userId)->unread()->count();
}
```

---

### Integration with LoanStageService

In `LoanStageService`, after key actions, fire notifications:

```php
// In assignStage():
app(NotificationService::class)->notifyStageAssignment($loan, $stageKey, $userId);

// In updateStageStatus() when status = 'completed':
app(NotificationService::class)->notifyStageCompleted($loan, $stageKey);

// In handleStageCompletion() when disbursement completes:
app(NotificationService::class)->notifyLoanCompleted($loan);
```

---

## Service: RemarkService

**File**: `app/Services/RemarkService.php`

### Methods

#### `addRemark(int $loanId, int $userId, string $remark, ?string $stageKey = null): Remark`

```php
public function addRemark(int $loanId, int $userId, string $remark, ?string $stageKey = null): Remark
{
    $remarkModel = Remark::create([
        'loan_id' => $loanId,
        'user_id' => $userId,
        'remark' => trim($remark),
        'stage_key' => $stageKey,
    ]);

    $loan = LoanDetail::find($loanId);
    ActivityLog::log('add_remark', $remarkModel, [
        'loan_number' => $loan?->loan_number,
        'stage_key' => $stageKey,
        'preview' => \Str::limit($remark, 100),
    ]);

    return $remarkModel;
}
```

#### `getRemarks(int $loanId, ?string $stageKey = null): Collection`

```php
public function getRemarks(int $loanId, ?string $stageKey = null): Collection
{
    $query = Remark::where('loan_id', $loanId)->with('user')->latest();

    if ($stageKey !== null) {
        $query->where(function ($q) use ($stageKey) {
            $q->where('stage_key', $stageKey)->orWhereNull('stage_key');
        });
    }

    return $query->get();
}
```

---

## Controllers

### LoanRemarkController

**File**: `app/Http/Controllers/LoanRemarkController.php`

#### `index(LoanDetail $loan)` — `GET /loans/{loan}/remarks`

**Permission**: `view_loans`

```php
public function index(Request $request, LoanDetail $loan): JsonResponse
{
    $stageKey = $request->get('stage_key');
    $remarks = app(RemarkService::class)->getRemarks($loan->id, $stageKey);

    return response()->json([
        'remarks' => $remarks->map(fn($r) => [
            'id' => $r->id,
            'remark' => $r->remark,
            'user_name' => $r->user->name,
            'stage_key' => $r->stage_key,
            'created_at' => $r->created_at->diffForHumans(),
            'created_at_full' => $r->created_at->format('d M Y H:i'),
        ]),
    ]);
}
```

#### `store(Request $request, LoanDetail $loan)` — `POST /loans/{loan}/remarks`

**Permission**: `add_remarks`

```php
public function store(Request $request, LoanDetail $loan): JsonResponse
{
    $validated = $request->validate([
        'remark' => 'required|string|max:5000',
        'stage_key' => 'nullable|string|max:50',
    ]);

    $remark = app(RemarkService::class)->addRemark(
        $loan->id,
        auth()->id(),
        $validated['remark'],
        $validated['stage_key'] ?? null,
    );

    return response()->json([
        'success' => true,
        'remark' => [
            'id' => $remark->id,
            'remark' => $remark->remark,
            'user_name' => auth()->user()->name,
            'stage_key' => $remark->stage_key,
            'created_at' => $remark->created_at->diffForHumans(),
        ],
    ]);
}
```

---

### NotificationController

**File**: `app/Http/Controllers/NotificationController.php`

#### `index()` — `GET /notifications`

**Permission**: `auth` (all authenticated users)

```php
public function index()
{
    $notifications = Notification::forUser(auth()->id())
        ->with('loan')
        ->recent(100)
        ->get();

    return view('notifications.index', compact('notifications'));
}
```

#### `unreadCount()` — `GET /api/notifications/count`

**Permission**: `auth`

```php
public function unreadCount(): JsonResponse
{
    $count = app(NotificationService::class)->getUnreadCount(auth()->id());
    return response()->json(['count' => $count]);
}
```

#### `markRead(Notification $notification)` — `POST /notifications/{notification}/read`

**Permission**: `auth`

```php
public function markRead(Notification $notification): JsonResponse
{
    abort_unless($notification->user_id === auth()->id(), 403);
    app(NotificationService::class)->markRead($notification);
    return response()->json(['success' => true]);
}
```

#### `markAllRead()` — `POST /notifications/read-all`

**Permission**: `auth`

```php
public function markAllRead(): JsonResponse
{
    app(NotificationService::class)->markAllRead(auth()->id());
    return response()->json(['success' => true]);
}
```

---

## Routes

```php
// Remarks
Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('permission:view_loans')->group(function () {
        Route::get('/loans/{loan}/remarks', [LoanRemarkController::class, 'index'])
            ->name('loans.remarks.index');
    });
    Route::middleware('permission:add_remarks')->group(function () {
        Route::post('/loans/{loan}/remarks', [LoanRemarkController::class, 'store'])
            ->name('loans.remarks.store');
    });
});

// Notifications
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('/api/notifications/count', [NotificationController::class, 'unreadCount'])
        ->name('api.notifications.count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});
```

---

## Views

### `resources/views/loans/partials/remarks.blade.php`

**Included in**: `loans/show.blade.php`, `loans/stages.blade.php`

```
┌──────────────────────────────────────────┐
│ Remarks                                   │
├──────────────────────────────────────────┤
│ [textarea: Add a remark...]              │
│ Stage: [dropdown: General / specific]     │
│ [Post Remark]                             │
├──────────────────────────────────────────┤
│ Admin User · 2 hours ago                  │
│ Customer submitted all property docs      │
│                                           │
│ Staff User · 1 day ago                    │
│ Called customer, will bring docs tomorrow  │
��� Stage: Document Collection                │
│                                           │
│ ... (loaded via AJAX, newest first)       │
└──────────────────────────────────────────┘
```

**JS**: Remarks load via AJAX `GET /loans/{id}/remarks`, new remarks posted via AJAX `POST /loans/{id}/remarks`.

---

### `resources/views/notifications/index.blade.php`

**Extends**: `layouts.app`

```
┌────────────────────────────────────────────────────┐
│ Notifications                    [Mark All Read]    │
├────────────────────────────────────────────────────┤
│ 🔵 Stage Assigned · 10 min ago                     │
│    You have been assigned to 'Document Collection'  │
│    for Loan #SHF-202604-0001 (Ramesh Patel)        │
│    [→ View]                                         │
├────────────────────────────────────────────────────┤
│ ✅ Stage Completed · 1 hour ago                    │
│    Stage 'Loan Inquiry' completed for #SHF-...     │
│    [→ View]                                         │
├────────────────────────────────────────────────────┤
│ ... more notifications ...                          │
└────────────────────────────────────────────────────┘
```

---

### Navigation: Notification Bell

**Modify**: `resources/views/layouts/navigation.blade.php`

Add notification bell next to user dropdown:

```blade
{{-- Notification Bell --}}
<li class="nav-item">
    <a class="nav-link position-relative" href="{{ route('notifications.index') }}">
        <i class="bi bi-bell"></i>
        <span class="shf-notification-badge badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none"
              id="notificationBadge">0</span>
    </a>
</li>
```

**JS polling** (in `shf-app.js` or layout scripts):
```javascript
// Poll for unread notifications every 60 seconds
function updateNotificationBadge() {
    $.get('/api/notifications/count', function(response) {
        const $badge = $('#notificationBadge');
        if (response.count > 0) {
            $badge.text(response.count > 99 ? '99+' : response.count).removeClass('d-none');
        } else {
            $badge.addClass('d-none');
        }
    });
}
updateNotificationBadge();
setInterval(updateNotificationBadge, 60000);
```

---

### Dashboard Enhancement

**Modify**: `resources/views/dashboard.blade.php`

Add loan stats section below existing quotation stats:

```blade
{{-- Loan Stats (only if user has view_loans permission) --}}
@if(auth()->user()->hasPermission('view_loans'))
<div class="row mt-4">
    <div class="col-12">
        <h5>Loan Tasks</h5>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shf-stat-card">
            <div class="card-body text-center">
                <h3>{{ $loanStats['active'] }}</h3>
                <small class="text-muted">Active Loans</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shf-stat-card">
            <div class="card-body text-center">
                <h3>{{ $loanStats['my_tasks'] }}</h3>
                <small class="text-muted">My Pending Stages</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shf-stat-card">
            <div class="card-body text-center">
                <h3>{{ $loanStats['completed_month'] }}</h3>
                <small class="text-muted">Completed This Month</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shf-stat-card">
            <div class="card-body text-center">
                <h3>{{ $loanStats['total'] }}</h3>
                <small class="text-muted">Total Loans</small>
            </div>
        </div>
    </div>
</div>

{{-- My Tasks Quick List --}}
@if($myTasks->isNotEmpty())
<div class="shf-section mt-3">
    <div class="shf-section-header"><h6>My Pending Stages</h6></div>
    <div class="shf-section-body">
        @foreach($myTasks as $task)
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <a href="{{ route('loans.stages', $task->loan_id) }}">
                        {{ $task->stage?->stage_name_en }}
                    </a>
                    <small class="text-muted ms-2">{{ $task->loan?->loan_number }}</small>
                </div>
                <span class="badge bg-{{ StageAssignment::PRIORITY_LABELS[$task->priority]['color'] }}">
                    {{ $task->priority }}
                </span>
            </div>
        @endforeach
    </div>
</div>
@endif
@endif
```

**DashboardController modifications**:
```php
// In index() method, add:
$loanStats = [];
$myTasks = collect();

if (auth()->user()->hasPermission('view_loans')) {
    $base = LoanDetail::visibleTo(auth()->user());
    $loanStats = [
        'active' => (clone $base)->where('status', 'active')->count(),
        'total' => (clone $base)->count(),
        'completed_month' => (clone $base)->where('status', 'completed')
            ->whereMonth('updated_at', now()->month)->count(),
        'my_tasks' => StageAssignment::where('assigned_to', auth()->id())
            ->whereIn('status', ['pending', 'in_progress'])->count(),
    ];

    $myTasks = StageAssignment::where('assigned_to', auth()->id())
        ->whereIn('status', ['pending', 'in_progress'])
        ->with(['stage', 'loan'])
        ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
        ->limit(10)
        ->get();
}
```

---

## Permissions

**Add to `config/permissions.php`** under `'Loans'` group:

```php
['slug' => 'add_remarks', 'name' => 'Add Remarks', 'description' => 'Add remarks to loan stages'],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `add_remarks` | yes | yes |

---

## Verification

```bash
php artisan migrate    # remarks + notifications tables
php artisan db:seed --class=PermissionSeeder
php artisan serve

# Test remarks:
# 1. Go to loan show page → see remarks section
# 2. Add a remark → appears in list
# 3. Add a stage-specific remark → tagged with stage
# 4. View remarks via AJAX

# Test notifications:
# 1. Assign a user to a stage → notification created for that user
# 2. Complete a stage → notification created for loan creator/advisor
# 3. Check notification bell → shows count
# 4. Click bell → see notification list
# 5. Mark as read → badge updates
# 6. Mark all read → all cleared

# Test dashboard:
# 1. Dashboard shows loan stats cards
# 2. "My Pending Stages" shows assigned tasks
# 3. Click task → goes to loan stages page
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_remarks_table.php` |
| Create | `database/migrations/xxxx_create_notifications_table.php` |
| Create | `app/Models/Remark.php` |
| Create | `app/Models/Notification.php` |
| Create | `app/Services/NotificationService.php` |
| Create | `app/Services/RemarkService.php` |
| Create | `app/Http/Controllers/LoanRemarkController.php` |
| Create | `app/Http/Controllers/NotificationController.php` |
| Create | `resources/views/loans/partials/remarks.blade.php` |
| Create | `resources/views/notifications/index.blade.php` |
| Modify | `app/Models/LoanDetail.php` (add remarks relationship) |
| Modify | `app/Models/User.php` (add notifications relationships) |
| Modify | `app/Services/LoanStageService.php` (fire notifications on assign/complete) |
| Modify | `app/Http/Controllers/DashboardController.php` (add loan stats) |
| Modify | `resources/views/dashboard.blade.php` (add loan stats section) |
| Modify | `resources/views/layouts/navigation.blade.php` (add notification bell) |
| Modify | `resources/views/loans/show.blade.php` (include remarks partial) |
| Modify | `config/permissions.php` (add add_remarks) |
| Modify | `routes/web.php` (add remarks + notification routes) |

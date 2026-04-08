<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanDetail extends Model
{
    use HasAuditColumns, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_COMPLETED = 'completed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_ON_HOLD = 'on_hold';

    const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_ON_HOLD,
    ];

    const STATUS_LABELS = [
        'active' => ['label' => 'Active', 'color' => 'primary'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'secondary'],
        'on_hold' => ['label' => 'On Hold', 'color' => 'warning'],
    ];

    const CUSTOMER_TYPE_LABELS = [
        'proprietor' => 'Proprietor / માલિકી',
        'partnership_llp' => 'Partnership / LLP / ભાગીદારી',
        'pvt_ltd' => 'Pvt. Ltd. / પ્રા. લિ.',
        'salaried' => 'Salaried / પગારદાર',
    ];

    protected $fillable = [
        'loan_number', 'quotation_id', 'branch_id', 'bank_id', 'product_id', 'location_id',
        'customer_name', 'customer_type', 'customer_phone', 'customer_email',
        'loan_amount', 'status', 'current_stage', 'bank_name', 'roi_min', 'roi_max',
        'total_charges', 'application_number', 'assigned_bank_employee',
        'due_date', 'rejected_at', 'rejected_by', 'rejected_stage', 'rejection_reason',
        'created_by', 'assigned_advisor', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'integer',
            'roi_min' => 'decimal:2',
            'roi_max' => 'decimal:2',
            'due_date' => 'date',
            'rejected_at' => 'datetime',
        ];
    }

    // Relationships

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_advisor');
    }

    public function bankEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_bank_employee');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LoanDocument::class, 'loan_id');
    }

    public function stageAssignments(): HasMany
    {
        return $this->hasMany(StageAssignment::class, 'loan_id');
    }

    public function progress(): HasOne
    {
        return $this->hasOne(LoanProgress::class, 'loan_id');
    }

    public function stageTransfers(): HasMany
    {
        return $this->hasMany(StageTransfer::class, 'loan_id');
    }

    public function stageQueries(): HasMany
    {
        return $this->hasMany(StageQuery::class, 'loan_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(Remark::class, 'loan_id');
    }

    public function valuationDetails(): HasMany
    {
        return $this->hasMany(ValuationDetail::class, 'loan_id');
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(DisbursementDetail::class, 'loan_id');
    }

    // Stage helpers

    public function getStageAssignment(string $key): ?StageAssignment
    {
        return $this->stageAssignments()->where('stage_key', $key)->first();
    }

    // Ownership

    public function getCurrentOwnerAttribute(): ?User
    {
        $assignment = $this->stageAssignments()->where('stage_key', $this->current_stage)->first();

        return $assignment?->assignee;
    }

    public function getTimeWithCurrentOwnerAttribute(): string
    {
        $assignment = $this->stageAssignments()->where('stage_key', $this->current_stage)->first();
        if (! $assignment || ! $assignment->assigned_to) {
            return '—';
        }

        $lastTransfer = $assignment->transfers()
            ->where('transferred_to', $assignment->assigned_to)
            ->latest('created_at')
            ->first();

        $since = $lastTransfer?->created_at ?? $assignment->started_at ?? $assignment->created_at;

        if (! $since) {
            return '—';
        }

        $diff = $since->diff(now());
        $parts = [];
        if ($diff->d > 0) {
            $parts[] = $diff->d.'d';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h.'h';
        }
        $parts[] = $diff->i.'m';

        return implode(' ', $parts);
    }

    public function getTotalLoanTimeAttribute(): string
    {
        if (! $this->created_at) {
            return '—';
        }

        $diff = $this->created_at->diff(now());
        $parts = [];
        if ($diff->days > 0) {
            $parts[] = $diff->days . 'd';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . 'h';
        }
        $parts[] = $diff->i . 'm';

        return implode(' ', $parts);
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return '₹ '.$this->formatIndianNumber($this->loan_amount);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['color'] ?? 'secondary';
    }

    public function getCustomerTypeLabelAttribute(): string
    {
        return self::CUSTOMER_TYPE_LABELS[$this->customer_type] ?? $this->customer_type;
    }

    public function getCurrentStageNameAttribute(): string
    {
        return Stage::where('stage_key', $this->current_stage)->value('stage_name_en')
            ?? $this->current_stage;
    }

    // Static Methods

    public static function generateLoanNumber(): string
    {
        $prefix = 'SHF-'.now()->format('Ym').'-';
        $lastLoan = static::where('loan_number', 'like', $prefix.'%')
            ->orderByDesc('loan_number')
            ->first();

        if ($lastLoan) {
            $lastNum = (int) substr($lastLoan->loan_number, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    // Scopes

    public function scopeActive($query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeVisibleTo($query, User $user): void
    {
        // Super admin and admin with view_all_loans see everything
        if ($user->hasPermission('view_all_loans')) {
            return;
        }

        $query->where(function ($q) use ($user) {
            // Own loans (created or assigned as advisor)
            $q->where('created_by', $user->id)
                ->orWhere('assigned_advisor', $user->id)
                // Assigned to a stage
                ->orWhereHas('stageAssignments', fn ($sq) => $sq->where('assigned_to', $user->id));

            // Branch managers can see all loans in their branches
            if ($user->isTaskRole('branch_manager')) {
                $branchIds = $user->branches()->pluck('branches.id')->toArray();
                if ($branchIds) {
                    $q->orWhereIn('branch_id', $branchIds);
                }
            }
        });
    }

    // Helpers

    private function formatIndianNumber($num): string
    {
        $num = (int) $num;
        if ($num < 1000) {
            return (string) $num;
        }

        $lastThree = $num % 1000;
        $remaining = (int) ($num / 1000);
        $result = '';

        if ($remaining > 0) {
            $remainStr = (string) $remaining;
            $len = strlen($remainStr);
            $groups = [];
            $i = $len;
            while ($i > 0) {
                $start = max(0, $i - 2);
                $groups[] = substr($remainStr, $start, $i - $start);
                $i = $start;
            }
            $result = implode(',', array_reverse($groups)).',';
        }

        $result .= str_pad($lastThree, 3, '0', STR_PAD_LEFT);

        return ltrim($result, '0,') ?: '0';
    }
}

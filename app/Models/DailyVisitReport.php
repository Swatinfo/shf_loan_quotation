<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DailyVisitReport extends Model
{
    protected $fillable = [
        'user_id',
        'visit_date',
        'contact_name',
        'contact_phone',
        'contact_type',
        'purpose',
        'notes',
        'outcome',
        'follow_up_needed',
        'follow_up_date',
        'follow_up_notes',
        'is_follow_up_done',
        'parent_visit_id',
        'follow_up_visit_id',
        'quotation_id',
        'loan_id',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'follow_up_date' => 'date',
            'follow_up_needed' => 'boolean',
            'is_follow_up_done' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function parentVisit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_visit_id');
    }

    public function followUpVisit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_visit_id');
    }

    // ── Scopes ──

    /**
     * Scope visits visible to the given user.
     * - view_all_dvr: see everything
     * - BDH/branch_manager: own + branch users' visits
     * - Others: own visits only
     */
    public function scopeVisibleTo($query, User $user): void
    {
        if ($user->hasPermission('view_all_dvr')) {
            return;
        }

        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            if ($user->hasAnyRole(['bdh', 'branch_manager'])) {
                $branchUserIds = DB::table('user_branches')
                    ->whereIn('branch_id', $user->branches()->pluck('branches.id'))
                    ->pluck('user_id')
                    ->unique()
                    ->toArray();

                if ($branchUserIds) {
                    $q->orWhereIn('user_id', $branchUserIds);
                }
            }
        });
    }

    public function scopePendingFollowUps($query): void
    {
        $query->where('follow_up_needed', true)
            ->where('is_follow_up_done', false);
    }

    public function scopeOverdueFollowUps($query): void
    {
        $query->where('follow_up_needed', true)
            ->where('is_follow_up_done', false)
            ->where('follow_up_date', '<', today());
    }

    // ── Authorization helpers ──

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasPermission('view_all_dvr')) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($user->hasAnyRole(['bdh', 'branch_manager'])) {
            $branchUserIds = DB::table('user_branches')
                ->whereIn('branch_id', $user->branches()->pluck('branches.id'))
                ->pluck('user_id')
                ->unique()
                ->toArray();

            return in_array($this->user_id, $branchUserIds);
        }

        return false;
    }

    public function isEditableBy(User $user): bool
    {
        if ($user->hasPermission('edit_dvr') && $this->user_id === $user->id) {
            return true;
        }

        return $user->isSuperAdmin();
    }

    public function isDeletableBy(User $user): bool
    {
        return $user->hasPermission('delete_dvr');
    }

    // ── Accessors ──

    public function getIsOverdueFollowUpAttribute(): bool
    {
        return $this->follow_up_needed
            && ! $this->is_follow_up_done
            && $this->follow_up_date
            && $this->follow_up_date->isPast();
    }

    /**
     * Get the full visit chain (all linked visits from root to leaf).
     */
    public function getVisitChain(): \Illuminate\Support\Collection
    {
        $chain = collect();

        // Walk back to root
        $root = $this;
        while ($root->parent_visit_id) {
            $root = self::find($root->parent_visit_id);
            if (! $root) {
                break;
            }
        }

        // Walk forward from root
        $current = $root;
        while ($current) {
            $chain->push($current);
            if ($current->follow_up_visit_id) {
                $current = self::find($current->follow_up_visit_id);
            } else {
                break;
            }
        }

        return $chain;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Impersonate;

    // Task workflow roles (separate from system role)
    const TASK_ROLES = ['branch_manager', 'loan_advisor', 'bank_employee', 'office_employee', 'legal_advisor'];

    const TASK_ROLE_LABELS = [
        'branch_manager' => 'Branch Manager',
        'loan_advisor' => 'Loan Advisor',
        'bank_employee' => 'Bank Employee',
        'office_employee' => 'Office Employee',
        'legal_advisor' => 'Legal Advisor',
    ];

    const TASK_ROLE_LABELS_GU = [
        'branch_manager' => 'બ્રાન્ચ મેનેજર',
        'loan_advisor' => 'લોન સલાહકાર',
        'bank_employee' => 'બેંક કર્મચારી',
        'office_employee' => 'ઓફિસ કર્મચારી',
        'legal_advisor' => 'કાનૂની સલાહકાર',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'created_by', 'phone',
        'task_role', 'employee_id', 'default_branch_id', 'task_bank_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot('is_default_office_employee');
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function taskBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'task_bank_id');
    }

    public function employerBanks(): BelongsToMany
    {
        return $this->belongsToMany(Bank::class, 'bank_employees')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user')->withTimestamps();
    }

    // System role helpers
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function hasPermission(string $slug): bool
    {
        return app(\App\Services\PermissionService::class)->userHasPermission($this, $slug);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'staff' => 'Staff',
            default => ucfirst($this->role),
        };
    }

    // Task role helpers

    public function hasTaskRole(): bool
    {
        return $this->task_role !== null;
    }

    public function isTaskRole(string $role): bool
    {
        return $this->task_role === $role;
    }

    public function isBankEmployee(): bool
    {
        return $this->task_role === 'bank_employee';
    }

    public function isLoanAdvisor(): bool
    {
        return $this->task_role === 'loan_advisor';
    }

    public function isLegalAdvisor(): bool
    {
        return $this->task_role === 'legal_advisor';
    }

    public function getTaskRoleLabelAttribute(): string
    {
        return self::TASK_ROLE_LABELS[$this->task_role] ?? '';
    }

    public function getTaskRoleLabelGuAttribute(): string
    {
        return self::TASK_ROLE_LABELS_GU[$this->task_role] ?? '';
    }

    // Impersonation

    public function canImpersonate(): bool
    {
        if (config('app.allow_impersonate_all')) {
            return true;
        }

        return $this->isSuperAdmin();
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRolePermission extends Model
{
    protected $fillable = ['task_role', 'permission_id'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}

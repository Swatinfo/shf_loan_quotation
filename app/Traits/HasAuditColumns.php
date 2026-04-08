<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

/**
 * Auto-fills updated_by on save and deleted_by on soft delete.
 * Add this trait to any model that has these columns.
 */
trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model): void {
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = $model->updated_by ?? auth()->id();
            }
        });

        static::updating(function ($model): void {
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model): void {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                return;
            }

            if (auth()->check() && Schema::hasColumn($model->getTable(), 'deleted_by')) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }
}

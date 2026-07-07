<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryResponse extends Model
{
    public $timestamps = false;

    protected $fillable = ['stage_query_id', 'response_text', 'responded_by'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function stageQuery(): BelongsTo
    {
        return $this->belongsTo(StageQuery::class, 'stage_query_id');
    }

    public function respondedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}

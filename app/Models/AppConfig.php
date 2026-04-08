<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table = 'app_config';

    protected $fillable = ['config_key', 'config_json'];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }
}

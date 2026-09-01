<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotFlow extends Model
{
    protected $fillable = [
        'name',
        'flow_json',
        'is_active',
    ];

    protected $casts = [
        'flow_json' => 'array',
        'is_active' => 'boolean',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConnection extends Model
{
    protected $fillable = [
        'status',
        'phone',
        'display_name',
        'last_connected_at',
        'last_disconnected_at',
        'last_error',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'last_disconnected_at' => 'datetime',
    ];
}

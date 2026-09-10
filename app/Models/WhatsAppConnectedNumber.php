<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppConnectedNumber extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'role',
        'is_active',
        'notify_new_leads',
        'last_notified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_new_leads' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_phone',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNotifyLeads(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('notify_new_leads', true);
    }

    /**
     * Get clean formatted representation of phone number with country code.
     */
    public function getFormattedPhoneAttribute(): string
    {
        $raw = preg_replace('/[^0-9]/', '', (string)$this->phone);
        if (empty($raw)) {
            return $this->phone;
        }

        // Indian 12-digit format (91 98765 43210)
        if (strlen($raw) === 12 && str_starts_with($raw, '91')) {
            return '+91 ' . substr($raw, 2, 5) . ' ' . substr($raw, 7, 5);
        }

        // Indian standard 10-digit format
        if (strlen($raw) === 10) {
            return '+91 ' . substr($raw, 0, 5) . ' ' . substr($raw, 5, 5);
        }

        return '+' . $raw;
    }
}

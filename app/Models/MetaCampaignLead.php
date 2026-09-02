<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MetaCampaignLead extends Model
{
    protected $fillable = [
        'campaign_id',
        'meta_lead_id',
        'name',
        'phone',
        'raw_phone',
        'email',
        'city',
        'platform',
        'lead_status',
        'email_status',
        'email_sent_at',
        'email_error',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_error',
        'custom_fields',
        'raw_data',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'raw_data' => 'array',
        'email_sent_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MetaAutomationLog::class, 'lead_id');
    }

    /**
     * Resolve a variable value from the lead's core attributes or custom fields.
     */
    public function getVariableValue(string $key): string
    {
        $normalizedKey = strtolower(trim($key));
        
        // Direct core attributes mapping
        if (in_array($normalizedKey, ['name', 'full_name', 'client_name', 'lead_name'])) {
            return (string) ($this->name ?? '');
        }
        if (in_array($normalizedKey, ['phone', 'mobile', 'contact_number', 'phone_number'])) {
            return (string) ($this->phone ?? $this->raw_phone ?? '');
        }
        if ($normalizedKey === 'email') {
            return (string) ($this->email ?? '');
        }
        if ($normalizedKey === 'city') {
            return (string) ($this->city ?? '');
        }
        if ($normalizedKey === 'platform') {
            return (string) ($this->platform ?? '');
        }
        if ($normalizedKey === 'lead_status') {
            return (string) ($this->lead_status ?? '');
        }
        if ($normalizedKey === 'meta_lead_id' || $normalizedKey === 'lead_id') {
            return (string) ($this->meta_lead_id ?? '');
        }

        // Search custom fields (exact or normalized match)
        $custom = $this->custom_fields ?? [];
        if (isset($custom[$key])) {
            return (string) $custom[$key];
        }

        // Case-insensitive / slug-matched lookup in custom fields
        $keySlug = preg_replace('/[^a-z0-9]/', '', $normalizedKey);
        foreach ($custom as $cKey => $cVal) {
            $cSlug = preg_replace('/[^a-z0-9]/', '', strtolower($cKey));
            if ($cSlug === $keySlug) {
                return (string) $cVal;
            }
        }

        // Also check raw_data if available
        $rawData = $this->raw_data ?? [];
        if (isset($rawData[$key])) {
            return (string) $rawData[$key];
        }

        return '';
    }

    /**
     * Scope for searching leads by name, phone, email, or city.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%")
              ->orWhere('meta_lead_id', 'like', "%{$term}%");
        });
    }
}

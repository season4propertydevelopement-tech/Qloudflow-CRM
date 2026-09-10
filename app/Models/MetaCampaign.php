<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MetaCampaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'sheet_url',
        'status',
        'total_leads',
        'emails_sent',
        'emails_failed',
        'whatsapp_sent',
        'whatsapp_failed',
        'headers',
        'auto_welcome_enabled',
        'auto_welcome_whatsapp',
        'auto_welcome_whatsapp_template_id',
        'auto_welcome_whatsapp_message',
        'auto_welcome_whatsapp_media_url',
        'auto_welcome_email',
        'auto_welcome_email_template_id',
        'auto_welcome_email_subject',
        'auto_welcome_email_body',
        'webhook_token',
    ];

    protected $casts = [
        'headers' => 'array',
        'total_leads' => 'integer',
        'emails_sent' => 'integer',
        'emails_failed' => 'integer',
        'whatsapp_sent' => 'integer',
        'whatsapp_failed' => 'integer',
        'auto_welcome_enabled' => 'boolean',
        'auto_welcome_whatsapp' => 'boolean',
        'auto_welcome_email' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($campaign) {
            if (empty($campaign->webhook_token)) {
                $campaign->webhook_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function ensureWebhookToken(): string
    {
        if (empty($this->webhook_token)) {
            $this->webhook_token = bin2hex(random_bytes(16));
            $this->saveQuietly();
        }
        return $this->webhook_token;
    }

    public function whatsappWelcomeTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MetaTemplate::class, 'auto_welcome_whatsapp_template_id');
    }

    public function emailWelcomeTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MetaTemplate::class, 'auto_welcome_email_template_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(MetaCampaignLead::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MetaAutomationLog::class, 'campaign_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Recompute and save current statistics based on leads table.
     */
    public function recalculateStats(): void
    {
        $this->total_leads = $this->leads()->count();
        $this->emails_sent = $this->leads()->where('email_status', 'sent')->count();
        $this->emails_failed = $this->leads()->where('email_status', 'failed')->count();
        $this->whatsapp_sent = $this->leads()->where('whatsapp_status', 'sent')->count();
        $this->whatsapp_failed = $this->leads()->where('whatsapp_status', 'failed')->count();
        $this->save();
    }

    /**
     * Return all dynamic variables available for this campaign derived from the uploaded Google Sheet.
     */
    public function getAvailableVariables(): array
    {
        $base = ['full_name', 'phone', 'email', 'city'];
        $excluded = [
            'id', 'ad_id', 'ad_name', 'adset_id', 'adset_name',
            'campaign_id', 'campaign_name', 'form_id', 'form_name',
            'is_organic', 'inbox_url', 'created_time', 'lead_status',
            'raw_phone', 'whatsapp_status', 'email_status', 'whatsapp_error',
            'email_error', 'whatsapp_sent_at', 'email_sent_at', 'created_at', 'updated_at'
        ];

        $sheetHeaders = $this->headers ?? [];
        $custom = [];
        foreach ($sheetHeaders as $h) {
            $trimmed = trim($h);
            $lower = strtolower($trimmed);
            if (!in_array($lower, $excluded) && !in_array($lower, $base) && !empty($trimmed)) {
                $custom[] = $trimmed;
            }
        }

        // Fallback: inspect leads' custom_fields if headers list is empty
        if (empty($custom) && $this->leads()->exists()) {
            $sampleLead = $this->leads()->whereNotNull('custom_fields')->first();
            if ($sampleLead && is_array($sampleLead->custom_fields)) {
                foreach (array_keys($sampleLead->custom_fields) as $key) {
                    if (!in_array(strtolower($key), $excluded) && !in_array(strtolower($key), $base)) {
                        $custom[] = $key;
                    }
                }
            }
        }

        return array_values(array_unique(array_merge($base, $custom)));
    }
}

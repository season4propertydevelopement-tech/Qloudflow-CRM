<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAutomationLog extends Model
{
    protected $fillable = [
        'campaign_id',
        'lead_id',
        'channel',
        'recipient',
        'subject',
        'message_preview',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'campaign_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MetaCampaignLead::class, 'lead_id');
    }
}

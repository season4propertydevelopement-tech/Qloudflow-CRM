<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'caller_name',
        'caller_phone',
        'persona_id',
        'persona_name',
        'duration',
        'status',
        'call_summary',
        'lead_score',
        'lead_stage',
        'sentiment',
        'key_entities',
        'whatsapp_followup_message',
        'transcript',
        'model_used',
        'latency_ms',
    ];

    protected $casts = [
        'key_entities' => 'array',
        'transcript' => 'array',
        'lead_score' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}

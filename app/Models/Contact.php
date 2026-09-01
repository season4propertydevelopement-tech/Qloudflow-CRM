<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Contact extends Model
{
    protected $attributes = [
        'chatbot_enabled' => true,
        'human_handoff' => false,
        'lead_status' => 'cold',
        'lead_score' => 10,
    ];

    protected $fillable = [
        'name',
        'phone',
        'whatsapp_id',
        'profile_image_url',
        'first_message_at',
        'last_message_at',
        'opt_in_status',
        'notes',
        'tags',
        'lead_status',
        'lead_score',
        'chatbot_enabled',
        'assigned_user_id',
        'human_handoff',
        'handoff_reason',
        'current_node_id',
    ];

    protected $casts = [
        'first_message_at' => 'datetime',
        'last_message_at' => 'datetime',
        'opt_in_status' => 'boolean',
        'chatbot_enabled' => 'boolean',
        'human_handoff' => 'boolean',
        'lead_score' => 'integer',
    ];

    protected $appends = [
        'formatted_phone',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // Scopes
    public function scopeHot(Builder $query): Builder
    {
        return $query->where('lead_status', 'hot');
    }

    public function scopeCold(Builder $query): Builder
    {
        return $query->where('lead_status', 'cold');
    }

    public function scopeWarm(Builder $query): Builder
    {
        return $query->where('lead_status', 'warm');
    }

    /**
     * Get clean formatted phone number representation.
     */
    public function getFormattedPhoneAttribute(): string
    {
        $raw = preg_replace('/[^0-9]/', '', (string)$this->phone);
        if (empty($raw)) {
            return 'WhatsApp User';
        }

        // Indian 12-digit format (91 98765 43210)
        if (strlen($raw) === 12 && str_starts_with($raw, '91')) {
            return '+91 ' . substr($raw, 2, 5) . ' ' . substr($raw, 7, 5);
        }

        // Indian standard 10-digit format
        if (strlen($raw) === 10) {
            return '+91 ' . substr($raw, 0, 5) . ' ' . substr($raw, 5, 5);
        }

        // US / International 11-digit format (1 555 123 4567)
        if (strlen($raw) === 11 && str_starts_with($raw, '1')) {
            return '+1 ' . substr($raw, 1, 3) . ' ' . substr($raw, 4, 3) . ' ' . substr($raw, 7, 4);
        }

        // Show complete full number without masking
        return '+' . $raw;
    }

    /**
     * Evaluate lead after conversation has accumulated messages (at least 5 messages)
     */
    public function evaluateLeadFromConversationHistory(?Conversation $conversation = null): void
    {
        $conv = $conversation ?? $this->conversations()->latest()->first();
        if (!$conv) {
            return;
        }

        $messages = $conv->messages()->orderBy('created_at', 'asc')->get();
        $totalCount = $messages->count();

        // At least 5 conversation messages required for full qualification
        if ($totalCount < 5) {
            // Early intent trigger for immediate hot signals (e.g. asking for price or quote immediately)
            $incoming = $messages->where('direction', 'incoming')->pluck('message')->implode(' ');
            if ($this->hasHotKeywords(strtolower($incoming))) {
                $this->lead_status = 'hot';
                $this->lead_score = 85;
                $this->save();
            }
            return;
        }

        // Instant Real-Time Heuristic Scoring (Sub-millisecond execution)

        // Comprehensive NLP & Intent Analysis over cumulative text
        $incomingMessages = $messages->where('direction', 'incoming')->pluck('message')->toArray();
        $allClientText = strtolower(implode(' ', $incomingMessages));

        $hotSignals = [
            'price', 'pricing', 'cost', 'quote', 'quotation', 'rate', 'rates',
            '1bhk', '2bhk', '1 bhk', '2 bhk', '3bhk', 'flat', 'apartment', 'residence',
            'buy', 'purchase', 'booking', 'book', 'schedule', 'site visit', 'visit',
            'call me', 'talk to', 'contact number', 'phone number', 'meet',
            'budget', 'down payment', 'emi', 'loan', 'how much', 'eoi', 'priority'
        ];

        $warmSignals = [
            'naigaon', 'dahisar', 'bhayandar',
            'property', 'real estate', 'rera', 'amenities', 'tower', 'project', 'location',
            'possession', 'carpet area', 'sqft', 'sq.ft', 'details', 'information', 'about',
            'sample flat', 'brochure', 'floor plan', 'raj kumar', 'dubey', 'sai krupa', 'office'
        ];

        $hotHits = 0;
        foreach ($hotSignals as $word) {
            if (str_contains($allClientText, $word)) {
                $hotHits++;
            }
        }

        $warmHits = 0;
        foreach ($warmSignals as $word) {
            if (str_contains($allClientText, $word)) {
                $warmHits++;
            }
        }

        $clientMsgCount = count($incomingMessages);

        if ($hotHits >= 1) {
            $this->lead_status = 'hot';
            $this->lead_score = min(98, 80 + ($hotHits * 5));
            $this->notes = "Lead Qualified as HOT based on property purchase intent in {$clientMsgCount} messages.";
        } elseif ($warmHits >= 1) {
            $this->lead_status = 'warm';
            $this->lead_score = min(75, 50 + ($warmHits * 5));
            $this->notes = "Lead Qualified as WARM based on property inquiry in {$clientMsgCount} messages.";
        } else {
            $this->lead_status = 'cold';
            $this->lead_score = 15;
            $this->notes = "Lead Classified as COLD (Casual/Inactive after {$totalCount} interactions).";
        }

        $this->save();
    }

    /**
     * Check if text contains strong hot keywords
     */
    protected function hasHotKeywords(string $text): bool
    {
        $hotKeywords = [
            'price', 'pricing', 'cost', 'quote', 'quotation', 'rate',
            '1bhk', '2bhk', 'flat', 'buy', 'purchase', 'booking', 'book',
            'site visit', 'visit', 'call me', 'talk to', 'naigaon', 'how much'
        ];

        foreach ($hotKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }
}

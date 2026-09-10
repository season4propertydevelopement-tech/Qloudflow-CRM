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
     * Comprehensive Lead Qualification: Evaluates conversation history, bot stage,
     * numeric choices (1, 2, 3, 4, etc.), and NLP text keywords.
     */
    public function evaluateLeadFromConversationHistory(?Conversation $conversation = null): void
    {
        $conv = $conversation ?? $this->conversations()->latest()->first();
        if (!$conv) {
            return;
        }

        $messages = $conv->messages()->orderBy('created_at', 'asc')->get();
        $totalCount = $messages->count();

        if ($totalCount === 0) {
            return;
        }

        $incomingMessages = $messages->where('direction', 'incoming')->pluck('message')->toArray();
        $outgoingMessages = $messages->where('direction', 'outgoing')->pluck('message')->toArray();
        $allClientText = strtolower(implode(' ', $incomingMessages));
        $allBotText = implode(' ', $outgoingMessages);
        $clientMsgCount = count($incomingMessages);

        // 1. VIP Site Visit Booked / Confirmed Detection (Highest Hot Intent)
        $hasScheduledVisit = str_contains($allBotText, 'VIP Site Visit Scheduled')
            || str_contains($allBotText, 'Site Visit Scheduled')
            || str_contains($allBotText, 'Visit Confirmed')
            || ($this->current_node_id === 'site_visit_node' && in_array($messages->last()?->message, ['1', '2', '3']));

        if ($hasScheduledVisit) {
            $this->lead_status = 'hot';
            $this->lead_score = 98;
            $this->notes = "Lead Qualified as HOT (VIP Site Visit Scheduled via WhatsApp Bot).";
            $this->save();
            return;
        }

        // 2. Human Advisor Handoff Detection
        if ($this->human_handoff || $this->current_node_id === 'human_node' || str_contains($allBotText, 'Raj Kumar Dubey')) {
            $this->lead_status = 'hot';
            $this->lead_score = 95;
            $this->notes = "Lead Qualified as HOT (Requested Senior Sales Advisor / Raj Kumar Dubey).";
            $this->save();
            return;
        }

        // 3. High-Intent Node Stages (2 BHK, 1 BHK, Payment Plans, Furniture Package, Site Visit Menu)
        $hotNodes = [
            'site_visit_node',
            '2bhk_node',
            '1bhk_node',
            '2bhk_large_node',
            'payment_plans_node',
            'furniture_offer_node',
        ];

        if (in_array($this->current_node_id, $hotNodes)) {
            $this->lead_status = 'hot';
            $this->lead_score = max(85, (int)$this->lead_score);
            $this->notes = "Lead Qualified as HOT (Actively exploring pricing / configurations / site visits in bot flow).";
            $this->save();
            return;
        }

        // 4. Numeric Menu Intent Evaluation (Customer using 1, 2, 3, 4, 5)
        $hasSelectedSiteVisit = false;
        $hasSelectedHumanAgent = false;
        $hasSelectedPropertyOption = false;
        $hasSelectedExploreOption = false;

        foreach ($incomingMessages as $rawMsg) {
            $cleaned = trim($rawMsg);
            if ($cleaned === '4') {
                $hasSelectedSiteVisit = true;
            } elseif ($cleaned === '5') {
                $hasSelectedHumanAgent = true;
            } elseif (in_array($cleaned, ['1', '2'])) {
                $hasSelectedPropertyOption = true;
            } elseif (in_array($cleaned, ['3'])) {
                $hasSelectedExploreOption = true;
            }
        }

        if ($hasSelectedSiteVisit) {
            $this->lead_status = 'hot';
            $this->lead_score = 92;
            $this->notes = "Lead Qualified as HOT (Selected Site Visit booking menu in WhatsApp).";
            $this->save();
            return;
        }

        if ($hasSelectedHumanAgent) {
            $this->lead_status = 'hot';
            $this->lead_score = 95;
            $this->notes = "Lead Qualified as HOT (Selected direct advisor contact in WhatsApp).";
            $this->save();
            return;
        }

        // 5. Comprehensive Text Keyword NLP Analysis
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

        if ($hotHits >= 1) {
            $this->lead_status = 'hot';
            $this->lead_score = min(98, 80 + ($hotHits * 5));
            $this->notes = "Lead Qualified as HOT based on property purchase intent in {$clientMsgCount} messages.";
            $this->save();
            return;
        }

        // 6. Warm Engagement Evaluation (Exploring nodes or selected numeric options 1, 2, 3)
        $warmNodes = [
            'growth_city_node',
            'offers_node',
            'location_node',
            'amenities_node',
            '1bhk_media_node',
            '2bhk_media_node',
            'hoabl_about_node',
        ];

        if ($warmHits >= 1 || in_array($this->current_node_id, $warmNodes) || $hasSelectedPropertyOption || $hasSelectedExploreOption || $clientMsgCount >= 2) {
            $this->lead_status = 'warm';
            $this->lead_score = min(78, 55 + ($warmHits * 5) + ($clientMsgCount * 3));
            $this->notes = "Lead Qualified as WARM (Active prospect inquiring via WhatsApp menu options).";
            $this->save();
            return;
        }

        // 7. Cold default only for truly dormant/unresponsive contacts
        $this->lead_status = 'cold';
        $this->lead_score = 15;
        $this->notes = "Lead Classified as COLD (Initial interaction, awaiting selection).";
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

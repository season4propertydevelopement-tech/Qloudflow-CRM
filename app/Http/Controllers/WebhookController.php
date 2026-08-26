<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\ChatbotRule;
use App\Services\WhatsAppApiService;

class WebhookController extends Controller
{
    public function handleIncoming(Request $request, WhatsAppApiService $apiService)
    {
        // Respond gracefully with 200 OK when visited in a browser or pinged with GET
        if ($request->isMethod('GET')) {
            return response()->json([
                'status' => 'active',
                'service' => 'Qloudflow WhatsApp Webhook Gateway',
                'message' => 'Webhook endpoint is active and listening for POST events.',
                'timestamp' => now()->toIso8601String()
            ]);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        // Save Raw Webhook Event
        $webhook = WebhookEvent::create([
            'event_type' => $event ?? 'unknown',
            'external_event_id' => $payload['messageId'] ?? null,
            'payload' => $payload,
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        if ($event === 'message.received') {
            $this->processIncomingMessage($payload, $apiService);
        } elseif ($event === 'message.status') {
            $this->processMessageStatus($payload);
        }

        return response()->json(['success' => true]);
    }

    public function processIncomingMessage(array $payload, WhatsAppApiService $apiService)
    {
        $messageId = $payload['messageId'] ?? null;
        if ($messageId) {
            $alreadyExists = Message::where('external_message_id', $messageId)->exists();
            if ($alreadyExists) {
                return; // Prevent duplicate processing
            }
        }

        $rawJid = $payload['whatsapp_id'] ?? $payload['from'] ?? $payload['phone'] ?? '';
        $realPhone = $payload['real_phone'] ?? null;
        $isGroup = !empty($payload['isGroup']) 
            || str_ends_with($rawJid, '@g.us') 
            || str_contains($rawJid, '@g.us') 
            || !empty($payload['groupId']);
        $extractedDigits = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid', '@g.us'], '', $rawJid)) ?: $rawJid;
        $phone = $realPhone ?: $extractedDigits;
        $messageText = $payload['message'] ?? '';

        // Find Contact by whatsapp_id or phone
        $contact = Contact::where('whatsapp_id', $rawJid)
            ->orWhere('phone', $phone)
            ->first();

        if (!$contact) {
            $contact = Contact::create([
                'phone' => $phone,
                'name' => $payload['pushName'] ?? ($isGroup ? 'WhatsApp Group' : 'Unknown'),
                'whatsapp_id' => $rawJid,
                'first_message_at' => now(),
                'lead_status' => 'cold',
                'lead_score' => 10,
                'chatbot_enabled' => !$isGroup, // Never enable chatbot on groups
            ]);
        } else {
            if ($realPhone && $contact->phone !== $realPhone) {
                $contact->phone = $realPhone;
            }
        }
        
        $contact->whatsapp_id = $rawJid;
        $contact->last_message_at = now();

        if ($isGroup) {
            $contact->chatbot_enabled = false;
        }

        if (($contact->name === 'Unknown' || empty($contact->name)) && !empty($payload['pushName'])) {
            $contact->name = $payload['pushName'];
        }

        $contact->save();

        // Find or Create Conversation
        $conversation = Conversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['status' => 'active']
        );
        $conversation->last_message_at = now();
        $conversation->unread_count += 1;
        $conversation->save();

        // Create Incoming Message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'external_message_id' => $messageId ?? uniqid('msg_'),
            'direction' => 'incoming',
            'message' => $messageText,
            'sent_at' => now(),
        ]);

        // Chatbot Logic: Strictly for individual/direct messages only (NEVER in WhatsApp groups)
        if (!$isGroup && $contact->chatbot_enabled && !$contact->human_handoff) {
            $chatbotService = app(\App\Services\ChatbotService::class);
            $chatbotService->handleMessage($contact, $conversation, $messageText);
        }

        // Post-conversation Lead Qualification (only for individual private chats)
        if (!$isGroup) {
            $contact->evaluateLeadFromConversationHistory($conversation);
        }
    }

    protected function processMessageStatus(array $payload)
    {
        if (isset($payload['messageId']) && isset($payload['status'])) {
            $message = Message::where('external_message_id', $payload['messageId'])->first();
            if ($message) {
                $message->status = $payload['status'];
                
                $fieldMap = [
                    'delivered' => 'delivered_at',
                    'read' => 'read_at',
                    'failed' => 'failed_at',
                ];

                if (isset($fieldMap[$payload['status']])) {
                    $message->{$fieldMap[$payload['status']]} = now();
                }

                $message->save();
            }
        }
    }
}

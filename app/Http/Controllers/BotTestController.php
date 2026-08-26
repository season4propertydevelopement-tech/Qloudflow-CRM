<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatbotService;

class BotTestController extends Controller
{
    /**
     * Simulate an incoming WhatsApp user message through the live Chatbot engine.
     */
    public function simulateMessage(Request $request, ChatbotService $chatbotService)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $sessionId = $request->input('session_id', session()->getId());
        $testPhone = 'simulator_' . substr(md5($sessionId), 0, 10);

        // Find or create sandbox contact
        $contact = Contact::firstOrCreate(
            ['phone' => $testPhone],
            [
                'name' => 'Sandbox Lead (Tester)',
                'lead_status' => 'cold',
                'lead_score' => 10,
                'chatbot_enabled' => true,
                'human_handoff' => false,
                'current_node_id' => 'welcome_node',
                'first_message_at' => now(),
            ]
        );
        $contact->last_message_at = now();
        $contact->save();

        // Find or create sandbox conversation
        $conversation = Conversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['status' => 'active']
        );
        $conversation->last_message_at = now();
        $conversation->save();

        $messageText = trim($request->input('message'));

        // Save incoming user test message
        Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'incoming',
            'message' => $messageText,
            'sent_at' => now(),
        ]);

        // Process message through live Chatbot logic
        $chatbotService->handleMessage($contact, $conversation, $messageText);

        // Run multi-message conversation lead qualification
        $contact->evaluateLeadFromConversationHistory($conversation);
        $contact->refresh();

        // Get latest bot reply
        $latestReply = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outgoing')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'reply' => $latestReply ? $latestReply->message : "Message received! How else can I help?",
            'media_url' => $latestReply ? $latestReply->media_url : null,
            'lead_status' => $contact->lead_status ?? 'cold',
            'lead_score' => $contact->lead_score ?? 10,
            'current_node' => $contact->current_node_id ?? 'welcome_node',
            'human_handoff' => (bool)$contact->human_handoff,
            'sent_at' => now()->format('h:i A'),
        ]);
    }

    /**
     * Reset the simulator conversation session.
     */
    public function resetSession(Request $request, ChatbotService $chatbotService)
    {
        $sessionId = $request->input('session_id', session()->getId());
        $testPhone = 'simulator_' . substr(md5($sessionId), 0, 10);

        $contact = Contact::where('phone', $testPhone)->first();
        if ($contact) {
            $contact->lead_status = 'cold';
            $contact->lead_score = 10;
            $contact->current_node_id = 'welcome_node';
            $contact->human_handoff = false;
            $contact->notes = null;
            $contact->save();

            // Clear sandbox messages
            Message::where('contact_id', $contact->id)->delete();
        }

        $welcomeMessage = $chatbotService->getWelcomeMessage();
        $welcomeMedia = $chatbotService->getWelcomeMediaUrl();

        return response()->json([
            'success' => true,
            'message' => 'Sandbox session reset successfully.',
            'welcome_message' => $welcomeMessage,
            'media_url' => $welcomeMedia,
            'lead_status' => 'cold',
            'lead_score' => 10,
        ]);
    }
}

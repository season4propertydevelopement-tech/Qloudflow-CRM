<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Services\WhatsAppApiService;
use App\Models\Message;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = Conversation::with('contact')->latest('last_message_at')->paginate(15);
        return view('conversations.index', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        $conversation->load(['contact', 'messages' => function($q) {
            $q->orderBy('created_at', 'asc');
        }]);
        
        return view('conversations.show', compact('conversation'));
    }

    public function reply(Request $request, Conversation $conversation, WhatsAppApiService $apiService)
    {
        $request->validate(['message' => 'required|string']);

        $messageText = $request->input('message');
        $contact = $conversation->contact;
        $cleanPhone = preg_replace('/\D/', '', $contact->phone ?? '');
        if (!empty($cleanPhone) && strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 14) {
            $recipient = $cleanPhone;
        } else {
            $recipient = $contact->phone ?: $contact->whatsapp_id;
        }
        
        // Send via WhatsApp API
        $response = $apiService->sendMessage($recipient, $messageText);
        
        \Illuminate\Support\Facades\Log::info('UI WhatsApp API Response: ', ['recipient' => $recipient, 'response' => $response]);

        if (isset($response['success']) && $response['success']) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'external_message_id' => $response['messageId'] ?? null,
                'direction' => 'outgoing',
                'message' => $messageText,
                'status' => 'sent',
                'sent_at' => now(),
                'is_bot_message' => false,
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent!',
                    'data' => $message
                ]);
            }
            
            return back()->with('success', 'Message sent!');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . ($response['error'] ?? $response['message'] ?? 'API Error')
            ], 400);
        }

        return back()->with('error', 'Failed to send message.');
    }

    public function toggleBot(Request $request, Conversation $conversation)
    {
        $contact = $conversation->contact;
        $contact->chatbot_enabled = !$contact->chatbot_enabled;
        if ($contact->chatbot_enabled) {
            $contact->human_handoff = false;
        }
        $contact->save();

        $status = $contact->chatbot_enabled ? 'enabled' : 'disabled';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'chatbot_enabled' => $contact->chatbot_enabled,
                'message' => "Chatbot {$status} for this conversation."
            ]);
        }

        return back()->with('success', "Chatbot {$status} for {$contact->name}.");
    }

    public function destroy(Request $request, Conversation $conversation)
    {
        // Delete all associated messages first
        $conversation->messages()->delete();
        $conversation->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Conversation and messages deleted successfully.'
            ]);
        }

        return redirect()->route('conversations.index')->with('success', 'Conversation deleted successfully.');
    }
}

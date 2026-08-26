<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query();

        // Lead Status Filter
        if ($request->filled('lead_status') && in_array($request->lead_status, ['hot', 'warm', 'cold'])) {
            $query->where('lead_status', $request->lead_status);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->latest('last_message_at')->paginate(15)->withQueryString();

        $counts = [
            'all' => Contact::count(),
            'hot' => Contact::where('lead_status', 'hot')->count(),
            'warm' => Contact::where('lead_status', 'warm')->count(),
            'cold' => Contact::where('lead_status', 'cold')->count(),
        ];

        return view('contacts.index', compact('contacts', 'counts'));
    }

    public function show(Contact $contact)
    {
        $contact->load('conversations.messages');
        return view('contacts.show', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'lead_status' => 'nullable|in:hot,warm,cold',
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone) ?: $request->phone;

        if ($request->filled('name')) {
            $contact->name = $request->name;
        }
        $contact->phone = $cleanPhone;
        if ($request->filled('notes')) {
            $contact->notes = $request->notes;
        }
        if ($request->filled('lead_status')) {
            $contact->lead_status = $request->lead_status;
        }
        $contact->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contact details updated successfully.',
                'contact' => $contact
            ]);
        }

        return back()->with('success', 'Contact updated successfully.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return back()->with('success', 'Contact removed successfully.');
    }

    public function toggleBot(Request $request, Contact $contact)
    {
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
                'message' => "Chatbot {$status} for this contact."
            ]);
        }

        return back()->with('success', "Chatbot {$status} for {$contact->name}.");
    }

    public function setLeadStatus(Request $request, Contact $contact)
    {
        $request->validate([
            'lead_status' => 'required|in:hot,warm,cold',
        ]);

        $contact->lead_status = $request->lead_status;
        if ($request->lead_status === 'hot') {
            $contact->lead_score = 90;
        } elseif ($request->lead_status === 'warm') {
            $contact->lead_score = 50;
        } else {
            $contact->lead_score = 10;
        }
        $contact->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'lead_status' => $contact->lead_status,
                'message' => "Lead marked as " . ucfirst($contact->lead_status)
            ]);
        }

        return back()->with('success', "Lead marked as " . ucfirst($contact->lead_status) . " for {$contact->name}.");
    }
}

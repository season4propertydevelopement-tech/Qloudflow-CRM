<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_contacts' => Contact::count(),
            'hot_leads' => Contact::where('lead_status', 'hot')->count(),
            'cold_leads' => Contact::where('lead_status', 'cold')->count(),
            'warm_leads' => Contact::where('lead_status', 'warm')->count(),
            'total_conversations' => Conversation::count(),
            'messages_received_today' => Message::where('direction', 'incoming')->whereDate('created_at', today())->count(),
            'messages_sent_today' => Message::where('direction', 'outgoing')->whereDate('created_at', today())->count(),
        ];

        return view('dashboard.index', compact('stats'));
    }
}

@extends('layouts.app')

@section('content')
<div class="space-y-4 sm:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-emerald-600 text-base sm:text-xl"></i>
                <span>Call CRM Intelligence & History</span>
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">AI Telemetry</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Automated lead rating, conversation summaries, entity extraction, and WhatsApp follow-up telemetry</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('voice-agent.index') }}" class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-xs transition">
                <i class="fa-solid fa-phone text-xs"></i>
                <span>Start New Voice Call</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-5">
        <!-- Stat 1: Total Voice Calls -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs">
            <div>
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Voice Calls</p>
                <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 leading-tight">{{ $stats['total_calls'] ?? 0 }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1 gap-1">
                    <i class="fa-solid fa-headset text-[10px] text-indigo-500"></i> AI Conversations
                </span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center text-lg shadow-2xs">
                <i class="fa-solid fa-phone-volume"></i>
            </div>
        </div>

        <!-- Stat 2: Avg Lead Qualification Score -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs">
            <div>
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-emerald-600">Avg Lead Score</p>
                <p class="text-2xl sm:text-3xl font-black text-emerald-600 mt-1 leading-tight">{{ $stats['avg_lead_score'] ?? 0 }}<span class="text-sm text-slate-400 font-bold">/100</span></p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-emerald-700 mt-1 gap-1">
                    <i class="fa-solid fa-chart-simple text-[10px]"></i> AI Rated Quality
                </span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-lg shadow-2xs">
                <i class="fa-solid fa-star"></i>
            </div>
        </div>

        <!-- Stat 3: Hot Prospects -->
        <div class="bg-white rounded-2xl border border-rose-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs">
            <div>
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-rose-600">Hot Prospects (>=80)</p>
                <p class="text-2xl sm:text-3xl font-black text-rose-600 mt-1 leading-tight">{{ $stats['hot_prospects'] ?? 0 }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-rose-700 mt-1 gap-1">
                    <i class="fa-solid fa-fire text-[10px] text-rose-500"></i> High Purchase Intent
                </span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-lg shadow-2xs">
                <i class="fa-solid fa-fire"></i>
            </div>
        </div>

        <!-- Stat 4: Talk Time -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs">
            <div>
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Talk Time</p>
                <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 leading-tight font-mono">
                    {{ gmdate("i:s", $stats['total_duration_sec'] ?? 0) }}
                </p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1 gap-1">
                    <i class="fa-solid fa-clock text-[10px] text-indigo-500"></i> Human Talk Time Saved
                </span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-lg shadow-2xs">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-2xl border border-slate-200 p-3 sm:p-4 shadow-xs">
        <form method="GET" action="{{ route('voice-agent.analytics') }}" class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-1">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search caller name, phone or summary..."
                        class="w-full pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800"
                    >
                </div>

                <select name="persona" class="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-700">
                    <option value="">All Personas</option>
                    <option value="universal" {{ request('persona') === 'universal' || request('persona') === 'avni' ? 'selected' : '' }}>Avni (Sales & Growth)</option>
                    <option value="avni" {{ request('persona') === 'avni' ? 'selected' : '' }}>Avni (Outbound)</option>
                </select>

                <button type="submit" class="px-3.5 py-2 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 rounded-xl transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Call Logs Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Recent AI Voice Call Transcripts & CRM Reports</h3>
        </div>

        @if($calls->count() > 0)
            <div class="divide-y divide-slate-100">
                @foreach($calls as $call)
                    <div class="p-4 hover:bg-slate-50/80 transition-colors" x-data="{ expanded: false }">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-900 to-indigo-900 text-white flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($call->caller_name ?: 'C', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold text-slate-900">{{ $call->caller_name ?: 'Guest Caller' }}</h4>
                                        <span class="text-xs font-mono text-slate-500">{{ $call->caller_phone ?: 'No Phone' }}</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-0.5 line-clamp-1">{{ $call->call_summary ?: 'No summary recorded.' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                                <!-- Lead Score Badge -->
                                <span class="px-2.5 py-1 rounded-lg text-xs font-black {{ ($call->lead_score >= 80) ? 'bg-rose-100 text-rose-700 border border-rose-200' : (($call->lead_score >= 60) ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700') }}">
                                    ⭐ {{ $call->lead_score }}/100
                                </span>

                                <!-- Agent Tag -->
                                <span class="px-2 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                                    {{ $call->persona_name }}
                                </span>

                                <!-- Duration -->
                                <span class="text-xs font-mono font-bold text-slate-600 px-2 py-1 bg-slate-100 rounded-lg">
                                    ⏱️ {{ $call->duration }}
                                </span>

                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    class="p-2 text-xs font-bold text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition"
                                >
                                    <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="expanded ? 'rotate-180 text-indigo-600' : ''"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Accordion Expansion: Transcript & WhatsApp Message -->
                        <div x-show="expanded" x-cloak class="mt-4 pt-3 border-t border-slate-100 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- WhatsApp Follow-Up Box -->
                                <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-200">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">📲 Auto-Generated WhatsApp Follow-Up</span>
                                        @if($call->caller_phone)
                                            <a
                                                href="https://wa.me/{{ preg_replace('/\D/', '', $call->caller_phone) }}?text={{ urlencode($call->whatsapp_followup_message ?? '') }}"
                                                target="_blank"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded-lg shadow-2xs transition"
                                            >
                                                <i class="fa-brands fa-whatsapp mr-1"></i> Send Now
                                            </a>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-800 leading-relaxed italic">
                                        "{{ $call->whatsapp_followup_message ?: 'No follow-up message generated.' }}"
                                    </p>
                                </div>

                                <!-- Extracted Next Step & Intent -->
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
                                    <div>
                                        <span class="text-slate-400 font-bold uppercase text-[10px] block">Customer Intent:</span>
                                        <span class="font-bold text-slate-800">{{ $call->key_entities['intent'] ?? 'General Inquiry' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 font-bold uppercase text-[10px] block">Agreed Next Action:</span>
                                        <span class="font-bold text-indigo-600">{{ $call->key_entities['next_step'] ?? 'Follow up via WhatsApp' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Conversation Transcript -->
                            @if(is_array($call->transcript) && count($call->transcript) > 0)
                                <div class="p-3 rounded-xl bg-slate-950 text-slate-200 text-xs space-y-2 max-h-48 overflow-y-auto">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Full Call Transcript</div>
                                    @foreach($call->transcript as $entry)
                                        <div class="flex items-start gap-2">
                                            <span class="font-bold text-indigo-400 shrink-0">[{{ ($entry['role'] ?? '') === 'model' || ($entry['role'] ?? '') === 'agent' ? $call->persona_name : ($call->caller_name ?: 'Caller') }}]:</span>
                                            <span class="text-slate-300">{{ $entry['text'] ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if(method_exists($calls, 'links'))
                <div class="p-4 border-t border-slate-200">
                    {{ $calls->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    🎙️
                </div>
                <h3 class="text-base font-bold text-slate-800">No Call Records Found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">Start a live voice call from the Voice Calling Studio to record your first call telemetry and post-call CRM analysis.</p>
                <div class="mt-4">
                    <a href="{{ route('voice-agent.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                        <i class="fa-solid fa-headset text-xs"></i>
                        <span>Launch Voice Calling Studio</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

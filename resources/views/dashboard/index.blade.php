@extends('layouts.app')

@section('content')
<div class="space-y-4 sm:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-3 sm:pb-4 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-gauge-high text-indigo-600 text-base sm:text-xl"></i>
                <span>Qloudflow Workspace Dashboard</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Real-time metrics, automated lead classification, and WhatsApp client activity</p>
        </div>

        <div class="grid grid-cols-2 sm:flex items-center gap-2 sm:gap-2.5 shrink-0 w-full sm:w-auto">
            <a href="{{ route('voice-agent.index') }}" class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-3.5 py-2.5 bg-pink-600 hover:bg-pink-700 active:bg-pink-800 text-white font-bold text-xs rounded-xl shadow-xs transition text-center col-span-2 sm:col-span-1">
                <i class="fa-solid fa-headset text-xs"></i>
                <span>Voice AI Studio</span>
            </a>
            <a href="{{ route('whatsapp.connection') }}" class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-xs transition text-center">
                <i class="fa-solid fa-qrcode text-xs"></i>
                <span>Pair WhatsApp</span>
            </a>
            <a href="{{ route('conversations.index') }}" class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-3.5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs rounded-xl shadow-xs transition text-center">
                <i class="fa-solid fa-comments text-xs"></i>
                <span>Live Inbox</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-5">
        <!-- Stat 1: Total Contacts -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs hover:border-slate-300 transition-colors">
            <div class="min-w-0 mr-3">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500 truncate">Total Contacts</p>
                <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 leading-tight">{{ $stats['total_contacts'] }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1 gap-1">
                    <i class="fa-solid fa-users text-[10px]"></i> Captured Leads
                </span>
            </div>
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-base sm:text-lg shrink-0 shadow-2xs">
                <i class="fa-solid fa-address-book"></i>
            </div>
        </div>

        <!-- Stat 2: Hot Leads -->
        <a href="{{ route('contacts.index', ['lead_status' => 'hot']) }}" class="bg-white rounded-2xl border border-rose-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs hover:border-rose-300 hover:shadow-xs transition-all group">
            <div class="min-w-0 mr-3">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-rose-600 truncate">Hot Leads</p>
                <p class="text-2xl sm:text-3xl font-black text-rose-600 mt-1 leading-tight">{{ $stats['hot_leads'] }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-bold text-rose-700 mt-1 gap-1 group-hover:underline">
                    <i class="fa-solid fa-fire text-[10px] text-rose-500"></i> High Intent →
                </span>
            </div>
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-base sm:text-lg shrink-0 shadow-2xs">
                <i class="fa-solid fa-fire"></i>
            </div>
        </a>

        <!-- Stat 3: Cold Leads -->
        <a href="{{ route('contacts.index', ['lead_status' => 'cold']) }}" class="bg-white rounded-2xl border border-sky-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs hover:border-sky-300 hover:shadow-xs transition-all group">
            <div class="min-w-0 mr-3">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-sky-600 truncate">Cold Leads</p>
                <p class="text-2xl sm:text-3xl font-black text-sky-700 mt-1 leading-tight">{{ $stats['cold_leads'] }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-bold text-sky-700 mt-1 gap-1 group-hover:underline">
                    <i class="fa-solid fa-snowflake text-[10px] text-sky-500"></i> Inquiries →
                </span>
            </div>
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-sky-50 border border-sky-100 text-sky-600 flex items-center justify-center text-base sm:text-lg shrink-0 shadow-2xs">
                <i class="fa-solid fa-snowflake"></i>
            </div>
        </a>

        <!-- Stat 4: Conversations -->
        <a href="{{ route('conversations.index') }}" class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 flex items-center justify-between shadow-2xs hover:border-slate-300 hover:shadow-xs transition-all group">
            <div class="min-w-0 mr-3">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-500 truncate">Conversations</p>
                <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 leading-tight">{{ $stats['total_conversations'] }}</p>
                <span class="inline-flex items-center text-[10px] sm:text-[11px] font-semibold text-indigo-600 mt-1 gap-1 group-hover:underline">
                    <i class="fa-solid fa-comments text-[10px]"></i> Live Threads →
                </span>
            </div>
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center text-base sm:text-lg shrink-0 shadow-2xs">
                <i class="fa-solid fa-message"></i>
            </div>
        </a>
    </div>

    <!-- 2 Column Lower Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5">
        <!-- Main Feature Card (2 cols) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-4 sm:p-6 flex flex-col justify-between shadow-xs">
            <div class="space-y-4">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-400 via-indigo-600 to-emerald-500 text-white flex items-center justify-center shadow-sm text-base shrink-0 mt-0.5 sm:mt-0">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Qloudflow WhatsApp Automation Suite</h3>
                        <p class="text-xs text-slate-500 font-medium">Smart AI customer assistance, lead temperature classification, and knowledge base routing</p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Your automated WhatsApp assistant is trained with comprehensive knowledge base content, website & mobile application services, and intelligent customer query routing.
                </p>

                <!-- Feature Pills -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 pt-1">
                    <!-- Hot Leads Pill -->
                    <div class="bg-rose-50/70 border border-rose-200 rounded-xl p-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-rose-900 mb-1">
                            <i class="fa-solid fa-fire text-rose-600"></i>
                            <span>Hot Lead Detection</span>
                        </div>
                        <p class="text-[11px] text-rose-700 leading-normal">Auto-identifies quote requests and high purchase intent.</p>
                    </div>

                    <!-- Chatbot Toggle Pill -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-1">
                            <i class="fa-solid fa-toggle-on text-emerald-600"></i>
                            <span>Chatbot Toggle</span>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-normal">Enable or disable bot per chat with a single click.</p>
                    </div>

                    <!-- Human Handoff Pill -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-1">
                            <i class="fa-solid fa-headset text-amber-600"></i>
                            <span>Human Handoff</span>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-normal">Seamlessly take over conversations from the live inbox.</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 sm:pt-5 mt-4 sm:mt-5 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3">
                <a href="{{ route('contacts.index', ['lead_status' => 'hot']) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl shadow-xs text-white bg-rose-600 hover:bg-rose-700 active:bg-rose-800 transition text-center">
                    <i class="fa-solid fa-fire text-xs"></i>
                    <span>View Hot Leads ({{ $stats['hot_leads'] }})</span>
                </a>
                <a href="{{ route('contacts.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 active:bg-slate-100 transition text-center">
                    <i class="fa-solid fa-address-book text-xs"></i>
                    <span>All Contacts</span>
                </a>
            </div>
        </div>

        <!-- Status & Health Card (1 col) -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-6 flex flex-col justify-between shadow-xs">
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 sm:mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-server text-indigo-500"></i>
                    <span>System Status</span>
                </h4>

                <div class="space-y-2.5 sm:space-y-3">
                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <i class="fa-solid fa-database text-slate-400 text-xs"></i>
                            <span>Database</span>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 shrink-0">
                            Online
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <i class="fa-solid fa-robot text-emerald-500 text-xs"></i>
                            <span>AI Chatbot Engine</span>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 shrink-0">
                            Active
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <i class="fa-solid fa-network-wired text-indigo-500 text-xs"></i>
                            <span>Webhook Listener</span>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-300 shrink-0">
                            Ready
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <i class="fa-solid fa-headset text-indigo-500 text-xs"></i>
                            <span>Voice AI Engine (Hindi/Hinglish)</span>
                        </div>
                        <a href="{{ route('voice-agent.index') }}" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-pink-100 text-pink-700 hover:bg-pink-200 transition shrink-0">
                            Launch Studio →
                        </a>
                    </div>

                    <div class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-xs"></i>
                            <span>WhatsApp Client</span>
                        </div>
                        <a href="{{ route('whatsapp.connection') }}" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 hover:bg-slate-300 transition shrink-0">
                            Check Status →
                        </a>
                    </div>
                </div>
            </div>

            <div class="pt-3 sm:pt-4 mt-3 sm:mt-4 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-400 font-medium">Qloudflow Suite • WhatsApp Automation</p>
            </div>
        </div>
    </div>
</div>
@endsection


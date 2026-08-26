@extends('layouts.app')

@section('content')
<div class="space-y-4 sm:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-comments text-indigo-600 text-base sm:text-xl"></i>
                <span>Live Conversations & Lead Inbox</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Real-time WhatsApp chats, lead intent classification, and AI chatbot controls</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <span class="text-xs font-bold text-slate-600 bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-2xs">
                Total: <strong class="text-slate-900">{{ $conversations->total() }}</strong> Threads
            </span>
        </div>
    </div>

    <!-- Conversation Feed Card -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @forelse($conversations as $conversation)
                <li class="p-3.5 sm:p-5 hover:bg-slate-50/90 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <a href="{{ route('conversations.show', $conversation) }}" class="flex items-start sm:items-center flex-1 min-w-0 group">
                        <!-- Avatar -->
                        <div class="relative shrink-0 mr-3 sm:mr-4 mt-0.5 sm:mt-0">
                            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-black text-base sm:text-lg shadow-2xs group-hover:scale-105 transition-transform">
                                {{ strtoupper(substr($conversation->contact->name ?? 'U', 0, 1)) }}
                            </div>
                            @if($conversation->unread_count > 0)
                                <span class="absolute -top-1 -right-1 block h-4 w-4 rounded-full ring-2 ring-white bg-emerald-500 text-[9px] text-white font-black flex items-center justify-center shadow-xs">
                                    {{ $conversation->unread_count }}
                                </span>
                            @endif
                        </div>

                        <!-- Contact Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center justify-between gap-1.5 sm:gap-2">
                                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 max-w-full">
                                    <p class="text-xs sm:text-sm font-bold text-slate-900 truncate group-hover:text-indigo-600 transition-colors">
                                        {{ $conversation->contact->name ?? 'Unknown Contact' }}
                                    </p>
                                    
                                    <!-- Lead Temperature Badge -->
                                    @php
                                        $lead = $conversation->contact->lead_status ?? 'cold';
                                    @endphp
                                    @if($lead === 'hot')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200 shrink-0">
                                            <i class="fa-solid fa-fire text-[9px]"></i> Hot
                                        </span>
                                    @elseif($lead === 'warm')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                                            <i class="fa-solid fa-sun text-[9px]"></i> Warm
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-sky-50 text-sky-700 border border-sky-200 shrink-0">
                                            <i class="fa-solid fa-snowflake text-[9px]"></i> Cold
                                        </span>
                                    @endif
                                </div>

                                <p class="text-[11px] font-medium text-slate-400 shrink-0">
                                    <i class="fa-solid fa-clock text-[10px] mr-0.5"></i>
                                    {{ $conversation->last_message_at ? $conversation->last_message_at->diffForHumans() : 'Active' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center justify-between mt-1.5 gap-1.5 sm:gap-2">
                                <p class="text-xs font-semibold text-slate-500 font-mono truncate">
                                    <i class="fa-solid fa-phone text-[10px] text-slate-400 mr-1"></i>
                                    {{ $conversation->contact->formatted_phone }}
                                </p>
                                
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if($conversation->contact->chatbot_enabled)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Bot Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                            <i class="fa-solid fa-pause text-[9px]"></i>
                                            Bot Paused
                                        </span>
                                    @endif

                                    @if($conversation->contact->human_handoff)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                            <i class="fa-solid fa-headset text-[9px]"></i>
                                            Human Support
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>

                    <!-- Quick Action & Toggle Button -->
                    <div class="shrink-0 flex items-center justify-end sm:justify-center gap-2 pt-2 sm:pt-0 border-t border-slate-100 sm:border-0">
                        <a
                            href="{{ route('conversations.show', $conversation) }}"
                            class="sm:hidden inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition"
                        >
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                            <span>Open Chat</span>
                        </a>

                        <form action="{{ route('conversations.toggle-bot', $conversation) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 text-xs font-bold px-3 sm:px-3.5 py-1.5 sm:py-2 rounded-xl border transition cursor-pointer {{ $conversation->contact->chatbot_enabled ? 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100 active:bg-slate-200' : 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100 active:bg-emerald-200' }}"
                                title="{{ $conversation->contact->chatbot_enabled ? 'Pause bot for this conversation' : 'Enable bot for this conversation' }}"
                            >
                                <i class="fa-solid fa-robot text-xs"></i>
                                <span>{{ $conversation->contact->chatbot_enabled ? 'Disable Bot' : 'Enable Bot' }}</span>
                            </button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="py-12 sm:py-16 text-center text-slate-500 p-4">
                    <div class="max-w-sm mx-auto space-y-2">
                        <i class="fa-solid fa-comments-slash text-3xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-700">No active conversations found</p>
                        <p class="text-xs text-slate-400">Incoming messages from customers will show up here in real time.</p>
                    </div>
                </li>
            @endforelse
        </ul>

        @if($conversations->hasPages())
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-slate-200 bg-slate-50/50 overflow-x-auto">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection


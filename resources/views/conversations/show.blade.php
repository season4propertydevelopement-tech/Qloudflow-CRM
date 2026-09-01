@extends('layouts.app')

@section('content')
<div class="h-[calc(100dvh-5.5rem)] sm:h-[calc(100vh-8.5rem)] flex flex-col bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden" x-data="{
    botEnabled: {{ $conversation->contact->chatbot_enabled ? 'true' : 'false' }},
    leadStatus: '{{ $conversation->contact->lead_status ?? 'cold' }}',
    isTogglingBot: false,
    isUpdatingLead: false,
    toggleBot() {
        if (this.isTogglingBot) return;
        this.isTogglingBot = true;
        
        fetch('{{ route('conversations.toggle-bot', $conversation) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            this.isTogglingBot = false;
            if (data.success) {
                this.botEnabled = data.chatbot_enabled;
            }
        })
        .catch(err => {
            this.isTogglingBot = false;
            console.error('Toggle bot failed', err);
        });
    },
    changeLeadStatus(newStatus) {
        if (this.isUpdatingLead) return;
        this.isUpdatingLead = true;
        
        fetch('{{ route('contacts.set-lead-status', $conversation->contact) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ lead_status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            this.isUpdatingLead = false;
            if (data.success) {
                this.leadStatus = data.lead_status;
            }
        })
        .catch(err => {
            this.isUpdatingLead = false;
            console.error('Lead status update failed', err);
        });
    }
}">
    <!-- Chat Header (Responsive 2-Tier on Mobile, 1-Row on Tablet/Desktop) -->
    <div class="px-3 sm:px-6 py-2.5 sm:py-3.5 border-b border-slate-200 bg-slate-50 shrink-0 space-y-2.5 sm:space-y-0 sm:flex sm:justify-between sm:items-center">
        <!-- Top Row: Back Button, Avatar, Contact Details -->
        <div class="flex items-center min-w-0">
            <a href="{{ route('conversations.index') }}" class="mr-2 sm:mr-3.5 p-2 bg-white rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition shadow-2xs shrink-0" title="Back to Inbox">
                <i class="fa-solid fa-arrow-left text-xs sm:text-sm"></i>
            </a>
            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-extrabold text-xs sm:text-sm mr-2.5 sm:mr-3 shadow-2xs shrink-0">
                {{ strtoupper(substr($conversation->contact->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <h3 class="font-black text-slate-900 text-xs sm:text-base leading-tight truncate">{{ $conversation->contact->name ?? 'Unknown Contact' }}</h3>
                    <a href="{{ route('contacts.show', $conversation->contact) }}" class="text-[10px] sm:text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 border border-indigo-100 px-1.5 sm:px-2 py-0.5 rounded-md transition shrink-0">
                        Profile
                    </a>
                </div>
                <p class="text-[11px] sm:text-xs text-slate-500 font-mono mt-0.5 font-semibold flex items-center gap-1 truncate">
                    <i class="fa-solid fa-phone text-[9px] sm:text-[10px] text-slate-400"></i>
                    <span>{{ $conversation->contact->formatted_phone }}</span>
                </p>
            </div>
        </div>

        <!-- Controls: Lead Temperature Selector & Chatbot Toggle -->
        <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-2 sm:pt-0 border-t border-slate-200 sm:border-0">
            <!-- Lead Status Selector -->
            <div class="inline-flex items-center bg-white p-0.5 sm:p-1 rounded-xl border border-slate-200 shadow-2xs">
                <button
                    type="button"
                    @click="changeLeadStatus('hot')"
                    class="px-2 sm:px-2.5 py-1 rounded-lg text-[10px] sm:text-[11px] font-extrabold transition cursor-pointer flex items-center gap-1"
                    :class="leadStatus === 'hot' ? 'bg-rose-500 text-white shadow-2xs' : 'text-rose-700 hover:bg-rose-50'"
                    title="Mark as Hot Lead"
                >
                    <i class="fa-solid fa-fire text-[10px] sm:text-xs"></i>
                    <span>Hot</span>
                </button>
                <button
                    type="button"
                    @click="changeLeadStatus('warm')"
                    class="px-2 sm:px-2.5 py-1 rounded-lg text-[10px] sm:text-[11px] font-extrabold transition cursor-pointer flex items-center gap-1"
                    :class="leadStatus === 'warm' ? 'bg-amber-500 text-white shadow-2xs' : 'text-amber-800 hover:bg-amber-50'"
                    title="Mark as Warm Lead"
                >
                    <i class="fa-solid fa-sun text-[10px] sm:text-xs"></i>
                    <span>Warm</span>
                </button>
                <button
                    type="button"
                    @click="changeLeadStatus('cold')"
                    class="px-2 sm:px-2.5 py-1 rounded-lg text-[10px] sm:text-[11px] font-extrabold transition cursor-pointer flex items-center gap-1"
                    :class="leadStatus === 'cold' ? 'bg-sky-600 text-white shadow-2xs' : 'text-sky-700 hover:bg-sky-50'"
                    title="Mark as Cold Lead"
                >
                    <i class="fa-solid fa-snowflake text-[10px] sm:text-xs"></i>
                    <span>Cold</span>
                </button>
            </div>

            <!-- Chatbot Toggle Action -->
            <button
                type="button"
                @click="toggleBot()"
                :disabled="isTogglingBot"
                class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl text-[11px] sm:text-xs font-bold transition border cursor-pointer select-none shadow-2xs shrink-0"
                :class="botEnabled ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200'"
                :title="botEnabled ? 'Click to disable AI bot for this contact' : 'Click to enable AI bot for this contact'"
            >
                <span
                    class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full shrink-0"
                    :class="botEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                ></span>
                
                <i class="fa-solid fa-robot text-xs" :class="botEnabled ? 'text-emerald-600' : 'text-slate-500'"></i>
                
                <span x-text="botEnabled ? 'Bot ON' : 'Bot Paused'"></span>
            </button>

            <!-- Delete Conversation Button -->
            <form action="{{ route('conversations.destroy', $conversation) }}" method="POST" class="inline shrink-0" onsubmit="return confirm('Are you sure you want to delete this conversation and all its messages?');">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-2.5 py-1.5 rounded-xl text-[11px] sm:text-xs font-bold transition border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 hover:border-red-300 cursor-pointer select-none shadow-2xs"
                    title="Delete this conversation thread"
                >
                    <i class="fa-solid fa-trash-can text-xs"></i>
                    <span class="hidden sm:inline">Delete</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Messages Feed Scroll Container -->
    <div id="messages-container" class="flex-1 p-3.5 sm:p-6 overflow-y-auto bg-slate-50/50 flex flex-col space-y-3 sm:space-y-4" x-data="{
        init() {
            // Auto scroll to bottom on load
            this.$nextTick(() => { this.$el.scrollTop = this.$el.scrollHeight; });
            
            // Poll for new messages every 3 seconds
            setInterval(() => {
                fetch(window.location.href)
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('messages-container');
                        if (newContainer && this.$el.innerHTML !== newContainer.innerHTML) {
                            this.$el.innerHTML = newContainer.innerHTML;
                            this.$el.scrollTop = this.$el.scrollHeight;
                        }
                    });
            }, 3000);
        }
    }">
        @forelse($conversation->messages as $msg)
            @if($msg->direction === 'incoming')
                <!-- Incoming User Message (Left) -->
                <div class="flex items-start gap-2 sm:gap-2.5 max-w-[88%] sm:max-w-[78%] md:max-w-xl">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0 mt-1 shadow-2xs">
                        <i class="fa-solid fa-user text-[10px] sm:text-xs"></i>
                    </div>
                    <div class="bg-white border border-slate-200 text-slate-800 p-3 sm:p-3.5 rounded-2xl rounded-tl-none shadow-xs min-w-0">
                        @if($msg->media_url)
                            @php
                                $mediaPath = ltrim($msg->media_url, '/');
                                $isVideo = str_ends_with(strtolower($mediaPath), '.mp4') || str_ends_with(strtolower($mediaPath), '.webm');
                            @endphp
                            <div class="mb-2 rounded-xl overflow-hidden border border-slate-200 bg-slate-900">
                                @if($isVideo)
                                    <video src="{{ asset($mediaPath) }}" controls class="w-full max-h-48 sm:max-h-60 rounded-xl bg-black" playsinline></video>
                                @else
                                    <a href="{{ asset($mediaPath) }}" target="_blank" class="block group relative">
                                        <img src="{{ asset($mediaPath) }}" alt="Attached Media" class="w-full max-h-48 sm:max-h-60 object-cover rounded-xl group-hover:opacity-95 transition">
                                    </a>
                                @endif
                            </div>
                        @endif
                        <p class="text-xs sm:text-sm font-medium whitespace-pre-wrap break-words leading-relaxed">{{ $msg->message }}</p>
                        <div class="mt-1.5 flex items-center justify-between text-[9px] sm:text-[10px] text-slate-400 gap-3">
                            <span class="font-bold text-indigo-600">Client</span>
                            <span>{{ $msg->sent_at ? $msg->sent_at->format('h:i A') : '' }}</span>
                        </div>
                    </div>
                </div>
            @else
                <!-- Outgoing AI Bot / Agent Message (Right) -->
                <div class="flex items-start justify-end gap-2 sm:gap-2.5 max-w-[88%] sm:max-w-[78%] md:max-w-xl ml-auto">
                    <div class="{{ $msg->is_bot_message ? 'bg-emerald-700' : 'bg-indigo-600' }} text-white p-3 sm:p-3.5 rounded-2xl rounded-tr-none shadow-xs min-w-0">
                        @if($msg->media_url)
                            @php
                                $mediaPath = ltrim($msg->media_url, '/');
                                $isVideo = str_ends_with(strtolower($mediaPath), '.mp4') || str_ends_with(strtolower($mediaPath), '.webm');
                            @endphp
                            <div class="mb-2 rounded-xl overflow-hidden border border-white/20 bg-black/40">
                                @if($isVideo)
                                    <video src="{{ asset($mediaPath) }}" controls class="w-full max-h-48 sm:max-h-60 rounded-xl bg-black" playsinline></video>
                                @else
                                    <a href="{{ asset($mediaPath) }}" target="_blank" class="block group relative">
                                        <img src="{{ asset($mediaPath) }}" alt="Attached Media" class="w-full max-h-48 sm:max-h-60 object-cover rounded-xl group-hover:opacity-95 transition">
                                        <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[9px] sm:text-[10px] px-2 py-0.5 rounded-md backdrop-blur-xs flex items-center gap-1 font-semibold">
                                            <i class="fa-solid fa-expand text-[9px]"></i> View Full
                                        </div>
                                    </a>
                                @endif
                            </div>
                        @endif
                        <p class="text-xs sm:text-sm font-medium whitespace-pre-wrap break-words leading-relaxed">{{ $msg->message }}</p>
                        <div class="mt-1.5 pt-1 border-t border-white/20 flex items-center justify-between text-[9px] sm:text-[10px] text-white/80 gap-3">
                            <span class="font-bold flex items-center gap-1">
                                <i class="fa-solid {{ $msg->is_bot_message ? 'fa-user-tie' : 'fa-headset' }} text-[9px] sm:text-[10px]"></i>
                                <span>{{ $msg->is_bot_message ? 'Consultant' : 'Representative' }}</span>
                            </span>
                            <div class="flex items-center space-x-1">
                                <span>{{ $msg->sent_at ? $msg->sent_at->format('h:i A') : '' }}</span>
                                @if($msg->status === 'read')
                                    <i class="fa-solid fa-check-double text-sky-300 text-[9px] sm:text-[10px]"></i>
                                @else
                                    <i class="fa-solid fa-check text-white/80 text-[9px] sm:text-[10px]"></i>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl {{ $msg->is_bot_message ? 'bg-emerald-600' : 'bg-indigo-600' }} text-white flex items-center justify-center font-bold text-xs shrink-0 mt-1 shadow-2xs">
                        <i class="fa-solid {{ $msg->is_bot_message ? 'fa-robot' : 'fa-headset' }} text-[10px] sm:text-xs"></i>
                    </div>
                </div>
            @endif
        @empty
            <div class="py-12 text-center text-slate-400">
                <i class="fa-solid fa-comments text-3xl text-slate-300 mb-2"></i>
                <p class="text-xs sm:text-sm font-semibold text-slate-600">No messages in this thread yet</p>
                <p class="text-[11px] text-slate-400">Type a message below to start the conversation.</p>
            </div>
        @endforelse
    </div>

    <!-- Reply Box Container -->
    <div class="p-2.5 sm:p-4 border-t border-slate-200 bg-white shrink-0" x-data="{
        message: '',
        isSubmitting: false,
        error: null,
        submitMessage() {
            if (!this.message.trim() || this.isSubmitting) return;
            
            this.isSubmitting = true;
            this.error = null;
            
            fetch('{{ route('conversations.reply', $conversation) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: this.message })
            })
            .then(res => res.json())
            .then(data => {
                this.isSubmitting = false;
                if (data.success) {
                    this.message = '';
                } else {
                    this.error = data.message || 'Failed to send message';
                }
            })
            .catch(err => {
                this.isSubmitting = false;
                this.error = 'Network error occurred';
                console.error(err);
            });
        }
    }">
        <template x-if="error">
            <div class="mb-2 text-xs font-bold text-red-700 bg-red-50 border border-red-200 p-2.5 rounded-xl flex items-center justify-between" x-text="error"></div>
        </template>
        <form @submit.prevent="submitMessage" class="flex items-center gap-2 sm:gap-3">
            <input
                type="text"
                x-model="message"
                class="flex-1 bg-slate-50 border border-slate-300 rounded-xl px-3.5 sm:px-4 py-2 sm:py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all disabled:opacity-50 text-xs sm:text-sm font-medium text-slate-900 placeholder-slate-400 min-w-0"
                placeholder="Type WhatsApp reply message..."
                required
                autocomplete="off"
                :disabled="isSubmitting"
            >
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-1.5 px-4 sm:px-5 py-2 sm:py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs sm:text-sm rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer shrink-0"
                :disabled="isSubmitting"
            >
                <template x-if="isSubmitting">
                    <i class="fa-solid fa-circle-notch fa-spin text-xs sm:text-sm"></i>
                </template>
                <template x-if="!isSubmitting">
                    <div class="flex items-center gap-1.5">
                        <span class="hidden xs:inline">Send</span>
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </div>
                </template>
            </button>
        </form>
    </div>
</div>
@endsection


@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-4 sm:space-y-6" x-data="{
    leadStatus: '{{ $contact->lead_status ?? 'cold' }}',
    isUpdating: false,
    editModalOpen: false,
    editName: '{{ addslashes($contact->name ?? '') }}',
    editPhone: '{{ addslashes($contact->phone ?? '') }}',
    editNotes: '{{ addslashes($contact->notes ?? '') }}',
    changeStatus(newStatus) {
        if (this.isUpdating) return;
        this.isUpdating = true;
        fetch('{{ route('contacts.set-lead-status', $contact) }}', {
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
            this.isUpdating = false;
            if (data.success) {
                this.leadStatus = data.lead_status;
            }
        })
        .catch(err => {
            this.isUpdating = false;
            console.error('Status update failed', err);
        });
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
            <a href="{{ route('contacts.index') }}" class="p-2 sm:p-2.5 bg-white rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition shadow-2xs shrink-0" title="Back to Contacts">
                <i class="fa-solid fa-arrow-left text-xs sm:text-sm"></i>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight truncate">{{ $contact->name ?? 'Unknown Contact' }}</h2>
                    <!-- Lead Badge -->
                    <span
                        class="px-2 sm:px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-bold uppercase tracking-wider flex items-center gap-1 border shrink-0"
                        :class="{
                            'bg-rose-50 text-rose-700 border-rose-200': leadStatus === 'hot',
                            'bg-amber-50 text-amber-800 border-amber-200': leadStatus === 'warm',
                            'bg-sky-50 text-sky-700 border-sky-200': leadStatus === 'cold'
                        }"
                    >
                        <i :class="{
                            'fa-solid fa-fire text-rose-600': leadStatus === 'hot',
                            'fa-solid fa-sun text-amber-600': leadStatus === 'warm',
                            'fa-solid fa-snowflake text-sky-600': leadStatus === 'cold'
                        }"></i>
                        <span x-text="leadStatus + ' lead'"></span>
                    </span>
                </div>
                <p class="text-[11px] sm:text-xs text-slate-500 font-medium truncate mt-0.5">Contact ID: #{{ $contact->id }} • Created {{ $contact->created_at ? $contact->created_at->format('M d, Y') : '' }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
            <!-- Edit Details Button -->
            <button
                type="button"
                @click="editModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-2 text-xs font-bold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 active:bg-slate-100 rounded-xl shadow-2xs transition cursor-pointer"
            >
                <i class="fa-solid fa-pen-to-square text-xs text-indigo-600"></i>
                <span>Edit Lead Info</span>
            </button>

            <!-- Toggle Bot Button -->
            <form action="{{ route('contacts.toggle-bot', $contact) }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 text-xs font-bold rounded-xl border shadow-2xs transition cursor-pointer {{ $contact->chatbot_enabled ? 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100' : 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100' }}"
                >
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $contact->chatbot_enabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                    <i class="fa-solid fa-robot text-xs"></i>
                    <span>{{ $contact->chatbot_enabled ? 'Pause AI Bot' : 'Enable AI Bot' }}</span>
                </button>
            </form>

            @php
                $conversation = $contact->conversations->first();
            @endphp
            @if($conversation)
                <a href="{{ route('conversations.show', $conversation) }}" class="inline-flex items-center gap-1.5 sm:gap-2 px-3.5 sm:px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-xs transition">
                    <i class="fa-solid fa-comments text-xs"></i>
                    <span>Open Live Chat</span>
                </a>
            @endif

            <form action="{{ route('contacts.destroy', $contact) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this contact?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-200 text-red-600 hover:bg-red-50 active:bg-red-100 text-xs font-bold rounded-xl transition cursor-pointer" title="Delete Contact">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Contact Meta Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Profile Card -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-5 sm:p-6 flex flex-col items-center text-center">
            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-black text-xl sm:text-2xl mb-3 sm:mb-4 shadow-sm">
                {{ strtoupper(substr($contact->name ?? 'U', 0, 1)) }}
            </div>
            <h3 class="text-base sm:text-lg font-black text-slate-900">{{ $contact->name ?? 'Unknown Contact' }}</h3>
            <p class="text-xs text-slate-600 font-mono mt-1 font-bold flex items-center gap-1">
                <i class="fa-solid fa-phone text-emerald-600 text-[10px]"></i>
                <span>{{ $contact->formatted_phone }}</span>
            </p>

            <!-- Lead Status Quick Selector -->
            <div class="mt-4 w-full pt-4 border-t border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Change Lead Status</p>
                <div class="grid grid-cols-3 gap-1.5 w-full">
                    <button
                        type="button"
                        @click="changeStatus('hot')"
                        class="px-2 py-1.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer border"
                        :class="leadStatus === 'hot' ? 'bg-rose-500 text-white border-rose-600 shadow-xs' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'"
                    >
                        <i class="fa-solid fa-fire text-[10px]"></i> Hot
                    </button>
                    <button
                        type="button"
                        @click="changeStatus('warm')"
                        class="px-2 py-1.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer border"
                        :class="leadStatus === 'warm' ? 'bg-amber-500 text-white border-amber-600 shadow-xs' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100'"
                    >
                        <i class="fa-solid fa-sun text-[10px]"></i> Warm
                    </button>
                    <button
                        type="button"
                        @click="changeStatus('cold')"
                        class="px-2 py-1.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer border"
                        :class="leadStatus === 'cold' ? 'bg-sky-600 text-white border-sky-700 shadow-xs' : 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100'"
                    >
                        <i class="fa-solid fa-snowflake text-[10px]"></i> Cold
                    </button>
                </div>
            </div>
        </div>

        <!-- Details Card -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-xs border border-slate-200 p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-info text-indigo-500"></i> Contact Information & Lead Intelligence
                </h4>
                <button type="button" @click="editModalOpen = true" class="text-xs font-bold text-indigo-600 hover:underline">
                    Edit Details →
                </button>
            </div>
            
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-sm">
                <div class="bg-slate-50 p-3.5 sm:p-4 rounded-xl border border-slate-200">
                    <dt class="text-xs text-slate-500 font-bold uppercase">Phone Number</dt>
                    <dd class="text-slate-900 font-black font-mono text-sm sm:text-base mt-1">{{ $contact->formatted_phone }}</dd>
                </div>

                <div class="bg-slate-50 p-3.5 sm:p-4 rounded-xl border border-slate-200">
                    <dt class="text-xs text-slate-500 font-bold uppercase">Lead Intent Score</dt>
                    <dd class="text-slate-900 font-bold mt-1 flex items-center gap-2">
                        <span class="text-sm sm:text-base font-black">{{ $contact->lead_score ?? 10 }} / 100</span>
                        <span class="text-xs text-slate-400">(Auto Evaluated)</span>
                    </dd>
                </div>

                <div class="bg-slate-50 p-3.5 sm:p-4 rounded-xl border border-slate-200">
                    <dt class="text-xs text-slate-500 font-bold uppercase">First Interaction</dt>
                    <dd class="text-slate-800 font-semibold text-xs mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-calendar-day text-slate-400 text-[11px]"></i>
                        <span>{{ $contact->first_message_at ? $contact->first_message_at->format('M d, Y h:i A') : 'N/A' }}</span>
                    </dd>
                </div>

                <div class="bg-slate-50 p-3.5 sm:p-4 rounded-xl border border-slate-200">
                    <dt class="text-xs text-slate-500 font-bold uppercase">Last Interaction</dt>
                    <dd class="text-slate-800 font-semibold text-xs mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-clock text-slate-400 text-[11px]"></i>
                        <span>{{ $contact->last_message_at ? $contact->last_message_at->format('M d, Y h:i A') : 'N/A' }}</span>
                    </dd>
                </div>

                @if($contact->notes)
                <div class="sm:col-span-2 bg-indigo-50/60 p-3.5 rounded-xl border border-indigo-100">
                    <dt class="text-[11px] text-indigo-900 font-bold uppercase">Lead Assessment Notes</dt>
                    <dd class="text-xs text-indigo-950 font-medium mt-1 leading-relaxed">{{ $contact->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Message History Card -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 bg-slate-50">
            <h4 class="font-bold text-slate-900 flex items-center gap-2 text-xs sm:text-sm">
                <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i>
                <span>Conversation & History Log</span>
            </h4>
            <span class="text-xs font-bold text-slate-600 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-2xs">
                @php
                    $allMessages = $contact->conversations->flatMap->messages->sortBy('sent_at');
                @endphp
                {{ $allMessages->count() }} Messages Recorded
            </span>
        </div>

        <div class="p-3.5 sm:p-6 bg-slate-50/50 max-h-[500px] overflow-y-auto space-y-3 sm:space-y-4">
            @forelse($allMessages as $msg)
                @if($msg->direction === 'incoming')
                    <div class="flex items-start gap-2 sm:gap-3 max-w-[88%] sm:max-w-xl">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-2xs">
                            <i class="fa-solid fa-user text-[10px] sm:text-xs"></i>
                        </div>
                        <div class="bg-white border border-slate-200 text-slate-800 p-3 sm:p-4 rounded-2xl rounded-tl-none shadow-xs min-w-0">
                            @if($msg->media_url)
                                <div class="mb-2 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                                    <a href="{{ asset(ltrim($msg->media_url, '/')) }}" target="_blank" class="block group">
                                        <img src="{{ asset(ltrim($msg->media_url, '/')) }}" alt="Attached Media" class="w-full max-h-48 sm:max-h-60 object-cover rounded-xl group-hover:opacity-95 transition">
                                    </a>
                                </div>
                            @endif
                            <p class="text-xs sm:text-sm font-medium whitespace-pre-wrap break-words leading-relaxed">{{ $msg->message }}</p>
                            <div class="mt-2 pt-1 border-t border-slate-100 flex items-center justify-between text-[9px] sm:text-[10px] text-slate-400 gap-4">
                                <span class="font-bold text-indigo-600">User (Incoming)</span>
                                <span>{{ $msg->sent_at ? $msg->sent_at->format('M d, h:i A') : '' }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-start justify-end gap-2 sm:gap-3 max-w-[88%] sm:max-w-xl ml-auto">
                        <div class="bg-emerald-700 text-white p-3 sm:p-4 rounded-2xl rounded-tr-none shadow-xs min-w-0">
                            @if($msg->media_url)
                                <div class="mb-2 rounded-xl overflow-hidden border border-white/20 bg-black/10">
                                    <a href="{{ asset(ltrim($msg->media_url, '/')) }}" target="_blank" class="block group relative">
                                        <img src="{{ asset(ltrim($msg->media_url, '/')) }}" alt="Attached Media" class="w-full max-h-48 sm:max-h-60 object-cover rounded-xl group-hover:opacity-95 transition">
                                        <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[9px] sm:text-[10px] px-2 py-0.5 rounded-md backdrop-blur-xs flex items-center gap-1 font-semibold">
                                            <i class="fa-solid fa-expand text-[9px]"></i> View Full
                                        </div>
                                    </a>
                                </div>
                            @endif
                            <p class="text-xs sm:text-sm font-medium whitespace-pre-wrap break-words leading-relaxed">{{ $msg->message }}</p>
                            <div class="mt-2 pt-1 border-t border-emerald-600/50 flex items-center justify-between text-[9px] sm:text-[10px] text-emerald-200 gap-4">
                                <span class="font-bold flex items-center gap-1">
                                    <i class="fa-solid {{ $msg->is_bot_message ? 'fa-user-tie' : 'fa-headset' }} text-[9px] sm:text-[10px]"></i>
                                    <span>{{ $msg->is_bot_message ? 'Consultant Reply' : 'Direct Reply' }}</span>
                                </span>
                                <span>{{ $msg->sent_at ? $msg->sent_at->format('M d, h:i A') : '' }}</span>
                            </div>
                        </div>
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-2xs">
                            <i class="fa-solid {{ $msg->is_bot_message ? 'fa-user-tie' : 'fa-headset' }} text-[10px] sm:text-xs"></i>
                        </div>
                    </div>
                @endif
            @empty
                <div class="py-12 text-center text-slate-500">
                    <p class="text-xs sm:text-sm font-medium">No messages recorded for this contact yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Edit Lead Details Modal -->
    <div
        x-show="editModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-4"
    >
        <div
            @click.away="editModalOpen = false"
            class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-200 w-full max-w-lg p-5 sm:p-7 space-y-4 max-h-[90dvh] overflow-y-auto"
        >
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-indigo-600"></i>
                    <span>Edit Lead Information</span>
                </h3>
                <button type="button" @click="editModalOpen = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <form action="{{ route('contacts.update', $contact) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Contact Name</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ $contact->name }}"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
                        placeholder="e.g. John Doe"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone Number (Real WhatsApp Number)</label>
                    <input
                        type="text"
                        name="phone"
                        value="{{ $contact->phone }}"
                        required
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-mono font-semibold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
                        placeholder="e.g. 919876543210"
                    >
                    <p class="text-[11px] text-slate-400 mt-1">Enter digits with country code (e.g. 917387517576).</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Lead Notes</label>
                    <textarea
                        name="notes"
                        rows="3"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-medium text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
                        placeholder="Add notes about customer requirements..."
                    >{{ $contact->notes }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="editModalOpen = false"
                        class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


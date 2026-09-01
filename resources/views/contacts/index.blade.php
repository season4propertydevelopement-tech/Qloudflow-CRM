@extends('layouts.app')

@section('content')
<div class="space-y-4 sm:space-y-6" x-data="{ importModalOpen: false, fileName: '', isDragging: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-address-book text-emerald-600 text-base sm:text-xl"></i>
                <span>Contacts & Lead Management</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Filter by lead temperature, manage customer profiles, and toggle AI bot status</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <!-- Export Button -->
            <a
                href="{{ route('contacts.export', request()->all()) }}"
                title="Export contacts as Excel / CSV"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 shadow-2xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-file-excel text-emerald-600 text-sm"></i>
                <span>Export Excel</span>
            </a>

            <!-- Import Modal Trigger Button -->
            <button
                type="button"
                @click="importModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-file-import text-sm"></i>
                <span>Import Contacts</span>
            </button>

            <span class="text-xs font-bold text-slate-600 bg-white border border-slate-200 px-3 py-2 rounded-xl shadow-2xs">
                Total: <strong class="text-slate-900">{{ $counts['all'] }}</strong>
            </span>
        </div>
    </div>

    <!-- Filter Tabs & Search Bar -->
    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-white p-2.5 sm:p-3 rounded-2xl border border-slate-200 shadow-xs">
        <!-- Lead Category Filter Tabs (Swipeable on Mobile) -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 sm:pb-0 -mx-1 px-1 flex-nowrap md:flex-wrap shrink-0">
            <a
                href="{{ route('contacts.index', array_merge(request()->except('lead_status', 'page'))) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ !request('lead_status') ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                <span>All Contacts</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ !request('lead_status') ? 'bg-slate-800 text-slate-200' : 'bg-slate-200 text-slate-700' }}">{{ $counts['all'] }}</span>
            </a>

            <a
                href="{{ route('contacts.index', array_merge(request()->except('lead_status', 'page'), ['lead_status' => 'hot'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('lead_status') === 'hot' ? 'bg-rose-600 text-white shadow-xs' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100' }}"
            >
                <i class="fa-solid fa-fire text-xs"></i>
                <span>Hot Leads</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('lead_status') === 'hot' ? 'bg-rose-700 text-rose-100' : 'bg-rose-200/80 text-rose-800' }}">{{ $counts['hot'] }}</span>
            </a>

            <a
                href="{{ route('contacts.index', array_merge(request()->except('lead_status', 'page'), ['lead_status' => 'warm'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('lead_status') === 'warm' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' }}"
            >
                <i class="fa-solid fa-sun text-xs"></i>
                <span>Warm Leads</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('lead_status') === 'warm' ? 'bg-amber-700 text-amber-100' : 'bg-amber-200/80 text-amber-800' }}">{{ $counts['warm'] }}</span>
            </a>

            <a
                href="{{ route('contacts.index', array_merge(request()->except('lead_status', 'page'), ['lead_status' => 'cold'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('lead_status') === 'cold' ? 'bg-sky-600 text-white shadow-xs' : 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100' }}"
            >
                <i class="fa-solid fa-snowflake text-xs"></i>
                <span>Cold Leads</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ request('lead_status') === 'cold' ? 'bg-sky-700 text-sky-100' : 'bg-sky-200/80 text-sky-800' }}">{{ $counts['cold'] }}</span>
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="{{ route('contacts.index') }}" class="flex items-center gap-2 pt-1 md:pt-0">
            @if(request('lead_status'))
                <input type="hidden" name="lead_status" value="{{ request('lead_status') }}">
            @endif
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search name or phone..."
                    class="w-full md:w-56 pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800 placeholder-slate-400"
                >
            </div>
            @if(request('search'))
                <a href="{{ route('contacts.index', request()->except('search')) }}" class="p-2 text-xs text-slate-400 hover:text-slate-600 shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            @endif
        </form>
    </div>

    <!-- Contacts View (Responsive Card List for Mobile, Clean Table for Desktop) -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        
        <!-- Mobile Card List (< md) -->
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($contacts as $contact)
                <div class="p-3.5 space-y-3" x-data="{
                    leadStatus: '{{ $contact->lead_status ?? 'cold' }}',
                    isUpdating: false,
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
                    <!-- Top Row: Avatar, Name, Phone, and Bot Status -->
                    <div class="flex items-start justify-between gap-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-extrabold text-sm shadow-2xs shrink-0">
                                {{ strtoupper(substr($contact->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('contacts.show', $contact) }}" class="font-bold text-slate-900 hover:text-indigo-600 block text-sm truncate">
                                    {{ $contact->name ?? 'Unknown Contact' }}
                                </a>
                                <p class="text-xs font-semibold text-slate-600 font-mono flex items-center gap-1 mt-0.5 truncate">
                                    <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                    <span>{{ $contact->formatted_phone }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 flex flex-col items-end gap-1">
                            @if($contact->chatbot_enabled)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Bot Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    <i class="fa-solid fa-pause text-[8px]"></i>
                                    Bot Paused
                                </span>
                            @endif

                            <span class="text-[10px] text-slate-400 font-medium">
                                {{ $contact->last_message_at ? $contact->last_message_at->diffForHumans() : 'No activity' }}
                            </span>
                        </div>
                    </div>

                    <!-- Temperature Quick Select Bar -->
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <div class="flex items-center gap-1 bg-slate-50 p-1 rounded-xl border border-slate-200">
                            <button
                                type="button"
                                @click="changeStatus('hot')"
                                class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold transition cursor-pointer flex items-center gap-1"
                                :class="leadStatus === 'hot' ? 'bg-rose-500 text-white shadow-2xs' : 'text-rose-700 hover:bg-rose-100'"
                            >
                                <i class="fa-solid fa-fire text-[9px]"></i> Hot
                            </button>
                            <button
                                type="button"
                                @click="changeStatus('warm')"
                                class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold transition cursor-pointer flex items-center gap-1"
                                :class="leadStatus === 'warm' ? 'bg-amber-500 text-white shadow-2xs' : 'text-amber-800 hover:bg-amber-100'"
                            >
                                <i class="fa-solid fa-sun text-[9px]"></i> Warm
                            </button>
                            <button
                                type="button"
                                @click="changeStatus('cold')"
                                class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold transition cursor-pointer flex items-center gap-1"
                                :class="leadStatus === 'cold' ? 'bg-sky-600 text-white shadow-2xs' : 'text-sky-700 hover:bg-sky-100'"
                            >
                                <i class="fa-solid fa-snowflake text-[9px]"></i> Cold
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-1.5">
                            @php
                                $firstConv = $contact->conversations->first();
                            @endphp
                            @if($firstConv)
                                <a href="{{ route('conversations.show', $firstConv) }}" class="p-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100 rounded-xl transition text-xs font-bold" title="Open Chat">
                                    <i class="fa-solid fa-comments text-xs"></i>
                                </a>
                            @endif

                            <a href="{{ route('contacts.show', $contact) }}" class="p-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl transition text-xs font-bold" title="View Profile">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>

                            <form action="{{ route('contacts.toggle-bot', $contact) }}" method="POST" class="inline">
                                @csrf
                                <button
                                    type="submit"
                                    class="p-1.5 rounded-xl border transition cursor-pointer text-xs font-bold {{ $contact->chatbot_enabled ? 'bg-white border-slate-300 text-slate-700' : 'bg-emerald-50 border-emerald-300 text-emerald-700' }}"
                                    title="{{ $contact->chatbot_enabled ? 'Pause AI Bot' : 'Enable AI Bot' }}"
                                >
                                    <i class="fa-solid fa-robot text-xs"></i>
                                </button>
                            </form>

                            <form action="{{ route('contacts.destroy', $contact) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this contact?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-xl transition cursor-pointer" title="Delete Contact">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 px-4 text-center text-slate-500">
                    <i class="fa-solid fa-users-slash text-3xl text-slate-300 mb-2"></i>
                    <p class="text-sm font-semibold text-slate-700">No matching contacts found</p>
                    <p class="text-xs text-slate-400">Incoming messages from customers will show up here in real time.</p>
                </div>
            @endforelse
        </div>

        <!-- Desktop Table View (md+) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider">Contact</th>
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider">Phone Number</th>
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider">Lead Status</th>
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider">Chatbot</th>
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider">Last Activity</th>
                        <th class="py-3.5 px-5 font-bold text-xs text-slate-600 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($contacts as $contact)
                    <tr class="hover:bg-slate-50/90 transition-colors" x-data="{
                        leadStatus: '{{ $contact->lead_status ?? 'cold' }}',
                        isUpdating: false,
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
                        <!-- Contact Info -->
                        <td class="py-4 px-5">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-extrabold text-sm mr-3 shadow-xs shrink-0">
                                    {{ strtoupper(substr($contact->name ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <a href="{{ route('contacts.show', $contact) }}" class="font-bold text-slate-900 hover:text-indigo-600 block text-sm">
                                        {{ $contact->name ?? 'Unknown Contact' }}
                                    </a>
                                    <span class="text-[11px] font-mono text-slate-400">Node: {{ $contact->current_node_id ?? 'start' }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Phone -->
                        <td class="py-4 px-5">
                            <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-800 font-mono">
                                <i class="fa-solid fa-phone text-xs text-slate-400"></i>
                                <span>{{ $contact->formatted_phone }}</span>
                            </div>
                        </td>

                        <!-- Lead Status (Hot / Warm / Cold with Quick Selector) -->
                        <td class="py-4 px-5">
                            <div class="inline-flex items-center gap-1">
                                <!-- Hot Badge -->
                                <button
                                    type="button"
                                    @click="changeStatus('hot')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-extrabold transition cursor-pointer border"
                                    :class="leadStatus === 'hot' ? 'bg-rose-500 text-white border-rose-600 shadow-xs scale-105' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'"
                                    title="Mark as Hot Lead"
                                >
                                    <i class="fa-solid fa-fire text-xs"></i>
                                    <span>Hot</span>
                                </button>

                                <!-- Warm Badge -->
                                <button
                                    type="button"
                                    @click="changeStatus('warm')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-extrabold transition cursor-pointer border"
                                    :class="leadStatus === 'warm' ? 'bg-amber-500 text-white border-amber-600 shadow-xs scale-105' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100'"
                                    title="Mark as Warm Lead"
                                >
                                    <i class="fa-solid fa-sun text-xs"></i>
                                    <span>Warm</span>
                                </button>

                                <!-- Cold Badge -->
                                <button
                                    type="button"
                                    @click="changeStatus('cold')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-extrabold transition cursor-pointer border"
                                    :class="leadStatus === 'cold' ? 'bg-sky-600 text-white border-sky-700 shadow-xs scale-105' : 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100'"
                                    title="Mark as Cold Lead"
                                >
                                    <i class="fa-solid fa-snowflake text-xs"></i>
                                    <span>Cold</span>
                                </button>
                            </div>
                        </td>

                        <!-- Chatbot Status -->
                        <td class="py-4 px-5">
                            <div class="flex items-center gap-1.5">
                                @if($contact->chatbot_enabled)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Bot ON
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                        <i class="fa-solid fa-pause text-[9px]"></i>
                                        Bot Paused
                                    </span>
                                @endif

                                @if($contact->human_handoff)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                        <i class="fa-solid fa-headset text-[9px]"></i>
                                        Human
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Last Activity -->
                        <td class="py-4 px-5 text-xs font-medium text-slate-500">
                            <div class="flex items-center gap-1">
                                <i class="fa-solid fa-clock text-slate-400 text-[10px]"></i>
                                <span>{{ $contact->last_message_at ? $contact->last_message_at->diffForHumans() : 'Never' }}</span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 px-5 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <!-- Toggle Bot -->
                                <form action="{{ route('contacts.toggle-bot', $contact) }}" method="POST" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1.5 rounded-xl border transition cursor-pointer {{ $contact->chatbot_enabled ? 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100' : 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100' }}"
                                        title="{{ $contact->chatbot_enabled ? 'Pause AI Bot for this contact' : 'Enable AI Bot for this contact' }}"
                                    >
                                        <i class="fa-solid fa-robot text-xs"></i>
                                        <span>{{ $contact->chatbot_enabled ? 'Pause' : 'Enable' }}</span>
                                    </button>
                                </form>

                                <!-- View Details -->
                                <a href="{{ route('contacts.show', $contact) }}" class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl transition">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                    <span>View</span>
                                </a>

                                <!-- Delete -->
                                <form action="{{ route('contacts.destroy', $contact) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this contact?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-bold p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-xl transition cursor-pointer" title="Delete Contact">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 px-6 text-center text-slate-500">
                            <div class="max-w-sm mx-auto space-y-2">
                                <i class="fa-solid fa-users-slash text-3xl text-slate-300"></i>
                                <p class="text-sm font-semibold text-slate-700">No matching contacts found</p>
                                <p class="text-xs text-slate-400">Incoming messages from customers will show up here in real time.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contacts->hasPages())
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-slate-200 bg-slate-50/50 overflow-x-auto">
            {{ $contacts->links() }}
        </div>
        @endif
    </div>

    <!-- Import Modal Popup -->
    <div
        x-show="importModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            x-show="importModalOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
            @click="importModalOpen = false"
        ></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div
                x-show="importModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl border border-slate-200 transition-all sm:my-8 sm:w-full sm:max-w-lg"
                @click.stop
            >
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 px-6 py-5 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 shadow-xs">
                            <i class="fa-solid fa-file-excel text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white tracking-tight">Import Contacts via Excel</h3>
                            <p class="text-xs text-slate-300">Upload your Excel spreadsheet (.xlsx) to import leads into CRM</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="importModalOpen = false"
                        class="w-8 h-8 rounded-full hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <!-- Form -->
                <form action="{{ route('contacts.import') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    <!-- Step 1: Download Template Callout -->
                    <div class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <h4 class="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-black">1</span>
                                <span>Download Excel Template (.xlsx)</span>
                            </h4>
                            <p class="text-[11px] text-emerald-800 font-medium">Download sample Excel file with sample contact rows.</p>
                        </div>
                        <a
                            href="{{ route('contacts.template') }}"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition active:scale-95 shrink-0"
                        >
                            <i class="fa-solid fa-file-excel text-xs"></i>
                            <span>Download Excel Template</span>
                        </a>
                    </div>

                    <!-- Step 2: Upload File Drag & Drop Zone -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] font-black">2</span>
                            <span>Upload Filled Excel File (.xlsx)</span>
                        </label>

                        <div
                            class="relative border-2 border-dashed rounded-2xl p-6 text-center transition cursor-pointer"
                            :class="isDragging ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-300 hover:border-emerald-400 bg-slate-50/50 hover:bg-slate-50'"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0].name; }"
                            @click="$refs.fileInput.click()"
                        >
                            <input
                                type="file"
                                name="file"
                                x-ref="fileInput"
                                accept=".xlsx, .xls, .csv, .txt"
                                class="hidden"
                                required
                                @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                            >

                            <template x-if="!fileName">
                                <div class="space-y-2">
                                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 mx-auto flex items-center justify-center shadow-xs">
                                        <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">
                                            <span class="text-emerald-600 underline">Click to browse file</span> or drag & drop here
                                        </p>
                                        <p class="text-[10px] text-slate-500 mt-0.5">Supports CSV, XLSX, XLS files up to 5MB</p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="fileName">
                                <div class="flex items-center justify-center gap-3 py-2">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 shadow-2xs">
                                        <i class="fa-solid fa-file-excel text-lg"></i>
                                    </div>
                                    <div class="text-left min-w-0">
                                        <p class="text-xs font-bold text-slate-900 truncate" x-text="fileName"></p>
                                        <p class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-check-circle text-[10px]"></i>
                                            <span>File ready to import</span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="pt-1 bg-slate-50 p-3 rounded-xl border border-slate-200/80">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input
                                type="checkbox"
                                name="update_existing"
                                value="1"
                                checked
                                class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            >
                            <span class="text-xs text-slate-700 font-medium leading-tight">
                                <strong class="text-slate-900">Update existing contacts</strong> if the phone number already exists in your CRM (recommended)
                            </span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            @click="importModalOpen = false"
                            class="px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!fileName"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95 cursor-pointer disabled:cursor-not-allowed"
                        >
                            <i class="fa-solid fa-file-import text-xs"></i>
                            <span>Import Contacts Now</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection


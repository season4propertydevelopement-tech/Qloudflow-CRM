@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-4 sm:space-y-6" x-data="{
    status: 'checking',
    qrCode: null,
    qrLoading: false,
    phone: '',
    name: '',
    isLoading: false,
    pollInterval: null,
    addModalOpen: false,
    newName: '',
    newPhone: '',
    newRole: 'Sales Consultant',
    newNotifyLeads: true,
    isSavingNumber: false,
    testingNumberId: null,
    feedbackMessage: null,
    feedbackType: 'success',
    
    showFeedback(msg, type = 'success') {
        this.feedbackMessage = msg;
        this.feedbackType = type;
        setTimeout(() => { this.feedbackMessage = null; }, 8000);
    },

    async handleAddNumber() {
        if (!this.newName.trim() || !this.newPhone.trim()) {
            this.showFeedback('Please provide both name and WhatsApp number.', 'error');
            return;
        }
        this.isSavingNumber = true;
        try {
            const res = await fetch('{{ route('whatsapp.numbers.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name: this.newName,
                    phone: this.newPhone,
                    role: this.newRole,
                    notify_new_leads: this.newNotifyLeads
                })
            });
            const data = await res.json();
            this.isSavingNumber = false;
            if (data.success) {
                this.addModalOpen = false;
                this.newName = '';
                this.newPhone = '';
                this.showFeedback(data.message, 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                this.showFeedback(data.message || 'Failed to add number.', 'error');
            }
        } catch (e) {
            this.isSavingNumber = false;
            this.showFeedback('Network error while adding WhatsApp number.', 'error');
        }
    },

    async handleToggleNumber(numberId) {
        try {
            const res = await fetch(`{{ url('/whatsapp/numbers') }}/${numberId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.showFeedback(data.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            }
        } catch (e) {
            this.showFeedback('Failed to update number status.', 'error');
        }
    },

    async handleDeleteNumber(numberId, name) {
        if (!confirm(`Are you sure you want to remove ${name} from receiving WhatsApp lead alerts?`)) return;
        try {
            const res = await fetch(`{{ url('/whatsapp/numbers') }}/${numberId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.showFeedback(data.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            }
        } catch (e) {
            this.showFeedback('Failed to delete number.', 'error');
        }
    },

    async handleTestNumber(numberId) {
        this.testingNumberId = numberId;
        try {
            const res = await fetch(`{{ url('/whatsapp/numbers') }}/${numberId}/test-message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            this.testingNumberId = null;
            if (data.success) {
                this.showFeedback(data.message, 'success');
            } else {
                this.showFeedback(data.error || 'Test alert delivery failed.', 'error');
            }
        } catch (e) {
            this.testingNumberId = null;
            this.showFeedback('Network error while testing WhatsApp number.', 'error');
        }
    },
    
    async fetchStatus() {
        try {
            const res = await fetch('{{ url('/whatsapp/api/status') }}');
            const data = await res.json();
            if (data && data.success) {
                this.status = data.status;
                if (data.status === 'connected') {
                    this.phone = data.phone || 'N/A';
                    this.name = data.name || 'WhatsApp Account';
                    this.qrCode = null;
                    fetch('{{ url('/whatsapp/api/sync') }}').catch(() => {});
                } else if (data.qrAvailable || data.status === 'connecting') {
                    this.fetchQR();
                } else if (data.status === 'disconnected') {
                    if (!this.isLoading && !this.qrCode) {
                        this.handleConnect();
                    }
                }
            }
        } catch (e) {
            console.error('Failed to fetch status', e);
        }
    },
    
    async fetchQR() {
        try {
            this.qrLoading = true;
            const res = await fetch('{{ url('/whatsapp/api/qr') }}');
            const data = await res.json();
            this.qrLoading = false;
            if (data.success && data.qr) {
                this.qrCode = data.qr;
            }
        } catch (e) {
            this.qrLoading = false;
            console.error('Failed to fetch QR', e);
        }
    },
    
    async handleConnect() {
        this.isLoading = true;
        try {
            await fetch('{{ url('/whatsapp/api/connect') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            await this.fetchStatus();
        } catch (e) {
            console.error('Connect failed', e);
        } finally {
            this.isLoading = false;
        }
    },
    
    async handleLogout() {
        if (!confirm('Are you sure you want to disconnect this WhatsApp session?')) return;
        this.isLoading = true;
        try {
            await fetch('{{ url('/whatsapp/api/logout') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            await this.fetchStatus();
        } catch (e) {
            console.error('Logout failed', e);
        } finally {
            this.isLoading = false;
        }
    },
    
    init() {
        this.fetchStatus();
        this.pollInterval = setInterval(() => this.fetchStatus(), 3000);
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-brands fa-whatsapp text-emerald-600 text-base sm:text-xl"></i>
                <span>WhatsApp Device Pairing Hub</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Pair your WhatsApp account to enable AI auto-replies, customer lead capture, and live inbox management</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold shadow-2xs transition">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Main Status & QR Pairing Grid -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 sm:gap-6">
        <!-- Pairing Card (3 cols on desktop) -->
        <div class="md:col-span-3 bg-white rounded-3xl border border-slate-200 p-5 sm:p-7 shadow-xs flex flex-col justify-between text-center relative overflow-hidden">
            <!-- Background Decorative Accent -->
            <div class="absolute -top-24 -right-24 w-48 h-48 rounded-full bg-emerald-500/5 blur-2xl pointer-events-none"></div>

            <div class="space-y-4">
                <!-- Status Badge -->
                <div>
                    <span
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider border shadow-2xs transition-all"
                        :class="{
                            'bg-amber-50 text-amber-800 border-amber-300 animate-pulse': status === 'connecting',
                            'bg-emerald-50 text-emerald-800 border-emerald-300': status === 'connected',
                            'bg-rose-50 text-rose-800 border-rose-300': status === 'disconnected',
                            'bg-slate-100 text-slate-700 border-slate-300': status === 'checking'
                        }"
                    >
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="{
                                'bg-amber-500 animate-ping': status === 'connecting',
                                'bg-emerald-500 animate-pulse': status === 'connected',
                                'bg-rose-500': status === 'disconnected',
                                'bg-slate-400': status === 'checking'
                            }"
                        ></span>
                        <span x-text="status === 'connected' ? 'Connected & Online' : status === 'connecting' ? 'Generating QR Code...' : status === 'disconnected' ? 'Disconnected' : 'Checking Status...'"></span>
                    </span>
                </div>

                <!-- QR Code Box / Connection Info State -->
                <div class="my-3 flex flex-col items-center justify-center">
                    <!-- Disconnected State -->
                    <template x-if="status === 'disconnected' || status === 'checking'">
                        <div class="w-full max-w-[260px] p-6 sm:p-8 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-300 flex flex-col items-center justify-center space-y-3">
                            <div class="w-14 h-14 rounded-2xl bg-slate-200/80 text-slate-500 flex items-center justify-center text-2xl">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <p class="text-xs font-bold text-slate-700">Device Not Paired</p>
                            <p class="text-[11px] text-slate-400 leading-normal">Click the button below to generate a real-time pairing QR code.</p>
                        </div>
                    </template>

                    <!-- Connecting / QR Code State -->
                    <template x-if="status === 'connecting'">
                        <div class="w-full max-w-[260px] p-4 rounded-2xl bg-white border border-slate-200 shadow-md flex flex-col items-center justify-center space-y-3">
                            <div class="w-52 h-52 sm:w-56 sm:h-56 bg-slate-100 rounded-xl flex items-center justify-center overflow-hidden border border-slate-200 p-2">
                                <template x-if="qrCode">
                                    <img :src="qrCode" alt="WhatsApp QR Code" class="w-full h-full object-contain rounded-lg">
                                </template>
                                <template x-if="!qrCode">
                                    <div class="text-center space-y-2 text-slate-500">
                                        <i class="fa-solid fa-circle-notch fa-spin text-2xl text-emerald-600"></i>
                                        <p class="text-xs font-bold">Generating QR Code...</p>
                                    </div>
                                </template>
                            </div>
                            <p class="text-[11px] font-bold text-slate-600 flex items-center gap-1.5 animate-pulse">
                                <i class="fa-solid fa-qrcode text-emerald-600"></i>
                                <span>Scan with WhatsApp on your phone</span>
                            </p>
                        </div>
                    </template>

                    <!-- Connected State -->
                    <template x-if="status === 'connected'">
                        <div class="w-full max-w-sm p-5 rounded-2xl bg-emerald-50/80 border border-emerald-200 text-left space-y-3 shadow-2xs">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center font-bold text-xl shadow-xs">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase text-emerald-700 tracking-wider">Active Device Session</p>
                                    <h4 class="text-sm sm:text-base font-black text-slate-900 truncate" x-text="name"></h4>
                                    <p class="text-xs font-mono font-bold text-emerald-800 flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-phone text-[10px]"></i>
                                        <span x-text="phone"></span>
                                    </p>
                                </div>
                            </div>
                            <div class="pt-2 border-t border-emerald-200/80 text-[11px] text-emerald-900 font-medium flex items-center justify-between">
                                <span class="font-bold">Engine: Baileys v7 Multi-Device</span>
                                <span class="font-black text-emerald-700 flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-100/80 border border-emerald-300">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Live & Ready</span>
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-center gap-2.5">
                <template x-if="status === 'connected'">
                    <button
                        type="button"
                        @click="handleLogout()"
                        :disabled="isLoading"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 active:bg-red-200 border border-red-200 transition cursor-pointer disabled:opacity-50"
                    >
                        <i class="fa-solid fa-right-from-bracket text-xs"></i>
                        <span>Disconnect Session</span>
                    </button>
                </template>

                <template x-if="status === 'disconnected' || status === 'checking'">
                    <button
                        type="button"
                        @click="handleConnect()"
                        :disabled="isLoading"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 shadow-sm transition cursor-pointer disabled:opacity-50"
                    >
                        <i class="fa-solid fa-link text-xs"></i>
                        <span>Pair WhatsApp Device</span>
                    </button>
                </template>

                <template x-if="status === 'connecting'">
                    <button
                        type="button"
                        @click="fetchQR()"
                        :disabled="qrLoading"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition cursor-pointer"
                    >
                        <i class="fa-solid fa-rotate text-xs" :class="qrLoading ? 'fa-spin' : ''"></i>
                        <span>Refresh QR Code</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Instructions & Guidelines Card (2 cols on desktop) -->
        <div class="md:col-span-2 bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-question text-indigo-500"></i>
                    <span>How to Connect</span>
                </h3>

                <ol class="space-y-3 text-xs text-slate-600">
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">1</span>
                        <span>Open <strong>WhatsApp</strong> on your mobile phone.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">2</span>
                        <span>Tap <strong>Menu (⋮)</strong> or <strong>Settings</strong> and select <strong>Linked Devices</strong>.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">3</span>
                        <span>Tap <strong>Link a Device</strong> and point your camera at the QR code on screen.</span>
                    </li>
                </ol>
            </div>

            <!-- Features Info Box -->
            <div class="p-3.5 rounded-2xl bg-indigo-50/70 border border-indigo-100 text-[11px] text-indigo-950 space-y-1.5">
                <div class="flex items-center gap-1.5 font-bold text-indigo-900">
                    <i class="fa-solid fa-shield-halved text-xs text-indigo-600"></i>
                    <span>Qloudflow Multi-Device Ready</span>
                </div>
                <p class="leading-relaxed">
                    Once linked, your bot handles inquiries 24/7 without needing your phone to stay powered on.
                </p>
            </div>
        </div>
    </div>

    <!-- Live Feedback Notification Toast -->
    <template x-if="feedbackMessage">
        <div
            x-transition
            class="p-4 rounded-2xl border flex items-start justify-between gap-3 shadow-sm transition-all"
            :class="feedbackType === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-300' : 'bg-rose-50 text-rose-900 border-rose-300'"
        >
            <div class="flex items-start gap-3">
                <i
                    class="fa-solid text-base mt-0.5"
                    :class="feedbackType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'"
                ></i>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider" x-text="feedbackType === 'success' ? 'Action Completed' : 'Alert Notice'"></h4>
                    <p class="text-xs font-medium mt-0.5" x-text="feedbackMessage"></p>
                </div>
            </div>
            <button type="button" @click="feedbackMessage = null" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </template>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-900 border border-emerald-300 flex items-start gap-3 shadow-2xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base mt-0.5"></i>
            <div class="text-xs font-medium">{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 text-rose-900 border border-rose-300 flex items-start gap-3 shadow-2xs">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-base mt-0.5"></i>
            <div class="text-xs font-medium">{{ session('error') }}</div>
        </div>
    @endif

    <!-- Connected WhatsApp Numbers (Lead Broadcast Network) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-7 shadow-xs space-y-6">
        <!-- Section Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-black shadow-2xs">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                        Connected WhatsApp Numbers & Lead Distribution
                    </h3>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1 max-w-2xl">
                    Add team WhatsApp numbers to receive real-time alerts. When a number is registered, notifications are automatically sent to both the <strong>primary connected WhatsApp device</strong> and the <strong>newly added number</strong>. All new customer leads are automatically broadcast to all connected numbers.
                </p>
            </div>

            <button
                type="button"
                @click="addModalOpen = true"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer shrink-0"
            >
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Add WhatsApp Number</span>
            </button>
        </div>

        <!-- Network Quick Stats Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-700 flex items-center justify-center text-base">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Primary Device</p>
                    <p class="text-xs font-black text-slate-800 truncate" x-text="phone !== 'N/A' ? '+' + phone : 'Checking device...'"></p>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-700 flex items-center justify-center text-base">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Connected Alert Numbers</p>
                    <p class="text-xs font-black text-slate-800">
                        {{ $connectedNumbers->count() }} Registered
                    </p>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-700 flex items-center justify-center text-base">
                    <i class="fa-solid fa-tower-broadcast"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Lead Broadcast Engine</p>
                    <p class="text-xs font-black text-emerald-700 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ $connectedNumbers->where('is_active', true)->count() }} Numbers Active</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Numbers List -->
        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
            @if($connectedNumbers->isEmpty())
                <div class="py-10 px-4 text-center space-y-3 bg-slate-50/50">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-users-slash"></i>
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-black text-slate-800">No Team WhatsApp Numbers Registered Yet</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            Currently, incoming leads are delivered to your primary paired WhatsApp account. Add sales agents and managers to automatically broadcast new customer leads to them.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="addModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add First WhatsApp Number</span>
                    </button>
                </div>
            @else
                <!-- Desktop Table View -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Contact / Name</th>
                                <th class="py-3 px-4">WhatsApp Number</th>
                                <th class="py-3 px-4">Role / Department</th>
                                <th class="py-3 px-4">Lead Alerts</th>
                                <th class="py-3 px-4">Last Alert</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($connectedNumbers as $number)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <!-- Name & Avatar -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                                                {{ strtoupper(substr($number->name, 0, 1)) }}
                                            </div>
                                            <span class="font-bold text-slate-900">{{ $number->name }}</span>
                                        </div>
                                    </td>

                                    <!-- Phone -->
                                    <td class="py-3.5 px-4">
                                        <a
                                            href="https://wa.me/{{ $number->phone }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 font-mono font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200/80 transition"
                                            title="Open in WhatsApp"
                                        >
                                            <i class="fa-brands fa-whatsapp text-xs"></i>
                                            <span>{{ $number->formatted_phone }}</span>
                                        </a>
                                    </td>

                                    <!-- Role -->
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $number->role ?: 'Sales Consultant' }}
                                        </span>
                                    </td>

                                    <!-- Status / Lead Alerts Toggle -->
                                    <td class="py-3.5 px-4">
                                        <button
                                            type="button"
                                            @click="handleToggleNumber({{ $number->id }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition cursor-pointer border shadow-2xs {{ $number->is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-300 hover:bg-slate-200' }}"
                                            title="Click to toggle active broadcast"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full {{ $number->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                            <span>{{ $number->is_active ? 'Active & Receiving' : 'Paused' }}</span>
                                        </button>
                                    </td>

                                    <!-- Last Alert Timestamp -->
                                    <td class="py-3.5 px-4 text-[11px] text-slate-400">
                                        {{ $number->last_notified_at ? $number->last_notified_at->diffForHumans() : 'No alerts yet' }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            <!-- Test Alert Button -->
                                            <button
                                                type="button"
                                                @click="handleTestNumber({{ $number->id }})"
                                                :disabled="testingNumberId === {{ $number->id }}"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition cursor-pointer disabled:opacity-50"
                                                title="Send test WhatsApp alert"
                                            >
                                                <i class="fa-solid fa-paper-plane text-[10px]" :class="testingNumberId === {{ $number->id }} ? 'fa-bounce' : ''"></i>
                                                <span x-text="testingNumberId === {{ $number->id }} ? 'Sending...' : 'Test Alert'"></span>
                                            </button>

                                            <!-- Delete Button -->
                                            <button
                                                type="button"
                                                @click="handleDeleteNumber({{ $number->id }}, '{{ addslashes($number->name) }}')"
                                                class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                title="Remove number"
                                            >
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List (< sm) -->
                <div class="sm:hidden divide-y divide-slate-100">
                    @foreach($connectedNumbers as $number)
                        <div class="p-3.5 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                                        {{ strtoupper(substr($number->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-slate-900 text-sm truncate">{{ $number->name }}</h4>
                                        <p class="text-xs font-mono font-bold text-emerald-700 mt-0.5">
                                            {{ $number->formatted_phone }}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="handleToggleNumber({{ $number->id }})"
                                    class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer {{ $number->is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-slate-100 text-slate-600 border-slate-300' }}"
                                >
                                    {{ $number->is_active ? 'Active' : 'Paused' }}
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-50 text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-bold text-[10px]">
                                    {{ $number->role ?: 'Sales Consultant' }}
                                </span>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="handleTestNumber({{ $number->id }})"
                                        :disabled="testingNumberId === {{ $number->id }}"
                                        class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-[10px] border border-indigo-200 transition"
                                    >
                                        <span x-text="testingNumberId === {{ $number->id }} ? 'Testing...' : 'Test Alert'"></span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="handleDeleteNumber({{ $number->id }}, '{{ addslashes($number->name) }}')"
                                        class="p-1 text-rose-600 hover:text-rose-800"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Modal: Add WhatsApp Number -->
    <div
        x-show="addModalOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        style="display: none;"
    >
        <div
            @click.away="if (!isSavingNumber) addModalOpen = false"
            class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 space-y-5 text-left relative"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900">Add WhatsApp Alert Number</h3>
                        <p class="text-xs text-slate-400 font-medium">Broadcast incoming customer leads to team members</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="addModalOpen = false"
                    :disabled="isSavingNumber"
                    class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="handleAddNumber()" class="space-y-4">
                <!-- Name -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">
                        Full Name / Consultant Name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        x-model="newName"
                        placeholder="e.g. Raj Kumar Dubey"
                        required
                        class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800 placeholder-slate-400"
                    >
                </div>

                <!-- Phone -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">
                        WhatsApp Mobile Number <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            x-model="newPhone"
                            placeholder="e.g. 9619747074 or +919619747074"
                            required
                            class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium font-mono text-slate-800 placeholder-slate-400"
                        >
                    </div>
                    <p class="text-[11px] text-slate-400">Enter a 10-digit Indian number or include your country code.</p>
                </div>

                <!-- Role -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">
                        Role / Designation
                    </label>
                    <input
                        type="text"
                        x-model="newRole"
                        placeholder="e.g. Sales Head, Property Consultant, Lead Closer"
                        class="w-full px-3.5 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800 placeholder-slate-400"
                    >
                </div>

                <!-- Automated Action Notice Box -->
                <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 text-xs text-emerald-950 space-y-2 shadow-2xs">
                    <div class="flex items-center gap-1.5 font-black text-emerald-800 text-[11px] uppercase tracking-wider">
                        <i class="fa-solid fa-bolt text-emerald-600"></i>
                        <span>Automated Instant Notifications</span>
                    </div>
                    <ul class="space-y-1 text-[11px] text-emerald-900 list-disc list-inside leading-relaxed font-medium">
                        <li><strong>Alert connected device:</strong> Sends notification to the primary paired WhatsApp device with this number's details.</li>
                        <li><strong>Welcome added number:</strong> Sends an instant WhatsApp welcome message to this newly added number.</li>
                        <li><strong>Share new leads:</strong> All incoming customer leads (Meta Ads, WhatsApp, CSV) will be broadcast to this number.</li>
                    </ul>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="addModalOpen = false"
                        :disabled="isSavingNumber"
                        class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSavingNumber"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-xl shadow-xs transition cursor-pointer disabled:opacity-50"
                    >
                        <i class="fa-solid fa-circle-notch fa-spin text-xs" x-show="isSavingNumber" style="display: none;"></i>
                        <span x-text="isSavingNumber ? 'Connecting & Sending Alerts...' : 'Save & Send Alerts'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


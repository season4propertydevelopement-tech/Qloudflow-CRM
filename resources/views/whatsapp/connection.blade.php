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
                    // Trigger sync in background to process any unhandled incoming messages
                    fetch('{{ url('/whatsapp/api/sync') }}').catch(() => {});
                } else if (data.status === 'connecting' && data.qrAvailable) {
                    this.fetchQR();
                } else if (data.status === 'disconnected') {
                    this.qrCode = null;
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
</div>
@endsection


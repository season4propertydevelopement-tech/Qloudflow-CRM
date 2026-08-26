@extends('layouts.app')

@section('content')
<div class="space-y-4 sm:space-y-6">
    <!-- Top Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-3 sm:pb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-pink-500 text-white flex items-center justify-center text-sm shadow-sm shadow-indigo-500/25">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">
                    AI Voice Calling Studio
                </h2>
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Universal Agent Online
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">Real-time full-duplex conversational voice caller trained on Qloudsoft Solutions business knowledge base</p>
        </div>

        <!-- Quick Header Action Buttons & Status -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-600 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Latency: <strong id="hud-latency" class="font-mono text-slate-900 font-bold">~240ms</strong></span>
            </div>

            <a href="{{ route('voice-agent.analytics') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-2xs transition">
                <i class="fa-solid fa-chart-line text-emerald-600 text-xs"></i>
                <span>Call Intelligence & History</span>
            </a>
        </div>
    </div>

    <!-- Main Studio 3-Column Responsive Grid -->
    <div class="voice-studio-grid">
        
        <!-- ========================================================================= -->
        <!-- LEFT COLUMN: Universal Agent Profile & Dialer -->
        <!-- ========================================================================= -->
        <div class="space-y-4 sm:space-y-5">
            
            <!-- Universal Agent Profile Card -->
            <div class="voice-panel-card space-y-3.5">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-robot text-indigo-600 text-xs"></i>
                        <span>Universal AI Consultant</span>
                    </h3>
                    <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Always Ready
                    </span>
                </div>

                <div class="flex items-start gap-3.5 p-3 rounded-2xl bg-gradient-to-tr from-slate-50 to-indigo-50/40 border border-slate-200">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-700 to-slate-900 text-white flex items-center justify-center text-2xl shadow-sm shrink-0">
                        👩‍💼
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <h4 class="text-sm font-black text-slate-900 truncate">Avni</h4>
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Active & Ready"></span>
                        </div>
                        <p class="text-xs font-bold text-indigo-600 truncate mt-0.5">Senior Growth Consultant</p>
                        <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">Qloudsoft Solutions • Mumbai</p>
                    </div>
                </div>

                <!-- Capabilities & Business Knowledge Badges -->
                <div class="space-y-1.5 text-[11px]">
                    <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                        <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                        <span>Natural Hindi & Hinglish Conversation</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                        <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                        <span>Plivo Cloud Telephony Outbound (PSTN)</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                        <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                        <span>Websites, Apps, SEO & WhatsApp Auto-flow</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                        <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                        <span>Post-call WhatsApp CRM Follow-up</span>
                    </div>
                </div>
            </div>

            <!-- Outbound Dialer & Caller Information Card -->
            <div class="voice-panel-card space-y-3.5">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-emerald-600 text-xs"></i>
                        <span>Outbound Lead Dialer</span>
                    </h3>
                    @if($contact)
                        <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Linked Contact
                        </span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">
                            Customer / Lead Name
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="text"
                                id="caller-name-input"
                                value="{{ $callerName }}"
                                placeholder="Customer Name (e.g. Rahul Sharma)"
                                class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">
                            Mobile Number (WhatsApp Enabled)
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-phone absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="tel"
                                id="caller-phone-input"
                                value="{{ $callerPhone }}"
                                placeholder="e.g. +91 98765 43210"
                                class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-600">
                                Microphone Input Device
                            </label>
                            <span id="mic-status-indicator" class="text-[10px] font-bold text-emerald-600 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span id="mic-status-text">Headset Ready</span>
                            </span>
                        </div>
                        <div class="relative">
                            <i class="fa-solid fa-headset absolute left-3 top-1/2 -translate-y-1/2 text-indigo-500 text-xs"></i>
                            <select
                                id="mic-device-select"
                                class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                            >
                                <option value="">🎧 Auto-Detect Headset / Default Mic</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dual Call Trigger Buttons (Browser Call & Real Plivo PSTN Call) -->
                    <div class="space-y-2 pt-1">
                        <!-- 1. Browser Voice Call -->
                        <button
                            type="button"
                            id="btn-start-call"
                            class="w-full py-2.5 px-4 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-600 active:scale-[0.99] text-white font-extrabold text-xs sm:text-sm rounded-xl shadow-md shadow-emerald-600/25 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-microphone-lines text-sm"></i>
                            <span>Start Browser Voice Call (Avni)</span>
                        </button>

                        <!-- 2. Plivo Real Phone Call -->
                        <button
                            type="button"
                            id="btn-plivo-call"
                            class="w-full py-2.5 px-4 bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-700 hover:from-indigo-500 hover:to-purple-600 active:scale-[0.99] text-white font-extrabold text-xs sm:text-sm rounded-xl shadow-md shadow-indigo-600/25 transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-phone-volume text-sm"></i>
                            <span>Dial via Plivo (918031803464)</span>
                        </button>
                    </div>

                    <!-- Plivo Telephony Status Pill -->
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-satellite-dish text-indigo-500 text-[10px]"></i>
                            <span>Plivo: <strong class="text-slate-800">Avni (918031803464)</strong></span>
                        </span>
                        <span id="plivo-hud-badge" class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Active ($9.49)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- CENTER COLUMN: Real-Time Calling Stage & 3D Voice Orb -->
        <!-- ========================================================================= -->
        <div>
            <div class="voice-calling-stage">
                
                <!-- Background Ambient Glow -->
                <div class="absolute -top-24 -left-24 w-72 h-72 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -right-24 w-72 h-72 bg-emerald-600/15 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Top Stage HUD -->
                <div class="stage-hud">
                    <div class="stage-timer">
                        <span class="stage-timer-dot"></span>
                        <div id="call-timer">00:00</div>
                    </div>

                    <div id="call-state-badge" class="stage-badge">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span id="state-text">Ready to Call</span>
                    </div>
                </div>

                <!-- 3D Voice Orb Canvas Area -->
                <div class="relative z-10 my-auto flex flex-col items-center justify-center py-4">
                    <div class="visualizer-container">
                        <canvas id="visualizer-canvas"></canvas>
                    </div>

                    <!-- Active Agent Name & Role -->
                    <div class="text-center mt-2">
                        <h3 id="active-agent-name" class="voice-stage-title">
                            Avni
                        </h3>
                        <p id="active-agent-role" class="voice-stage-subtitle">
                            Senior Growth & Digital Solutions Consultant • Qloudsoft Solutions
                        </p>
                    </div>

                    <!-- Live Hearing Speech Bubble (Shows what mic is hearing in real-time) -->
                    <div id="live-speech-bubble" class="mt-3 px-4 py-1.5 rounded-full bg-slate-900/90 border border-slate-700 text-xs font-semibold text-slate-200 max-w-sm text-center truncate shadow-lg transition-all" style="display: none;">
                        <span class="text-indigo-400 font-bold mr-1 animate-pulse">🎙️ You:</span> <span id="live-speech-text" class="text-white">...</span>
                    </div>
                </div>

                <!-- Live Telemetry Stats Bar -->
                <div class="voice-telemetry-grid">
                    <div class="voice-telemetry-item">
                        <span class="label">Sentiment</span>
                        <span id="hud-sentiment" class="val sentiment">Positive</span>
                    </div>
                    <div class="voice-telemetry-item">
                        <span class="label">Voice Style</span>
                        <span id="hud-rate" class="val">Natural HD</span>
                    </div>
                    <div class="voice-telemetry-item">
                        <span class="label">Language</span>
                        <span class="val lang">Hinglish</span>
                    </div>
                    <div class="voice-telemetry-item">
                        <span class="label">Voice Tone</span>
                        <span id="hud-emotion" class="val tone">Female</span>
                    </div>
                </div>

                <!-- Call Control Action Bar (Appears when call is active) -->
                <div id="active-call-controls" class="voice-controls-bar" style="display: none;">
                    <button
                        type="button"
                        id="btn-mute"
                        class="btn-ctrl"
                        title="Mute Microphone"
                    >
                        🎤
                    </button>

                    <button
                        type="button"
                        id="btn-keypad"
                        class="btn-ctrl"
                        title="Dialpad (DTMF)"
                    >
                        🔢
                    </button>

                    <button
                        type="button"
                        id="btn-hold"
                        class="btn-ctrl"
                        title="Hold Call"
                    >
                        ⏸️
                    </button>

                    <button
                        type="button"
                        id="btn-end-call"
                        class="btn-ctrl-end"
                        title="Hang Up Call"
                    >
                        📵
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- RIGHT COLUMN: Live Transcript & Quick Prompts -->
        <!-- ========================================================================= -->
        <div class="space-y-4">
            <div class="voice-panel-card flex flex-col justify-between min-h-[560px]">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                            <i class="fa-solid fa-comments text-indigo-600 text-xs"></i>
                            <span>Live Transcript</span>
                        </h3>
                        <span class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            Live Feed
                        </span>
                    </div>

                    <!-- Scrollable Transcript Feed -->
                    <div id="transcript-feed" class="transcript-feed space-y-2.5 max-h-[300px] overflow-y-auto pr-1 text-xs">
                        <div class="transcript-msg agent bg-indigo-50/70 border border-indigo-100 rounded-xl p-3">
                            <div class="msg-header flex items-center justify-between text-[10px] font-bold text-indigo-900 mb-1">
                                <span>👩‍💼 Avni</span>
                                <span class="text-indigo-400">System</span>
                            </div>
                            <div class="msg-bubble text-slate-800 leading-relaxed">
                                "Start Browser Voice Call" ya "Dial via Plivo" par click karke Avni ke saath baat kijiye!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Quick Chips & Manual Input -->
                <div class="pt-3 border-t border-slate-100 space-y-3 mt-3">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1.5">
                            Quick Hindi Voice Prompts:
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="chip-btn px-2 py-1 text-[11px] font-bold bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-200 hover:border-indigo-200 rounded-lg transition cursor-pointer" data-prompt="Website banane ka kitna charge hoga aur kitne din me banegi?">
                                🌐 Website Cost?
                            </button>
                            <button type="button" class="chip-btn px-2 py-1 text-[11px] font-bold bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-200 hover:border-indigo-200 rounded-lg transition cursor-pointer" data-prompt="Mujhe 15-minute ka quick live demo schedule karna hai.">
                                📅 Book Demo
                            </button>
                            <button type="button" class="chip-btn px-2 py-1 text-[11px] font-bold bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-200 hover:border-indigo-200 rounded-lg transition cursor-pointer" data-prompt="WhatsApp par brochure aur package details bhej dijiye.">
                                📲 WhatsApp Link
                            </button>
                            <button type="button" class="chip-btn px-2 py-1 text-[11px] font-bold bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-200 hover:border-indigo-200 rounded-lg transition cursor-pointer" data-prompt="Google SEO aur local Map ranking ke kya packages hain?">
                                📈 SEO Packages?
                            </button>
                        </div>
                    </div>

                    <!-- Manual Text Input Fallback -->
                    <div class="flex items-center gap-1.5">
                        <input
                            type="text"
                            id="manual-msg-input"
                            placeholder="Type in Hindi/English..."
                            class="flex-1 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <button
                            type="button"
                            id="manual-send-btn"
                            class="w-9 h-9 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl flex items-center justify-center text-xs font-bold transition shrink-0 cursor-pointer shadow-xs"
                            title="Send Message"
                        >
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- POST-CALL CRM INTELLIGENCE MODAL -->
    <!-- ========================================================================= -->
    <div id="analytics-modal" class="modal-overlay">
        <div class="modal-container bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-5 sm:p-6 overflow-hidden">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-emerald-600"></i>
                        <span>Post-Call CRM Intelligence</span>
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">Lead scoring, entity extraction & WhatsApp follow-up</p>
                </div>
                <button type="button" id="btn-close-analytics" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 hover:text-slate-800 hover:bg-slate-200 flex items-center justify-center transition cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div id="analytics-loader" class="text-center py-8" style="display: none;">
                <div class="w-10 h-10 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-xs font-bold text-slate-600">Analyzing speech conversation & generating CRM report...</p>
            </div>

            <div id="analytics-content" class="space-y-4">
                <!-- Lead Score Header -->
                <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center gap-4">
                    <div id="lead-score-val" class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl font-black shadow-md shadow-emerald-600/30 shrink-0">
                        92
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Qualification Rating</span>
                        <h4 id="lead-stage-badge" class="text-sm font-black text-emerald-900">Hot Qualified Prospect</h4>
                        <p id="summary-text" class="text-xs text-slate-600 mt-1 line-clamp-2">Customer showed strong interest in website packages and requested WhatsApp follow-up.</p>
                    </div>
                </div>

                <!-- Extracted Facts Grid -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Customer Name</span>
                        <span id="entity-name" class="font-bold text-slate-800 block mt-0.5 truncate">Rahul Sharma</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Contact Phone</span>
                        <span id="entity-phone" class="font-bold font-mono text-slate-800 block mt-0.5 truncate">+91 98765 43210</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Customer Intent</span>
                        <span id="entity-intent" class="font-bold text-slate-800 block mt-0.5 truncate">Website Development</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Next Action Item</span>
                        <span id="entity-next-step" class="font-bold text-indigo-600 block mt-0.5 truncate">Send Proposal on WhatsApp</span>
                    </div>
                </div>

                <!-- Auto-Generated WhatsApp Follow-Up -->
                <div class="p-3.5 rounded-2xl bg-emerald-50/60 border border-emerald-200 space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-emerald-800">
                        <span>📲 Ready-to-Send WhatsApp Follow-Up</span>
                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-emerald-200 text-emerald-900">1-Click</span>
                    </div>
                    <div id="whatsapp-msg-preview" class="p-2.5 rounded-xl bg-white border border-emerald-200/80 text-xs text-slate-800 leading-relaxed">
                        Namaste Rahul ji! 🌟 Qloudsoft Solutions ki taraf se Avni baat kar rahi hoon. Call par baat karke bahut accha laga. Aapke liye website packages aur proposal details ready hain! 🚀
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" id="btn-copy-wa" class="px-3 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer">
                        📋 Copy Text
                    </button>
                    <button type="button" id="btn-send-whatsapp" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>Dispatch WhatsApp</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- DTMF TELEPHONE KEYPAD MODAL -->
    <!-- ========================================================================= -->
    <div id="dialpad-modal" class="modal-overlay">
        <div class="modal-container bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xs w-full p-5">
            <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-calculator text-indigo-600"></i>
                    <span>Telephone Dialpad</span>
                </h3>
                <button type="button" id="btn-close-dialpad" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-slate-800 flex items-center justify-center cursor-pointer">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
            <div class="dialpad-grid grid grid-cols-3 gap-2">
                <button type="button" class="dialpad-key" data-key="1">1<span>&nbsp;</span></button>
                <button type="button" class="dialpad-key" data-key="2">2<span>ABC</span></button>
                <button type="button" class="dialpad-key" data-key="3">3<span>DEF</span></button>
                <button type="button" class="dialpad-key" data-key="4">4<span>GHI</span></button>
                <button type="button" class="dialpad-key" data-key="5">5<span>JKL</span></button>
                <button type="button" class="dialpad-key" data-key="6">6<span>MNO</span></button>
                <button type="button" class="dialpad-key" data-key="7">7<span>PQRS</span></button>
                <button type="button" class="dialpad-key" data-key="8">8<span>TUV</span></button>
                <button type="button" class="dialpad-key" data-key="9">9<span>WXYZ</span></button>
                <button type="button" class="dialpad-key" data-key="*">*<span>&nbsp;</span></button>
                <button type="button" class="dialpad-key" data-key="0">0<span>+</span></button>
                <button type="button" class="dialpad-key" data-key="#">#<span>&nbsp;</span></button>
            </div>
        </div>
    </div>
</div>

<!-- Stylesheet & Script Injections -->
<link rel="stylesheet" href="{{ asset('assets/voice-agent/css/voice-agent.css') }}?v={{ time() }}">
<script>
    window.PERSONAS_DATA = @json($personas);
    window.SELECTED_PERSONA_ID = "universal";
    window.CSRF_TOKEN = "{{ csrf_token() }}";
    window.PLIVO_PHONE = "918031803464";
    window.PLIVO_AGENT = "Avni";
</script>
<script src="{{ asset('assets/voice-agent/js/audio-effects.js') }}?v={{ time() }}"></script>
<script src="{{ asset('assets/voice-agent/js/visualizer.js') }}?v={{ time() }}"></script>
<script src="{{ asset('assets/voice-agent/js/voice-engine.js') }}?v={{ time() }}"></script>
<script src="{{ asset('assets/voice-agent/js/app.js') }}?v={{ time() }}"></script>
@endsection

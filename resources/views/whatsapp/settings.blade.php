@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-4 sm:space-y-6" x-data="{
    isEnabled: {{ ($settings['is_enabled'] ?? true) ? 'true' : 'false' }},
    scheduleMode: '{{ $settings['schedule_mode'] ?? 'always' }}',
    startTime: '{{ $settings['start_time'] ?? '09:00' }}',
    endTime: '{{ $settings['end_time'] ?? '20:00' }}',
    timezone: '{{ $settings['timezone'] ?? 'Asia/Kolkata' }}',
    activeDays: {{ json_encode($settings['active_days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']) }},
    sendWelcomeMedia: {{ ($settings['send_welcome_media'] ?? true) ? 'true' : 'false' }},
    autoLeadScoring: {{ ($settings['auto_lead_scoring'] ?? true) ? 'true' : 'false' }},
    humanHandoffEnabled: {{ ($settings['human_handoff_enabled'] ?? true) ? 'true' : 'false' }},
    humanKeywords: '{{ addslashes($settings['human_handoff_keywords'] ?? 'human, agent, talk to an expert, representative, live support, consultant, call') }}',
    outOfHoursMessage: `{{ addslashes($settings['out_of_hours_message'] ?? '') }}`,
    
    toggleDay(day) {
        if (this.activeDays.includes(day)) {
            if (this.activeDays.length > 1) {
                this.activeDays = this.activeDays.filter(d => d !== day);
            }
        } else {
            this.activeDays.push(day);
        }
    },
    
    resetDefaults() {
        if (confirm('Restore default bot settings and standard operating schedule (Mon-Sat, 9AM-8PM)?')) {
            this.isEnabled = true;
            this.scheduleMode = 'always';
            this.startTime = '09:00';
            this.endTime = '20:00';
            this.timezone = 'Asia/Kolkata';
            this.activeDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
            this.sendWelcomeMedia = true;
            this.autoLeadScoring = true;
            this.humanHandoffEnabled = true;
            this.humanKeywords = 'human, agent, talk to an expert, representative, live support, consultant, call';
            this.outOfHoursMessage = '🌙 *Thank you for reaching out to Qloudsoft Solutions!*\n\nOur team and AI consultants are currently outside standard business hours (Mon-Sat, 9:00 AM - 8:00 PM).\n\nWe have recorded your inquiry and our consultant will connect with you first thing in the morning.\n\n_💬 Feel free to leave your project requirements or budget here in the meantime._';
        }
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-sliders text-indigo-600 text-base sm:text-xl"></i>
                <span>Bot Automation Settings & Schedule</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Control global AI auto-responder switches, custom working hours schedule, and out-of-hours messages</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Current Live Status Badge -->
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold shadow-xs border {{ $isBotActiveNow ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-300' }}">
                <span class="w-2 h-2 rounded-full {{ $isBotActiveNow ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                <span>{{ $isBotActiveNow ? '● Live & Active Now' : '🌙 Currently Outside Hours / Off' }}</span>
            </div>
        </div>
    </div>

    <!-- Flash Message -->
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm p-4 rounded-2xl flex items-center gap-3 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-500 text-base shrink-0"></i>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('whatsapp.settings.update') }}" class="space-y-6">
        @csrf

        <!-- 1. Master Control Card -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 p-4 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-power-off"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900">Master Automation Controls</h3>
                        <p class="text-[11px] text-slate-400">Global toggles for WhatsApp AI auto-responses and lead intelligence</p>
                    </div>
                </div>
            </div>

            <!-- Global Bot Master Toggle -->
            <div class="p-4 rounded-2xl transition-colors border flex flex-col sm:flex-row sm:items-center justify-between gap-4" :class="isEnabled ? 'bg-indigo-50/50 border-indigo-200' : 'bg-slate-50 border-slate-200'">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-slate-900">Global AI Auto-Responder</span>
                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full" :class="isEnabled ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600'" x-text="isEnabled ? 'ENABLED' : 'PAUSED'"></span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-xl">
                        When enabled, incoming customer messages will receive smart AI replies based on your Qloudsoft Solutions service catalog, packages, and lead routing.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" x-model="isEnabled" class="sr-only peer">
                    <div class="w-12 h-7 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <!-- Feature Toggles Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2">
                <!-- Auto Lead Scoring -->
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-1.5 font-bold text-xs text-slate-800">
                            <i class="fa-solid fa-fire-flame-curved text-rose-500"></i>
                            <span>Auto Lead Scoring</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Classify contacts as Hot, Warm, or Cold dynamically</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                        <input type="hidden" name="auto_lead_scoring" value="0">
                        <input type="checkbox" name="auto_lead_scoring" value="1" x-model="autoLeadScoring" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <!-- Welcome Creative -->
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-1.5 font-bold text-xs text-slate-800">
                            <i class="fa-solid fa-image text-indigo-500"></i>
                            <span>Welcome Creative</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Send brand greeting image card with first welcome message</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                        <input type="hidden" name="send_welcome_media" value="0">
                        <input type="checkbox" name="send_welcome_media" value="1" x-model="sendWelcomeMedia" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- Human Takeover -->
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-1.5 font-bold text-xs text-slate-800">
                            <i class="fa-solid fa-headset text-teal-600"></i>
                            <span>Human Handoff</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Pause bot when user requests live strategy consultant</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                        <input type="hidden" name="human_handoff_enabled" value="0">
                        <input type="checkbox" name="human_handoff_enabled" value="1" x-model="humanHandoffEnabled" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-teal-600"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- 2. Operating Schedule & Active Hours Card -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 p-4 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900">Operating Schedule & Business Hours</h3>
                        <p class="text-[11px] text-slate-400">Configure when the AI bot responds automatically vs out-of-hours note</p>
                    </div>
                </div>
            </div>

            <!-- Schedule Mode Selector (24/7 vs Custom) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- 24/7 Option -->
                <label class="relative flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all" :class="scheduleMode === 'always' ? 'border-emerald-500 bg-emerald-50/40 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                    <input type="radio" name="schedule_mode" value="always" x-model="scheduleMode" class="sr-only">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0" :class="scheduleMode === 'always' ? 'border-emerald-600 bg-emerald-600' : 'border-slate-300'">
                        <div class="w-2 h-2 rounded-full bg-white" x-show="scheduleMode === 'always'"></div>
                    </div>
                    <div>
                        <div class="font-bold text-xs sm:text-sm text-slate-900 flex items-center gap-1.5">
                            <span>24/7 Always Active</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-extrabold uppercase">Recommended</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">Bot answers every incoming lead immediately at all hours of the day</p>
                    </div>
                </label>

                <!-- Custom Operating Hours Option -->
                <label class="relative flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all" :class="scheduleMode === 'custom' ? 'border-indigo-500 bg-indigo-50/40 ring-2 ring-indigo-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'">
                    <input type="radio" name="schedule_mode" value="custom" x-model="scheduleMode" class="sr-only">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0" :class="scheduleMode === 'custom' ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300'">
                        <div class="w-2 h-2 rounded-full bg-white" x-show="scheduleMode === 'custom'"></div>
                    </div>
                    <div>
                        <div class="font-bold text-xs sm:text-sm text-slate-900">Custom Business Hours</div>
                        <p class="text-[11px] text-slate-500 mt-0.5">Bot only auto-replies during specific hours and sends an offline note otherwise</p>
                    </div>
                </label>
            </div>

            <!-- Custom Schedule Settings Area (Collapsible) -->
            <div x-show="scheduleMode === 'custom'" x-collapse class="space-y-4 pt-3 border-t border-slate-100">
                <!-- Time Range & Timezone Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <!-- Start Time -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-regular fa-sun text-amber-500 mr-1"></i> Opening Time
                        </label>
                        <input
                            type="time"
                            name="start_time"
                            x-model="startTime"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                    </div>

                    <!-- End Time -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-regular fa-moon text-indigo-500 mr-1"></i> Closing Time
                        </label>
                        <input
                            type="time"
                            name="end_time"
                            x-model="endTime"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                    </div>

                    <!-- Timezone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-globe text-emerald-500 mr-1"></i> Operating Timezone
                        </label>
                        <select
                            name="timezone"
                            x-model="timezone"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                            <option value="Asia/Kolkata">Asia/Kolkata (IST +5:30) - Mumbai</option>
                            <option value="Asia/Dubai">Asia/Dubai (GST +4:00) - Dubai</option>
                            <option value="Europe/London">Europe/London (GMT / BST) - London</option>
                            <option value="America/New_York">America/New_York (EST -5:00) - New York</option>
                            <option value="America/Los_Angeles">America/Los_Angeles (PST -8:00) - California</option>
                            <option value="Asia/Singapore">Asia/Singapore (SGT +8:00) - Singapore</option>
                            <option value="UTC">UTC (Coordinated Universal Time)</option>
                        </select>
                    </div>
                </div>

                <!-- Active Days Multi-Select Badges -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        <i class="fa-regular fa-calendar-check text-indigo-500 mr-1"></i> Working Days
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="day in [
                            { key: 'mon', label: 'Monday' },
                            { key: 'tue', label: 'Tuesday' },
                            { key: 'wed', label: 'Wednesday' },
                            { key: 'thu', label: 'Thursday' },
                            { key: 'fri', label: 'Friday' },
                            { key: 'sat', label: 'Saturday' },
                            { key: 'sun', label: 'Sunday' }
                        ]" :key="day.key">
                            <button
                                type="button"
                                @click="toggleDay(day.key)"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer select-none flex items-center gap-1.5"
                                :class="activeDays.includes(day.key) ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'"
                            >
                                <i class="fa-solid fa-check text-[10px]" x-show="activeDays.includes(day.key)"></i>
                                <span x-text="day.label"></span>
                            </button>
                        </template>
                    </div>
                    <!-- Hidden Inputs for Array Transmission -->
                    <template x-for="day in activeDays" :key="day">
                        <input type="hidden" name="active_days[]" :value="day">
                    </template>
                </div>
            </div>
        </div>

        <!-- 3. Out of Hours Auto-Responder Message -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 p-4 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-moon"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900">Out-of-Hours Auto-Response Message</h3>
                        <p class="text-[11px] text-slate-400">Sent to leads who message when your business is closed</p>
                    </div>
                </div>
                <span class="text-[10px] text-slate-400 font-semibold hidden sm:inline">Anti-Spam Throttled (1x per 6 hrs)</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Message Content (WhatsApp Formatted)
                </label>
                <textarea
                    name="out_of_hours_message"
                    rows="4"
                    x-model="outOfHoursMessage"
                    required
                    class="w-full px-3.5 py-3 rounded-xl border border-slate-300 bg-slate-50/50 text-slate-900 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none transition leading-relaxed"
                    placeholder="Enter custom offline message..."
                ></textarea>
                <p class="text-[11px] text-slate-400 mt-1">Supports standard WhatsApp formatting: <strong>*bold*</strong>, <em>_italic_</em>, and emoji.</p>
            </div>
        </div>

        <!-- 4. Human Consultation Keywords -->
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 p-4 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900">Human Takeover Trigger Keywords</h3>
                        <p class="text-[11px] text-slate-400">Keywords that immediately alert a live consultant and pause the bot</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Keywords (Comma-separated)
                </label>
                <input
                    type="text"
                    name="human_handoff_keywords"
                    x-model="humanKeywords"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-semibold focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    placeholder="human, agent, talk to an expert, representative, live support"
                >
                <p class="text-[11px] text-slate-400 mt-1">Typing number <strong>5</strong> in the WhatsApp menu will also trigger human handoff automatically.</p>
            </div>
        </div>

        <!-- Sticky Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <button
                type="button"
                @click="resetDefaults()"
                class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
            >
                <i class="fa-solid fa-rotate-left text-xs"></i>
                <span>Reset to Defaults</span>
            </button>

            <button
                type="submit"
                class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-black text-xs sm:text-sm shadow-md shadow-indigo-500/20 transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Save All Settings & Schedule</span>
            </button>
        </div>
    </form>
</div>
@endsection

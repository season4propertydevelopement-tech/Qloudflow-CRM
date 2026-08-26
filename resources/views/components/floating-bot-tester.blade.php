<div
    x-data="{
        isOpen: false,
        isLoading: false,
        inputText: '',
        leadStatus: 'cold',
        leadScore: 10,
        currentNode: 'welcome_node',
        messages: [
            {
                id: 1,
                direction: 'bot',
                text: '👋 *Welcome to Qloudsoft Solutions!* ✨\n_Transforming Ideas into Digital Powerhouses (MMR & Global)_\n\nHow can we help your business grow today?\n1️⃣ 📦 *Packages & Pricing* (SMM, SEO, Meta Ads & Web)\n2️⃣ 🚀 *12 Industry Verticals* (IVF, Jewellery, Real Estate, etc.)\n3️⃣ 🛡️ *Our 5-Step Process & 2-Tier Quality Review*\n4️⃣ 📞 *Office & Contact Details*\n5️⃣ 👤 *Talk to a Strategy Expert*\n\n_💬 Reply with a number (1–5) or type your query directly!_',
                media_url: '{{ asset('media/wellcome-creativity.jpg') }}',
                time: '{{ now()->format('h:i A') }}'
            }
        ],
        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => this.scrollToBottom());
            }
        },
        sendMessage(textToSend = null) {
            const text = (textToSend || this.inputText).trim();
            if (!text || this.isLoading) return;

            const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            this.messages.push({
                id: Date.now(),
                direction: 'user',
                text: text,
                time: nowTime
            });

            this.inputText = '';
            this.isLoading = true;
            this.$nextTick(() => this.scrollToBottom());

            fetch('{{ route('bot.test') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: text })
            })
            .then(res => res.json())
            .then(data => {
                this.isLoading = false;
                if (data.success) {
                    this.messages.push({
                        id: Date.now() + 1,
                        direction: 'bot',
                        text: data.reply,
                        media_url: data.media_url || null,
                        time: data.sent_at || nowTime
                    });
                    this.leadStatus = data.lead_status || 'cold';
                    this.leadScore = data.lead_score || 10;
                    this.currentNode = data.current_node || 'welcome_node';
                } else {
                    this.messages.push({
                        id: Date.now() + 1,
                        direction: 'bot',
                        text: '⚠️ An error occurred while processing your message.',
                        time: nowTime
                    });
                }
                this.$nextTick(() => this.scrollToBottom());
            })
            .catch(err => {
                this.isLoading = false;
                this.messages.push({
                    id: Date.now() + 1,
                    direction: 'bot',
                    text: '⚠️ Could not connect to the bot simulator server.',
                    time: nowTime
                });
                this.$nextTick(() => this.scrollToBottom());
            });
        },
        resetChat() {
            if (confirm('Reset simulator to the initial welcome menu?')) {
                this.isLoading = true;
                fetch('{{ route('bot.test.reset') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    this.isLoading = false;
                    this.leadStatus = 'cold';
                    this.leadScore = 10;
                    this.currentNode = 'welcome_node';
                    this.messages = [{
                        id: Date.now(),
                        direction: 'bot',
                        text: data.welcome_message || '👋 *Welcome to Qloudsoft Solutions!* ✨\n\nHow can we help your business grow today?',
                        media_url: data.media_url ? this.getMediaUrl(data.media_url) : '{{ asset('media/wellcome-creativity.jpg') }}',
                        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    }];
                    this.$nextTick(() => this.scrollToBottom());
                })
                .catch(() => {
                    this.isLoading = false;
                });
            }
        },
        getMediaUrl(url) {
            if (!url) return '';
            if (url.startsWith('http://') || url.startsWith('https://')) return url;
            return '{{ url('/') }}' + (url.startsWith('/') ? url : '/' + url);
        },
        formatText(text) {
            if (!text) return '';
            // Basic formatting for WhatsApp style *bold* and _italic_
            let formatted = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\*(.*?)\*/g, '<strong class=\'font-black\'>$1</strong>')
                .replace(/_(.*?)_/g, '<em class=\'italic\'>$1</em>')
                .replace(/\n/g, '<br>');
            return formatted;
        },
        scrollToBottom() {
            const container = this.$refs.chatContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
    }"
    class="relative z-50"
>
    <!-- Floating Trigger Bubble Button -->
    <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 flex items-center gap-2">
        <button
            type="button"
            @click="toggleChat()"
            class="relative flex items-center gap-2 sm:gap-2.5 px-3.5 sm:px-4 py-2.5 sm:py-3 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-black text-xs rounded-full shadow-2xl hover:shadow-emerald-500/30 transition-all duration-300 transform hover:scale-105 active:scale-95 cursor-pointer border border-emerald-400/30 group"
            :class="isOpen ? 'ring-4 ring-emerald-400/40' : ''"
        >
            <span class="relative flex h-2.5 w-2.5 sm:h-3 sm:w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 sm:h-3 sm:w-3 bg-emerald-400"></span>
            </span>
            <i class="fa-solid fa-robot text-xs sm:text-sm"></i>
            <span class="tracking-wide text-[11px] sm:text-xs">Test Bot</span>
            <span class="bg-emerald-900/60 text-emerald-200 text-[9px] sm:text-[10px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full uppercase tracking-wider hidden xs:inline">Live</span>
        </button>
    </div>

    <!-- WhatsApp Bot Simulator Floating Window -->
    <div
        x-show="isOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-8 scale-95"
        class="fixed inset-x-2 sm:inset-x-auto bottom-16 sm:bottom-22 sm:right-6 w-auto sm:w-[410px] h-[calc(100dvh-5.5rem)] sm:h-[590px] max-h-[85vh] sm:max-h-[82vh] bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-300/80 flex flex-col overflow-hidden z-50 font-sans"
    >
        <!-- Header: WhatsApp Theme -->
        <div class="bg-[#075e54] text-white p-3 sm:p-3.5 px-3.5 sm:px-4 flex items-center justify-between shadow-md shrink-0">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-emerald-800 border-2 border-emerald-400/40 flex items-center justify-center text-emerald-200 font-bold text-base shadow-xs">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 border-2 border-[#075e54] rounded-full"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-black text-sm text-white tracking-tight">Qloudsoft Solutions</h4>
                        <!-- Dynamic Lead Temperature Badge -->
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-2xs"
                            :class="{
                                'bg-rose-500 text-white animate-pulse': leadStatus === 'hot',
                                'bg-amber-500 text-white': leadStatus === 'warm',
                                'bg-emerald-800 text-emerald-200': leadStatus === 'cold'
                            }"
                        >
                            <span x-text="leadStatus === 'hot' ? '🔥 Hot Lead' : leadStatus === 'warm' ? '☀️ Warm Lead' : '❄️ New Inquiry'"></span>
                        </span>
                    </div>
                    <p class="text-[11px] text-emerald-200 font-medium flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Online • Senior Consultant</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <!-- Reset Conversation Button -->
                <button
                    type="button"
                    @click="resetChat()"
                    title="Reset Conversation"
                    class="w-8 h-8 rounded-full hover:bg-white/15 text-emerald-100 flex items-center justify-center transition cursor-pointer"
                >
                    <i class="fa-solid fa-rotate-right text-xs"></i>
                </button>
                <!-- Close Button -->
                <button
                    type="button"
                    @click="toggleChat()"
                    title="Close"
                    class="w-8 h-8 rounded-full hover:bg-white/15 text-emerald-100 flex items-center justify-center transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Chat Stream Body with WhatsApp Wallpaper Texture -->
        <div
            x-ref="chatContainer"
            class="flex-1 p-4 overflow-y-auto space-y-3 bg-[#efeae2] relative"
            style="background-image: radial-gradient(#d1d7db 0.75px, transparent 0.75px); background-size: 14px 14px;"
        >
            <!-- Messages Loop -->
            <template x-for="msg in messages" :key="msg.id">
                <div>
                    <!-- Bot Message (Incoming Bubble) -->
                    <template x-if="msg.direction === 'bot'">
                        <div class="flex items-start gap-2 max-w-[85%]">
                            <div class="bg-white text-slate-800 p-2.5 rounded-2xl rounded-tl-none shadow-xs border border-slate-200/60 text-xs leading-relaxed break-words">
                                <template x-if="msg.media_url">
                                    <div class="mb-2 rounded-xl overflow-hidden border border-slate-200/80 bg-slate-100 shadow-2xs">
                                        <img :src="getMediaUrl(msg.media_url)" alt="Welcome Creative" class="w-full h-auto max-h-44 object-cover rounded-xl transition duration-300 hover:scale-[1.02]">
                                    </div>
                                </template>
                                <div x-html="formatText(msg.text)" class="whitespace-pre-wrap px-1"></div>
                                <div class="text-[9px] text-slate-400 text-right mt-1.5 font-semibold flex items-center justify-end gap-1 px-1">
                                    <span x-text="msg.time"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- User Message (Outgoing Bubble) -->
                    <template x-if="msg.direction === 'user'">
                        <div class="flex items-end justify-end gap-2">
                            <div class="bg-[#d9fdd3] text-slate-900 px-3.5 py-2.5 rounded-2xl rounded-tr-none shadow-xs border border-emerald-200/60 text-xs leading-relaxed max-w-[85%] break-words">
                                <p x-text="msg.text" class="whitespace-pre-wrap font-medium"></p>
                                <div class="text-[9px] text-emerald-800 text-right mt-1 font-semibold flex items-center justify-end gap-1">
                                    <span x-text="msg.time"></span>
                                    <i class="fa-solid fa-check-double text-sky-600 text-[10px]"></i>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- WhatsApp Typing Animation Indicator -->
            <div x-show="isLoading" x-cloak class="flex items-center gap-2 max-w-[80%]">
                <div class="bg-white text-slate-600 px-4 py-3 rounded-2xl rounded-tl-none shadow-xs border border-slate-200/60 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce" style="animation-delay: 300ms"></span>
                    <span class="text-[10px] text-slate-400 font-semibold ml-1.5">typing...</span>
                </div>
            </div>
        </div>

        <!-- Quick Test Chips Carousel -->
        <div class="bg-slate-100/90 border-t border-slate-200 px-3 py-2 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0 mr-1">Quick:</span>
            <button
                type="button"
                @click="sendMessage('Why should I choose Qloudsoft over other agencies?')"
                class="px-2.5 py-1 bg-gradient-to-r from-amber-50 to-rose-50 hover:from-amber-100 hover:to-rose-100 text-rose-800 border border-rose-200 rounded-full text-[11px] font-black shrink-0 transition shadow-2xs cursor-pointer"
            >
                ⭐ Why Qloudsoft?
            </button>
            <button
                type="button"
                @click="sendMessage('1')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                1️⃣ Packages (SMM & Ads)
            </button>
            <button
                type="button"
                @click="sendMessage('2')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                2️⃣ 12 Verticals
            </button>
            <button
                type="button"
                @click="sendMessage('Can you show some project links or portfolio samples?')"
                class="px-2.5 py-1 bg-gradient-to-r from-emerald-50 to-teal-50 hover:from-emerald-100 hover:to-teal-100 text-emerald-800 border border-emerald-200 rounded-full text-[11px] font-black shrink-0 transition shadow-2xs cursor-pointer"
            >
                📂 Project Links & Samples
            </button>
            <button
                type="button"
                @click="sendMessage('What are your SMM and Meta Ads packages for a Jewellery showroom?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                💎 Jewellery Ads
            </button>
            <button
                type="button"
                @click="sendMessage('Do you provide digital marketing and local SEO for IVF clinics?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                🩺 IVF & Clinics
            </button>
            <button
                type="button"
                @click="sendMessage('What is your approval workflow for client deliverables?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                🛡️ 2-Tier Quality Review
            </button>
            <button
                type="button"
                @click="sendMessage('Where is your office located?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                📍 Nallasopara Office
            </button>
        </div>

        <!-- Input Box: WhatsApp Styled Footer -->
        <div class="bg-white p-3 border-t border-slate-200 shrink-0">
            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                <input
                    type="text"
                    x-model="inputText"
                    :disabled="isLoading"
                    placeholder="Type a message or option number..."
                    class="flex-1 bg-slate-100 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-emerald-500 rounded-full px-4 py-2 text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-400/30 outline-none transition disabled:opacity-50"
                    autofocus
                >
                <button
                    type="submit"
                    :disabled="!inputText.trim() || isLoading"
                    class="w-9 h-9 rounded-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-40 text-white flex items-center justify-center shadow-sm transition transform hover:scale-105 active:scale-95 cursor-pointer disabled:cursor-not-allowed shrink-0"
                >
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </form>
        </div>
    </div>
</div>

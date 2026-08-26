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
                text: '👋 *Welcome to Season 4 Property!* 🏡\n_Your Trusted Property Partner_\n\nHow can we assist your property search today?\n1️⃣ 🏢 *Ongoing Project (Naigaon East Township)*\n2️⃣ 📍 *Office & Contact Details*\n3️⃣ 📜 *MahaRERA & Legal Credentials*\n4️⃣ 🔑 *Book a Site Visit / Consultation*\n5️⃣ 👤 *Speak with Raj Kumar Dubey / Expert*\n\n_💬 Reply with a number (1–5) or type your query directly!_',
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
                    this.$nextTick(() => this.scrollToBottom());
                } else {
                    this.messages.push({
                        id: Date.now() + 1,
                        direction: 'bot',
                        text: '⚠️ ' + (data.error || 'Server error occurred. Please try again.'),
                        time: nowTime
                    });
                    this.$nextTick(() => this.scrollToBottom());
                }
            })
            .catch(err => {
                this.isLoading = false;
                this.messages.push({
                    id: Date.now() + 1,
                    direction: 'bot',
                    text: '⚠️ Network connection issue. Please check console.',
                    time: nowTime
                });
                this.$nextTick(() => this.scrollToBottom());
            });
        },
        resetChat() {
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
                this.leadStatus = 'cold';
                this.leadScore = 10;
                this.currentNode = 'welcome_node';
                this.messages = [
                    {
                        id: Date.now(),
                        direction: 'bot',
                        text: data.welcome_message || '👋 *Welcome to Season 4 Property!* 🏡\n\nHow can we assist your property search today?',
                        media_url: data.media_url || null,
                        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    }
                ];
            });
        },
        formatText(text) {
            if (!text) return '';
            // Basic formatting for WhatsApp style *bold*, _italic_, ~strike~
            let formatted = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\*(.*?)\*/g, '<strong class="font-bold text-slate-900">$1</strong>')
                .replace(/_(.*?)_/g, '<em class="italic text-slate-700">$1</em>')
                .replace(/~(.*?)~/g, '<del class="line-through text-slate-400">$1</del>');
            return formatted;
        },
        getMediaUrl(url) {
            if (!url) return '';
            return url.startsWith('http') ? url : (url.startsWith('/') ? url : '/' + url);
        },
        scrollToBottom() {
            if (this.$refs.chatContainer) {
                this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
            }
        }
    }"
    class="fixed bottom-6 right-6 z-50 select-none font-sans"
>
    <!-- Floating Trigger Launcher Pill -->
    <div x-show="!isOpen" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
        <button
            type="button"
            @click="toggleChat()"
            class="group flex items-center gap-3 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white pl-4 pr-5 py-3 rounded-full shadow-2xl hover:shadow-emerald-500/30 transition-all duration-300 transform hover:-translate-y-0.5 active:scale-95 border-2 border-white/20 cursor-pointer"
        >
            <div class="relative">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white">
                    <i class="fa-brands fa-whatsapp text-lg"></i>
                </div>
                <span class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-400 rounded-full border-2 border-emerald-700 animate-pulse"></span>
            </div>
            <div class="text-left">
                <p class="text-xs font-black tracking-tight leading-tight">Test WhatsApp AI Bot</p>
                <p class="text-[10px] text-emerald-100/90 font-medium">Live Simulator & NLP Tester</p>
            </div>
            <span class="w-6 h-6 rounded-full bg-white/15 group-hover:bg-white/25 flex items-center justify-center ml-1 transition">
                <i class="fa-solid fa-chevron-up text-[10px] text-emerald-100"></i>
            </span>
        </button>
    </div>

    <!-- Simulator Modal Window -->
    <div
        x-show="isOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-95"
        class="w-[360px] sm:w-[410px] h-[580px] sm:h-[620px] bg-slate-900 rounded-3xl shadow-2xl border border-slate-700/60 overflow-hidden flex flex-col relative"
    >
        <!-- Header: WhatsApp Theme -->
        <div class="bg-[#075e54] text-white p-3 sm:p-3.5 px-3.5 sm:px-4 flex items-center justify-between shadow-md shrink-0">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-emerald-800 border-2 border-emerald-400/40 flex items-center justify-center text-emerald-200 font-bold text-base shadow-xs">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 border-2 border-[#075e54] rounded-full"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-black text-sm text-white tracking-tight">Season 4 Property</h4>
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
                        <span>Online • Property Advisor</span>
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
                @click="sendMessage('1')"
                class="px-2.5 py-1 bg-gradient-to-r from-amber-50 to-rose-50 hover:from-amber-100 hover:to-rose-100 text-rose-800 border border-rose-200 rounded-full text-[11px] font-black shrink-0 transition shadow-2xs cursor-pointer"
            >
                🏢 Naigaon Township
            </button>
            <button
                type="button"
                @click="sendMessage('What is the price of 1 BHK and 2 BHK flats?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                🏠 1 BHK / 2 BHK Pricing
            </button>
            <button
                type="button"
                @click="sendMessage('Where is your office located?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                📍 Dahisar Office
            </button>
            <button
                type="button"
                @click="sendMessage('Are you Maha RERA registered?')"
                class="px-2.5 py-1 bg-gradient-to-r from-emerald-50 to-teal-50 hover:from-emerald-100 hover:to-teal-100 text-emerald-800 border border-emerald-200 rounded-full text-[11px] font-black shrink-0 transition shadow-2xs cursor-pointer"
            >
                📜 MahaRERA No.
            </button>
            <button
                type="button"
                @click="sendMessage('I want to schedule a site visit this Sunday')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                🔑 Book Site Visit
            </button>
            <button
                type="button"
                @click="sendMessage('Can I speak with Raj Kumar Dubey?')"
                class="px-2.5 py-1 bg-white hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 text-slate-700 border border-slate-200 rounded-full text-[11px] font-bold shrink-0 transition shadow-2xs cursor-pointer"
            >
                👤 Speak with Raj Kumar Dubey
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

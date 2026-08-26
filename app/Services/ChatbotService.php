<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatbotService
{
    protected WhatsAppApiService $apiService;
    protected GeminiService $geminiService;
    protected BotSettingsService $botSettings;
    protected array $flow;

    public function __construct(WhatsAppApiService $apiService, GeminiService $geminiService, BotSettingsService $botSettings)
    {
        $this->apiService = $apiService;
        $this->geminiService = $geminiService;
        $this->botSettings = $botSettings;
        $this->flow = $this->loadFlow();
    }

    protected function loadFlow(): array
    {
        $path = storage_path('app/bot_flow.json');
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }
        return [];
    }

    /**
     * Get customized welcome message text.
     */
    public function getWelcomeMessage(): string
    {
        return "👋 Hello! Welcome to *Qloudsoft Solutions*. ✨\n\nHow can our team help your business grow today?\n\n1️⃣ 📦 *Packages & Pricing* (SMM, SEO & Web)\n2️⃣ 🚀 *Industry Solutions* (12+ Verticals)\n3️⃣ 🛡️ *Process & Quality Guarantee*\n4️⃣ 📍 *Office & Contact Info*\n5️⃣ 📞 *Speak with Senior Consultant*\n\n_💬 Reply with a number (1-5) or type your query directly!_";
    }

    /**
     * Get welcome creative image media URL.
     */
    public function getWelcomeMediaUrl(): string
    {
        return asset('media/wellcome-creativity.jpg');
    }

    /**
     * Main message handling entry point.
     */
    public function handleMessage(Contact $contact, Conversation $conversation, string $input): void
    {
        $recipient = $contact->whatsapp_id ?: $contact->phone;

        // Strictly prevent bot from executing or messaging in WhatsApp groups (@g.us)
        if (str_ends_with($recipient, '@g.us') || str_contains($recipient, '@g.us')) {
            Log::info("ChatbotService: Skipped execution for WhatsApp group ({$recipient}). Bot only messages individual contacts.");
            return;
        }

        // Global Bot Enable/Disable Check
        $settings = $this->botSettings->getSettings();
        if (!($settings['is_enabled'] ?? true)) {
            Log::info("ChatbotService: Bot is globally disabled in Settings. Skipping reply to {$recipient}.");
            return;
        }

        // Schedule & Operating Hours Check
        if (!$this->botSettings->isBotActiveNow()) {
            Log::info("ChatbotService: Outside scheduled operating hours for {$recipient}. Sending out-of-hours note.");
            
            // Check if out-of-hours message was already sent in the last 6 hours to avoid spamming
            $lastOutOfHours = Message::where('conversation_id', $conversation->id)
                ->where('direction', 'outgoing')
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();

            if (!$lastOutOfHours) {
                $outOfHoursMsg = $this->botSettings->getOutOfHoursMessage();
                $this->sendReply($contact, $conversation, $outOfHoursMsg);
            }
            return;
        }

        $inputLower = strtolower(trim($input));

        // 1. Check for human consultation trigger (dynamic from settings + default list)
        $handoffKeywords = $this->botSettings->getHumanHandoffKeywords();
        $isHandoffTriggered = ($inputLower === '5');
        if (!$isHandoffTriggered) {
            foreach ($handoffKeywords as $kw) {
                if (str_contains($inputLower, $kw)) {
                    $isHandoffTriggered = true;
                    break;
                }
            }
        }

        if ($isHandoffTriggered) {
            $contact->update([
                'chatbot_enabled' => false,
                'human_handoff' => true,
                'handoff_reason' => 'User requested human consultation'
            ]);
            $reply = "🤝 *Connected!* I've alerted our Senior Growth Consultant for you.\n\n📞 We will connect with you shortly, or feel free to call us directly at *+91 73875 17576*.";
            $this->sendReply($contact, $conversation, $reply);
            return;
        }

        // 2. Check if user sent greeting/main menu OR if this is the very first outgoing bot message
        $outgoingCount = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outgoing')
            ->count();
        $isGreeting = in_array($inputLower, [
            'hi', 'hello', 'hey', 'start', 'menu', 'main menu', 'help', 'welcome', 
            'hlo', 'namaste', 'restart', 'good morning', 'good afternoon', 'good evening', 
            'hiii', 'heyy', 'hii', 'hy', 'hlw', 'halo', 'hola'
        ]);

        if ($isGreeting || $outgoingCount === 0) {
            $reply = $this->getWelcomeMessage();
            $sendMedia = ($settings['send_welcome_media'] ?? true);
            $mediaUrl = $sendMedia ? $this->getWelcomeMediaUrl() : null;
            $contact->update(['current_node_id' => 'welcome_node']);
            $this->sendReply($contact, $conversation, $reply, $mediaUrl);
            return;
        }

        // 3. Exact numbered menu selections (1, 2, 3, 4)
        if (in_array($inputLower, ['1', '2', '3', '4'])) {
            $menuReply = $this->evaluateMenuNumber($inputLower);
            if ($menuReply) {
                $this->sendReply($contact, $conversation, $menuReply);
                return;
            }
        }

        // 4. Fetch recent conversation history for AI context
        $recentMessages = Message::where('conversation_id', $conversation->id)
            ->latest('sent_at')
            ->take(6)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'direction' => $msg->direction,
                    'message' => $msg->message,
                ];
            })
            ->toArray();

        // 5. Try Gemini AI for dynamic natural language intelligence
        $aiReply = $this->geminiService->generateReply($input, $recentMessages);
        if (!empty($aiReply)) {
            $this->sendReply($contact, $conversation, $aiReply);
            return;
        }

        // 6. Multi-Dimensional NLP Semantic Synthesizer
        $customReply = $this->generateSmartCustomReply($input, $recentMessages);
        $this->sendReply($contact, $conversation, $customReply);
    }

    /**
     * Exact numbered menu selections updated with 2025-2026 Package Guide & Company Profile.
     */
    protected function evaluateMenuNumber(string $num): ?string
    {
        switch ($num) {
            case '1':
                return "📦 *Our Popular Growth Packages:*\n\n• 🌐 *Websites & Apps:* ₹15,000 – ₹35,000 (Fast 4-7 days launch)\n• 📈 *Social Media & Ads:* ₹12,000 – ₹54,500/mo\n• 🔍 *Google SEO & Ranking:* ₹12,000 – ₹32,000/mo\n\n_Which service package would you like details for?_ 💬";
            case '2':
                return "🚀 *Industries We Specialize In:*\n\n💎 *Jewellery & Luxury* (Shoots & Festive Ads)\n🩺 *Healthcare & IVF Clinics* (Doctor Reels & SEO)\n🏡 *Real Estate & Interiors* (3D Renders & Lead Gen)\n🍽️ *Restaurants, Manufacturing & Education*\n\n_Which industry is your business in?_ 🎯";
            case '3':
                return "🛡️ *Our 5-Step Process & Quality Promise:*\n\n1️⃣ *Discovery* ➔ 2️⃣ *Strategy* ➔ 3️⃣ *Design & Dev* ➔ 4️⃣ *2-Tier Review* ➔ 5️⃣ *Launch & Growth*\n\n⭐ *Two-Tier Review:* Every project is reviewed by our *Manager & Owner* before delivery, with *1-year free maintenance*! 🚀";
            case '4':
                return "📍 *Qloudsoft Solutions:*\n\n🏢 *Office:* Nallasopara East, Palghar, Maharashtra (MMR)\n📞 *Call / WhatsApp:* +91 73875 17576\n✉️ *Email:* info@qloudsoft.in\n🌐 *Website:* https://qloudsoft.in\n\n_Would you like to schedule a quick 10-minute discovery call?_ ☕";
            default:
                return null;
        }
    }

    /**
     * Multi-Dimensional Semantic NLP Intent Synthesizer with Multilingual & Hinglish Support.
     */
    protected function generateSmartCustomReply(string $input, array $recentMessages = []): string
    {
        $text = strtolower(trim($input));

        // Detect language & script styles
        $isDevanagari = preg_match('/[\x{0900}-\x{097F}]/u', $input); // Devanagari script (Hindi/Marathi)
        $isIndianLang = $isDevanagari || preg_match('/\b(kya|hai|hain|mujhe|hum|karo|bhejo|kitna|kitne|kharcha|chahiye|milega|kidhar|kaha|kaise|namaste|bhai|sir|madam|banao|chalega|kaam|bataye|batao|karein|chahie|lagao|kam|sasta|kiti|ahe|aahe|pahije|sang|dya|shu|thase|che|aapo|koni)\b/i', $text);

        // 1. Discount / Deals / Special Offers / Best Price
        if (preg_match('/\b(discount|special offer|deals?|coupon|best price|negotiat|cheap|affordable|concession|promo|rate cut|any offer|kam|sasta|offer kya|kuch discount|kam karo|rate kam)\b/', $text)) {
            if ($isDevanagari) {
                return "🔥 *स्पेशल ग्रोथ ऑफर:* इस हफ्ते प्रोजेक्ट बुक करने पर *फ्री गूगल SEO सेटअप + 1 साल की फ्री डोमेन और होस्टिंग* शामिल है!\n\nक्या हम यह ऑफर आपके लिए सुरक्षित करें? 😊";
            }
            if ($isIndianLang) {
                return "🔥 *Special Growth Offer:* Is hafte project book karne par *Free Google SEO Setup + 1-Year Free Domain & Hosting* mil raha hai!\n\nKya aapke liye yeh offer reserve karein? 😊";
            }
            return "🔥 *Special Growth Offer:* Book your project this week and get *Free Google SEO Setup + 1-Year Free Domain & Hosting* included!\n\nWould you like me to reserve this offer for you? 😊";
        }

        // 2. "Why choose you" / "Why Qloudsoft" / "Why us" / "Difference" / "Better than others"
        if (preg_match('/(why choose|why you|why qloudsoft|why us|why should|better than|different from|why work with|what makes you|advantages|benefits|kyu choose|fayda kya|fayda|dusro se)/', $text)) {
            if ($isDevanagari) {
                return "⭐ *120+ कंपनियां हम पर क्यों भरोसा करती हैं:*\n\n⚡ *तेज डिलीवरी* (वेबसाइट्स 4–7 दिनों में तैयार)\n🛡️ *2-स्तरीय समीक्षा* (Manager और Owner रिव्यू)\n📈 *सोशल मीडिया और मेटा लीड विज्ञापन*\n💰 *सुरक्षित माइलस्टोन भुगतान*\n\nक्या हम 15 मिनट की त्वरित कॉल शेड्यूल करें? 🚀";
            }
            if ($isIndianLang) {
                return "⭐ *120+ Businesses Hum Par Kyu Bharosa Karte Hain:*\n\n⚡ *Lightning Fast 4–7 Days Delivery*\n🛡️ *2-Tier Approval* (Manager & Owner Review)\n📈 *Full-Funnel Social Media & Meta Ads*\n💰 *Safe Milestone-Based Payments*\n\nKya hum ek quick 15-minute discovery call schedule karein? 🚀";
            }
            return "⭐ *Why 120+ Businesses Choose Us:*\n\n⚡ *Super Fast Delivery* (4–7 Days for Websites)\n🛡️ *2-Tier Approval* (Manager & Owner Review)\n📈 *Full-Funnel Social Media & Lead Ads*\n💰 *Transparent Milestone Payments*\n\nShall we schedule a quick 15-min discovery call? 🚀";
        }

        // 3. Trust / Guarantee / Quality / Security / Approval Workflow
        if (preg_match('/\b(guarantee|trust|secure|security|reliable|safety|scam|proof|milestone|safe|genuine|legit|approval|workflow|process|quality|bharosa|viswas|guaranty)\b/', $text)) {
            if ($isDevanagari) {
                return "🛡️ क्लाउडसॉफ्ट में हर प्रोजेक्ट मैनेजर और ओनर दोनों द्वारा रिव्यू होता है, साथ ही *1 साल का फ्री मेंटेनेंस* और माइलस्टोन सुरक्षा मिलती है! ✨";
            }
            if ($isIndianLang) {
                return "🛡️ Qloudsoft me har project ko deliver karne se pehle Manager aur Owner dono review karte hain, sath hi *1-Year Free Maintenance* aur milestone safety milti hai! ✨";
            }
            return "🛡️ Every deliverable at Qloudsoft passes a mandatory 2-tier review by our Manager & Owner, backed by *1-year free maintenance* and milestone safety!\n\nWould you like to see our client case studies? ✨";
        }

        // 4. Meta Ads / SMM / Retainer Pricing
        if (preg_match('/\b(meta ads|google ads|ad spend|management fee|smm|social media management|retainer|retainers|lead ads|campaign)\b/', $text)) {
            if ($isDevanagari) {
                return "📈 *विज्ञापन और SMM पैकेज:*\nहमारे SMM पैकेज ₹12,000–₹54,500/माह हैं, और विज्ञापनों पर 30% मैनेजमेंट शुल्क रहता है।\n\nआपका मासिक लक्ष्य या बजट कितना है? 🎯";
            }
            if ($isIndianLang) {
                return "📈 *Ads & SMM Packages:*\nHumare SMM plans ₹12,000–₹54,500/month hain, aur Meta/Google Ads ka 30% management fee hota hai ad spend par.\n\nAapka monthly target ya budget kitna hai? 🎯";
            }
            return "📈 *Ads & SMM Retainers:*\nOur SMM plans range from ₹12,000–₹54,500/mo, and Meta/Google Ads management is a 30% management fee on monthly ad spend.\n\nWhat is your monthly target or budget? 🎯";
        }

        // 5. Industry-Specific Tailored Knowledge Matching (12 Verticals)
        $industries = [
            'jewellery' => 'Jewellery & Luxury Showrooms',
            'jewelry' => 'Jewellery & Luxury Showrooms',
            'ज्वेलरी' => 'Jewellery Showrooms',
            'ivf' => 'IVF & Fertility Clinics',
            'fertility' => 'IVF & Fertility Clinics',
            'doctor' => 'Healthcare & Doctor Clinics',
            'clinic' => 'Healthcare & Doctor Clinics',
            'hospital' => 'Healthcare & Hospitals',
            'interior' => 'Interior Designers & Architects',
            'architect' => 'Architects & Interior Designers',
            'real estate' => 'Real Estate & Channel Partners',
            'property' => 'Real Estate & Properties',
            'builder' => 'Builders & Real Estate',
            'waterproof' => 'Waterproofing Contractors',
            'automobile' => 'Automobile Garages & Service Centers',
            'garage' => 'Automobile Garages & Services',
            'pest control' => 'Pest Control Services',
            'manufacturing' => 'B2B Manufacturers',
            'restaurant' => 'Restaurants & Cafes',
            'cafe' => 'Cafes & Restaurants',
            'education' => 'Education & Coaching Institutes',
            'school' => 'Schools & Educational Institutes',
            'coaching' => 'Coaching Centres & Institutes',
            'salon' => 'Beauty & Wellness Studios',
            'spa' => 'Beauty & Spa Salons',
            'gym' => 'Gym & Fitness Studios',
        ];

        foreach ($industries as $keyword => $industryName) {
            if (str_contains($text, $keyword) || ($isDevanagari && mb_strpos($input, $keyword) !== false)) {
                if ($isDevanagari) {
                    return "जी बिल्कुल! 🚀 हम *{$industryName}* के लिए विशेष ग्रोथ पैकेज (सोशल मीडिया, लोकल SEO और लीड विज्ञापन) प्रदान करते हैं।\n\nक्या हम आपके व्यवसाय के लिए कस्टमाइज्ड प्रपोजल साझा करें? 💼";
                }
                if ($isIndianLang) {
                    return "Ji bilkul! 🚀 Hum *{$industryName}* ke liye specialized growth packages provide karte hain (targeted social media, local SEO, aur lead ads).\n\nKya aapke {$keyword} business ke liye tailored proposal share karein? 💼";
                }
                return "Yes! 🚀 We provide specialized growth packages for *{$industryName}* with targeted social media, local SEO, and lead ads.\n\nWould you like a customized proposal for your {$keyword} business? 💼";
            }
        }

        // 6. Project Links / Portfolio / Samples / Demos -> 15-Minute Strategy Call
        if (preg_match('/\b(portfolio|samples?|examples?|demos?|case stud(y|ies)|project links?|links?|show me work|previous work|live work|kaam dikhao|sample|purana kaam)\b/', $text)) {
            if ($isDevanagari) {
                return "📂 *लाइव प्रोजेक्ट्स और केस स्टडीज:*\nहमारे पास आपके उद्योग के लाइव काम के नमूने उपलब्ध हैं! ✨\n\nक्या हम लाइव डेमो के लिए *15 मिनट की कॉल* तय करें? 💬\n(या हमें सीधे कॉल करें: *+91 73875 17576*)";
            }
            if ($isIndianLang) {
                return "📂 *Live Projects & Case Studies:*\nHumare paas aapki industry ke live samples available hain! ✨\n\nKya hum ek *Quick 15-Minute Strategy Call* schedule karein live demos dikhane ke liye? 💬\n(Ya hume direct call karein: *+91 73875 17576*)";
            }
            return "📂 *Live Case Studies & Demos:*\nWe have tailored live work samples for your industry!\n\nLet's schedule a *Quick 15-Minute Strategy Call* to walk you through live demos. When works best for you? 💬\n(Or call us directly at *+91 73875 17576*)";
        }

        // 7. Time / Duration / Fast Delivery
        if (preg_match('/(how much time|how long|duration|days|timeline|when can|fast|urgent|deadline|turnaround|kitne din|kitna time|kab tak|kiti divas)/', $text)) {
            if ($isDevanagari) {
                return "⚡ *तेज डिलीवरी:*\n• 🌐 *वेबसाइट्स:* *4 से 7 दिनों* में तैयार\n• 📱 *कस्टम ऐप्स:* *2 से 3 सप्ताह*\n\nहमारे पास इस सप्ताह का स्लॉट उपलब्ध है—आप कब शुरू करना चाहते हैं? 🚀";
            }
            if ($isIndianLang) {
                return "⚡ *Fast Delivery:*\n• 🌐 *Websites:* *4 se 7 din* me ready\n• 📱 *Custom Apps:* *2 se 3 weeks*\n\nHumare paas is week ka launch slot available hai—aap kab start karna chahte hain? 🚀";
            }
            return "⚡ *Fast Turnaround:*\n• 🌐 *Websites:* Ready in *4 to 7 days*\n• 📱 *Custom Apps:* *2 to 3 weeks*\n\nWe have an immediate launch slot open this week—when are you looking to start? 🚀";
        }

        // 8. Mobile App & Web Development Inquiries
        if (preg_match('/\b(app|mobile|flutter|android|ios|playstore|appstore|website|wordpress|laravel|react|banwani|banwana|chahiye|site)\b/', $text) || ($isDevanagari && (str_contains($input, 'वेबसाइट') || str_contains($input, 'ऐप')))) {
            if ($isDevanagari) {
                return "💻 *वेबसाइट और ऐप डेवलपमेंट:*\nहम आधुनिक वेबसाइट्स और मोबाइल ऐप्स *₹15,000 से ₹35,000* में तैयार करते हैं (1 साल का मेंटेनेंस और SEO शामिल)! 💡\n\nआपको किस तरह की वेबसाइट/ऐप बनवानी है?";
            }
            if ($isIndianLang) {
                return "💻 *Web & App Development:*\nHum custom high-speed websites aur mobile apps banate hain *₹15,000 se ₹35,000* me (1-year free support aur SEO included)!\n\nAapko kis type ki website/app banwani hai? 💡";
            }
            return "💻 *Web & App Development:*\nWe engineer custom high-performance websites and Flutter apps starting from *₹15,000 to ₹35,000* with 1-year free support and SEO included!\n\nWhat features do you need? 💡";
        }

        // 9. General Pricing / Cost inquiries
        if (preg_match('/\b(price|pricing|cost|rate|charge|package|budget|how much|charges|fee|fees|quotation|quote|kitna kharcha|kitna lagega|rate kya|paisa|charges kya|kiti kharch|shu kharch)\b/', $text) || ($isDevanagari && (str_contains($input, 'खर्च') || str_contains($input, 'प्राइस') || str_contains($input, 'पैकेज') || str_contains($input, 'कीमत')))) {
            if ($isDevanagari) {
                return "💰 *हमारे पैकेज और मूल्य:*\n• 🌐 *वेबसाइट्स और ऐप्स:* ₹15,000 – ₹35,000 (4-7 दिनों में डिलीवरी)\n• 📈 *SMM रिटेनर:* ₹12,000 – ₹54,500/माह\n• 🔍 *गूगल SEO:* ₹12,000 – ₹32,000/माह\n\nआप किस सेवा के बारे में जानकारी चाहते हैं? 📊";
            }
            if ($isIndianLang) {
                return "💰 *Humare Packages & Pricing:*\n• 🌐 *Websites & Apps:* ₹15,000 – ₹35,000 (4-7 din me launch)\n• 📈 *SMM Retainers:* ₹12,000 – ₹54,500/month\n• 🔍 *Google SEO:* ₹12,000 – ₹32,000/month\n\nAapko kis service ki jankari chahiye? 📊";
            }
            return "💰 *Our Pricing Guide:*\n• *Websites & Apps:* ₹15,000 – ₹35,000\n• *SMM Retainers:* ₹12,000 – ₹54,500/mo\n• *Google SEO:* ₹12,000 – ₹32,000/mo\n\nWhich service are you interested in? 📊";
        }

        // 10. Location & Office
        if (preg_match('/\b(location|office|where|address|nallasopara|palghar|mumbai|mmr|kidhar|kaha|kahan|kuthle|patta)\b/', $text) || ($isDevanagari && (str_contains($input, 'ऑफिस') || str_contains($input, 'कहाँ') || str_contains($input, 'पता')))) {
            if ($isDevanagari) {
                return "📍 *हमारा कार्यालय:*\nहम *नालासोपारा ईस्ट, पालघर (मुंबई MMR)* में स्थित हैं, और मुंबई व देश-विदेश के क्लाइंट्स को सेवा प्रदान करते हैं! 🌍\n\nफोन: *+91 73875 17576* 📞";
            }
            if ($isIndianLang) {
                return "📍 *Humara Office:*\nHum *Nallasopara East, Palghar (Mumbai MMR)* me based hain, aur Mumbai & worldwide clients ko serve karte hain! 🌍\n\nDirect Phone: *+91 73875 17576* 📞";
            }
            return "📍 *Our Office:*\nWe are located in *Nallasopara East, Palghar (MMR)*, serving clients across Mumbai and worldwide! 🌍\n\nDirect Phone: *+91 73875 17576* 📞";
        }

        // 11. Meeting / Call / Discussion inquiries
        if (preg_match('/\b(call|meet|talk|contact|discuss|phone|number|reach|appointment|schedule|baat|phone karo|call karo)\b/', $text) || ($isDevanagari && (str_contains($input, 'कॉल') || str_contains($input, 'बात')))) {
            if ($isDevanagari) {
                return "🤝 हमें आपसे बात करके खुशी होगी!\nआप हमें *+91 73875 17576* पर कॉल कर सकते हैं, या बताएं हम आपको कब कॉल करें? 📱";
            }
            if ($isIndianLang) {
                return "🤝 Hum aapse baat karke khush honge!\nAap hume *+91 73875 17576* par direct call kar sakte hain, ya batayein kab call karna convenient hoga? 📱";
            }
            return "🤝 I'd love to discuss your growth strategy!\nYou can call our team directly at *+91 73875 17576*, or let me know a convenient time to call you back. 📱";
        }

        // 12. Dynamic Conversational Synthesis
        if ($isDevanagari) {
            return "नमस्ते! Qloudsoft Solutions में आपका स्वागत है। ✨\nकृपया अपनी आवश्यकता के बारे में बताएं ताकि हम आपको सही जानकारी और पैकेज बता सकें। 🚀";
        }
        if ($isIndianLang) {
            return "Qloudsoft Solutions me reach out karne ke liye dhanyawad! ✨\nAapki requirement ke baare me thoda batayein taaki hum best solution aur details share kar sakein? 🚀";
        }
        return "Thanks for reaching out to *Qloudsoft Solutions*! ✨\nCould you share a quick line about what you're looking to build or grow, so I can give you the exact details? 🚀";
    }

    /**
     * Dispatch WhatsApp message to API.
     */
    protected function sendReply(Contact $contact, Conversation $conversation, string $reply, ?string $mediaUrl = null)
    {
        $recipient = $contact->whatsapp_id ?: $contact->phone;

        // Never send bot replies to WhatsApp groups (@g.us)
        if (str_ends_with($recipient, '@g.us') || str_contains($recipient, '@g.us')) {
            Log::warning("ChatbotService sendReply: Blocked attempt to send bot message to group ({$recipient}).");
            return;
        }

        if (!str_starts_with($contact->phone, 'simulator_')) {
            if ($mediaUrl) {
                // If local file exists, resolve full public path for Node.js API
                $mediaPath = null;
                $relativePath = ltrim(parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl, '/\\');
                if (file_exists(public_path($relativePath))) {
                    $mediaPath = public_path($relativePath);
                } elseif (file_exists($mediaUrl)) {
                    $mediaPath = $mediaUrl;
                }

                $fullUrl = str_starts_with($mediaUrl, 'http') ? $mediaUrl : asset(ltrim($mediaUrl, '/'));
                $response = $this->apiService->sendMedia($recipient, $fullUrl, $reply, $mediaPath);
                Log::info('WhatsApp API sendMedia Response: ', ['recipient' => $recipient, 'response' => $response]);
            } else {
                $response = $this->apiService->sendMessage($recipient, $reply);
                Log::info('WhatsApp API Response: ', ['recipient' => $recipient, 'response' => $response]);
            }
        }

        $formattedMediaUrl = null;
        if ($mediaUrl) {
            $formattedMediaUrl = '/' . ltrim(parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl, '/');
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outgoing',
            'message' => $reply,
            'media_url' => $formattedMediaUrl,
            'message_type' => $mediaUrl ? 'image' : 'text',
            'is_bot_message' => true,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}

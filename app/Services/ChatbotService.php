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
        return "👋 *Welcome to Season 4 Property!* 🏡\n_Your Trusted Property Partner_\n\nHow can we assist your property search today?\n\n1️⃣ 🏢 *Ongoing Project (Naigaon East Township)*\n2️⃣ 📍 *Office & Contact Details*\n3️⃣ 📜 *MahaRERA & Legal Credentials*\n4️⃣ 🔑 *Book a Site Visit / Consultation*\n5️⃣ 👤 *Speak with Raj Kumar Dubey / Expert*\n\n_💬 Reply with a number (1-5) or type your query directly!_";
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
            $reply = "🤝 *Connected!* I've alerted Raj Kumar Dubey / our Senior Property Consultant.\n\n📞 We will connect with you shortly, or feel free to call us directly at *9619747074*.";
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
     * Exact numbered menu selections for Season 4 Property.
     */
    protected function evaluateMenuNumber(string $num): ?string
    {
        switch ($num) {
            case '1':
                return "🏢 *The Next Big Landmark in Naigaon (Phase 2):*\n\n• 📍 *Location:* Near Don Bosco School, Naigaon East\n• 🏙️ 14-Acre Township, 9 Iconic High-Rise Towers (G+2 Podium+35 Storeys)\n• ✨ 80+ Curated Lifestyle Amenities & Dual-Level Clubhouse\n\n🏠 *Residences & Pricing:*\n🔹 *1 BHK (323 sq.ft + 30 sq.ft Dry Balcony):* ₹39.99 Lakh++\n🔹 *2 BHK (485 sq.ft + 40 sq.ft Dry Balcony):* ₹52.99 Lakh++\n🔹 *2 BHK Large (621 sq.ft + Dry Balcony):* ₹72.99 Lakh++\n\n_Would you like to schedule a site visit or priority registration?_ 🔑";
            case '2':
                return "📍 *Season 4 Property Office & Contact:*\n\n🏢 *Office Address:* Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068\n📞 *Call / WhatsApp:* 9619747074\n✉️ *Email:* rajkumardubey477@gmail.com\n👤 *Proprietor:* Raj Kumar Dubey\n\n_Would you like to visit our office or talk to Raj Kumar Dubey sir?_ ☕";
            case '3':
                return "📜 *Legal & Regulatory Credentials:*\n\n• 🏛️ *Maha RERA Number:* A51900035533\n• 📑 *MSME Udyam Reg. No.:* UDYAM-MH-33-0376504\n• 🆔 *PAN:* AKAPD4856H\n• 🏢 *Registered Enterprise:* Season 4 Property (Proprietary Firm)\n\n_100% verified, legal, and trusted property advisory._ ✨";
            case '4':
                return "🔑 *Book Site Visit & Consultation:*\n\nWe organize guided site visits for our Naigaon East township and Mumbai properties.\n\n📞 *Call / WhatsApp:* 9619747074 (Raj Kumar Dubey)\n📱 *Sales Manager:* 9152244654 (Udesh Khedekar)\n\n_Which day (e.g. Saturday / Sunday) works best for your site visit?_ 🗓️";
            default:
                return null;
        }
    }

    /**
     * Multi-Dimensional Semantic NLP Intent Synthesizer for Season 4 Property.
     */
    protected function generateSmartCustomReply(string $input, array $recentMessages = []): string
    {
        $text = strtolower(trim($input));

        // Detect language & script styles
        $isDevanagari = (bool) preg_match('/[\x{0900}-\x{097F}]/u', $input);
        $isIndianLang = $isDevanagari || (bool) preg_match('/\b(kya|hai|hain|mujhe|hum|karo|bhejo|kitna|kitne|kharcha|chahiye|milega|kidhar|kaha|kaise|namaste|bhai|sir|madam|banao|chalega|kaam|bataye|batao|karein|chahie|lagao|kam|sasta|kiti|ahe|aahe|pahije|sang|dya|shu|thase|che|aapo|koni)\b/i', $text);

        // 1. 1 BHK / 2 BHK / Flat / Naigaon project / Pricing
        if (preg_match('/\b(1\s*bhk|2\s*bhk|bhk|flat|apartment|ghar|home|house|naigaon|tower|phase|residence|39\.99|52\.99|72\.99)\b/i', $text) || ($isDevanagari && (str_contains($input, 'फ्लैट') || str_contains($input, 'घर') || str_contains($input, 'नायगांव')))) {
            if ($isDevanagari) {
                return "🏡 *नायगांव ईस्ट प्रोजेक्ट विवरण:*\n• 🔹 *1 BHK (323 sq.ft + 30 sq.ft ड्राई बालकनी):* ₹39.99 लाख++\n• 🔹 *2 BHK (485 sq.ft + 40 sq.ft ड्राई बालकनी):* ₹52.99 लाख++\n• 🔹 *2 BHK Large (621 sq.ft + ड्राई बालकनी):* ₹72.99 लाख++\n\n80+ एमेनिटीज और 14-एकड़ टाउनशिप। क्या आप साइट विजिट करना चाहेंगे? 🔑";
            }
            if ($isIndianLang) {
                return "🏡 *Naigaon East Landmark Project Details:*\n• 🔹 *1 BHK (323 sq.ft + 30 sq.ft Dry Balcony):* ₹39.99 Lakh++\n• 🔹 *2 BHK (485 sq.ft + 40 sq.ft Dry Balcony):* ₹52.99 Lakh++\n• 🔹 *2 BHK Large (621 sq.ft + Dry Balcony):* ₹72.99 Lakh++\n\n14-Acre Township with 80+ Amenities. Kya aap site visit plan karna chahte hain? 🔑";
            }
            return "🏡 *Naigaon East Landmark Project:*\n• 🔹 *1 BHK (323 sq.ft + 30 sq.ft Dry Balcony):* ₹39.99 Lakh++\n• 🔹 *2 BHK (485 sq.ft + 40 sq.ft Dry Balcony):* ₹52.99 Lakh++\n• 🔹 *2 BHK Large (621 sq.ft + Dry Balcony):* ₹72.99 Lakh++\n\n14-acre township near Don Bosco School. Would you like to schedule a site visit? 🔑";
        }

        // 2. Price / Cost / Rate / Budget / Down payment
        if (preg_match('/\b(price|pricing|cost|rate|rates|budget|how much|charges|kitna|kitne|paisa|kharcha|kiti|emi|loan|down payment)\b/i', $text) || ($isDevanagari && (str_contains($input, 'कीमत') || str_contains($input, 'प्राइस') || str_contains($input, 'बजट')))) {
            if ($isDevanagari) {
                return "💰 *प्रॉपर्टी मूल्य सूची (नायगांव ईस्ट):*\n• *1 BHK:* ₹39.99 लाख से शुरू\n• *2 BHK:* ₹52.99 लाख से शुरू\n• *2 BHK Large:* ₹72.99 लाख से शुरू\n\nअधिक जानकारी और बैंक लोन सहायता के लिए 9619747074 पर संपर्क करें। 📱";
            }
            if ($isIndianLang) {
                return "💰 *Property Pricing (Naigaon East Phase 2):*\n• *1 BHK:* ₹39.99 Lakh++ onwards\n• *2 BHK:* ₹52.99 Lakh++ onwards\n• *2 BHK Large:* ₹72.99 Lakh++ onwards\n\nBank loan & flexible payment plans available. Kya hum call par connect karein? 📱";
            }
            return "💰 *Pricing Overview (Naigaon East Phase 2):*\n• *1 BHK:* Starts at ₹39.99 Lakh++\n• *2 BHK:* Starts at ₹52.99 Lakh++\n• *2 BHK Large:* Starts at ₹72.99 Lakh++\n\nWould you like bank loan assistance or payment plan details? 📱";
        }

        // 3. Office / Location / Address
        if (preg_match('/\b(location|office|where|address|dahisar|bhayandar|mumbai|thane|kidhar|kaha|kahan|kuthle|patta)\b/i', $text) || ($isDevanagari && (str_contains($input, 'ऑफिस') || str_contains($input, 'कहाँ') || str_contains($input, 'पता')))) {
            if ($isDevanagari) {
                return "📍 *हमारा कार्यालय:*\nGround 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.\n\n📞 संपर्क: 9619747074 (राज कुमार दुबे)";
            }
            if ($isIndianLang) {
                return "📍 *Humara Office Address:*\nGround 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.\n\n📞 Contact: 9619747074 (Raj Kumar Dubey)";
            }
            return "📍 *Our Office Address:*\nGround 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.\n\n📞 Call/WhatsApp: 9619747074 (Raj Kumar Dubey)";
        }

        // 4. RERA / Registration / Trust / Legal
        if (preg_match('/\b(rera|maharera|register|registration|legal|udyam|pan|trust|genuine|scam|proof)\b/i', $text) || ($isDevanagari && (str_contains($input, 'रेरा') || str_contains($input, 'रजिस्ट्रेशन')))) {
            if ($isDevanagari) {
                return "📜 *कानूनी पंजीकरण:*\n• 🏛️ *Maha RERA:* A51900035533\n• 📑 *Udyam Registration:* UDYAM-MH-33-0376504\n\nSeason 4 Property एक पूर्णतः पंजीकृत और विश्वसनीय रियल एस्टेट फर्म है। ✨";
            }
            if ($isIndianLang) {
                return "📜 *Legal & RERA Credentials:*\n• 🏛️ *Maha RERA No.:* A51900035533\n• 📑 *MSME Udyam No.:* UDYAM-MH-33-0376504\n\nSeason 4 Property ek fully registered aur trusted real estate firm hai. ✨";
            }
            return "📜 *Legal Credentials:*\n• *Maha RERA No.:* A51900035533\n• *MSME Udyam Reg.:* UDYAM-MH-33-0376504\n\nSeason 4 Property is a certified and trusted real estate partner. ✨";
        }

        // 5. Site Visit / Booking / Contact / Raj Kumar Dubey / Udesh
        if (preg_match('/\b(visit|site visit|booking|book|meet|call|talk|raj kumar|rajkumar|dubey|udesh|khedekar|phone|number|contact)\b/i', $text) || ($isDevanagari && (str_contains($input, 'विजिट') || str_contains($input, 'बुकिंग') || str_contains($input, 'कॉल')))) {
            if ($isDevanagari) {
                return "🤝 *साइट विजिट और संपर्क:*\n• 👤 *राज कुमार दुबे (प्रोपराइटर):* 9619747074\n• 👤 *उमेश खेडेकर (सेल्स मैनेजर):* 9152244654\n\nआप कब साइट विजिट करना चाहते हैं? 🗓️";
            }
            if ($isIndianLang) {
                return "🤝 *Site Visit & Direct Contact:*\n• 👤 *Raj Kumar Dubey (Proprietor):* 9619747074\n• 👤 *Udesh Khedekar (Sales Manager):* 9152244654\n\nAap kis din site visit plan karna chahte hain? 🗓️";
            }
            return "🤝 *Site Visit & Direct Contact:*\n• *Raj Kumar Dubey (Proprietor):* 9619747074\n• *Udesh Khedekar (Sales Manager):* 9152244654\n\nLet us know when you'd like to schedule your site visit. 🗓️";
        }

        // 6. Generic Fallback
        if ($isDevanagari) {
            return "नमस्ते! Season 4 Property में आपका स्वागत है। 🏡\nआप 1 BHK / 2 BHK फ्लैट्स या लोकेशन की जानकारी के लिए हमें 9619747074 पर कॉल भी कर सकते हैं। ✨";
        }
        if ($isIndianLang) {
            return "Namaste! Season 4 Property me aapka swagat hai. 🏡\nAap 1 BHK / 2 BHK flats ya ongoing Naigaon project ke baare me kuch bhi pooch sakte hain! ✨";
        }
        return "Welcome to *Season 4 Property* — Your Trusted Property Partner! 🏡\nHow can we help you with your property search in Mumbai or Naigaon today? ✨";
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

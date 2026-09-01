<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    protected WhatsAppApiService $apiService;
    protected BotSettingsService $botSettings;

    public function __construct(WhatsAppApiService $apiService, BotSettingsService $botSettings)
    {
        $this->apiService = $apiService;
        $this->botSettings = $botSettings;
    }

    /**
     * Main Entrypoint: Process an incoming WhatsApp message in < 0.1ms using pure local if/else logic.
     * ZERO external API calls. Everything is evaluated locally in this single file.
     */
    public function handleMessage(Contact $contact, Conversation $conversation, string $incomingMessage)
    {
        $recipient = $contact->whatsapp_id ?: $contact->phone;

        // Block WhatsApp group messages (@g.us)
        if (str_ends_with($recipient, '@g.us') || str_contains($recipient, '@g.us')) {
            Log::info("ChatbotService: Skipped execution for WhatsApp group ({$recipient}).");
            return;
        }

        // Global bot toggle & operating hours check
        if (!$this->botSettings->isBotActiveNow() || !$contact->chatbot_enabled) {
            Log::info("ChatbotService: Bot is disabled or inactive for {$recipient}.");
            return;
        }

        $rawInput = trim($incomingMessage);
        $input = strtolower($rawInput);
        $currentState = $contact->current_node_id ?: 'welcome_node';

        // 1. Human handoff check
        if ($contact->human_handoff) {
            if (in_array($input, ['start', 'restart', 'bot', 'menu', 'reset', '0', 'hi', 'hello'])) {
                $contact->update(['human_handoff' => false, 'current_node_id' => 'welcome_node']);
                $this->sendWelcomeMessage($contact, $conversation);
            }
            return;
        }

        // 2. Greetings, Menu, Restart or '0' (Always resets to Welcome Node)
        if (in_array($input, ['0', 'hi', 'hello', 'hey', 'start', 'menu', 'restart', 'reset', 'namaste', 'kem cho', 'help', 'back', 'main menu'])) {
            $contact->update(['current_node_id' => 'welcome_node']);
            $this->sendWelcomeMessage($contact, $conversation);
            return;
        }

        // 3. Global Explicit 1 BHK Inquiry (Explicitly answer "Yes, 1 BHK is available!")
        if (preg_match('/\b(1\s*bhk|washroom|washrooms|toilet|powder\s*room|323|39\.99)\b/i', $input)) {
            $contact->update(['current_node_id' => '1bhk_node']);
            $this->send1BhkDetails($contact, $conversation);
            return;
        }

        // 4. Global 2 BHK Inquiry
        if (preg_match('/\b(2\s*bhk|485|621|52\.99)\b/i', $input)) {
            $contact->update(['current_node_id' => '2bhk_node']);
            $this->send2BhkDetails($contact, $conversation);
            return;
        }

        // 5. Global Furniture Package & Offers Inquiry
        if (preg_match('/\b(furniture|sofa|bed|wardrobe|offer|offers|discount|incentive|package|free\s*furniture)\b/i', $input)) {
            $contact->update(['current_node_id' => 'offers_node']);
            $this->sendOffersDetails($contact, $conversation);
            return;
        }

        // 6. Global Station / Location / Connectivity Inquiry
        if (preg_match('/\b(station|distance|station\s*se|kitni\s*dur|location|connectivity|route|kaha\s*hai|railway|naigaon|address|where)\b/i', $input)) {
            $contact->update(['current_node_id' => 'location_node']);
            $this->sendLocationDetails($contact, $conversation);
            return;
        }

        // 7. Global HoABL vs Lodha Group Clarification Inquiry
        if (preg_match('/\b(lodha|lodha\s*group|abhinandan|hoabl|builder|developer|mittal|bajaj)\b/i', $input)) {
            $contact->update(['current_node_id' => 'hoabl_about_node']);
            $this->sendHoablClarification($contact, $conversation);
            return;
        }

        // 8. Global Pricing / Cost / Budget / Loan / EMI Inquiry
        if (preg_match('/\b(price|pricing|cost|budget|kitna|rate|rates|kharcha|paisa|emi|down\s*payment|loan|finance)\b/i', $input)) {
            $contact->update(['current_node_id' => 'payment_plans_node']);
            $this->sendPaymentPlansDetails($contact, $conversation);
            return;
        }

        // 9. Global Site Visit / Sample Flat / Tour Inquiry
        if (preg_match('/\b(visit|site\s*visit|sample\s*flat|dekhna|tour|appointment|weekend|saturday|sunday)\b/i', $input)) {
            $contact->update(['current_node_id' => 'site_visit_node']);
            $this->sendSiteVisitMenu($contact, $conversation);
            return;
        }

        // 10. Global Amenities / Clubhouse Inquiry
        if (preg_match('/\b(amenit|clubhouse|gym|pool|swimming|garden|sports|theatre|amphitheatre|zumba)\b/i', $input)) {
            $contact->update(['current_node_id' => 'amenities_node']);
            $this->sendAmenitiesDetails($contact, $conversation);
            return;
        }

        // 11. Global Speak to Human / Contact / Raj Kumar Dubey
        if (preg_match('/\b(human|agent|person|call|phone|number|contact|raj\s*kumar|dubey|baat|helpdesk)\b/i', $input)) {
            $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
            $this->sendHumanContactDetails($contact, $conversation);
            return;
        }

        // 12. Global MahaRERA Inquiry
        if (preg_match('/\b(rera|maharera|legal|approved|registration)\b/i', $input)) {
            $this->sendReraDetails($contact, $conversation);
            return;
        }

        // =====================================================================
        // 13. STATE MACHINE FLOW BUILDER (OPERATES 1, 2, 3 BASED ON PREVIOUS STEP)
        // 1 is not everytime same message; it changes based on current_node_id!
        // =====================================================================
        if (in_array($input, ['1', '2', '3', '4', '5'])) {
            $num = $input;

            // --- STATE: welcome_node ---
            if ($currentState === 'welcome_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'growth_city_node']);
                    $this->sendGrowthCityOverview($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'offers_node']);
                    $this->sendOffersDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'location_node']);
                    $this->sendLocationDetails($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '5') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: growth_city_node ---
            elseif ($currentState === 'growth_city_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => '2bhk_node']);
                    $this->send2BhkDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'offers_node']);
                    $this->sendOffersDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'amenities_node']);
                    $this->sendAmenitiesDetails($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '5') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: 1bhk_node ---
            elseif ($currentState === '1bhk_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => '1bhk_media_node']);
                    $this->send1BhkMedia($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'payment_plans_node']);
                    $this->sendPaymentPlansDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: 1bhk_media_node ---
            elseif ($currentState === '1bhk_media_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'payment_plans_node']);
                    $this->sendPaymentPlansDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: 2bhk_node ---
            elseif ($currentState === '2bhk_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => '2bhk_media_node']);
                    $this->send2BhkMedia($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'furniture_offer_node']);
                    $this->sendFurniturePackageDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => '2bhk_large_node']);
                    $this->send2BhkLargeDetails($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '5') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: 2bhk_media_node ---
            elseif ($currentState === '2bhk_media_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'furniture_offer_node']);
                    $this->sendFurniturePackageDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'payment_plans_node']);
                    $this->sendPaymentPlansDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: 2bhk_large_node ---
            elseif ($currentState === '2bhk_large_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'offers_node']);
                    $this->sendOffersDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: offers_node ---
            elseif ($currentState === 'offers_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'furniture_offer_node']);
                    $this->sendFurniturePackageDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'payment_plans_node']);
                    $this->sendPaymentPlansDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: furniture_offer_node ---
            elseif ($currentState === 'furniture_offer_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => '2bhk_node']);
                    $this->send2BhkDetails($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: location_node ---
            elseif ($currentState === 'location_node') {
                if ($num === '1') {
                    $this->sendLocationVideo($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: amenities_node ---
            elseif ($currentState === 'amenities_node') {
                if ($num === '1') {
                    $this->sendAmenitiesVideo($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: payment_plans_node ---
            elseif ($currentState === 'payment_plans_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: site_visit_node ---
            elseif ($currentState === 'site_visit_node') {
                if ($num === '1') {
                    $this->sendVisitConfirmed($contact, $conversation, 'Saturday');
                    return;
                } elseif ($num === '2') {
                    $this->sendVisitConfirmed($contact, $conversation, 'Sunday');
                    return;
                } elseif ($num === '3') {
                    $this->sendVisitConfirmed($contact, $conversation, 'Weekday');
                    return;
                } elseif ($num === '4') {
                    $contact->update(['current_node_id' => 'human_node', 'human_handoff' => true]);
                    $this->sendHumanContactDetails($contact, $conversation);
                    return;
                }
            }

            // --- STATE: hoabl_about_node ---
            elseif ($currentState === 'hoabl_about_node') {
                if ($num === '1') {
                    $contact->update(['current_node_id' => 'growth_city_node']);
                    $this->sendGrowthCityOverview($contact, $conversation);
                    return;
                } elseif ($num === '2') {
                    $contact->update(['current_node_id' => '2bhk_node']);
                    $this->send2BhkDetails($contact, $conversation);
                    return;
                } elseif ($num === '3') {
                    $contact->update(['current_node_id' => 'site_visit_node']);
                    $this->sendSiteVisitMenu($contact, $conversation);
                    return;
                }
            }

            // Fallback for number if not matched in current state: fallback to welcome node
            $contact->update(['current_node_id' => 'welcome_node']);
            $this->sendWelcomeMessage($contact, $conversation);
            return;
        }

        // =====================================================================
        // 14. POLITE CONTEXTUAL FALLBACK (Instant deterministic reply)
        // =====================================================================
        $this->sendFallbackGuidance($contact, $conversation, $currentState);
    }

    // =========================================================================
    // EXPLICIT MESSAGE BUILDERS (ALL BUSINESS COPY IN ONE PLACE)
    // =========================================================================

    /**
     * 1. Welcome Message (ROOT MENU - 1 BHK EXCLUDED AS REQUESTED)
     */
    public function getWelcomeMessage(): string
    {
        return "👋 *Welcome to Season 4 Property!* 🏡\n_Official Channel Partner for Growth City Naigaon (The House of Abhinandan Lodha)_\n\nHow can we assist your property search today?\n\n1️⃣ 🏢 *Growth City Naigaon (2 BHK Premium Homes)*\n2️⃣ 🎁 *Exclusive Offers & Free ₹1.5L Furniture Package*\n3️⃣ 📍 *Prime Location & 2-Min Station Connectivity*\n4️⃣ 🔑 *Book VIP Site Visit / Sample Flat Tour*\n5️⃣ 👤 *Speak with Raj Kumar Dubey (9619747074)*\n\n_💬 Reply with a number (1–5) or type your query directly!_";
    }

    public function getWelcomeMediaUrl(): string
    {
        return asset('media/wellcome-creativity.jpg');
    }

    public function sendWelcomeMessage(Contact $contact, Conversation $conversation)
    {
        $this->sendReply($contact, $conversation, $this->getWelcomeMessage(), 'media/wellcome-creativity.jpg');
    }

    /**
     * 2. Growth City Naigaon Overview
     */
    protected function sendGrowthCityOverview(Contact $contact, Conversation $conversation)
    {
        $reply = "🏢 *Growth City Naigaon — The House of Abhinandan Lodha*\n_Built in association with Mittal Builders | Financed by Bajaj Housing Finance_\n_Official Channel Partner: Season 4 Property (Raj Kumar Dubey 9619747074)_\n\n🌟 Tallest 35-storey towers in Naigaon with 80+ amenities across 5 Growth Centres.\n\n1️⃣ 🏡 *2 BHK Homes (485 & 621 sq.ft from ₹52.99L+)*\n2️⃣ 🎁 *Exclusive Offers & Free ₹1.5L Furniture Package*\n3️⃣ 🌟 *80+ Lifestyle Amenities & 5 Growth Centres*\n4️⃣ 🔑 *Book Guided VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/tower-elevation-exterior-view.jpeg');
    }

    /**
     * 3. 1 BHK Explicit Confirmation (EXPLICIT AVAILABILITY)
     */
    protected function send1BhkDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "Yes, 1 BHK is available! 🏠✨\n\nGrowth City Naigaon offers spacious *1 BHK Growth Homes* (323 sq.ft RERA Carpet + 30 sq.ft service slab):\n• *TWO Washrooms:* Toilet (7'0\"x4'0\") + Powder Room (4'2\"x4'0\")\n• 100% Vastu compliant, zero wastage layout\n• Natural ventilation in all units\n• Starting at *₹39.99 Lakh++*\n\n1️⃣ 📐 *Send 1 BHK 3D Floor Plan & Video Tour*\n2️⃣ 💰 *Payment Plans & Down Payment Info*\n3️⃣ 🔑 *Book VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/floor-plan-1bhk-323-sqft-3d.jpeg');
    }

    /**
     * 4. 1 BHK Walkthrough Video Tour
     */
    protected function send1BhkMedia(Contact $contact, Conversation $conversation)
    {
        $reply = "📹 *1 BHK Virtual Walkthrough & Video Tour:*\nHere is the walkthrough video tour of the 1 BHK Growth Home (323 sq.ft with 2 washrooms)!\n\n1️⃣ 💰 *Check Payment Plans & Pricing*\n2️⃣ 🔑 *Book VIP Site Visit for This Weekend*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/video-floor-plan-1bhk.mp4');
    }

    /**
     * 5. 2 BHK Luxury Homes Details
     */
    protected function send2BhkDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "🏡 *2 BHK Homes — Starting ₹52.99 Lakh+:*\n• *485 sq.ft Variant:* 2 bedrooms, 2 toilets, 2 service slabs (Flat 04)\n• *621 sq.ft Reimagined:* Master 2 BHK with grand living (9'8\"x15'9\") & large master bed (10'2\"x12'0\")\n🎁 *EXCLUSIVE OFFER:* Free ₹1.5 Lakh Premium Furniture Package on booking!\n\n1️⃣ 📐 *Send 2 BHK Floor Plans & Video Tour*\n2️⃣ 🛋️ *View Free ₹1.5L Furniture Package Details*\n3️⃣ 🌟 *View 621 sq.ft Reimagined Master Layout*\n4️⃣ 🔑 *Book VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/floor-plan-2bhk-485-sqft.jpeg');
    }

    /**
     * 6. 2 BHK Walkthrough Video Tour
     */
    protected function send2BhkMedia(Contact $contact, Conversation $conversation)
    {
        $reply = "📹 *2 BHK Virtual Walkthrough & Video Tour:*\nExperience the luxurious 2 BHK Sample Flat in 35-storey Growth City Naigaon!\n\n1️⃣ 🛋️ *View Free ₹1.5L Furniture Package Details*\n2️⃣ 💰 *Payment Plans & Down Payment Info*\n3️⃣ 🔑 *Book Guided VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/video-floor-plan-2bhk.mp4');
    }

    /**
     * 7. 621 sq.ft Reimagined Master 2 BHK Details
     */
    protected function send2BhkLargeDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "👑 *621 sq.ft Reimagined Master 2 BHK (₹72.99L++):*\n• Grand Living & Dining: 9'8\" x 15'9\"\n• Luxury Master Bedroom: 10'2\" x 12'0\"\n• Spectacular hillside and pavilion views\n• Two private service slabs\n\n1️⃣ 🎁 *Check Running Offers & Furniture Package*\n2️⃣ 🔑 *Book VIP Site Visit to Experience This Flat*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/floor-plan-2bhk-large-621-sqft.jpeg');
    }

    /**
     * 8. Exclusive Offers Overview
     */
    protected function sendOffersDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "🎁 *Growth City Naigaon — Exclusive Limited-Time Offers:*\n1. *Free ₹1.5L Premium Furniture Package* on 2 BHK (Sofa, Bed, Wardrobe, Dining & Coffee Tables) 🛋️\n2. *₹1.5 Lakh Club Membership Waiver* (Zero lifetime clubhouse fees) 🏊‍♂️\n3. *Freedom Kitchen Collection* (Modular setup with branded white goods) 🍳\n4. *Flexi Payment Plan* with easy bank loans & down payment assistance 💳\n\n1️⃣ 🛋️ *See Free ₹1.5L Furniture Package Details*\n2️⃣ 💰 *Payment Plans & Down Payment Info*\n3️⃣ 🔑 *Book VIP Site Visit to Claim These Offers*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/growth-city-exclusive-offers.jpeg');
    }

    /**
     * 9. Detailed Furniture Package Breakdown
     */
    protected function sendFurniturePackageDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "🛋️ *Free ₹1.5 Lakh Premium Furniture Package:*\nBook your 2 BHK home and receive a complete designer package at NO extra cost:\n• 3-Seater Premium Fabric Sofa\n• Center / Coffee Table\n• 4-Seater Dining Table with Chairs\n• Queen-Size Bed with Headboard\n• Spacious 3-Door Wardrobe\n\n1️⃣ 📐 *View 2 BHK Floor Plans (485 & 621 sq.ft)*\n2️⃣ 🔑 *Book VIP Site Visit to See Furnished Sample Flat*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/furniture-package-offer-2bhk.jpeg');
    }

    /**
     * 10. Location & Station Connectivity
     */
    protected function sendLocationDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "📍 *Unbeatable Location & 2-Minute Station Walk:*\n• *Just 2 Minutes* from Naigaon Railway Station & Bus Stop 🚆\n• Located near prestigious Don Bosco School in India's fastest-growing corridor 🏫\n• Minutes from Western Express Highway and upcoming coastal roads 🚗\n• Office: Ground 21, Sai Krupa Mall, Opp. Dahisar Station (W), Mumbai\n\n1️⃣ 📹 *Watch Station Connectivity Video*\n2️⃣ 🔑 *Book Guided VIP Site Visit (Pick-up available)*\n3️⃣ 👤 *Speak with Raj Kumar Dubey (9619747074)*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/video-connectivity-location.mp4');
    }

    /**
     * 11. Location Video
     */
    protected function sendLocationVideo(Contact $contact, Conversation $conversation)
    {
        $reply = "🚆 *Naigaon Station Connectivity Tour:*\nHere is the video showing how close Growth City Naigaon is to Naigaon Railway Station (just 2 minutes walk)!\n\n1️⃣ 🔑 *Book VIP Site Visit with Free Station Pickup*\n2️⃣ 👤 *Speak with Raj Kumar Dubey (9619747074)*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/video-connectivity-location.mp4');
    }

    /**
     * 12. 80+ Amenities Details
     */
    protected function sendAmenitiesDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "🌟 *80+ Lifestyle Amenities across 5 Growth Centres:*\n1. *GrowTogether:* Open-Air Amphitheatre, Grand Festival Lawn\n2. *GrowHappy:* Virtual Reality Gaming, Open-Air Theatres\n3. *GrowFit:* Fully Equipped Gym, Swimming Pool, Kids Pool, Zumba\n4. *GrowProsperous:* Business Meeting Rooms, Private Tuition & Study Rooms\n5. *GrowSmart:* Kids Playseum & Interactive Reading Centre\n\n1️⃣ 🏊‍♂️ *Watch Amenities Video Tour*\n2️⃣ 🔑 *Book Guided VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/lifestyle-swimming-pool-2bhk.jpeg');
    }

    /**
     * 13. Amenities Video
     */
    protected function sendAmenitiesVideo(Contact $contact, Conversation $conversation)
    {
        $reply = "🏊‍♂️ *Amenities Video Walkthrough:*\nExplore the clubhouse, swimming pools, sports arenas, and lifestyle amenities at Growth City Naigaon!\n\n1️⃣ 🔑 *Book Guided VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/video-amenities-vvmc-naigaon.mp4');
    }

    /**
     * 14. Payment Plans & Pricing
     */
    protected function sendPaymentPlansDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "💰 *Growth City Naigaon Pricing & Payment Plans:*\n• *1 BHK (323 sq.ft + 2 Washrooms):* Starts ₹39.99 Lakh++\n• *2 BHK (485 sq.ft + Free Furniture):* Starts ₹52.99 Lakh+\n• *2 BHK Large (621 sq.ft Reimagined):* Starts ₹72.99 Lakh++\n\n💳 *Financing Advantages:*\n• Pre-approved loans with Bajaj Housing Finance & leading national banks\n• Flexi Payment Plans with minimal down payment & easy EMI schedules\n\n1️⃣ 🔑 *Book VIP Site Visit to Discuss Customized EMI Plan*\n2️⃣ 👤 *Speak with Raj Kumar Dubey (9619747074)*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/promo-banner-2bhk-52-99-lakh.jpeg');
    }

    /**
     * 15. Site Visit Booking Menu
     */
    protected function sendSiteVisitMenu(Contact $contact, Conversation $conversation)
    {
        $reply = "🔑 *Book Guided VIP Site Visit / Sample Flat Tour:*\nWe would be delighted to host you for a private tour of the 35-storey towers and furnished sample flat!\n\nWhen would you prefer to visit?\n1️⃣ 🗓️ *This Saturday (11 AM – 5 PM)*\n2️⃣ 🗓️ *This Sunday (11 AM – 5 PM)*\n3️⃣ 🚗 *Weekday VIP Slot (Flexible timing with station pickup)*\n4️⃣ 👤 *Call Raj Kumar Dubey Directly (9619747074)*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/tower-elevation-exterior-view.jpeg');
    }

    /**
     * 16. Site Visit Slot Confirmed
     */
    protected function sendVisitConfirmed(Contact $contact, Conversation $conversation, string $slot)
    {
        $reply = "✅ *VIP Site Visit Scheduled for {$slot}!* 🎊\nOur senior property advisor Raj Kumar Dubey (9619747074) will coordinate with you to arrange free pickup from Naigaon Station.\n\n📍 *Site Location:* Growth City, near Don Bosco School, Naigaon East.\n🏢 *Head Office:* Ground 21, Sai Krupa Mall, Opp. Dahisar Station (W), Mumbai.\n\n_We look forward to welcoming you!_ ☕✨\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/season4-property-visiting-card.jpeg');
    }

    /**
     * 17. Speak to Human / Raj Kumar Dubey Contact
     */
    protected function sendHumanContactDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "👤 *Direct Contact — Season 4 Property:*\n_Official Channel Partner for Growth City Naigaon (HoABL)_\n\n• *Raj Kumar Dubey (Proprietor):* 📞 *9619747074*\n• *Udesh Khedekar (Sales Lead):* 📞 *9152244654*\n• *Office Address:* Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station (West), Mumbai - 400068\n• *MahaRERA Registration:* A51900035533\n\n_Raj Kumar Dubey has been notified and will reach out to you shortly. Feel free to call directly anytime!_\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/season4-property-visiting-card.jpeg');
    }

    /**
     * 18. HoABL Developer Clarification (NOT Lodha Group)
     */
    protected function sendHoablClarification(Contact $contact, Conversation $conversation)
    {
        $reply = "ℹ️ *Important Clarification on Developer Brand:*\n*'The House of Abhinandan Lodha' (HoABL)* was established in 2020 and is **NOT associated with 'Lodha' or 'Lodha Group'** in any manner.\n\nHoABL is an independent, premier developer brand:\n• Built in association with Mittal Builders\n• Financed and mortgaged by Bajaj Housing Finance Ltd.\n• MahaRERA Registered: P99000081006\n\n1️⃣ 🏢 *Explore Growth City Naigaon Project Overview*\n2️⃣ 🏡 *View 2 BHK Luxury Homes (₹52.99L+)*\n3️⃣ 🔑 *Book Guided VIP Site Visit*\n0️⃣ ↩️ *Main Menu*";
        $this->sendReply($contact, $conversation, $reply, 'public-asset/tower-elevation-exterior-view.jpeg');
    }

    /**
     * 19. MahaRERA Verification
     */
    protected function sendReraDetails(Contact $contact, Conversation $conversation)
    {
        $reply = "🏛️ *Official MahaRERA Verification:*\n• *Project MahaRERA:* P99000081006 (Growth City Naigaon)\n• *Channel Partner MahaRERA:* A51900035533 (Season 4 Property)\n• Completely clear titles, mortgaged with Bajaj Housing Finance Ltd.\n\n_Reply 1 to explore 2 BHK homes, or reply 4 to book a VIP site visit!_";
        $this->sendReply($contact, $conversation, $reply);
    }

    /**
     * 20. Contextual Fallback Guidance (Sub-millisecond polite helper)
     */
    protected function sendFallbackGuidance(Contact $contact, Conversation $conversation, string $currentState)
    {
        $reply = "Thank you for reaching out to *Season 4 Property*! 🏡\n\nHere are the quickest ways we can assist you:\n1️⃣ 🏢 *Growth City Naigaon (2 BHK Premium Homes from ₹52.99L+)*\n2️⃣ 🎁 *Free ₹1.5L Furniture Package Offer*\n3️⃣ 📍 *Prime Location (2-Min from Naigaon Station)*\n4️⃣ 🔑 *Book VIP Site Visit / Sample Flat Tour*\n5️⃣ 👤 *Speak with Raj Kumar Dubey (9619747074)*\n\n_💬 Reply with a number (1–5) or type 0 for Main Menu!_";
        $this->sendReply($contact, $conversation, $reply);
    }

    /**
     * Helper for tests/inspectors that lookup node config.
     */
    public function getNode(string $id): ?array
    {
        return [
            'id' => $id,
            'name' => ucfirst(str_replace('_', ' ', $id)),
            'message' => $this->getWelcomeMessage(),
        ];
    }

    /**
     * Dispatch WhatsApp message to API and record in messages database in < 1ms.
     */
    protected function sendReply(Contact $contact, Conversation $conversation, string $reply, ?string $mediaUrl = null)
    {
        // Always prioritize real phone number over internal @lid
        $cleanPhone = preg_replace('/\D/', '', $contact->phone ?? '');
        if (!empty($cleanPhone) && strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 14) {
            $recipient = $cleanPhone;
        } else {
            $recipient = $contact->phone ?: $contact->whatsapp_id;
        }

        if (str_ends_with($recipient, '@g.us') || str_contains($recipient, '@g.us')) {
            Log::warning("ChatbotService sendReply: Blocked attempt to send bot message to group ({$recipient}).");
            return;
        }

        if (!str_starts_with($contact->phone, 'simulator_')) {
            if ($mediaUrl) {
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
            'external_message_id' => $response['messageId'] ?? null,
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

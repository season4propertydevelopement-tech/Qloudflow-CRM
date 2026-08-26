<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$season4Data = [
    'company' => [
        'name' => 'Season 4 Property',
        'tagline' => 'Your Trusted Property Partner',
        'business_type' => 'Real Estate Services (Proprietary Firm)',
        'owner' => 'Raj Kumar Dubey (Shri Rajkumar Ramsagar Dubey)',
        'phone' => '9619747074',
        'phone_raw' => '+919619747074',
        'email' => 'rajkumardubey477@gmail.com',
        'office_address' => 'Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068',
        'registered_address' => '208, B Wing, Avinash Apartment, Opp. Kiran Medical, Navghar, Navghar Cross Road / SV Road, Bhayandar East, Thane, Maharashtra - 401105',
        'rera_number' => 'A51900035533',
        'udyam_number' => 'UDYAM-MH-33-0376504',
        'pan' => 'AKAPD4856H',
        'enterprise_type' => 'Micro (MSME)',
        'incorporation_date' => '01/04/2023',
        'udyam_registration_date' => '17/09/2023',
        'nic_code' => '68100 - Real estate activities with own or leased property'
    ],
    'featured_project' => [
        'name' => 'The Next Big Landmark in Naigaon (Phase 2)',
        'location' => 'Near Don Bosco School, Naigaon East',
        'township_size' => '14 Acres Premium Township',
        'structure' => '9 Iconic High-Rise Towers (G + 2 Podium + 35 Storeys)',
        'amenities' => '80+ Curated Lifestyle Amenities, Grand Dual-Level Luxury Clubhouse',
        'configurations' => [
            '1_bhk' => [
                'type' => '1 BHK',
                'carpet_area' => '323 sq.ft + 30 sq.ft Dry Balcony',
                'price' => '₹39.99 Lakh++'
            ],
            '2_bhk' => [
                'type' => '2 BHK',
                'carpet_area' => '485 sq.ft + 40 sq.ft Dry Balcony',
                'price' => '₹52.99 Lakh++'
            ],
            '2_bhk_large' => [
                'type' => '2 BHK Large',
                'carpet_area' => '621 sq.ft + Dry Balcony',
                'price' => '₹72.99 Lakh++'
            ]
        ],
        'sales_manager' => [
            'name' => 'Udesh Khedekar',
            'designation' => 'Manager - Sales',
            'phone' => '9152244654'
        ]
    ]
];

// Save knowledge file
$knowledgePath = storage_path('app/season4_knowledge.json');
file_put_contents($knowledgePath, json_encode($season4Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Saved knowledge base to: {$knowledgePath}\n";

// Generate Interactive WhatsApp Bot Flow for Season 4 Property
$botFlow = [
    'start_node' => 'welcome_node',
    'nodes' => [
        [
            'id' => 'welcome_node',
            'name' => 'Welcome Menu',
            'message' => "👋 *Welcome to Season 4 Property!* 🏡\n_Your Trusted Property Partner_\n\nHow can we assist your property search today?\n\n1️⃣ 🏢 *Ongoing Project (Naigaon East Township)*\n2️⃣ 📍 *Office & Contact Details*\n3️⃣ 📜 *MahaRERA & Legal Credentials*\n4️⃣ 🔑 *Book a Site Visit / Consultation*\n5️⃣ 👤 *Speak with Raj Kumar Dubey / Expert*\n\n_💬 Reply with a number (1–5) or type your query directly!_",
            'options' => [
                ['trigger' => '1', 'match_type' => 'exact', 'next_node' => 'project_node'],
                ['trigger' => 'project', 'match_type' => 'contains', 'next_node' => 'project_node'],
                ['trigger' => 'naigaon', 'match_type' => 'contains', 'next_node' => 'project_node'],
                ['trigger' => 'flat', 'match_type' => 'contains', 'next_node' => 'project_node'],
                ['trigger' => '1bhk', 'match_type' => 'contains', 'next_node' => 'project_node'],
                ['trigger' => '2bhk', 'match_type' => 'contains', 'next_node' => 'project_node'],
                ['trigger' => 'price', 'match_type' => 'contains', 'next_node' => 'project_node'],

                ['trigger' => '2', 'match_type' => 'exact', 'next_node' => 'contact_node'],
                ['trigger' => 'office', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'address', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'location', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'dahisar', 'match_type' => 'contains', 'next_node' => 'contact_node'],

                ['trigger' => '3', 'match_type' => 'exact', 'next_node' => 'legal_node'],
                ['trigger' => 'rera', 'match_type' => 'contains', 'next_node' => 'legal_node'],
                ['trigger' => 'maharera', 'match_type' => 'contains', 'next_node' => 'legal_node'],
                ['trigger' => 'legal', 'match_type' => 'contains', 'next_node' => 'legal_node'],
                ['trigger' => 'udyam', 'match_type' => 'contains', 'next_node' => 'legal_node'],

                ['trigger' => '4', 'match_type' => 'exact', 'next_node' => 'site_visit_node'],
                ['trigger' => 'visit', 'match_type' => 'contains', 'next_node' => 'site_visit_node'],
                ['trigger' => 'booking', 'match_type' => 'contains', 'next_node' => 'site_visit_node'],
                ['trigger' => 'consultation', 'match_type' => 'contains', 'next_node' => 'site_visit_node'],

                ['trigger' => '5', 'match_type' => 'exact', 'next_node' => 'human_node'],
                ['trigger' => 'human', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'raj kumar', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'rajkumar', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'dubey', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'agent', 'match_type' => 'contains', 'next_node' => 'human_node'],
            ]
        ],
        [
            'id' => 'project_node',
            'name' => 'Naigaon Landmark Project',
            'message' => "🏢 *The Next Big Landmark in Naigaon (Phase 2):*\n\n• 📍 *Location:* Near Don Bosco School, Naigaon East\n• 🏙️ 14-Acre Township, 9 Iconic High-Rise Towers (G+2 Podium+35 Storeys)\n• ✨ 80+ Curated Lifestyle Amenities & Dual-Level Clubhouse\n\n🏠 *Residences & Pricing:*\n🔹 *1 BHK (323 sq.ft + 30 sq.ft Dry Balcony):* ₹39.99 Lakh++\n🔹 *2 BHK (485 sq.ft + 40 sq.ft Dry Balcony):* ₹52.99 Lakh++\n🔹 *2 BHK Large (621 sq.ft + Dry Balcony):* ₹72.99 Lakh++\n\n_Reply *Visit* to book a site visit, or reply *Menu* to return._",
            'options' => [
                ['trigger' => 'visit', 'match_type' => 'contains', 'next_node' => 'site_visit_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'contact_node',
            'name' => 'Office & Contact',
            'message' => "📍 *Season 4 Property Office & Contact:*\n\n🏢 *Office Address:* Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068\n📞 *Call / WhatsApp:* 9619747074\n✉️ *Email:* rajkumardubey477@gmail.com\n👤 *Proprietor:* Raj Kumar Dubey\n\n_Reply *Menu* to return to the main options._",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'legal_node',
            'name' => 'Legal & RERA Credentials',
            'message' => "📜 *Legal & Regulatory Credentials:*\n\n• 🏛️ *Maha RERA Number:* A51900035533\n• 📑 *MSME Udyam Reg. No.:* UDYAM-MH-33-0376504\n• 🆔 *PAN:* AKAPD4856H\n• 🏢 *Registered Enterprise:* Season 4 Property (Proprietary Firm)\n\n_100% verified, legal, and trusted property advisory._ ✨\n\n_Reply *Menu* to return to the main options._",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'site_visit_node',
            'name' => 'Book Site Visit',
            'message' => "🔑 *Book Site Visit & Consultation:*\n\nWe organize guided site visits for our Naigaon East township and Mumbai properties.\n\n📞 *Call / WhatsApp:* 9619747074 (Raj Kumar Dubey)\n📱 *Sales Manager:* 9152244654 (Udesh Khedekar)\n\nPlease share your preferred day (e.g., Saturday / Sunday) and time for the visit! 🗓️",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'human_node',
            'name' => 'Talk to Raj Kumar Dubey',
            'message' => "🤝 *Connecting You to Raj Kumar Dubey:*\n\nRaj Kumar Dubey sir / our senior sales advisor has been notified and will connect with you shortly. You can also reach out directly:\n\n📞 *Call / WhatsApp:* 9619747074\n📱 *Sales Manager:* 9152244654 (Udesh Khedekar)\n\nReply *Menu* to return to the main options.",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ]
    ]
];

$flowPath = storage_path('app/bot_flow.json');
file_put_contents($flowPath, json_encode($botFlow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Saved bot flow to: {$flowPath}\n";

echo "Season 4 Property Training & Knowledge Base Setup Completed Successfully!\n";

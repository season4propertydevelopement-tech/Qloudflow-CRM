<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qloudsoftData = [
    'company' => [
        'name' => 'Qloudsoft Solution',
        'tagline' => 'Web & App Development | SEO & GMB Services in Mumbai',
        'website' => 'https://qloudsoft.in',
        'location' => 'Mumbai, Maharashtra, India',
        'phone' => '+91 73875 17576',
        'phone_raw' => '+917387517576',
        'enquiry_email' => 'info@qloudsoft.in',
        'support_email' => 'support@qloudsoft.in',
        'alternate_email' => 'qloudsoftstudio@gmail.com',
        'certifications' => [
            'Google Ads Search Certified',
            'Google Ads Creative Certified',
            'Google Ads AI Certified',
            'Google Analytics Certified'
        ],
        'social_links' => [
            'facebook' => 'https://facebook.com',
            'instagram' => 'https://instagram.com',
            'youtube' => 'https://youtube.com',
            'linkedin' => 'https://linkedin.com'
        ]
    ],
    'services' => [
        'web_development' => [
            'name' => 'Website Development',
            'description' => 'Custom website design, modern web applications, CMS websites, and e-commerce stores tailored to grow your business.',
            'features' => [
                '100% Mobile & Tablet Responsive',
                'Fast Loading & Speed Optimized',
                'SEO Friendly Architecture',
                'Modern UI/UX with smooth animations',
                'Easy-to-use Admin Panel / CMS',
                'WhatsApp & Social Media Chat Integration',
                'Free SSL Security & 1 Year Domain/Hosting options'
            ],
            'technologies' => ['PHP', 'Laravel', 'Node.js', 'React', 'Vue.js', 'WordPress', 'MySQL', 'MongoDB', 'Tailwind CSS', 'Bootstrap']
        ],
        'app_development' => [
            'name' => 'Mobile App Development',
            'description' => 'High-performance Android and iOS mobile applications with sleek user experiences and robust backend architectures.',
            'features' => [
                'Native (Kotlin/Java, Swift) & Cross-platform (Flutter, React Native)',
                'Payment Gateway & In-App Purchases',
                'Push Notifications & Real-Time Chat',
                'Secure Authentication & API Integrations',
                'Google Play Store & Apple App Store Deployment'
            ]
        ],
        'seo_services' => [
            'name' => 'Search Engine Optimization (SEO)',
            'description' => 'Drive organic traffic, rank on Google first page, and generate high-intent inbound leads.',
            'features' => [
                'On-Page & Off-Page Optimization',
                'High-Value Keyword Research',
                'Technical SEO & Site Speed Audit',
                'Quality Backlink Strategy',
                'Monthly Traffic & Ranking Reports'
            ]
        ],
        'gmb_services' => [
            'name' => 'Google My Business (GMB) & Local SEO',
            'description' => 'Dominate local Google Map search results (Local 3-Pack) and turn local searches into calling customers.',
            'features' => [
                'GMB Profile Setup & Verification',
                'Local Keyword Optimization',
                'Customer Review Strategy & Management',
                'Local Citations & Geotagged Media'
            ]
        ],
        'digital_marketing' => [
            'name' => 'Digital Marketing & Paid Ads (PPC)',
            'description' => 'ROI-focused Google Ads and Social Media Advertising campaigns managed by Google Certified professionals.',
            'features' => [
                'Google Search & Display Ads',
                'AI-Powered Ads & Performance Max',
                'Targeted Social Media Ads (Meta/Instagram/LinkedIn)',
                'Conversion Tracking & Analytics'
            ]
        ]
    ],
    'packages' => [
        'starter' => [
            'name' => 'Starter Website Package',
            'price' => '₹15,000/-',
            'ideal_for' => 'Startups, small businesses, and individuals creating their first web presence',
            'highlights' => [
                'Up to 5 Pages Responsive Website',
                '1 Year Free Domain (.com/.in) & Cloud Hosting',
                'Contact & Enquiry Form with Email Alerts',
                'WhatsApp Chat Button Integration',
                'Basic SEO Setup & Google Indexing',
                'Free SSL Security Certificate',
                'Social Media Icons Linking'
            ]
        ],
        'economy' => [
            'name' => 'Economy Website Package',
            'price' => '₹20,000/-',
            'ideal_for' => 'Growing businesses wanting a polished multi-page presence',
            'highlights' => [
                'Up to 10 Dynamic / Custom Pages',
                'Custom Modern UI/UX Layout',
                '1 Year Free High-Speed Hosting & Domain',
                'Image Gallery & Video Embedding',
                'Interactive Google Maps Integration',
                'WhatsApp & Lead Generation Form',
                'On-Page SEO Optimization',
                'Free SSL Certificate & 1 Year Support'
            ]
        ],
        'deluxe' => [
            'name' => 'Deluxe Website Package',
            'price' => '₹27,500/-',
            'ideal_for' => 'Businesses requiring content management, blog, or product showcase',
            'highlights' => [
                'Up to 15-20 Pages / Product Catalog',
                'Custom Admin Dashboard / CMS (Easily edit text & images)',
                'Blog / News Section for SEO growth',
                'Advanced On-Page SEO & Schema Markup',
                'Lead Capture Forms & Popups',
                'Payment Gateway Integration (Optional)',
                'Fast SSD Cloud Hosting with CDN',
                'WhatsApp Chat & Priority Support'
            ]
        ],
        'ultimate' => [
            'name' => 'Ultimate Corporate / E-Commerce Package',
            'price' => '₹35,000/-',
            'ideal_for' => 'Full-scale businesses, corporate brands, and complete e-commerce stores',
            'highlights' => [
                'Unlimited Pages / Full E-Commerce Store or Corporate Portal',
                'Complete CMS Admin Control Panel (Products, Orders, Leads)',
                'Multi-Payment Gateway (Razorpay, UPI, Cards, Netbanking)',
                'Automated WhatsApp Order & Lead Alerts',
                'Multi-Language & Currency Support',
                'High-Speed Optimization (90+ PageSpeed score)',
                'Complete SEO, Analytics & Pixels Setup',
                '1 Year 24/7 Dedicated Support & Free Maintenance'
            ]
        ]
    ],
    'faqs' => [
        [
            'question' => 'How long does it take to develop a website?',
            'answer' => 'Typically, a Starter/Economy website takes 3 to 7 business days. Deluxe and custom E-commerce/Corporate websites take around 10 to 18 business days.'
        ],
        [
            'question' => 'Do you provide domain and hosting?',
            'answer' => 'Yes, all our standard website packages include 1 year of free domain name registration (.com or .in) and high-speed cloud hosting with free SSL security.'
        ],
        [
            'question' => 'Can I manage or update the website myself?',
            'answer' => 'Yes! Our Deluxe and Ultimate packages include a user-friendly Admin Dashboard where you can update text, images, products, and blogs with no coding needed.'
        ],
        [
            'question' => 'What is the payment process?',
            'answer' => 'We usually work on milestone payments: 40-50% advance to start development, and the remaining balance upon final preview and deployment.'
        ]
    ]
];

// Save knowledge file
$knowledgePath = storage_path('app/qloudsoft_knowledge.json');
file_put_contents($knowledgePath, json_encode($qloudsoftData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Saved knowledge base to: {$knowledgePath}\n";

// Generate Interactive WhatsApp Bot Flow for Qloudsoft Solution
$botFlow = [
    'start_node' => 'welcome_node',
    'nodes' => [
        [
            'id' => 'welcome_node',
            'name' => 'Welcome Menu',
            'message' => "👋 *Welcome to Qloudsoft Solution!* 🚀\n_Web & App Development | SEO & GMB Experts in Mumbai_\n\nHow can we help you today? Please reply with a number or keyword:\n\n1️⃣ *Website Packages & Pricing*\n2️⃣ *Our Services* (Web, App, SEO, GMB)\n3️⃣ *Get a Free Quote / Consultation*\n4️⃣ *Contact & Support Details*\n5️⃣ *Talk to Human Expert*\n\n_Feel free to also ask any specific question directly!_",
            'options' => [
                ['trigger' => '1', 'match_type' => 'exact', 'next_node' => 'packages_node'],
                ['trigger' => 'package', 'match_type' => 'contains', 'next_node' => 'packages_node'],
                ['trigger' => 'pricing', 'match_type' => 'contains', 'next_node' => 'packages_node'],
                ['trigger' => 'price', 'match_type' => 'contains', 'next_node' => 'packages_node'],
                ['trigger' => 'cost', 'match_type' => 'contains', 'next_node' => 'packages_node'],

                ['trigger' => '2', 'match_type' => 'exact', 'next_node' => 'services_node'],
                ['trigger' => 'service', 'match_type' => 'contains', 'next_node' => 'services_node'],
                ['trigger' => 'website', 'match_type' => 'contains', 'next_node' => 'services_node'],
                ['trigger' => 'app', 'match_type' => 'contains', 'next_node' => 'services_node'],
                ['trigger' => 'seo', 'match_type' => 'contains', 'next_node' => 'services_node'],

                ['trigger' => '3', 'match_type' => 'exact', 'next_node' => 'quote_node'],
                ['trigger' => 'quote', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'consultation', 'match_type' => 'contains', 'next_node' => 'quote_node'],

                ['trigger' => '4', 'match_type' => 'exact', 'next_node' => 'contact_node'],
                ['trigger' => 'contact', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'phone', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'email', 'match_type' => 'contains', 'next_node' => 'contact_node'],
                ['trigger' => 'address', 'match_type' => 'contains', 'next_node' => 'contact_node'],

                ['trigger' => '5', 'match_type' => 'exact', 'next_node' => 'human_node'],
                ['trigger' => 'human', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'agent', 'match_type' => 'contains', 'next_node' => 'human_node'],
                ['trigger' => 'support', 'match_type' => 'contains', 'next_node' => 'human_node'],
            ]
        ],
        [
            'id' => 'packages_node',
            'name' => 'Website Packages',
            'message' => "💼 *Qloudsoft Website Development Packages:*\n\n🌟 *1. Starter Package — ₹15,000/-*\n• Up to 5 Pages Responsive Website\n• 1 Yr Free Domain & Hosting\n• WhatsApp Chat & Contact Form\n• SSL & Essential SEO\n\n⚡ *2. Economy Package — ₹20,000/-*\n• Up to 10 Custom Dynamic Pages\n• High Speed Optimized + Gallery\n• Google Maps & Lead Forms\n• 1 Yr Support & SSL\n\n💎 *3. Deluxe Package — ₹27,500/-*\n• Up to 20 Pages / Product Catalog\n• *Admin Dashboard CMS* (Edit yourself)\n• Blog & Advanced SEO Schema\n• Priority Support\n\n👑 *4. Ultimate Package — ₹35,000/-*\n• Corporate / Full E-Commerce Store\n• Payment Gateway (UPI/Cards) + Admin\n• WhatsApp Automated Ordering\n• Dedicated 24/7 Maintenance\n\n_Reply *1*, *2*, *3*, or *4* for more details, or reply *Menu* to return._",
            'options' => [
                ['trigger' => '1', 'match_type' => 'exact', 'next_node' => 'starter_node'],
                ['trigger' => 'starter', 'match_type' => 'contains', 'next_node' => 'starter_node'],
                ['trigger' => '2', 'match_type' => 'exact', 'next_node' => 'economy_node'],
                ['trigger' => 'economy', 'match_type' => 'contains', 'next_node' => 'economy_node'],
                ['trigger' => '3', 'match_type' => 'exact', 'next_node' => 'deluxe_node'],
                ['trigger' => 'deluxe', 'match_type' => 'contains', 'next_node' => 'deluxe_node'],
                ['trigger' => '4', 'match_type' => 'exact', 'next_node' => 'ultimate_node'],
                ['trigger' => 'ultimate', 'match_type' => 'contains', 'next_node' => 'ultimate_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'starter_node',
            'name' => 'Starter Details',
            'message' => "🌟 *Starter Website Package — ₹15,000/-*\n\n✅ Up to 5 Mobile-Friendly Pages\n✅ Free .com/.in Domain & Hosting (1st Year)\n✅ Contact & Inquiry Forms\n✅ WhatsApp Direct Click-to-Chat Button\n✅ Basic SEO Setup & Search Console Indexing\n✅ Delivery in 4-6 Days\n\nWould you like to book this plan or speak with our consultant?\nReply *Quote* or call us at *+91 73875 17576*.",
            'options' => [
                ['trigger' => 'quote', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'economy_node',
            'name' => 'Economy Details',
            'message' => "⚡ *Economy Website Package — ₹20,000/-*\n\n✅ Up to 10 Dynamic Pages with Modern UI\n✅ Free High-Speed Cloud Hosting & Domain (1 Yr)\n✅ Image & Video Gallery + Google Map\n✅ Fast Loading Speed (<2s)\n✅ Lead Capture Form with Instant Email Alerts\n✅ Delivery in 6-8 Days\n\nReady to get started? Reply *Quote* or call *+91 73875 17576*.",
            'options' => [
                ['trigger' => 'quote', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'deluxe_node',
            'name' => 'Deluxe Details',
            'message' => "💎 *Deluxe Website Package — ₹27,500/-*\n\n✅ 15-20 Pages or Product Showcase Catalog\n✅ *User-Friendly Admin Dashboard (CMS)* to add/edit pages & images\n✅ Blog Section for SEO Growth\n✅ Advanced On-Page SEO & Schema Markup\n✅ Optional Payment Gateway\n✅ Delivery in 8-12 Days\n\nReply *Quote* or call *+91 73875 17576* to get started!",
            'options' => [
                ['trigger' => 'quote', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'ultimate_node',
            'name' => 'Ultimate Details',
            'message' => "👑 *Ultimate E-Commerce / Corporate Package — ₹35,000/-*\n\n✅ Full E-Commerce Store or Enterprise Portal\n✅ Product Management, Orders & Customer Admin Dashboard\n✅ Payment Gateway (Razorpay/UPI/Cards) + Shipping integration\n✅ Automated WhatsApp Order & Lead Notifications\n✅ Multi-Currency & Multi-Language Support\n✅ 1 Year 24/7 Dedicated Support\n\nReply *Quote* or call *+91 73875 17576* for instant onboarding!",
            'options' => [
                ['trigger' => 'quote', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'services_node',
            'name' => 'Our Services',
            'message' => "🛠️ *Our Core Services at Qloudsoft Solution:*\n\n1. 🌐 *Website Development* (Custom, WordPress, Laravel, E-Commerce)\n2. 📱 *Mobile App Development* (Android & iOS with Flutter/React Native)\n3. 📈 *Search Engine Optimization (SEO)* (First Page Google Rankings)\n4. 📍 *Google My Business (GMB)* (Dominate Local Google Maps)\n5. 🎯 *Digital Marketing & PPC* (Google Ads & Social Media Campaigns)\n\nReply with the service name (e.g. *Website*, *App*, *SEO*, *GMB*) for details or *Menu* to return.",
            'options' => [
                ['trigger' => 'website', 'match_type' => 'contains', 'next_node' => 'packages_node'],
                ['trigger' => 'app', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'seo', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'gmb', 'match_type' => 'contains', 'next_node' => 'quote_node'],
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'contact_node',
            'name' => 'Contact Details',
            'message' => "📞 *Get in Touch with Qloudsoft Solution:*\n\n📍 *Office:* Mumbai, Maharashtra, India\n📱 *Phone / WhatsApp:* +91 73875 17576\n📧 *Enquiry:* info@qloudsoft.in\n🛠️ *Support:* support@qloudsoft.in / qloudsoftstudio@gmail.com\n🌐 *Website:* https://qloudsoft.in\n\nReply *Menu* to return to the main options.",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'quote_node',
            'name' => 'Get a Quote',
            'message' => "📝 *Request a Free Quote & Consultation*\n\nPlease reply with:\n1. Your Name\n2. Your Business / Project Requirement\n3. Preferred Timeline or Budget\n\nOur team will review and get back to you with a personalized proposal within 2 hours! You can also call us directly at *+91 73875 17576*.",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ],
        [
            'id' => 'human_node',
            'name' => 'Human Agent',
            'message' => "🙋‍♂️ *Connecting You to Our Team:*\n\nA representative from Qloudsoft Solution has been notified and will assist you shortly. You can also call directly at *+91 73875 17576* for immediate support.\n\nReply *Menu* if you'd like to browse options in the meantime.",
            'options' => [
                ['trigger' => 'menu', 'match_type' => 'contains', 'next_node' => 'welcome_node'],
            ]
        ]
    ]
];

$flowPath = storage_path('app/bot_flow.json');
file_put_contents($flowPath, json_encode($botFlow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Saved bot flow to: {$flowPath}\n";

echo "Qloudsoft Solution Training & Knowledge Base Setup Completed Successfully!\n";

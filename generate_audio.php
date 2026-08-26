<?php
/**
 * Pre-generate Fast Audio MP3 Cache for Voice Calling Agent
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$outputDir = public_path('conversation-audio');
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$phrases = [
    'greeting_general' => "Namaste! Main Qloudsoft Solutions se Avni baat kar rahi hoon. Kya aap apne business ki website ya digital growth ke baare mein baat karna chahte hain?",
    'website_cost' => "Humare custom website packages ₹15,000 se shuru hote hain jo 4 se 7 din mein ready ho jaate hain with free domain aur hosting.",
    'pricing_overview' => "Humare Starter Website ₹15,000, E-Commerce ₹35,000, aur Google SEO ₹12,000 per month se start hote hain.",
    'company_address' => "Qloudsoft Solutions Mumbai aur Palghar, Maharashtra me located hai. Hum pan-India aur global clients ko serve karte hain.",
    'smm_seo' => "Google SEO aur Social Media Marketing ₹12,000 per month se shuru hoti hai jisse aapka business Google ke 1st page par rank karega.",
    'app_development' => "Android aur iOS Mobile App development ₹35,000 se start hota hai with Play Store aur App Store launch.",
    'whatsapp_proposal' => "Main abhi aapke isi WhatsApp number par hamara complete portfolio aur pricing brochure share kar rahi hoon.",
    'fallback_repeat' => "Main sun nahi paayi. Kya aap dobara bata sakte hain ki aapko website ya marketing service chahiye?",
    'call_farewell' => "Thank you for speaking with Qloudsoft Solutions! Aapka din shubh rahe.",
    'meeting_schedule' => "Humare Senior Consultant ke sath quick 15-minute discovery consultation fix kar dete hain. Kaunsa time best rahega?"
];

echo "Generating Neural MP3 Audio in: {$outputDir}\n\n";

$ttsEndpoint = 'https://qloudsoft-piper-tts.onrender.com/tts';

foreach ($phrases as $key => $text) {
    $filePath = $outputDir . '/' . $key . '.mp3';
    echo "Synthesizing '{$key}'... ";

    try {
        $res = Http::timeout(15)->post($ttsEndpoint, [
            'text' => $text,
            'voice' => 'hi-IN-SwaraNeural',
            'format' => 'mp3'
        ]);

        if ($res->successful() && strlen($res->body()) > 500) {
            file_put_contents($filePath, $res->body());
            echo "DONE (" . round(strlen($res->body()) / 1024, 1) . " KB)\n";
        } else {
            echo "FAILED (Status: " . $res->status() . ")\n";
        }
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nCompleted all pre-generated audio files!\n";

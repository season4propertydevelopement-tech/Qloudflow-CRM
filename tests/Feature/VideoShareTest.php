<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\ChatbotService;
use App\Services\WhatsAppApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VideoShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_1bhk_video_watch_page()
    {
        $response = $this->get('/watch/1bhk-tour');

        $response->assertStatus(200);
        $response->assertSee('1 BHK Growth Home Walkthrough Tour');
        $response->assertSee('video-floor-plan-1bhk.mp4');
        $response->assertSee('og:video', false);
        $response->assertSee('Download MP4');
        $response->assertSee('Book VIP Site Visit');
    }

    public function test_can_render_all_property_tours_via_slugs_and_aliases()
    {
        $slugs = ['2bhk-tour', 'connectivity-tour', 'amenities-tour', 'elevation-tour', '1bhk', '2bhk'];

        foreach ($slugs as $slug) {
            $response = $this->get("/watch/{$slug}");
            $response->assertStatus(200);
            $response->assertSee('Growth City Naigaon');
            $response->assertSee('<video', false);
        }
    }

    public function test_can_download_video_directly()
    {
        $response = $this->get('/watch/1bhk-tour/download');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_chatbot_delivers_video_tour_as_shareable_link()
    {
        $contact = Contact::create([
            'name' => 'Video Prospect',
            'phone' => 'simulator_9876543210',
            'whatsapp_id' => 'simulator_9876543210',
            'chatbot_enabled' => true,
        ]);

        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        $chatbot = app(ChatbotService::class);

        // Send 1 BHK inquiry: "1" from main menu or "1 BHK"
        $chatbot->handleMessage($contact, $conversation, '1 BHK');

        // Verify conversation now offers 1 BHK details
        $lastMessage = $conversation->messages()->latest('id')->first();
        $this->assertNotNull($lastMessage);

        // Next ask for 1 BHK video tour: "1" from 1 BHK menu
        $chatbot->handleMessage($contact, $conversation, '1');

        $videoReply = $conversation->messages()->latest('id')->first();
        $this->assertNotNull($videoReply);
        $this->assertEquals('video', $videoReply->message_type);
        $this->assertStringContainsString('watch/1bhk-tour', $videoReply->message);
        $this->assertStringContainsString('▶️ *Tap to Watch Full Video Tour Online:*', $videoReply->message);
    }
}

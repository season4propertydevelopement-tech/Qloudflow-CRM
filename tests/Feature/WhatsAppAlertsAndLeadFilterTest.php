<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppConnectedNumber;
use App\Services\WhatsAppApiService;
use App\Services\WhatsAppAlertService;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WhatsAppAlertsAndLeadFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_add_whatsapp_connected_number_and_dispatch_notification(): void
    {
        $user = User::factory()->create();

        // Mock WhatsAppApiService to simulate sending message to primary & new number
        $mockApi = $this->createMock(WhatsAppApiService::class);
        $mockApi->method('getStatus')->willReturn([
            'success' => true,
            'status' => 'connected',
            'phone' => '917506678323',
            'name' => 'Season 4 property'
        ]);
        $mockApi->method('sendMessage')->willReturn(['success' => true]);

        $this->app->instance(WhatsAppApiService::class, $mockApi);

        $response = $this->actingAs($user)->postJson('/whatsapp/numbers', [
            'name' => 'Raj Kumar Dubey',
            'phone' => '9619747074',
            'role' => 'Senior Sales Manager',
            'notify_new_leads' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('whats_app_connected_numbers', [
            'name' => 'Raj Kumar Dubey',
            'phone' => '919619747074',
            'role' => 'Senior Sales Manager',
            'is_active' => true,
            'notify_new_leads' => true,
        ]);
    }

    public function test_can_broadcast_lead_to_all_connected_numbers(): void
    {
        $number1 = WhatsAppConnectedNumber::create([
            'name' => 'Advisor One',
            'phone' => '919876543210',
            'role' => 'Agent',
            'is_active' => true,
            'notify_new_leads' => true,
        ]);

        $number2 = WhatsAppConnectedNumber::create([
            'name' => 'Advisor Two',
            'phone' => '919876543211',
            'role' => 'Closer',
            'is_active' => true,
            'notify_new_leads' => true,
        ]);

        $mockApi = $this->createMock(WhatsAppApiService::class);
        $mockApi->method('getStatus')->willReturn([
            'success' => true,
            'status' => 'connected',
            'phone' => '917506678323',
        ]);
        $mockApi->expects($this->atLeast(2))
            ->method('sendMessage')
            ->willReturn(['success' => true]);

        $alertService = new WhatsAppAlertService($mockApi);

        $result = $alertService->broadcastNewLead([
            'name' => 'Pooja Patel',
            'phone' => '+91 91234 56789',
            'email' => 'pooja@example.com',
            'project' => 'Growth City Naigaon',
            'location' => 'Mumbai',
            'source' => 'Meta Ads',
            'notes' => 'Looking for 2 BHK on high floor',
        ]);

        $this->assertGreaterThanOrEqual(2, $result['sent_count']);
    }

    public function test_customer_numeric_message_qualifies_lead_as_hot_or_warm(): void
    {
        // 1. Customer selecting site visit (Option 4 / site visit confirmed)
        $contact = Contact::create([
            'name' => 'Numeric Lead Tester',
            'phone' => '919999988888',
            'lead_status' => 'cold',
            'lead_score' => 10,
            'current_node_id' => 'welcome_node',
            'first_message_at' => now(),
            'chatbot_enabled' => true,
        ]);

        $conv = Conversation::create([
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'direction' => 'incoming',
            'message' => 'hi',
            'sent_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'direction' => 'outgoing',
            'message' => 'Welcome to The House of Abhinandan Lodha! Reply 1-5.',
            'sent_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'direction' => 'incoming',
            'message' => '4',
            'sent_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'direction' => 'outgoing',
            'message' => 'VIP Site Visit Scheduled for Sunday! Our senior property advisor will assist you.',
            'sent_at' => now(),
        ]);

        $contact->current_node_id = 'site_visit_node';
        $contact->evaluateLeadFromConversationHistory($conv);
        $contact->refresh();

        $this->assertEquals('hot', $contact->lead_status);
        $this->assertGreaterThanOrEqual(90, $contact->lead_score);
    }

    public function test_contacts_filter_shows_leads_correctly(): void
    {
        $user = User::factory()->create();

        $hotContact = Contact::create([
            'name' => 'Hot Prospect',
            'phone' => '919811111111',
            'lead_status' => 'hot',
            'lead_score' => 95,
        ]);

        $coldContact = Contact::create([
            'name' => 'Cold Contact',
            'phone' => '919822222222',
            'lead_status' => 'cold',
            'lead_score' => 10,
        ]);

        $resHot = $this->actingAs($user)->get('/contacts?lead_status=hot');
        $resHot->assertStatus(200);
        $resHot->assertSee('Hot Prospect');
        $resHot->assertDontSee('Cold Contact');

        $resCold = $this->actingAs($user)->get('/contacts?lead_status=cold');
        $resCold->assertStatus(200);
        $resCold->assertSee('Cold Contact');
        $resCold->assertDontSee('Hot Prospect');
    }
}

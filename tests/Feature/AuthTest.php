<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'name' => 'admin',
            'email' => 'amarvcode@gmail.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_authenticate_using_username(): void
    {
        $response = $this->post('/login', [
            'login' => 'admin',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    public function test_user_can_authenticate_using_email(): void
    {
        $response = $this->post('/login', [
            'login' => 'amarvcode@gmail.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'login' => 'admin',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('login');
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('name', 'admin')->first();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
    }

    public function test_contact_show_can_be_rendered(): void
    {
        $user = User::where('name', 'admin')->first();
        $contact = \App\Models\Contact::create([
            'name' => 'Test Contact',
            'phone' => '1234567890',
        ]);

        $response = $this->actingAs($user)->get("/contacts/{$contact->id}");
        $response->assertStatus(200);
        $response->assertSee('Test Contact');
    }

    public function test_can_toggle_bot_for_contact(): void
    {
        $user = User::where('name', 'admin')->first();
        $contact = \App\Models\Contact::create([
            'name' => 'Toggle User',
            'phone' => '9988776655',
            'chatbot_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post("/contacts/{$contact->id}/toggle-bot");
        $response->assertRedirect();

        $this->assertFalse($contact->fresh()->chatbot_enabled);

        // Toggle back to true
        $this->actingAs($user)->post("/contacts/{$contact->id}/toggle-bot");
        $this->assertTrue($contact->fresh()->chatbot_enabled);
    }

    public function test_can_toggle_bot_for_conversation(): void
    {
        $user = User::where('name', 'admin')->first();
        $contact = \App\Models\Contact::create([
            'name' => 'Conv User',
            'phone' => '1122334455',
            'chatbot_enabled' => true,
        ]);
        $conversation = \App\Models\Conversation::create([
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/toggle-bot");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'chatbot_enabled' => false,
        ]);

        $this->assertFalse($contact->fresh()->chatbot_enabled);
    }

    public function test_can_filter_contacts_by_lead_status(): void
    {
        $user = User::where('name', 'admin')->first();
        \App\Models\Contact::create([
            'name' => 'Hot Lead Person',
            'phone' => '8888888881',
            'lead_status' => 'hot',
        ]);
        \App\Models\Contact::create([
            'name' => 'Cold Lead Person',
            'phone' => '8888888882',
            'lead_status' => 'cold',
        ]);

        $response = $this->actingAs($user)->get('/contacts?lead_status=hot');
        $response->assertStatus(200);
        $response->assertSee('Hot Lead Person');
        $response->assertDontSee('Cold Lead Person');
    }

    public function test_can_update_lead_status(): void
    {
        $user = User::where('name', 'admin')->first();
        $contact = \App\Models\Contact::create([
            'name' => 'Lead Subject',
            'phone' => '7777777771',
            'lead_status' => 'cold',
        ]);

        $response = $this->actingAs($user)->postJson("/contacts/{$contact->id}/set-lead-status", [
            'lead_status' => 'hot',
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'lead_status' => 'hot',
        ]);

        $this->assertEquals('hot', $contact->fresh()->lead_status);
        $this->assertEquals(90, $contact->fresh()->lead_score);
    }

    public function test_auto_evaluates_lead_temperature_on_message(): void
    {
        $contact = \App\Models\Contact::create([
            'name' => 'Prospective Buyer',
            'phone' => '6666666661',
            'lead_status' => 'cold',
        ]);

        $contact->evaluateLeadFromConversationHistory();
        $this->assertEquals('cold', $contact->fresh()->lead_status);
    }

    public function test_evaluates_hot_lead_after_5_conversation_messages(): void
    {
        $contact = \App\Models\Contact::create([
            'name' => 'Hot Project Lead',
            'phone' => '5555555551',
            'lead_status' => 'cold',
        ]);
        $conv = \App\Models\Conversation::create([
            'contact_id' => $contact->id,
            'status' => 'active'
        ]);

        // Add 5 conversation messages
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'Hello there']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'outgoing', 'message' => 'Welcome to Qloudsoft']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'I want to know the pricing for website development']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'outgoing', 'message' => 'We offer Starter, Economy, Deluxe, Ultimate']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'Please share a quotation and let us schedule a call to start the project']);

        $contact->evaluateLeadFromConversationHistory($conv);
        $this->assertEquals('hot', $contact->fresh()->lead_status);
        $this->assertGreaterThanOrEqual(80, $contact->fresh()->lead_score);
    }

    public function test_evaluates_warm_lead_after_5_conversation_messages(): void
    {
        $contact = \App\Models\Contact::create([
            'name' => 'Warm Services Lead',
            'phone' => '4444444441',
            'lead_status' => 'cold',
        ]);
        $conv = \App\Models\Conversation::create([
            'contact_id' => $contact->id,
            'status' => 'active'
        ]);

        // Add 5 conversation messages inquiring about general services
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'Hi']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'outgoing', 'message' => 'Welcome!']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'What development services and tech stack do you use?']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'outgoing', 'message' => 'We specialize in Flutter, React, Laravel, WordPress and SEO.']);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'contact_id' => $contact->id, 'direction' => 'incoming', 'message' => 'Can you show some portfolio samples for mobile apps?']);

        $contact->evaluateLeadFromConversationHistory($conv);
        $this->assertEquals('warm', $contact->fresh()->lead_status);
        $this->assertGreaterThanOrEqual(40, $contact->fresh()->lead_score);
    }

    public function test_can_simulate_bot_message_in_sandbox(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('bot.test'), [
            'message' => '1',
            'session_id' => 'test_session_123',
        ]);

        $response->assertJsonStructure([
            'success',
            'reply',
            'lead_status',
            'lead_score',
            'current_node',
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Season 4 Property', $response->json('reply'));
    }

    public function test_can_reset_bot_sandbox_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('bot.test.reset'), [
            'session_id' => 'test_session_123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'lead_status' => 'cold',
            'lead_score' => 10,
        ]);
    }
}


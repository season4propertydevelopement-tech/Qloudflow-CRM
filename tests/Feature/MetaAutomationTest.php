<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MetaCampaign;
use App\Models\MetaCampaignLead;
use App\Models\MetaTemplate;
use App\Services\GoogleSheetService;
use App\Services\WhatsAppApiService;
use App\Services\MetaAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;

class MetaAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_automation_dashboard(): void
    {
        $response = $this->get(route('meta-leads.automation'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_automation_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('meta-leads.automation'));
        $response->assertStatus(200);
        $response->assertSee('Campaign Automation Dashboard');
        $response->assertSee('Create Campaign');
    }

    public function test_can_render_templates_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('meta-leads.templates.index'));
        $response->assertStatus(200);
        $response->assertSee('Templates Library');
        $response->assertSee('Email Templates');
        $response->assertSee('WhatsApp Templates');
    }

    public function test_can_render_campaign_show_page(): void
    {
        $campaign = MetaCampaign::create([
            'name' => 'Bisani Rocketpay Leads Campaign',
            'description' => 'Test description',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/test',
            'status' => 'active',
            'total_leads' => 1,
        ]);

        MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ankit Sharma',
            'phone' => '919068919789',
            'email' => 'ankit@example.com',
            'city' => 'Meerut City',
            'custom_fields' => [
                'which_position_are_you_applying_for?' => 'area_sales_manager',
            ],
            'raw_data' => [
                'full_name' => 'Ankit Sharma',
                'which_position_are_you_applying_for?' => 'area_sales_manager',
            ]
        ]);

        $response = $this->actingAs($this->user)->get(route('meta-leads.campaigns.show', $campaign));
        $response->assertStatus(200);
        $response->assertSee('Bisani Rocketpay Leads Campaign');
        $response->assertSee('Ankit Sharma');
        $response->assertSee('Bulk WhatsApp');
        $response->assertSee('Bulk Email');
    }

    public function test_can_preview_google_sheet(): void
    {
        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('parseSheet')
            ->once()
            ->andReturn([
                'success' => true,
                'sheet_id' => '1ni-dYXcAk-WMDWsF98nnv1an832xViwZoE6u9Sacnl8',
                'gid' => '0',
                'headers' => ['full_name', 'phone', 'email', 'city'],
                'rows' => [
                    ['full_name' => 'Ankit Sharma', 'phone' => 'p:+919068919789', 'email' => 'ankit@example.com', 'city' => 'Meerut City']
                ],
                'total_rows' => 1,
                'sample_preview' => []
            ]);
        $this->app->instance(GoogleSheetService::class, $mockService);

        $response = $this->actingAs($this->user)->postJson(route('meta-leads.campaigns.preview-sheet'), [
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/1ni-dYXcAk-WMDWsF98nnv1an832xViwZoE6u9Sacnl8/edit?gid=0#gid=0'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_rows' => 1,
        ]);
    }

    public function test_can_create_campaign_and_import_leads_with_cleaning(): void
    {
        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('parseSheet')
            ->once()
            ->andReturn([
                'success' => true,
                'sheet_id' => '1ni-dYXcAk-WMDWsF98nnv1an832xViwZoE6u9Sacnl8',
                'gid' => '0',
                'headers' => ['id', 'full_name', 'phone', 'email', 'city', 'which_position_are_you_applying_for?'],
                'rows' => [
                    [
                        'id' => 'l:1017327737936934',
                        'full_name' => 'Ankit Sharma',
                        'phone' => 'p:+919068919789',
                        'email' => 'ankit@example.com',
                        'city' => 'Meerut City',
                        'which_position_are_you_applying_for?' => 'area_sales_manager',
                    ],
                    [
                        'id' => 'l:1530558489106828',
                        'full_name' => 'Bal Krishna',
                        'phone' => '9336784692',
                        'email' => 'bal@example.com',
                        'city' => 'Prayagraj',
                        'which_position_are_you_applying_for?' => 'business_development_manager',
                    ]
                ],
                'total_rows' => 2,
                'sample_preview' => []
            ]);

        $realService = new GoogleSheetService();
        $mockService->shouldReceive('normalizeRow')
            ->andReturnUsing(function ($row) use ($realService) {
                return $realService->normalizeRow($row);
            });

        $this->app->instance(GoogleSheetService::class, $mockService);

        $response = $this->actingAs($this->user)->post(route('meta-leads.campaigns.store'), [
            'name' => 'Rocketpay Sales Hiring',
            'description' => 'Field sales outreach campaign',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/1ni-dYXcAk-WMDWsF98nnv1an832xViwZoE6u9Sacnl8/edit?gid=0#gid=0',
            'duplicate_mode' => 'skip',
        ]);

        $campaign = MetaCampaign::where('name', 'Rocketpay Sales Hiring')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals(2, $campaign->total_leads);

        $lead1 = MetaCampaignLead::where('campaign_id', $campaign->id)->where('name', 'Ankit Sharma')->first();
        $this->assertNotNull($lead1);
        $this->assertEquals('919068919789', $lead1->phone); // Cleaned phone
        $this->assertEquals('area_sales_manager', $lead1->custom_fields['which_position_are_you_applying_for?']);

        $lead2 = MetaCampaignLead::where('campaign_id', $campaign->id)->where('name', 'Bal Krishna')->first();
        $this->assertNotNull($lead2);
        $this->assertEquals('919336784692', $lead2->phone); // Auto-prefixed 91 for Indian 10-digit number

        $response->assertRedirect(route('meta-leads.campaigns.show', $campaign));
    }

    public function test_dynamic_variable_substitution_in_templates(): void
    {
        $campaign = MetaCampaign::create([
            'name' => 'Test Campaign',
            'total_leads' => 1,
        ]);

        $lead = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Kunal Dhawale',
            'phone' => '918454937698',
            'email' => 'kunal@example.com',
            'city' => 'Omerga',
            'custom_fields' => [
                'which_position_are_you_applying_for?' => 'area_sales_manager',
                'when_can_you_join?' => 'immediately',
            ]
        ]);

        $automationService = app(MetaAutomationService::class);

        $template = "Hi {{full_name}}, we noticed you applied for {{which_position_are_you_applying_for?}} in {{city}}. Can you join {{when_can_you_join?}}?";
        $rendered = $automationService->renderTemplate($template, $lead);

        $expected = "Hi Kunal Dhawale, we noticed you applied for area_sales_manager in Omerga. Can you join immediately?";
        $this->assertEquals($expected, $rendered);
    }

    public function test_can_bulk_send_whatsapp_and_update_lead_status(): void
    {
        $campaign = MetaCampaign::create([
            'name' => 'WhatsApp Broadcast Campaign',
            'total_leads' => 2,
        ]);

        $lead1 = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Lead One',
            'phone' => '919068919789',
            'whatsapp_status' => 'not_sent',
        ]);

        $lead2 = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Lead Two',
            'phone' => '919336784692',
            'whatsapp_status' => 'not_sent',
        ]);

        $mockWhatsAppApi = Mockery::mock(WhatsAppApiService::class);
        $mockWhatsAppApi->shouldReceive('sendMessage')
            ->twice()
            ->andReturn(['success' => true, 'messageId' => 'msg_test_123']);
        $this->app->instance(WhatsAppApiService::class, $mockWhatsAppApi);

        $response = $this->actingAs($this->user)->post(route('meta-leads.campaigns.send-whatsapp', $campaign), [
            'message' => 'Hi {{name}}, this is a broadcast.',
            'lead_ids' => [$lead1->id, $lead2->id],
            'skip_already_sent' => 1,
        ]);

        $response->assertSessionHas('success');

        $lead1->refresh();
        $lead2->refresh();
        $this->assertEquals('sent', $lead1->whatsapp_status);
        $this->assertEquals('sent', $lead2->whatsapp_status);
        $this->assertNotNull($lead1->whatsapp_sent_at);

        $campaign->refresh();
        $this->assertEquals(2, $campaign->whatsapp_sent);
    }

    public function test_can_bulk_send_email_and_prevent_duplicate_sends(): void
    {
        Mail::fake();

        $campaign = MetaCampaign::create([
            'name' => 'Email Broadcast Campaign',
            'total_leads' => 2,
        ]);

        $lead1 = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Candidate A',
            'email' => 'cand.a@example.com',
            'email_status' => 'not_sent',
        ]);

        $lead2 = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'name' => 'Candidate B',
            'email' => 'cand.b@example.com',
            'email_status' => 'sent', // Already sent!
        ]);

        $response = $this->actingAs($this->user)->post(route('meta-leads.campaigns.send-email', $campaign), [
            'subject' => 'Job Offer for {{name}}',
            'body' => 'Hello {{name}}, you are selected!',
            'lead_ids' => [$lead1->id, $lead2->id],
            'skip_already_sent' => 1,
        ]);

        $response->assertSessionHas('success');

        $lead1->refresh();
        $lead2->refresh();

        // Lead 1 should be sent
        $this->assertEquals('sent', $lead1->email_status);
        // Lead 2 was already sent and remained sent
        $this->assertEquals('sent', $lead2->email_status);

        $campaign->refresh();
        $this->assertEquals(2, $campaign->emails_sent);
    }

    public function test_template_crud_operations(): void
    {
        // 1. Create Email Template
        $response = $this->actingAs($this->user)->post(route('meta-leads.templates.store'), [
            'type' => 'email',
            'name' => 'Interview Confirmation Letter',
            'subject' => 'Interview Scheduled for {{full_name}}',
            'body' => 'Dear {{full_name}}, your interview for {{position}} in {{city}} is confirmed.',
        ]);

        $response->assertSessionHas('success');

        $template = MetaTemplate::where('name', 'Interview Confirmation Letter')->first();
        $this->assertNotNull($template);
        $this->assertEquals('email', $template->type);
        $this->assertContains('full_name', $template->variables);
        $this->assertContains('city', $template->variables);

        // 2. Update Template
        $updateResponse = $this->actingAs($this->user)->put(route('meta-leads.templates.update', $template), [
            'name' => 'Updated Interview Letter',
            'subject' => 'Updated: {{full_name}}',
            'body' => 'Updated text with {{when_can_you_join?}}',
        ]);

        $updateResponse->assertSessionHas('success');
        $template->refresh();
        $this->assertEquals('Updated Interview Letter', $template->name);
        $this->assertContains('when_can_you_join?', $template->variables);

        // 3. Delete Template
        $deleteResponse = $this->actingAs($this->user)->delete(route('meta-leads.templates.destroy', $template));
        $deleteResponse->assertSessionHas('success');
        $this->assertNull(MetaTemplate::find($template->id));
    }

    public function test_template_image_upload_and_test_dispatch(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Mail::fake();

        // Test Image Upload
        $file = \Illuminate\Http\UploadedFile::fake()->create('brochure.png', 10, 'image/png');
        $uploadRes = $this->actingAs($this->user)->postJson(route('meta-leads.templates.upload-image'), [
            'image' => $file,
        ]);

        $uploadRes->assertStatus(200);
        $uploadRes->assertJsonFragment(['success' => true]);
        $this->assertNotEmpty($uploadRes->json('url'));

        // Test WhatsApp Direct Test Send
        $mockWa = \Mockery::mock(\App\Services\WhatsAppApiService::class);
        $mockWa->shouldReceive('sendMessage')
            ->once()
            ->with('919699867990', 'Hello Amar test message')
            ->andReturn(['success' => true]);
        $this->app->instance(\App\Services\WhatsAppApiService::class, $mockWa);

        $waTestRes = $this->actingAs($this->user)->postJson(route('meta-leads.templates.test-whatsapp'), [
            'phone' => '9699867990',
            'message' => 'Hello Amar test message',
        ]);
        $waTestRes->assertStatus(200);
        $waTestRes->assertJsonFragment(['success' => true]);

        // Test Email Direct Test Send
        $emailTestRes = $this->actingAs($this->user)->postJson(route('meta-leads.templates.test-email'), [
            'email' => 'amarvcode@gmail.com',
            'subject' => 'Test Subject for Amar',
            'body' => '<h1>Hello Amar</h1><p>This is a test HTML email.</p>',
        ]);
        $emailTestRes->assertStatus(200);
        $emailTestRes->assertJsonFragment(['success' => true]);
    }
}

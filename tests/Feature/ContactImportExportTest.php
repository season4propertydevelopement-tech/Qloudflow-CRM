<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Shuchkin\SimpleXLSXGen;

class ContactImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_export_contacts_as_native_excel(): void
    {
        Contact::create([
            'name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'lead_status' => 'hot',
            'lead_score' => 90,
            'chatbot_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.export'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_authenticated_user_can_download_excel_import_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_authenticated_user_can_import_contacts_from_excel_xlsx(): void
    {
        $rows = [
            ['Name', 'Phone', 'Lead Status', 'Lead Score', 'Notes'],
            ['Vikram Malhotra', '9811223344', 'hot', 90, 'Looking for 2 BHK Naigaon'],
            ['Sneha Kulkarni', '9822334455', 'warm', 50, 'Inquired about 1 BHK'],
        ];

        $xlsx = SimpleXLSXGen::fromArray($rows);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $xlsx->saveAs($tempPath);

        $file = new UploadedFile($tempPath, 'leads.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->user)->post(route('contacts.import'), [
            'file' => $file,
            'update_existing' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'name' => 'Vikram Malhotra',
            'phone' => '9811223344',
            'lead_status' => 'hot',
            'lead_score' => 90,
        ]);

        $this->assertDatabaseHas('contacts', [
            'name' => 'Sneha Kulkarni',
            'phone' => '9822334455',
            'lead_status' => 'warm',
            'lead_score' => 50,
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}

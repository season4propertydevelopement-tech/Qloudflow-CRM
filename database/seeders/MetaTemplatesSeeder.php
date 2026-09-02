<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetaTemplate;

class MetaTemplatesSeeder extends Seeder
{
    /**
     * Seed default Meta Lead templates.
     */
    public function run(): void
    {
        if (MetaTemplate::count() === 0) {
            MetaTemplate::create([
                'type' => 'email',
                'name' => 'Application Acknowledgment & Next Steps',
                'subject' => 'Application Update: {{which_position_are_you_applying_for?}} - {{full_name}}',
                'body' => "Dear {{full_name}},\n\nThank you for applying for the position of {{which_position_are_you_applying_for?}} via our Meta Lead campaign.\n\nWe have received your application details:\n• Contact: {{phone}}\n• City: {{city}}\n• Educational Qualification: {{what_is_your_highest_educational_qualification?}}\n• Field Sales Experience: {{do_you_have_experience_in_field_sales?}}\n• Joining Timeline: {{when_can_you_join?}}\n\nOur recruitment team has shortlisted your application. We will reach out to you within 24–48 hours regarding the interview schedule.\n\nBest regards,\nRecruitment Team\nRocketpay / Qloudflow Suite",
                'variables' => ['full_name', 'which_position_are_you_applying_for?', 'phone', 'city', 'what_is_your_highest_educational_qualification?', 'do_you_have_experience_in_field_sales?', 'when_can_you_join?'],
            ]);

            MetaTemplate::create([
                'type' => 'whatsapp',
                'name' => 'Instant WhatsApp Follow-up & Interview Check',
                'subject' => null,
                'body' => "Hi {{full_name}}, 👋\n\nThank you for your interest in the *{{which_position_are_you_applying_for?}}* position with Rocketpay!\n\nWe noticed you are based in *{{city}}* and can join *{{when_can_you_join?}}*.\n\nAre you available for a quick 10-minute briefing today? Please reply *YES* to confirm.",
                'variables' => ['full_name', 'which_position_are_you_applying_for?', 'city', 'when_can_you_join?'],
            ]);

            MetaTemplate::create([
                'type' => 'whatsapp',
                'name' => 'Field Sales Briefing & Location Confirmation',
                'subject' => null,
                'body' => "Hello {{full_name}},\n\nWe received your Meta inquiry for *{{which_position_are_you_applying_for?}}* in *{{city}}*.\n\nKindly confirm if you have a two-wheeler ready for local visits: *{{do_you_have_a_two-wheeler_for_local_travel?}}*.\n\nOur territory manager will coordinate with you shortly.",
                'variables' => ['full_name', 'which_position_are_you_applying_for?', 'city', 'do_you_have_a_two-wheeler_for_local_travel?'],
            ]);
        }
    }
}

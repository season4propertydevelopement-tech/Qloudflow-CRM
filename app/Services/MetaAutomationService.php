<?php

namespace App\Services;

use App\Models\MetaCampaign;
use App\Models\MetaCampaignLead;
use App\Models\MetaAutomationLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MetaAutomationService
{
    protected WhatsAppApiService $whatsAppApiService;

    public function __construct(WhatsAppApiService $whatsAppApiService)
    {
        $this->whatsAppApiService = $whatsAppApiService;
    }

    /**
     * Replace dynamic variables like {{full_name}}, {{city}}, {{which_position_are_you_applying_for?}}
     * with lead's real attributes and custom field values.
     */
    public function renderTemplate(string $text, MetaCampaignLead $lead): string
    {
        if (empty($text)) {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_?]+)\s*\}\}/', function ($matches) use ($lead) {
            $key = $matches[1];
            $value = $lead->getVariableValue($key);
            
            // If empty, also check with/without question mark
            if ($value === '') {
                $trimmedKey = rtrim($key, '?');
                $value = $lead->getVariableValue($trimmedKey);
            }

            return $value;
        }, $text);
    }

    /**
     * Send an email to an individual lead.
     */
    public function sendEmail(MetaCampaignLead $lead, string $subjectTemplate, string $bodyTemplate): array
    {
        if (empty($lead->email)) {
            $lead->update([
                'email_status' => 'failed',
                'email_error' => 'No valid email address found for this lead.',
            ]);
            return ['success' => false, 'error' => 'No valid email address'];
        }

        $subject = $this->renderTemplate($subjectTemplate, $lead);
        $body = $this->renderTemplate($bodyTemplate, $lead);

        // Configure dynamic SMTP mailer if credentials exist in .env
        $this->configureMetaSmtp();

        try {
            $lead->update(['email_status' => 'sending']);

            $fromEmail = env('SMTP_EMAIL', config('mail.from.address', 'season4property.developement@gmail.com'));
            $fromName = config('app.name', 'Qloudflow Suite');
            $recipientEmail = $lead->email;
            $recipientName = $lead->name ?: 'Valued Prospect';

            // Check if meta_smtp is configured, otherwise fallback to default mailer
            $mailer = config('mail.mailers.meta_smtp') ? 'meta_smtp' : config('mail.default', 'log');

            $isHtml = (strip_tags($body) !== $body);
            $finalHtml = $isHtml ? $body : nl2br(e($body));

            Mail::mailer($mailer)->send([], [], function ($message) use ($fromEmail, $fromName, $recipientEmail, $recipientName, $subject, $finalHtml) {
                $message->from($fromEmail, $fromName)
                    ->to($recipientEmail, $recipientName)
                    ->subject($subject)
                    ->html($finalHtml);
            });

            $lead->update([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_error' => null,
            ]);

            MetaAutomationLog::create([
                'campaign_id' => $lead->campaign_id,
                'lead_id' => $lead->id,
                'channel' => 'email',
                'recipient' => $recipientEmail,
                'subject' => $subject,
                'message_preview' => mb_substr(strip_tags($body), 0, 255),
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::warning("Meta Lead Email dispatch failed for {$lead->email}: {$errorMsg}");

            $lead->update([
                'email_status' => 'failed',
                'email_error' => $errorMsg,
            ]);

            MetaAutomationLog::create([
                'campaign_id' => $lead->campaign_id,
                'lead_id' => $lead->id,
                'channel' => 'email',
                'recipient' => $lead->email,
                'subject' => $subject,
                'message_preview' => mb_substr(strip_tags($body), 0, 255),
                'status' => 'failed',
                'error_message' => $errorMsg,
                'sent_at' => now(),
            ]);

            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Send a WhatsApp message to an individual lead, optionally with an image/media attachment.
     */
    public function sendWhatsApp(MetaCampaignLead $lead, string $messageTemplate, ?string $mediaUrl = null): array
    {
        $phone = $lead->phone ?: $lead->raw_phone;
        if (empty($phone)) {
            $lead->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => 'No valid phone number found for this lead.',
            ]);
            return ['success' => false, 'error' => 'No valid phone number'];
        }

        $message = $this->renderTemplate($messageTemplate, $lead);

        $lead->update(['whatsapp_status' => 'sending']);

        try {
            if (!empty($mediaUrl)) {
                $response = $this->whatsAppApiService->sendMedia($phone, $mediaUrl, $message);
            } else {
                $response = $this->whatsAppApiService->sendMessage($phone, $message);
            }

            if (isset($response['success']) && $response['success']) {
                $lead->update([
                    'whatsapp_status' => 'sent',
                    'whatsapp_sent_at' => now(),
                    'whatsapp_error' => null,
                ]);

                MetaAutomationLog::create([
                    'campaign_id' => $lead->campaign_id,
                    'lead_id' => $lead->id,
                    'channel' => 'whatsapp',
                    'recipient' => $phone,
                    'message_preview' => mb_substr($message, 0, 255),
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                return ['success' => true];
            }

            $errorMsg = $response['error'] ?? $response['message'] ?? 'WhatsApp API reported failure.';
            $lead->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => $errorMsg,
            ]);

            MetaAutomationLog::create([
                'campaign_id' => $lead->campaign_id,
                'lead_id' => $lead->id,
                'channel' => 'whatsapp',
                'recipient' => $phone,
                'message_preview' => mb_substr($message, 0, 255),
                'status' => 'failed',
                'error_message' => $errorMsg,
                'sent_at' => now(),
            ]);

            return ['success' => false, 'error' => $errorMsg];
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::warning("WhatsApp dispatch exception for {$phone}: {$errorMsg}");

            $lead->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => $errorMsg,
            ]);

            MetaAutomationLog::create([
                'campaign_id' => $lead->campaign_id,
                'lead_id' => $lead->id,
                'channel' => 'whatsapp',
                'recipient' => $phone,
                'message_preview' => mb_substr($message, 0, 255),
                'status' => 'failed',
                'error_message' => $errorMsg,
                'sent_at' => now(),
            ]);

            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Bulk send emails to selected leads in a campaign.
     */
    public function bulkSendEmails(MetaCampaign $campaign, array $leadIds, string $subject, string $body, bool $skipAlreadySent = true): array
    {
        $query = $campaign->leads();
        if (!empty($leadIds)) {
            $query->whereIn('id', $leadIds);
        }

        if ($skipAlreadySent) {
            $query->where('email_status', '!=', 'sent');
        }

        $leads = $query->get();
        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($leads as $lead) {
            if ($skipAlreadySent && $lead->email_status === 'sent') {
                $skippedCount++;
                continue;
            }

            $res = $this->sendEmail($lead, $subject, $body);
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $campaign->recalculateStats();

        return [
            'total_processed' => count($leads),
            'sent' => $sentCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Bulk send WhatsApp messages to selected leads in a campaign, optionally with media/image.
     */
    public function bulkSendWhatsApp(MetaCampaign $campaign, array $leadIds, string $message, bool $skipAlreadySent = true, ?string $mediaUrl = null): array
    {
        $query = $campaign->leads();
        if (!empty($leadIds)) {
            $query->whereIn('id', $leadIds);
        }

        if ($skipAlreadySent) {
            $query->where('whatsapp_status', '!=', 'sent');
        }

        $leads = $query->get();
        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($leads as $lead) {
            if ($skipAlreadySent && $lead->whatsapp_status === 'sent') {
                $skippedCount++;
                continue;
            }

            $res = $this->sendWhatsApp($lead, $message, $mediaUrl);
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $campaign->recalculateStats();

        return [
            'total_processed' => count($leads),
            'sent' => $sentCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Send a direct WhatsApp test message to any phone number.
     */
    public function sendDirectWhatsApp(string $phone, string $message, ?string $mediaUrl = null): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10 && in_array($cleanPhone[0], ['6', '7', '8', '9'])) {
            $cleanPhone = '91' . $cleanPhone;
        }

        if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
            return ['success' => false, 'error' => 'Please provide a valid 10-digit mobile number.'];
        }

        try {
            if (!empty($mediaUrl)) {
                $response = $this->whatsAppApiService->sendMedia($cleanPhone, $mediaUrl, $message);
            } else {
                $response = $this->whatsAppApiService->sendMessage($cleanPhone, $message);
            }

            if (isset($response['success']) && $response['success']) {
                return ['success' => true, 'message' => "Test WhatsApp delivered successfully to {$cleanPhone}."];
            }

            return ['success' => false, 'error' => $response['error'] ?? 'WhatsApp API reported failure.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a direct test email to any inbox.
     */
    public function sendDirectEmail(string $recipientEmail, string $subject, string $body): array
    {
        $recipientEmail = trim(strtolower($recipientEmail));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid email address.'];
        }

        $this->configureMetaSmtp();

        try {
            $fromEmail = env('SMTP_EMAIL', config('mail.from.address', 'season4property.developement@gmail.com'));
            $fromName = config('app.name', 'Qloudflow Suite');
            $mailer = config('mail.mailers.meta_smtp') ? 'meta_smtp' : config('mail.default', 'log');

            $isHtml = (strip_tags($body) !== $body);
            $finalHtml = $isHtml ? $body : nl2br(e($body));

            Mail::mailer($mailer)->send([], [], function ($message) use ($fromEmail, $fromName, $recipientEmail, $subject, $finalHtml) {
                $message->from($fromEmail, $fromName)
                    ->to($recipientEmail)
                    ->subject($subject)
                    ->html($finalHtml);
            });

            return ['success' => true, 'message' => "Test email dispatched successfully to {$recipientEmail}."];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dynamically configure SMTP mailer using environment variables if available.
     */
    protected function configureMetaSmtp(): void
    {
        $smtpUser = env('SMTP_EMAIL');
        $smtpPass = env('SMTP_APP_PASS');

        if (!empty($smtpUser) && !empty($smtpPass)) {
            $cleanPass = str_replace(' ', '', $smtpPass);
            config([
                'mail.mailers.meta_smtp' => [
                    'transport' => 'smtp',
                    'host' => 'smtp.gmail.com',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => $smtpUser,
                    'password' => $cleanPass,
                    'timeout' => 15,
                ],
            ]);
        }
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BotSettingsService
{
    protected string $storagePath = 'bot_settings.json';

    /**
     * Default configuration array.
     */
    public function defaultSettings(): array
    {
        return [
            'is_enabled' => true,
            'schedule_mode' => 'always', // 'always' or 'custom'
            'timezone' => 'Asia/Kolkata',
            'start_time' => '09:00',
            'end_time' => '20:00',
            'active_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'out_of_hours_message' => "🌙 *Thank you for reaching out to Season 4 Property!* 🏡\n\nOur team is currently outside standard business hours (Mon-Sat, 9:00 AM - 8:00 PM).\n\nWe have recorded your inquiry and Raj Kumar Dubey / our property advisor will connect with you first thing in the morning.\n\n_💬 Feel free to leave your property requirements, budget, or preferred location here in the meantime._",
            'send_welcome_media' => true,
            'auto_lead_scoring' => true,
            'human_handoff_enabled' => true,
            'human_handoff_keywords' => 'human, agent, talk to an expert, representative, live support, consultant, call',
            'typing_delay_seconds' => 1,
        ];
    }

    /**
     * Get all current settings.
     */
    public function getSettings(): array
    {
        $defaults = $this->defaultSettings();

        if (Storage::disk('local')->exists($this->storagePath)) {
            try {
                $content = Storage::disk('local')->get($this->storagePath);
                $saved = json_decode($content, true);
                if (is_array($saved)) {
                    return array_merge($defaults, $saved);
                }
            } catch (\Throwable $e) {
                Log::warning("BotSettingsService: Error reading settings file: " . $e->getMessage());
            }
        }

        return $defaults;
    }

    /**
     * Save settings to storage.
     */
    public function saveSettings(array $newSettings): array
    {
        $current = $this->getSettings();
        $updated = array_merge($current, $newSettings);

        // Ensure active_days is an array
        if (isset($newSettings['active_days']) && is_string($newSettings['active_days'])) {
            $updated['active_days'] = array_map('trim', explode(',', $newSettings['active_days']));
        }

        Storage::disk('local')->put($this->storagePath, json_encode($updated, JSON_PRETTY_PRINT));
        return $updated;
    }

    /**
     * Check if the bot is currently active based on master switch and schedule.
     */
    public function isBotActiveNow(): bool
    {
        $settings = $this->getSettings();

        // 1. Master toggle check
        if (!($settings['is_enabled'] ?? true)) {
            return false;
        }

        // 2. Schedule mode: 'always' is 24/7 active
        if (($settings['schedule_mode'] ?? 'always') === 'always') {
            return true;
        }

        // 3. Custom operating hours check
        return $this->isWithinOperatingHours($settings);
    }

    /**
     * Check if current time falls within operating hours.
     */
    public function isWithinOperatingHours(?array $settings = null): bool
    {
        $settings = $settings ?? $this->getSettings();
        $tz = $settings['timezone'] ?? 'Asia/Kolkata';

        try {
            $now = Carbon::now($tz);
            $dayOfWeek = strtolower($now->format('D')); // 'mon', 'tue', etc.

            // Day check
            $activeDays = $settings['active_days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
            if (!in_array($dayOfWeek, $activeDays)) {
                return false;
            }

            // Time range check
            $startTime = Carbon::createFromTimeString($settings['start_time'] ?? '09:00', $tz);
            $endTime = Carbon::createFromTimeString($settings['end_time'] ?? '20:00', $tz);

            if ($startTime->lessThanOrEqualTo($endTime)) {
                return $now->between($startTime, $endTime);
            } else {
                // Overnight schedule (e.g. 20:00 to 04:00)
                return $now->greaterThanOrEqualTo($startTime) || $now->lessThanOrEqualTo($endTime);
            }
        } catch (\Throwable $e) {
            Log::warning("BotSettingsService: Operating hours check failed: " . $e->getMessage());
            return true; // Fail safe to active
        }
    }

    /**
     * Get the Out of Hours message.
     */
    public function getOutOfHoursMessage(): string
    {
        $settings = $this->getSettings();
        return $settings['out_of_hours_message'] ?? $this->defaultSettings()['out_of_hours_message'];
    }

    /**
     * Get keywords list that trigger human handoff.
     */
    public function getHumanHandoffKeywords(): array
    {
        $settings = $this->getSettings();
        $raw = $settings['human_handoff_keywords'] ?? '';
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}

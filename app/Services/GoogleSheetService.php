<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GoogleSheetService
{
    /**
     * Parse and fetch all data from a Google Sheet URL.
     */
    public function parseSheet(string $url): array
    {
        $details = $this->extractSheetDetails($url);
        if (!$details['sheet_id']) {
            return [
                'success' => false,
                'error' => 'Invalid Google Sheet URL. Please ensure it follows the format: https://docs.google.com/spreadsheets/d/{SHEET_ID}/...',
            ];
        }

        $csvContent = $this->fetchSheetCsv($details['sheet_id'], $details['gid']);
        if (!$csvContent['success']) {
            return $csvContent;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvContent['data']);
        rewind($stream);

        // Strip UTF-8 BOM if present
        $bom = fread($stream, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($stream);
        }

        $rawHeaders = fgetcsv($stream);
        if (!$rawHeaders || empty(array_filter($rawHeaders))) {
            fclose($stream);
            return [
                'success' => false,
                'error' => 'The Google Sheet appears to be empty or does not contain header columns.',
            ];
        }

        // Clean headers
        $headers = [];
        foreach ($rawHeaders as $idx => $header) {
            $cleaned = trim((string)$header);
            $headers[$idx] = !empty($cleaned) ? $cleaned : "column_" . ($idx + 1);
        }

        $rows = [];
        $samplePreview = [];
        $rowCount = 0;

        while (($row = fgetcsv($stream)) !== false) {
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) {
                continue; // Skip entirely empty rows
            }

            $assocRow = [];
            foreach ($headers as $idx => $header) {
                $assocRow[$header] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            }

            $rows[] = $assocRow;
            $rowCount++;

            if ($rowCount <= 5) {
                $samplePreview[] = $assocRow;
            }
        }

        fclose($stream);

        return [
            'success' => true,
            'sheet_id' => $details['sheet_id'],
            'gid' => $details['gid'],
            'headers' => array_values($headers),
            'rows' => $rows,
            'total_rows' => count($rows),
            'sample_preview' => $samplePreview,
        ];
    }

    /**
     * Extract sheet ID and gid from a Google Sheet URL.
     */
    public function extractSheetDetails(string $url): array
    {
        $sheetId = null;
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $sheetId = $matches[1];
        }

        $gid = '0';
        if (preg_match('/[#&?]gid=([0-9]+)/', $url, $gidMatches)) {
            $gid = $gidMatches[1];
        }

        return [
            'sheet_id' => $sheetId,
            'gid' => $gid,
        ];
    }

    /**
     * Download CSV export from Google Sheets server-side using cURL.
     */
    protected function fetchSheetCsv(string $sheetId, string $gid): array
    {
        $urls = [
            "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}",
            "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&gid={$gid}",
        ];

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && !empty($data) && !str_contains(substr($data, 0, 300), '<!DOCTYPE html>')) {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            if ($httpCode === 403 || $httpCode === 401) {
                return [
                    'success' => false,
                    'error' => 'Google Sheet access denied (HTTP 403). Please verify that the sheet sharing setting is set to "Anyone with the link can view".',
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success' => false,
                    'error' => 'Google Sheet not found (HTTP 404). Please check the sheet URL or ID.',
                ];
            }

            if (!empty($curlError)) {
                Log::warning("Google Sheet cURL failed for {$url}: {$curlError}");
            }
        }

        return [
            'success' => false,
            'error' => 'Could not fetch data from the Google Sheet. Please check the URL and ensure the sheet is publicly viewable.',
        ];
    }

    /**
     * Clean and normalize a phone number for international WhatsApp and SMS.
     */
    public function cleanPhone(string $rawPhone): string
    {
        // Strip prefixes like "p:" and all non-numeric characters except leading plus
        $phone = preg_replace('/^p:/i', '', trim($rawPhone));
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Strip leading zeros
        $phone = ltrim($phone, '0');

        // Standard Indian 10-digit mobile number starting with 6-9 -> prefix with 91
        if (strlen($phone) === 10 && preg_match('/^[6-9]/', $phone)) {
            $phone = '91' . $phone;
        }

        return $phone;
    }

    /**
     * Clean and validate email address.
     */
    public function cleanEmail(string $rawEmail): ?string
    {
        $email = strtolower(trim($rawEmail));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return null;
    }

    /**
     * Map a raw associative row from the sheet to standardized lead attributes.
     */
    public function normalizeRow(array $row): array
    {
        $name = null;
        $phone = null;
        $rawPhone = null;
        $email = null;
        $city = null;
        $platform = null;
        $metaLeadId = null;
        $leadStatus = 'CREATED';
        $customFields = [];

        foreach ($row as $key => $value) {
            $val = trim((string)$value);
            $normalizedKey = strtolower(trim($key));
            $cleanKey = preg_replace('/[^a-z0-9]/', '', $normalizedKey);

            // Lead ID mapping
            if ($normalizedKey === 'id' || $cleanKey === 'leadid' || $cleanKey === 'metaleadid') {
                $metaLeadId = $val;
            }
            // Name mapping
            elseif (in_array($cleanKey, ['fullname', 'name', 'clientname', 'customername', 'leadname'])) {
                if (empty($name)) $name = $val;
            }
            // Phone mapping
            elseif (in_array($cleanKey, ['phone', 'phonenumber', 'mobile', 'mobileno', 'contact', 'whatsapp', 'contactno'])) {
                if (empty($rawPhone)) {
                    $rawPhone = $val;
                    $phone = $this->cleanPhone($val);
                }
            }
            // Email mapping
            elseif (in_array($cleanKey, ['email', 'emailid', 'emailaddress'])) {
                if (empty($email)) $email = $this->cleanEmail($val);
            }
            // City mapping
            elseif (in_array($cleanKey, ['city', 'location', 'district', 'town'])) {
                if (empty($city)) $city = $val;
            }
            // Platform mapping
            elseif ($cleanKey === 'platform' || $cleanKey === 'source') {
                if (empty($platform)) $platform = $val;
            }
            // Lead status mapping
            elseif ($cleanKey === 'leadstatus' || $cleanKey === 'status') {
                $leadStatus = !empty($val) ? strtoupper($val) : 'CREATED';
            }
            // All other columns are kept in custom_fields
            else {
                $customFields[$key] = $val;
            }
        }

        return [
            'meta_lead_id' => $metaLeadId,
            'name' => $name,
            'phone' => $phone,
            'raw_phone' => $rawPhone,
            'email' => $email,
            'city' => $city,
            'platform' => $platform,
            'lead_status' => $leadStatus,
            'custom_fields' => $customFields,
            'raw_data' => $row,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query();

        // Lead Status Filter
        if ($request->filled('lead_status') && in_array($request->lead_status, ['hot', 'warm', 'cold'])) {
            $query->where('lead_status', $request->lead_status);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->latest('last_message_at')->paginate(15)->withQueryString();

        $counts = [
            'all' => Contact::count(),
            'hot' => Contact::where('lead_status', 'hot')->count(),
            'warm' => Contact::where('lead_status', 'warm')->count(),
            'cold' => Contact::where('lead_status', 'cold')->count(),
        ];

        return view('contacts.index', compact('contacts', 'counts'));
    }

    /**
     * Export contacts to Native Excel (.xlsx).
     */
    public function export(Request $request)
    {
        $query = Contact::query();

        if ($request->filled('lead_status') && in_array($request->lead_status, ['hot', 'warm', 'cold'])) {
            $query->where('lead_status', $request->lead_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->latest('id')->get();
        $filename = 'Abhinandan_Lodha_Contacts_Export_' . now()->format('Y-m-d_His') . '.xlsx';

        // Build Excel data array
        $rows = [
            [
                '<b>ID</b>',
                '<b>Name</b>',
                '<b>Phone</b>',
                '<b>Lead Status</b>',
                '<b>Lead Score</b>',
                '<b>Chatbot Active</b>',
                '<b>Human Handoff</b>',
                '<b>Notes / Budget</b>',
                '<b>Created At</b>',
                '<b>Last Message At</b>'
            ]
        ];

        foreach ($contacts as $c) {
            $rows[] = [
                $c->id,
                $c->name ?? 'Unknown',
                $c->phone,
                strtoupper($c->lead_status ?? 'COLD'),
                (int)($c->lead_score ?? 10),
                $c->chatbot_enabled ? 'Yes' : 'No',
                $c->human_handoff ? 'Yes' : 'No',
                $c->notes ?? '',
                $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : '',
                $c->last_message_at ? $c->last_message_at->format('Y-m-d H:i:s') : ''
            ];
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows, 'Contacts');

        return response((string) $xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Download sample template in Native Excel (.xlsx) format.
     */
    public function downloadTemplate()
    {
        $filename = 'contacts_sample_template.xlsx';

        $templateRows = [
            ['<b>Name</b>', '<b>Phone</b>', '<b>Lead Status</b>', '<b>Lead Score</b>', '<b>Notes</b>'],
            ['Amit Sharma', '9876543210', 'hot', 90, 'Interested in 2 BHK Naigaon Phase 2'],
            ['Pooja Patel', '9123456789', 'warm', 50, 'Looking for 1 BHK Dahisar East'],
            ['Ramesh Gupta', '9820011223', 'cold', 10, 'Inquired about MahaRERA number'],
        ];

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($templateRows, 'Template');

        return response((string) $xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import contacts from uploaded Excel (.xlsx, .xls) or CSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'update_existing' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $updateExisting = $request->boolean('update_existing', true);
        $filePath = $file->getRealPath();

        $rows = [];

        // Try reading as native Excel (.xlsx / .xls)
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($filePath)) {
            $rows = $xlsx->rows();
        }

        // Fallback: Try reading as CSV if Excel parser found no rows
        if (empty($rows)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                while (($r = fgetcsv($handle)) !== false) {
                    $rows[] = $r;
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            return back()->with('error', 'The uploaded Excel file contains no readable rows or is invalid.');
        }

        $headerRow = array_shift($rows);
        if (empty($headerRow)) {
            return back()->with('error', 'The uploaded Excel file is missing header columns.');
        }

        // Map column headers dynamically
        $headerMap = [];
        foreach ($headerRow as $index => $colName) {
            $colNameClean = strip_tags((string)$colName);
            $normalized = strtolower(trim($colNameClean));
            $normalized = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $normalized));
            
            if (in_array($normalized, ['name', 'full_name', 'client_name', 'customer_name', 'contact_name', 'lead_name'])) {
                $headerMap['name'] = $index;
            } elseif (in_array($normalized, ['phone', 'mobile', 'mobile_no', 'phone_number', 'contact', 'whatsapp', 'number', 'contact_no'])) {
                $headerMap['phone'] = $index;
            } elseif (in_array($normalized, ['lead_status', 'status', 'temperature', 'lead_temperature', 'type', 'stage'])) {
                $headerMap['lead_status'] = $index;
            } elseif (in_array($normalized, ['lead_score', 'score', 'priority', 'rating', 'points'])) {
                $headerMap['lead_score'] = $index;
            } elseif (in_array($normalized, ['notes', 'remarks', 'note', 'comment', 'budget', 'requirement', 'notes__budget', 'notes_budget'])) {
                $headerMap['notes'] = $index;
            }
        }

        if (!isset($headerMap['phone'])) {
            return back()->with('error', 'Excel sheet missing "Phone" column. Please download and use our sample template.');
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            $rawPhone = isset($headerMap['phone']) && isset($row[$headerMap['phone']]) ? (string) $row[$headerMap['phone']] : '';
            $cleanPhone = preg_replace('/[^0-9]/', '', trim($rawPhone));

            if (empty($cleanPhone) || strlen($cleanPhone) < 7) {
                $skippedCount++;
                continue;
            }

            $name = isset($headerMap['name']) && isset($row[$headerMap['name']]) ? trim((string)$row[$headerMap['name']]) : null;
            $rawStatus = isset($headerMap['lead_status']) && isset($row[$headerMap['lead_status']]) ? strtolower(trim((string)$row[$headerMap['lead_status']])) : 'cold';
            $leadStatus = in_array($rawStatus, ['hot', 'warm', 'cold']) ? $rawStatus : 'cold';
            
            $scoreVal = isset($headerMap['lead_score']) && isset($row[$headerMap['lead_score']]) ? (int) trim((string)$row[$headerMap['lead_score']]) : null;
            if (!$scoreVal) {
                $scoreVal = match($leadStatus) {
                    'hot' => 90,
                    'warm' => 50,
                    default => 10,
                };
            }
            $scoreVal = max(10, min(100, $scoreVal));

            $notes = isset($headerMap['notes']) && isset($row[$headerMap['notes']]) ? trim((string)$row[$headerMap['notes']]) : null;

            $contact = Contact::where('phone', $cleanPhone)->first();

            if ($contact) {
                if ($updateExisting) {
                    if (!empty($name)) $contact->name = $name;
                    $contact->lead_status = $leadStatus;
                    $contact->lead_score = $scoreVal;
                    if (!empty($notes)) {
                        $contact->notes = $contact->notes ? ($contact->notes . "\n" . $notes) : $notes;
                    }
                    $contact->save();
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
            } else {
                $newContact = Contact::create([
                    'name' => $name ?: 'New Contact',
                    'phone' => $cleanPhone,
                    'lead_status' => $leadStatus,
                    'lead_score' => $scoreVal,
                    'notes' => $notes,
                    'chatbot_enabled' => true,
                    'human_handoff' => false,
                    'current_node_id' => 'welcome_node',
                    'first_message_at' => now(),
                    'last_message_at' => now(),
                ]);

                // Create default active conversation
                \App\Models\Conversation::firstOrCreate(
                    ['contact_id' => $newContact->id],
                    ['status' => 'active', 'last_message_at' => now()]
                );

                $createdCount++;
            }
        }

        $msg = "Excel Import completed successfully: {$createdCount} contacts created, {$updatedCount} updated.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} rows skipped due to invalid phone numbers or duplicate settings).";
        }

        return back()->with('success', $msg);
    }

    public function show(Contact $contact)
    {
        $contact->load('conversations.messages');
        return view('contacts.show', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'lead_status' => 'nullable|in:hot,warm,cold',
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone) ?: $request->phone;

        if ($request->filled('name')) {
            $contact->name = $request->name;
        }
        $contact->phone = $cleanPhone;
        if ($request->filled('notes')) {
            $contact->notes = $request->notes;
        }
        if ($request->filled('lead_status')) {
            $contact->lead_status = $request->lead_status;
        }
        $contact->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contact details updated successfully.',
                'contact' => $contact
            ]);
        }

        return back()->with('success', 'Contact updated successfully.');
    }

    public function destroy(Request $request, Contact $contact)
    {
        // Delete all associated conversations and messages
        $contact->conversations()->each(function ($conv) {
            $conv->messages()->delete();
            $conv->delete();
        });
        $contact->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contact removed successfully.'
            ]);
        }

        return redirect()->route('contacts.index')->with('success', 'Contact removed successfully.');
    }

    public function toggleBot(Request $request, Contact $contact)
    {
        $contact->chatbot_enabled = !$contact->chatbot_enabled;
        if ($contact->chatbot_enabled) {
            $contact->human_handoff = false;
        }
        $contact->save();

        $status = $contact->chatbot_enabled ? 'enabled' : 'disabled';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'chatbot_enabled' => $contact->chatbot_enabled,
                'message' => "Chatbot {$status} for this contact."
            ]);
        }

        return back()->with('success', "Chatbot {$status} for {$contact->name}.");
    }

    public function setLeadStatus(Request $request, Contact $contact)
    {
        $request->validate([
            'lead_status' => 'required|in:hot,warm,cold',
        ]);

        $contact->lead_status = $request->lead_status;
        if ($request->lead_status === 'hot') {
            $contact->lead_score = 90;
        } elseif ($request->lead_status === 'warm') {
            $contact->lead_score = 50;
        } else {
            $contact->lead_score = 10;
        }
        $contact->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'lead_status' => $contact->lead_status,
                'message' => "Lead marked as " . ucfirst($contact->lead_status)
            ]);
        }

        return back()->with('success', "Lead marked as " . ucfirst($contact->lead_status) . " for {$contact->name}.");
    }
}

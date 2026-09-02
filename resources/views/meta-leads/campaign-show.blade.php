@extends('layouts.app')

@section('content')
<script>
window.campaignDashboard = function() {
    const leadsMap = @json($leads->getCollection()->keyBy('id'));
    const emailTemplatesMap = @json($emailTemplates->keyBy('id'));
    const whatsappTemplatesMap = @json($whatsappTemplates->keyBy('id'));

    return {
        selectedLeads: [],
        selectAllOnPage: false,
        bulkEmailModalOpen: false,
        bulkWhatsAppModalOpen: false,
        detailDrawerOpen: false,
        activeLead: null,
        leadsData: leadsMap || {},
        emailTemplatesData: emailTemplatesMap || {},
        whatsappTemplatesData: whatsappTemplatesMap || {},
        
        // Email Modal State
        emailTemplateId: '',
        emailSubject: '',
        emailBody: '',
        emailSkipDuplicates: true,
        emailSubmitting: false,
        emailPreviewMode: false,
        emailUploadedImageUrl: '',

        // WhatsApp Modal State
        whatsappTemplateId: '',
        whatsappMessage: '',
        whatsappMediaUrl: '',
        whatsappSkipDuplicates: true,
        whatsappSubmitting: false,
        whatsappPreviewMode: false,

        // Test Dispatch State
        testPhone: '9699867990',
        testEmailAddress: 'amarvcode@gmail.com',
        testSending: false,
        testAlert: null,
        imageUploading: false,

        init() {
            this.$watch('selectAllOnPage', value => {
                if (value) {
                    const pageIds = Array.from(document.querySelectorAll('.lead-checkbox')).map(cb => parseInt(cb.value));
                    this.selectedLeads = Array.from(new Set([...this.selectedLeads, ...pageIds]));
                } else {
                    const pageIds = Array.from(document.querySelectorAll('.lead-checkbox')).map(cb => parseInt(cb.value));
                    this.selectedLeads = this.selectedLeads.filter(id => !pageIds.includes(id));
                }
            });
        },

        toggleSelectLead(id) {
            id = parseInt(id);
            if (this.selectedLeads.includes(id)) {
                this.selectedLeads = this.selectedLeads.filter(item => item !== id);
            } else {
                this.selectedLeads.push(id);
            }
        },

        clearSelection() {
            this.selectedLeads = [];
            this.selectAllOnPage = false;
        },

        openDetailById(id) {
            id = parseInt(id);
            this.activeLead = this.leadsData[id] || null;
            this.detailDrawerOpen = true;
        },

        openWhatsAppForLead(id) {
            id = parseInt(id);
            this.selectedLeads = [id];
            this.activeLead = this.leadsData[id] || null;
            this.openBulkWhatsApp();
        },

        openEmailForLead(id) {
            id = parseInt(id);
            this.selectedLeads = [id];
            this.activeLead = this.leadsData[id] || null;
            this.openBulkEmail();
        },

        openBulkEmail() {
            if (!this.emailSubject) {
                this.emailSubject = 'Opportunity Update: @{{which_position_are_you_applying_for?}} - @{{full_name}}';
            }
            if (!this.emailBody) {
                this.emailBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">\n  <h2 style="color: #0284c7; margin-top: 0;">Hello @{{full_name}},</h2>\n  <p style="color: #334155; font-size: 14px; line-height: 1.6;">Thank you for applying for the <strong>@{{which_position_are_you_applying_for?}}</strong> role in <strong>@{{city}}</strong>.</p>\n  <p style="color: #334155; font-size: 14px; line-height: 1.6;">Our HR team has reviewed your profile and we would like to schedule a quick conversation.</p>\n  <p style="color: #64748b; font-size: 12px; border-top: 1px solid #f1f5f9; padding-top: 15px;">Best regards,<br>Rocketpay Talent Acquisition</p>\n</div>';
            }
            this.emailPreviewMode = false;
            this.testAlert = null;
            this.bulkEmailModalOpen = true;
        },

        openBulkWhatsApp() {
            if (!this.whatsappMessage) {
                this.whatsappMessage = 'Hi @{{full_name}}, 👋\n\nThank you for your application for the *@{{which_position_are_you_applying_for?}}* role with Rocketpay.\n\nAre you available for a brief briefing call today? Please reply *YES* to proceed.';
            }
            this.whatsappPreviewMode = false;
            this.testAlert = null;
            this.bulkWhatsAppModalOpen = true;
        },

        onEmailTemplateSelect(tmplId) {
            if (!tmplId || !this.emailTemplatesData[tmplId]) return;
            const tmpl = this.emailTemplatesData[tmplId];
            this.emailSubject = tmpl.subject || '';
            this.emailBody = tmpl.body || '';
        },

        onWhatsAppTemplateSelect(tmplId) {
            if (!tmplId || !this.whatsappTemplatesData[tmplId]) return;
            const tmpl = this.whatsappTemplatesData[tmplId];
            this.whatsappMessage = tmpl.body || '';
            this.whatsappMediaUrl = tmpl.media_url || '';
        },

        insertEmailVariable(varName) {
            this.emailBody += ' {{' + varName + '}}';
        },

        insertWhatsAppVariable(varName) {
            this.whatsappMessage += ' {{' + varName + '}}';
        },

        uploadModalImage(event, channel) {
            const file = event.target.files[0];
            if (!file) return;

            this.imageUploading = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('meta-leads.templates.upload-image') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.imageUploading = false;
                if (data.success && data.url) {
                    if (channel === 'whatsapp') {
                        this.whatsappMediaUrl = data.url;
                    } else if (channel === 'email') {
                        this.emailUploadedImageUrl = data.url;
                    }
                } else {
                    alert(data.error || 'Failed to upload image.');
                }
            })
            .catch(err => {
                this.imageUploading = false;
                alert('Image upload error: ' + err.message);
            });
        },

        insertEmailImage(url) {
            if (!url) return;
            this.emailBody += `\n<img src="${url}" alt="Attachment" style="max-width: 100%; height: auto; border-radius: 8px; margin: 12px 0; display: block;" />\n`;
        },

        sendModalTestWhatsApp() {
            if (!this.testPhone) {
                alert('Please enter a test phone number.');
                return;
            }

            this.testSending = true;
            this.testAlert = null;

            fetch('{{ route('meta-leads.templates.test-whatsapp') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    phone: this.testPhone,
                    message: this.whatsappMessage,
                    media_url: this.whatsappMediaUrl || null,
                    sample_lead_id: this.activeLead?.id || null
                })
            })
            .then(res => res.json())
            .then(data => {
                this.testSending = false;
                this.testAlert = data;
            })
            .catch(err => {
                this.testSending = false;
                this.testAlert = { success: false, error: err.message };
            });
        },

        sendModalTestEmail() {
            if (!this.testEmailAddress) {
                alert('Please enter a test email address.');
                return;
            }

            this.testSending = true;
            this.testAlert = null;

            fetch('{{ route('meta-leads.templates.test-email') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    email: this.testEmailAddress,
                    subject: this.emailSubject,
                    body: this.emailBody,
                    sample_lead_id: this.activeLead?.id || null
                })
            })
            .then(res => res.json())
            .then(data => {
                this.testSending = false;
                this.testAlert = data;
            })
            .catch(err => {
                this.testSending = false;
                this.testAlert = { success: false, error: err.message };
            });
        },

        getSamplePreview(text) {
            if (!text) return '';
            const sample = this.activeLead || Object.values(this.leadsData)[0] || null;
            if (!sample) return text;

            let result = text;
            result = result.replace(/\{\{\s*full_name\s*\}\}/gi, sample.name || 'Ankit Sharma');
            result = result.replace(/\{\{\s*name\s*\}\}/gi, sample.name || 'Ankit Sharma');
            result = result.replace(/\{\{\s*email\s*\}\}/gi, sample.email || 'ankit@example.com');
            result = result.replace(/\{\{\s*phone\s*\}\}/gi, sample.phone || '+919068919789');
            result = result.replace(/\{\{\s*city\s*\}\}/gi, sample.city || 'Meerut City');
            
            if (sample.custom_fields) {
                for (const [key, val] of Object.entries(sample.custom_fields)) {
                    const regex = new RegExp('\\{\\{\\s*' + key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '\\s*\\}\\}', 'gi');
                    result = result.replace(regex, val || '');
                }
            }
            return result;
        }
    };
};
</script>

<div class="space-y-4 sm:space-y-6" x-data="campaignDashboard()">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('meta-leads.automation') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> All Campaigns
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-xs font-bold text-slate-700 truncate max-w-xs">{{ $campaign->name }}</span>
            </div>
            <div class="flex items-center gap-2.5 mt-1 flex-wrap">
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">
                    {{ $campaign->name }}
                </h2>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ match($campaign->status) {
                    'active' => 'bg-sky-50 text-sky-700 border border-sky-200',
                    'completed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    default => 'bg-slate-100 text-slate-600 border border-slate-200'
                } }}">
                    {{ ucfirst($campaign->status) }}
                </span>
            </div>
            @if($campaign->description)
                <p class="text-xs text-slate-500 font-medium mt-0.5">{{ $campaign->description }}</p>
            @endif
        </div>

        <!-- Action Toolbar -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <!-- Sync Sheet Button -->
            <form method="POST" action="{{ route('meta-leads.campaigns.sync', $campaign) }}">
                @csrf
                <button
                    type="submit"
                    title="Pull newly added leads from Google Sheet"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 shadow-2xs transition active:scale-95 cursor-pointer"
                >
                    <i class="fa-solid fa-arrows-rotate text-emerald-600 text-xs"></i>
                    <span>Sync Sheet</span>
                </button>
            </form>

            <!-- Bulk WhatsApp Button -->
            <button
                type="button"
                @click="openBulkWhatsApp()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-brands fa-whatsapp text-sm"></i>
                <span>Bulk WhatsApp</span>
                <span x-show="selectedLeads.length > 0" class="ml-1 px-1.5 py-0.2 bg-emerald-800 text-[10px] rounded-full" x-text="selectedLeads.length"></span>
            </button>

            <!-- Bulk Email Button -->
            <button
                type="button"
                @click="openBulkEmail()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-envelope text-xs"></i>
                <span>Bulk Email</span>
                <span x-show="selectedLeads.length > 0" class="ml-1 px-1.5 py-0.2 bg-sky-800 text-[10px] rounded-full" x-text="selectedLeads.length"></span>
            </button>
        </div>
    </div>

    <!-- Live Status Overview Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total Leads -->
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-400 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total Leads</span>
                <i class="fa-solid fa-users text-indigo-500 text-sm"></i>
            </div>
            <p class="text-2xl font-black text-slate-900">{{ number_format($campaign->total_leads) }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">Google Sheet Rows Imported</p>
        </div>

        <!-- WhatsApp Automation Status -->
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-400 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">WhatsApp Status</span>
                <i class="fa-brands fa-whatsapp text-emerald-500 text-base"></i>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-emerald-600">{{ $whatsappStats['sent'] }}</span>
                <span class="text-xs font-semibold text-slate-500">Sent</span>
                @if($whatsappStats['failed'] > 0)
                    <span class="text-xs font-bold text-rose-600 ml-auto">{{ $whatsappStats['failed'] }} Failed</span>
                @endif
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $campaign->total_leads > 0 ? ($whatsappStats['sent'] / $campaign->total_leads) * 100 : 0 }}%"></div>
            </div>
        </div>

        <!-- Email Automation Status -->
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-400 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Email Status</span>
                <i class="fa-solid fa-envelope text-blue-500 text-sm"></i>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-blue-600">{{ $emailStats['sent'] }}</span>
                <span class="text-xs font-semibold text-slate-500">Sent</span>
                @if($emailStats['failed'] > 0)
                    <span class="text-xs font-bold text-rose-600 ml-auto">{{ $emailStats['failed'] }} Failed</span>
                @endif
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $campaign->total_leads > 0 ? ($emailStats['sent'] / $campaign->total_leads) * 100 : 0 }}%"></div>
            </div>
        </div>

        <!-- Remaining Uncontacted Leads -->
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-400 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Pending Outreach</span>
                <i class="fa-solid fa-clock text-amber-500 text-sm"></i>
            </div>
            <p class="text-2xl font-black text-amber-600">
                {{ $campaign->leads()->where('email_status', 'not_sent')->where('whatsapp_status', 'not_sent')->count() }}
            </p>
            <p class="text-[11px] text-slate-500 mt-0.5">Leads with zero messages sent</p>
        </div>
    </div>

    <!-- Sticky Bulk Selection Actions Bar (Appears when leads are checked) -->
    <div
        x-show="selectedLeads.length > 0"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="bg-slate-900 text-white p-3 rounded-2xl shadow-lg border border-slate-800 flex items-center justify-between gap-3 flex-wrap"
    >
        <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-500 text-white flex items-center justify-center font-bold text-xs">
                <span x-text="selectedLeads.length"></span>
            </div>
            <span class="text-xs font-bold text-slate-200">
                <strong x-text="selectedLeads.length"></strong> lead(s) selected
            </span>
            <button
                type="button"
                @click="clearSelection()"
                class="text-[11px] font-semibold text-slate-400 hover:text-white underline cursor-pointer"
            >
                Deselect All
            </button>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="openBulkWhatsApp()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition cursor-pointer"
            >
                <i class="fa-brands fa-whatsapp text-xs"></i>
                <span>Send WhatsApp to (<span x-text="selectedLeads.length"></span>)</span>
            </button>

            <button
                type="button"
                @click="openBulkEmail()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl transition cursor-pointer"
            >
                <i class="fa-solid fa-envelope text-xs"></i>
                <span>Send Email to (<span x-text="selectedLeads.length"></span>)</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-xs">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 lg:pb-0 flex-nowrap shrink-0">
            <a
                href="{{ route('meta-leads.campaigns.show', [$campaign, ...request()->except('email_status', 'whatsapp_status', 'page')]) }}"
                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition shrink-0 {{ (!request('email_status') && !request('whatsapp_status')) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                All Leads ({{ $campaign->total_leads }})
            </a>

            <!-- Filter: WhatsApp Pending -->
            <a
                href="{{ route('meta-leads.campaigns.show', [$campaign, ...request()->except('whatsapp_status', 'page'), 'whatsapp_status' => 'not_sent']) }}"
                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0 {{ request('whatsapp_status') === 'not_sent' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 border border-amber-200' }}"
            >
                <i class="fa-brands fa-whatsapp text-[10px]"></i> WhatsApp Pending
            </a>

            <!-- Filter: WhatsApp Sent -->
            <a
                href="{{ route('meta-leads.campaigns.show', [$campaign, ...request()->except('whatsapp_status', 'page'), 'whatsapp_status' => 'sent']) }}"
                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0 {{ request('whatsapp_status') === 'sent' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}"
            >
                <i class="fa-solid fa-check text-[10px]"></i> WhatsApp Sent ({{ $whatsappStats['sent'] }})
            </a>

            <!-- Filter: Email Sent -->
            <a
                href="{{ route('meta-leads.campaigns.show', [$campaign, ...request()->except('email_status', 'page'), 'email_status' => 'sent']) }}"
                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0 {{ request('email_status') === 'sent' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-800 border border-blue-200' }}"
            >
                <i class="fa-solid fa-envelope text-[10px]"></i> Email Sent ({{ $emailStats['sent'] }})
            </a>
        </div>

        <!-- Search Form -->
        <form method="GET" action="{{ route('meta-leads.campaigns.show', $campaign) }}" class="flex items-center gap-2">
            @if(request('email_status'))
                <input type="hidden" name="email_status" value="{{ request('email_status') }}">
            @endif
            @if(request('whatsapp_status'))
                <input type="hidden" name="whatsapp_status" value="{{ request('whatsapp_status') }}">
            @endif

            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search name, phone, email, city..."
                    class="w-full lg:w-64 pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800 placeholder-slate-400"
                >
            </div>
            @if(request('search'))
                <a href="{{ route('meta-leads.campaigns.show', [$campaign, ...request()->except('search')]) }}" class="p-2 text-xs text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            @endif
        </form>
    </div>

    <!-- Leads Table / Responsive Cards -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <!-- Desktop Table (md+) -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider">
                        <th class="py-3 px-3.5 w-10 text-center">
                            <input
                                type="checkbox"
                                x-model="selectAllOnPage"
                                class="rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                                title="Select all on this page"
                            >
                        </th>
                        <th class="py-3 px-3">Lead / Prospect</th>
                        <th class="py-3 px-3">Contact Details</th>
                        <th class="py-3 px-3">Meta / Question Details</th>
                        <th class="py-3 px-3 text-center">WhatsApp Status</th>
                        <th class="py-3 px-3 text-center">Email Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-sky-50/30 transition group">
                            <!-- Checkbox -->
                            <td class="py-3 px-3.5 text-center">
                                <input
                                    type="checkbox"
                                    value="{{ $lead->id }}"
                                    class="lead-checkbox rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                                    :checked="selectedLeads.includes({{ $lead->id }})"
                                    @change="toggleSelectLead({{ $lead->id }})"
                                >
                            </td>

                            <!-- Lead Name & Avatar -->
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($lead->name ?? 'M', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900 truncate max-w-[140px] sm:max-w-xs">
                                            {{ $lead->name ?? 'Unknown Lead' }}
                                        </p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if($lead->platform)
                                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 uppercase font-mono">
                                                    {{ $lead->platform }}
                                                </span>
                                            @endif
                                            @if($lead->city)
                                                <span class="text-[10px] text-slate-500 truncate flex items-center gap-0.5">
                                                    <i class="fa-solid fa-location-dot text-[9px] text-slate-400"></i>
                                                    {{ $lead->city }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Details (Phone & Email) -->
                            <td class="py-3 px-3">
                                <div class="space-y-0.5">
                                    <p class="font-mono text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                        <span>{{ $lead->phone ?? $lead->raw_phone ?? '—' }}</span>
                                    </p>
                                    @if($lead->email)
                                        <p class="text-[11px] text-slate-500 truncate max-w-[180px] flex items-center gap-1.5">
                                            <i class="fa-solid fa-envelope text-[10px] text-slate-400"></i>
                                            <span>{{ $lead->email }}</span>
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <!-- Dynamic Sheet Custom Fields Preview -->
                            <td class="py-3 px-3">
                                <div class="space-y-0.5 max-w-xs">
                                    @php
                                        $position = $lead->custom_fields['which_position_are_you_applying_for?'] ?? $lead->custom_fields['which_position_are_you_applying_for'] ?? null;
                                        $exp = $lead->custom_fields['do_you_have_experience_in_field_sales?'] ?? null;
                                        $join = $lead->custom_fields['when_can_you_join?'] ?? null;
                                    @endphp

                                    @if($position)
                                        <p class="font-bold text-indigo-700 text-xs truncate">
                                            <i class="fa-solid fa-briefcase text-[10px] text-indigo-400 mr-1"></i>
                                            {{ ucwords(str_replace('_', ' ', $position)) }}
                                        </p>
                                    @endif

                                    <div class="flex items-center gap-1.5 flex-wrap text-[10px] text-slate-500">
                                        @if($exp)
                                            <span class="bg-slate-100 px-1.5 py-0.2 rounded font-medium">Exp: {{ str_replace('_', ' ', $exp) }}</span>
                                        @endif
                                        @if($join)
                                            <span class="bg-slate-100 px-1.5 py-0.2 rounded font-medium">Join: {{ str_replace('_', ' ', $join) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- WhatsApp Status Badge -->
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ match($lead->whatsapp_status) {
                                    'sent' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                    'sending', 'queued' => 'bg-amber-50 text-amber-700 border border-amber-200 animate-pulse',
                                    'failed' => 'bg-rose-50 text-rose-700 border border-rose-200',
                                    default => 'bg-slate-100 text-slate-500 border border-slate-200'
                                } }}" title="{{ $lead->whatsapp_error }}">
                                    <i class="fa-brands fa-whatsapp text-[10px]"></i>
                                    <span>{{ match($lead->whatsapp_status) {
                                        'sent' => 'Sent',
                                        'sending' => 'Sending',
                                        'queued' => 'Queued',
                                        'failed' => 'Failed',
                                        default => 'Not Sent'
                                    } }}</span>
                                </span>
                                @if($lead->whatsapp_sent_at)
                                    <p class="text-[9px] text-slate-400 mt-0.5">{{ $lead->whatsapp_sent_at->format('M d, H:i') }}</p>
                                @endif
                            </td>

                            <!-- Email Status Badge -->
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ match($lead->email_status) {
                                    'sent' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                    'sending', 'queued' => 'bg-amber-50 text-amber-700 border border-amber-200 animate-pulse',
                                    'failed' => 'bg-rose-50 text-rose-700 border border-rose-200',
                                    default => 'bg-slate-100 text-slate-500 border border-slate-200'
                                } }}" title="{{ $lead->email_error }}">
                                    <i class="fa-solid fa-envelope text-[10px]"></i>
                                    <span>{{ match($lead->email_status) {
                                        'sent' => 'Sent',
                                        'sending' => 'Sending',
                                        'queued' => 'Queued',
                                        'failed' => 'Failed',
                                        default => 'Not Sent'
                                    } }}</span>
                                </span>
                                @if($lead->email_sent_at)
                                    <p class="text-[9px] text-slate-400 mt-0.5">{{ $lead->email_sent_at->format('M d, H:i') }}</p>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        @click="openDetailById({{ $lead->id }})"
                                        class="p-1.5 text-slate-500 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition cursor-pointer"
                                        title="View All Sheet Columns & Data"
                                    >
                                        <i class="fa-solid fa-table-list text-xs"></i>
                                    </button>

                                    <!-- Quick Individual WhatsApp -->
                                    <button
                                        type="button"
                                        @click="openWhatsAppForLead({{ $lead->id }})"
                                        class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition cursor-pointer"
                                        title="Quick WhatsApp Message"
                                    >
                                        <i class="fa-brands fa-whatsapp text-xs"></i>
                                    </button>

                                    <!-- Quick Individual Email -->
                                    <button
                                        type="button"
                                        @click="openEmailForLead({{ $lead->id }})"
                                        class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition cursor-pointer"
                                        title="Quick Email"
                                    >
                                        <i class="fa-solid fa-envelope text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                <i class="fa-solid fa-users-slash text-2xl mb-2 block text-slate-300"></i>
                                No leads match the selected filter or search term.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pt-2">
        {{ $leads->links() }}
    </div>

    <!-- Bulk Email Modal -->
    <div
        x-show="bulkEmailModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="if(!emailSubmitting) bulkEmailModalOpen = false"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Bulk Email Dispatch</h3>
                        <p class="text-xs text-slate-500">
                            Sending to <strong class="text-blue-600" x-text="selectedLeads.length > 0 ? selectedLeads.length + ' selected lead(s)' : 'All ' + '{{ $campaign->total_leads }}' + ' leads in campaign'"></strong>
                        </p>
                    </div>
                </div>
                <button type="button" @click="bulkEmailModalOpen = false" class="text-slate-400 hover:text-slate-700" :disabled="emailSubmitting">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('meta-leads.campaigns.send-email', $campaign) }}" @submit="emailSubmitting = true">
                @csrf
                <template x-for="id in selectedLeads" :key="id">
                    <input type="hidden" name="lead_ids[]" :value="id">
                </template>

                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Template Selector -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Load Saved Email Template</label>
                        <select
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-800"
                            x-model="emailTemplateId"
                            @change="onEmailTemplateSelect($event.target.value)"
                        >
                            <option value="">-- Choose an Email Template or Compose Custom --</option>
                            @foreach($emailTemplates as $tmpl)
                                <option value="{{ $tmpl->id }}">{{ $tmpl->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Variable Chips -->
                    <div>
                        <p class="text-[11px] font-bold text-slate-600 mb-1">Click to insert lead variables from uploaded sheet:</p>
                        <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                            @foreach($campaign->getAvailableVariables() as $var)
                                <button
                                    type="button"
                                    @click="insertEmailVariable('{{ $var }}')"
                                    class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded-md text-[10px] font-mono text-slate-700 cursor-pointer transition active:scale-95"
                                    title="Insert &#123;&#123;{{ $var }}&#125;&#125;"
                                >
                                    + &#123;&#123;{{ $var }}&#125;&#125;
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Subject <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            name="subject"
                            required
                            x-model="emailSubject"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-800"
                        >
                    </div>

                    <!-- Email Image Upload & Insertion Tool -->
                    <div class="p-3 bg-sky-50/60 rounded-xl border border-sky-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-sky-900">Upload Image to Insert into HTML Email</label>
                            <span class="text-[10px] text-sky-700 font-mono">PNG, JPG, WEBP</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadModalImage($event, 'email')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-blue-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <div x-show="emailUploadedImageUrl" class="flex items-center gap-2 p-1.5 bg-white rounded-lg border border-sky-200">
                            <img :src="emailUploadedImageUrl" class="h-8 w-8 rounded object-cover border border-slate-200">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-mono text-slate-500 truncate" x-text="emailUploadedImageUrl"></p>
                            </div>
                            <button
                                type="button"
                                @click="insertEmailImage(emailUploadedImageUrl)"
                                class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[9px]"></i> Insert into HTML
                            </button>
                        </div>
                    </div>

                    <!-- Message Tabs: Edit vs Live Sample Preview -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1">
                        <label class="text-xs font-bold text-slate-700">Email Content (HTML / Text) <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="emailPreviewMode = false"
                                :class="!emailPreviewMode ? 'text-blue-600 font-bold border-b-2 border-blue-600' : 'text-slate-400 font-medium'"
                                class="text-xs pb-1 cursor-pointer"
                            >
                                Edit Source
                            </button>
                            <button
                                type="button"
                                @click="emailPreviewMode = true"
                                :class="emailPreviewMode ? 'text-blue-600 font-bold border-b-2 border-blue-600' : 'text-slate-400 font-medium'"
                                class="text-xs pb-1 cursor-pointer flex items-center gap-1"
                            >
                                <i class="fa-solid fa-eye text-[10px]"></i>
                                Live HTML Preview
                            </button>
                        </div>
                    </div>

                    <div x-show="!emailPreviewMode">
                        <textarea
                            name="body"
                            rows="7"
                            required
                            x-model="emailBody"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-slate-800 leading-relaxed"
                        ></textarea>
                    </div>

                    <div x-show="emailPreviewMode" class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-3 max-h-80 overflow-y-auto">
                        <div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase">Subject Preview:</p>
                            <p class="font-bold text-slate-900 mt-0.5" x-text="getSamplePreview(emailSubject)"></p>
                        </div>
                        <hr class="border-slate-200">
                        <div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase mb-1">Visual Rendered Preview:</p>
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs text-xs overflow-x-auto" x-html="getSamplePreview(emailBody)"></div>
                        </div>
                    </div>

                    <!-- Safety duplicate prevention check -->
                    <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-200">
                        <label class="flex items-center gap-2 text-xs font-semibold text-blue-900 cursor-pointer">
                            <input type="checkbox" name="skip_already_sent" value="1" x-model="emailSkipDuplicates" checked class="rounded text-blue-600 focus:ring-blue-500">
                            <span>Skip leads who already received an email in this campaign (Prevents duplicate sends)</span>
                        </label>
                    </div>

                    <!-- Quick Single Test Email Bar -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                <i class="fa-solid fa-vial text-sky-600"></i>
                                Send 1 Test Email (Preview Before Broadcast)
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                type="email"
                                x-model="testEmailAddress"
                                placeholder="Your test email address"
                                class="flex-1 px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                            >
                            <button
                                type="button"
                                @click="sendModalTestEmail()"
                                :disabled="testSending"
                                class="inline-flex items-center gap-1 px-3.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer whitespace-nowrap"
                            >
                                <i class="fa-solid fa-spinner animate-spin" x-show="testSending"></i>
                                <i class="fa-solid fa-paper-plane text-[10px]" x-show="!testSending"></i>
                                <span x-text="testSending ? 'Sending...' : 'Send Test'"></span>
                            </button>
                        </div>
                        <div x-show="testAlert" class="p-2.5 rounded-lg text-xs" :class="testAlert?.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                            <span class="font-bold" x-text="testAlert?.success ? 'Delivered: ' : 'Failed: '"></span>
                            <span x-text="testAlert?.message || testAlert?.error"></span>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button type="button" @click="bulkEmailModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                        :disabled="emailSubmitting"
                    >
                        <i class="fa-solid fa-spinner animate-spin" x-show="emailSubmitting"></i>
                        <i class="fa-solid fa-paper-plane" x-show="!emailSubmitting"></i>
                        <span x-text="emailSubmitting ? 'Sending Broadcast...' : 'Send Broadcast Emails Now'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk WhatsApp Modal -->
    <div
        x-show="bulkWhatsAppModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="if(!whatsappSubmitting) bulkWhatsAppModalOpen = false"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-base font-bold">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Bulk WhatsApp Messaging</h3>
                        <p class="text-xs text-slate-500">
                            Broadcasting to <strong class="text-emerald-600" x-text="selectedLeads.length > 0 ? selectedLeads.length + ' selected lead(s)' : 'All ' + '{{ $campaign->total_leads }}' + ' leads in campaign'"></strong>
                        </p>
                    </div>
                </div>
                <button type="button" @click="bulkWhatsAppModalOpen = false" class="text-slate-400 hover:text-slate-700" :disabled="whatsappSubmitting">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('meta-leads.campaigns.send-whatsapp', $campaign) }}" @submit="whatsappSubmitting = true">
                @csrf
                <template x-for="id in selectedLeads" :key="id">
                    <input type="hidden" name="lead_ids[]" :value="id">
                </template>

                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Template Selector -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Load Saved WhatsApp Template</label>
                        <select
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800"
                            x-model="whatsappTemplateId"
                            @change="onWhatsAppTemplateSelect($event.target.value)"
                        >
                            <option value="">-- Choose a WhatsApp Template or Type Below --</option>
                            @foreach($whatsappTemplates as $tmpl)
                                <option value="{{ $tmpl->id }}">{{ $tmpl->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Variable Chips -->
                    <div>
                        <p class="text-[11px] font-bold text-slate-600 mb-1">Click to insert lead variables from uploaded sheet:</p>
                        <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                            @foreach($campaign->getAvailableVariables() as $var)
                                <button
                                    type="button"
                                    @click="insertWhatsAppVariable('{{ $var }}')"
                                    class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded-md text-[10px] font-mono text-slate-700 cursor-pointer transition active:scale-95"
                                    title="Insert &#123;&#123;{{ $var }}&#125;&#125;"
                                >
                                    + &#123;&#123;{{ $var }}&#125;&#125;
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- WhatsApp Image Attachment Input -->
                    <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-emerald-900">Attach Image to WhatsApp (Optional)</label>
                            <span class="text-[10px] text-emerald-700 font-mono">PNG, JPG, WEBP</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadModalImage($event, 'whatsapp')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <input type="hidden" name="media_url" :value="whatsappMediaUrl">

                        <div x-show="whatsappMediaUrl" class="flex items-center gap-2 p-1.5 bg-white rounded-lg border border-emerald-200">
                            <img :src="whatsappMediaUrl" class="h-10 rounded object-cover border border-slate-200">
                            <span class="text-[10px] font-mono text-slate-500 truncate flex-1" x-text="whatsappMediaUrl"></span>
                            <button type="button" @click="whatsappMediaUrl = ''" class="text-rose-500 hover:text-rose-700 p-1 text-xs">
                                <i class="fa-solid fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>

                    <!-- Mode Toggle -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1">
                        <label class="text-xs font-bold text-slate-700">WhatsApp Message Body <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="whatsappPreviewMode = false"
                                :class="!whatsappPreviewMode ? 'text-emerald-600 font-bold border-b-2 border-emerald-600' : 'text-slate-400 font-medium'"
                                class="text-xs pb-1 cursor-pointer"
                            >
                                Compose
                            </button>
                            <button
                                type="button"
                                @click="whatsappPreviewMode = true"
                                :class="whatsappPreviewMode ? 'text-emerald-600 font-bold border-b-2 border-emerald-600' : 'text-slate-400 font-medium'"
                                class="text-xs pb-1 cursor-pointer flex items-center gap-1"
                            >
                                <i class="fa-solid fa-eye text-[10px]"></i>
                                Chat Bubble Preview
                            </button>
                        </div>
                    </div>

                    <div x-show="!whatsappPreviewMode">
                        <textarea
                            name="message"
                            rows="6"
                            required
                            x-model="whatsappMessage"
                            placeholder="Type your WhatsApp message using *bold*, _italics_, and dynamic variables..."
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-slate-800"
                        ></textarea>
                    </div>

                    <!-- Realistic WhatsApp Bubble Preview -->
                    <div x-show="whatsappPreviewMode" class="p-4 bg-[#e5ddd5] rounded-xl border border-slate-300 flex justify-end">
                        <div class="max-w-md bg-[#d9fdd3] p-3 rounded-2xl shadow-sm text-xs text-slate-800 leading-relaxed relative space-y-2">
                            <div x-show="whatsappMediaUrl" class="rounded-lg overflow-hidden border border-slate-300">
                                <img :src="whatsappMediaUrl" class="max-h-48 w-full object-cover">
                            </div>
                            <p class="whitespace-pre-line" x-text="getSamplePreview(whatsappMessage)"></p>
                            <div class="flex items-center justify-end gap-1 text-[9px] text-slate-500 mt-1 font-mono">
                                <span>12:00 PM</span>
                                <i class="fa-solid fa-check-double text-sky-500 text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Safety duplicate prevention check -->
                    <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200">
                        <label class="flex items-center gap-2 text-xs font-semibold text-emerald-900 cursor-pointer">
                            <input type="checkbox" name="skip_already_sent" value="1" x-model="whatsappSkipDuplicates" checked class="rounded text-emerald-600 focus:ring-emerald-500">
                            <span>Skip leads who already received a WhatsApp message in this campaign</span>
                        </label>
                    </div>

                    <!-- Quick Single Test WhatsApp Bar -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                                Send 1 Test WhatsApp (Verify on Your Mobile)
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                x-model="testPhone"
                                placeholder="Your 10-digit mobile number"
                                class="flex-1 px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium text-slate-800"
                            >
                            <button
                                type="button"
                                @click="sendModalTestWhatsApp()"
                                :disabled="testSending"
                                class="inline-flex items-center gap-1 px-3.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer whitespace-nowrap"
                            >
                                <i class="fa-solid fa-spinner animate-spin" x-show="testSending"></i>
                                <i class="fa-solid fa-paper-plane text-[10px]" x-show="!testSending"></i>
                                <span x-text="testSending ? 'Sending...' : 'Send Test'"></span>
                            </button>
                        </div>
                        <div x-show="testAlert" class="p-2.5 rounded-lg text-xs" :class="testAlert?.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                            <span class="font-bold" x-text="testAlert?.success ? 'Delivered: ' : 'Response: '"></span>
                            <span x-text="testAlert?.message || testAlert?.error"></span>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button type="button" @click="bulkWhatsAppModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                        :disabled="whatsappSubmitting"
                    >
                        <i class="fa-solid fa-spinner animate-spin" x-show="whatsappSubmitting"></i>
                        <i class="fa-solid fa-paper-plane" x-show="!whatsappSubmitting"></i>
                        <span x-text="whatsappSubmitting ? 'Dispatching WhatsApp...' : 'Send WhatsApp Broadcast'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lead Detail Slide-over / Modal (Shows ALL 24+ Google Sheet Columns) -->
    <div
        x-show="detailDrawerOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center sm:justify-end p-3 sm:p-0 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="detailDrawerOpen = false"></div>

        <div class="relative w-full max-w-lg h-full max-h-[95vh] sm:max-h-full bg-white sm:rounded-l-3xl shadow-2xl border-l border-slate-200 flex flex-col z-10 overflow-hidden">
            <!-- Drawer Header -->
            <div class="p-5 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center font-black text-sm">
                        <span x-text="(activeLead?.name || 'M')[0]?.toUpperCase()"></span>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900" x-text="activeLead?.name || 'Meta Lead Details'"></h3>
                        <p class="text-xs text-slate-500 font-mono" x-text="activeLead?.meta_lead_id || 'ID: ' + activeLead?.id"></p>
                    </div>
                </div>
                <button type="button" @click="detailDrawerOpen = false" class="text-slate-400 hover:text-slate-700 p-2">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
                <!-- Contact Summary Box -->
                <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Phone</p>
                        <p class="font-mono font-bold text-slate-800" x-text="activeLead?.phone || activeLead?.raw_phone || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Email</p>
                        <p class="font-medium text-slate-800 truncate" x-text="activeLead?.email || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">City / Location</p>
                        <p class="font-medium text-slate-800" x-text="activeLead?.city || '—'"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Platform</p>
                        <p class="font-medium text-slate-800 uppercase" x-text="activeLead?.platform || '—'"></p>
                    </div>
                </div>

                <!-- Automation Delivery Status -->
                <div class="space-y-2">
                    <p class="text-xs font-bold text-slate-700">Automation Delivery Record</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                            <span class="text-[10px] font-bold uppercase text-emerald-600 block">WhatsApp</span>
                            <span class="font-bold text-slate-800 capitalize" x-text="activeLead?.whatsapp_status"></span>
                            <p class="text-[10px] text-rose-500 font-mono" x-show="activeLead?.whatsapp_error" x-text="activeLead?.whatsapp_error"></p>
                        </div>
                        <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                            <span class="text-[10px] font-bold uppercase text-blue-600 block">Email</span>
                            <span class="font-bold text-slate-800 capitalize" x-text="activeLead?.email_status"></span>
                            <p class="text-[10px] text-rose-500 font-mono" x-show="activeLead?.email_error" x-text="activeLead?.email_error"></p>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Google Sheet Row Columns -->
                <div class="space-y-2 pt-2">
                    <p class="text-xs font-bold text-slate-700 flex items-center justify-between">
                        <span>All Google Sheet Columns</span>
                        <span class="text-[10px] text-slate-400 font-mono">Dynamic Fields</span>
                    </p>
                    <div class="space-y-2">
                        <template x-for="(val, key) in (activeLead?.raw_data || activeLead?.custom_fields || {})" :key="key">
                            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80 space-y-0.5">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate" x-text="key"></p>
                                <p class="text-xs font-medium text-slate-800 break-words font-mono" x-text="val || '—'"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer Quick Triggers -->
            <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="detailDrawerOpen = false; selectedLeads = [activeLead.id]; openBulkWhatsApp();"
                    class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center gap-1.5"
                >
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                    <span>Send WhatsApp</span>
                </button>
                <button
                    type="button"
                    @click="detailDrawerOpen = false; selectedLeads = [activeLead.id]; openBulkEmail();"
                    class="flex-1 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center gap-1.5"
                >
                    <i class="fa-solid fa-envelope text-xs"></i>
                    <span>Send Email</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

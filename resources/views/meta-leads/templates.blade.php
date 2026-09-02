@extends('layouts.app')

@section('content')
@php
    $sampleLeadData = [
        'id' => $sampleLead?->id,
        'name' => $sampleLead?->name ?: 'Amar',
        'phone' => $sampleLead?->phone ?: '919699867990',
        'email' => $sampleLead?->email ?: 'amarvcode@gmail.com',
        'city' => $sampleLead?->city ?: 'Mumbai',
        'platform' => $sampleLead?->platform ?: 'FB',
        'custom_fields' => $sampleLead?->custom_fields ?? [
            'which_position_are_you_applying_for?' => 'Area Sales Manager',
            'when_can_you_join?' => 'Immediately',
            'do_you_have_experience_in_field_sales?' => 'Yes',
        ],
    ];
@endphp
<script>
window.templatesManager = function() {
    const allTemplates = @json($emailTemplates->merge($whatsappTemplates)->keyBy('id'));
    const defaultSampleLead = @json($sampleLeadData);

    return {
        activeTab: 'email',
        createModalOpen: false,
        editModalOpen: false,
        testModalOpen: false,
        templateType: 'email',
        createEditorTab: 'editor', // 'editor' or 'preview'
        editEditorTab: 'editor',   // 'editor' or 'preview'
        templatesData: allTemplates || {},
        sampleLead: defaultSampleLead,

        // Create form state
        createTemplate: {
            type: 'email',
            name: '',
            subject: '',
            body: '',
            media_url: ''
        },

        // Edit form state
        editTemplate: {
            id: null,
            type: 'email',
            name: '',
            subject: '',
            body: '',
            media_url: ''
        },

        // Test modal state
        testTemplate: null,
        testType: 'email',
        testRecipient: '',
        testSending: false,
        testResult: null,

        // Image upload state
        imageUploading: false,
        uploadedImageUrl: '',

        openCreate(type) {
            this.templateType = type;
            this.createTemplate = {
                type: type,
                name: '',
                subject: type === 'email' ? 'Update regarding your application for @{{which_position_are_you_applying_for?}}' : '',
                body: type === 'email' 
                    ? '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">\n  <h2 style="color: #0284c7; margin-top: 0;">Hello @{{full_name}},</h2>\n  <p style="color: #334155; font-size: 14px; line-height: 1.6;">Thank you for your interest in the <strong>@{{which_position_are_you_applying_for?}}</strong> position at our <strong>@{{city}}</strong> branch.</p>\n  <p style="color: #334155; font-size: 14px; line-height: 1.6;">We have reviewed your application and would like to invite you for an initial discussion.</p>\n  <div style="margin: 25px 0; text-align: center;">\n    <a href="https://abhinandanlodha.qloudsoft.in" style="background: #0284c7; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">Confirm Interview Slot</a>\n  </div>\n  <p style="color: #64748b; font-size: 12px; border-top: 1px solid #f1f5f9; padding-top: 15px;">Best regards,<br>Talent Acquisition Team</p>\n</div>'
                    : 'Hi @{{full_name}}, 👋\n\nThank you for applying for *@{{which_position_are_you_applying_for?}}* in *@{{city}}*.\n\nAre you available for a brief briefing call today? Reply *YES* to schedule.',
                media_url: ''
            };
            this.createEditorTab = 'editor';
            this.uploadedImageUrl = '';
            this.createModalOpen = true;
        },

        openEditById(id) {
            const tmpl = this.templatesData[id];
            if (tmpl) {
                this.editTemplate = {
                    id: tmpl.id,
                    type: tmpl.type,
                    name: tmpl.name,
                    subject: tmpl.subject || '',
                    body: tmpl.body || '',
                    media_url: tmpl.media_url || ''
                };
                this.editEditorTab = 'editor';
                this.uploadedImageUrl = tmpl.media_url || '';
                this.editModalOpen = true;
            }
        },

        openTestById(id) {
            const tmpl = this.templatesData[id];
            if (tmpl) {
                this.openTest(tmpl);
            }
        },

        openTest(tmpl) {
            this.testTemplate = tmpl;
            this.testType = tmpl.type;
            this.testRecipient = tmpl.type === 'email' 
                ? (this.sampleLead?.email || 'amarvcode@gmail.com')
                : (this.sampleLead?.phone || '919699867990');
            this.testResult = null;
            this.testSending = false;
            this.testModalOpen = true;
        },

        dispatchTest() {
            if (!this.testRecipient) {
                alert('Please enter a test phone number or email.');
                return;
            }

            this.testSending = true;
            this.testResult = null;

            const url = this.testType === 'email' 
                ? '{{ route('meta-leads.templates.test-email') }}'
                : '{{ route('meta-leads.templates.test-whatsapp') }}';

            const payload = this.testType === 'email' ? {
                email: this.testRecipient,
                subject: this.testTemplate.subject,
                body: this.testTemplate.body,
                sample_lead_id: this.sampleLead?.id || null
            } : {
                phone: this.testRecipient,
                message: this.testTemplate.body,
                media_url: this.testTemplate.media_url || null,
                sample_lead_id: this.sampleLead?.id || null
            };

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                this.testSending = false;
                this.testResult = data;
            })
            .catch(err => {
                this.testSending = false;
                this.testResult = { success: false, error: err.message || 'Network error occurred while dispatching test.' };
            });
        },

        uploadImage(event, targetMode) {
            const file = event.target.files[0];
            if (!file) return;

            this.imageUploading = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('meta-leads.templates.upload-image') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.imageUploading = false;
                if (data.success && data.url) {
                    this.uploadedImageUrl = data.url;
                    if (targetMode === 'create') {
                        this.createTemplate.media_url = data.url;
                    } else if (targetMode === 'edit') {
                        this.editTemplate.media_url = data.url;
                    }
                } else {
                    alert(data.error || 'Failed to upload image.');
                }
            })
            .catch(err => {
                this.imageUploading = false;
                alert('Error uploading image: ' + err.message);
            });
        },

        insertImageTag(url, targetMode) {
            if (!url) return;
            const imgTag = `\n<img src="${url}" alt="Attachment" style="max-width: 100%; height: auto; border-radius: 8px; margin: 12px 0; display: block;" />\n`;
            if (targetMode === 'create') {
                this.createTemplate.body += imgTag;
            } else if (targetMode === 'edit') {
                this.editTemplate.body += imgTag;
            }
        },

        getSamplePreview(text) {
            if (!text) return '';
            const sample = this.sampleLead || {};
            let result = text;
            result = result.replace(/\{\{\s*full_name\s*\}\}/gi, sample.name || 'Amar');
            result = result.replace(/\{\{\s*name\s*\}\}/gi, sample.name || 'Amar');
            result = result.replace(/\{\{\s*email\s*\}\}/gi, sample.email || 'amarvcode@gmail.com');
            result = result.replace(/\{\{\s*phone\s*\}\}/gi, sample.phone || '919699867990');
            result = result.replace(/\{\{\s*city\s*\}\}/gi, sample.city || 'Mumbai');

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

<div class="space-y-4 sm:space-y-6" x-data="templatesManager()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('meta-leads.automation') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> WhatsApp Automation
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-xs font-bold text-slate-700">Templates Library</span>
            </div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-1">
                <i class="fa-solid fa-file-lines text-sky-600 text-base sm:text-xl"></i>
                <span>Message & Email Templates</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
                Design rich HTML emails with images and WhatsApp templates with dynamic variables & attachments.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="openCreate('whatsapp')"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-brands fa-whatsapp text-sm"></i>
                <span>New WhatsApp Template</span>
            </button>

            <button
                type="button"
                @click="openCreate('email')"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-envelope text-xs"></i>
                <span>New HTML Email Template</span>
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
            type="button"
            @click="activeTab = 'email'"
            :class="activeTab === 'email' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
        >
            <i class="fa-solid fa-envelope text-xs"></i>
            <span>Email Templates ({{ $emailTemplates->count() }})</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'whatsapp'"
            :class="activeTab === 'whatsapp' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
        >
            <i class="fa-brands fa-whatsapp text-sm"></i>
            <span>WhatsApp Templates ({{ $whatsappTemplates->count() }})</span>
        </button>
    </div>

    <!-- Email Templates Grid -->
    <div x-show="activeTab === 'email'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($emailTemplates as $tmpl)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs flex flex-col justify-between space-y-3 hover:border-sky-300 transition">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                <i class="fa-solid fa-envelope text-[9px]"></i> Email
                            </span>
                            @if(strip_tags($tmpl->body) !== $tmpl->body)
                                <span class="inline-flex items-center gap-1 text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                    <i class="fa-solid fa-code text-[8px]"></i> Rich HTML
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1">
                            <!-- Quick Test Button -->
                            <button
                                type="button"
                                @click="openTestById({{ $tmpl->id }})"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 rounded-lg text-xs font-bold transition cursor-pointer"
                                title="Send a Test Email to 1 Address"
                            >
                                <i class="fa-solid fa-paper-plane text-[10px]"></i>
                                <span>Test</span>
                            </button>

                            <!-- Edit Button -->
                            <button
                                type="button"
                                @click="openEditById({{ $tmpl->id }})"
                                class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                title="Edit Template"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Delete Form -->
                            <form method="POST" action="{{ route('meta-leads.templates.destroy', $tmpl) }}" onsubmit="return confirm('Delete this email template?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer" title="Delete Template">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <h3 class="font-bold text-slate-900 text-sm sm:text-base">{{ $tmpl->name }}</h3>
                    <p class="text-xs font-semibold text-slate-600 truncate">
                        <span class="text-slate-400">Subject:</span> {{ $tmpl->subject }}
                    </p>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-700 font-mono whitespace-pre-line line-clamp-4 max-h-32 overflow-hidden">
                        {{ strip_tags($tmpl->body) }}
                    </div>
                </div>

                @if(!empty($tmpl->variables))
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Variables Used:</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($tmpl->variables as $v)
                                <span class="px-1.5 py-0.5 bg-sky-50 text-sky-700 border border-sky-200 rounded text-[9px] font-mono font-semibold">
                                    &#123;&#123;{{ $v }}&#125;&#125;
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-2 bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400">
                <i class="fa-solid fa-envelope-open-text text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-600">No email templates created yet.</p>
                <button
                    type="button"
                    @click="openCreate('email')"
                    class="mt-3 px-3 py-1.5 bg-sky-600 text-white rounded-xl text-xs font-bold cursor-pointer"
                >
                    Create Email Template
                </button>
            </div>
        @endforelse
    </div>

    <!-- WhatsApp Templates Grid -->
    <div x-show="activeTab === 'whatsapp'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($whatsappTemplates as $tmpl)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs flex flex-col justify-between space-y-3 hover:border-emerald-300 transition">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i class="fa-brands fa-whatsapp text-xs"></i> WhatsApp
                            </span>
                            @if(!empty($tmpl->media_url))
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <i class="fa-solid fa-image text-[8px]"></i> Image Attached
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1">
                            <!-- Quick Test Button -->
                            <button
                                type="button"
                                @click="openTestById({{ $tmpl->id }})"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold transition cursor-pointer"
                                title="Send a Test WhatsApp to 1 Number"
                            >
                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                <span>Test</span>
                            </button>

                            <!-- Edit Button -->
                            <button
                                type="button"
                                @click="openEditById({{ $tmpl->id }})"
                                class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                title="Edit Template"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Delete Form -->
                            <form method="POST" action="{{ route('meta-leads.templates.destroy', $tmpl) }}" onsubmit="return confirm('Delete this WhatsApp template?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer" title="Delete Template">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <h3 class="font-bold text-slate-900 text-sm sm:text-base">{{ $tmpl->name }}</h3>

                    @if(!empty($tmpl->media_url))
                        <div class="rounded-xl overflow-hidden border border-slate-200 max-h-36 bg-slate-100 flex items-center justify-center">
                            <img src="{{ $tmpl->media_url }}" alt="Template Media" class="max-h-36 w-auto object-cover">
                        </div>
                    @endif

                    <div class="p-3 bg-[#e5ddd5]/40 rounded-xl border border-slate-200 text-xs text-slate-800 font-sans whitespace-pre-line leading-relaxed">
                        {{ $tmpl->body }}
                    </div>
                </div>

                @if(!empty($tmpl->variables))
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Variables Used:</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($tmpl->variables as $v)
                                <span class="px-1.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[9px] font-mono font-semibold">
                                    &#123;&#123;{{ $v }}&#125;&#125;
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-2 bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400">
                <i class="fa-brands fa-whatsapp text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-600">No WhatsApp templates created yet.</p>
                <button
                    type="button"
                    @click="openCreate('whatsapp')"
                    class="mt-3 px-3 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold cursor-pointer"
                >
                    Create WhatsApp Template
                </button>
            </div>
        @endforelse
    </div>

    <!-- Create Template Modal -->
    <div
        x-show="createModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="createModalOpen = false"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm sm:text-base font-bold text-slate-900" x-text="'Create New ' + (templateType === 'email' ? 'HTML Email' : 'WhatsApp') + ' Template'"></h3>
                <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-700">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('meta-leads.templates.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" :value="templateType">
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Template Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            name="name"
                            required
                            x-model="createTemplate.name"
                            placeholder="e.g. Sales Interview Call Letter"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <!-- Email Specific Subject -->
                    <div x-show="templateType === 'email'">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Subject <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            name="subject"
                            :required="templateType === 'email'"
                            x-model="createTemplate.subject"
                            placeholder="e.g. Update regarding your application for &#123;&#123;which_position_are_you_applying_for?&#125;&#125;"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <!-- WhatsApp Image Attachment Input -->
                    <div x-show="templateType === 'whatsapp'" class="p-3.5 bg-emerald-50/50 rounded-xl border border-emerald-200 space-y-2">
                        <label class="block text-xs font-bold text-emerald-900">Attach Image to WhatsApp Message (Optional)</label>
                        <p class="text-[11px] text-emerald-700">Upload a banner, promotional flyer, or sample tour photo.</p>
                        
                        <div class="flex items-center gap-3">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadImage($event, 'create')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <input
                            type="text"
                            name="media_url"
                            x-model="createTemplate.media_url"
                            placeholder="Or paste an image URL here..."
                            class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 font-mono"
                        >

                        <div x-show="createTemplate.media_url" class="relative inline-block mt-2">
                            <img :src="createTemplate.media_url" class="h-20 rounded-lg border border-slate-300 object-cover">
                            <button type="button" @click="createTemplate.media_url = ''" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-500 text-white rounded-full flex items-center justify-center text-[10px]">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Email Image Upload & Insertion Tool -->
                    <div x-show="templateType === 'email'" class="p-3.5 bg-sky-50/60 rounded-xl border border-sky-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-sky-900">Upload Image to Insert into HTML Email</label>
                            <span class="text-[10px] text-sky-700 font-mono">PNG, JPG, WEBP (Max 10MB)</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadImage($event, 'create')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-600 file:text-white hover:file:bg-sky-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-sky-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <div x-show="uploadedImageUrl" class="flex items-center gap-3 p-2 bg-white rounded-lg border border-sky-200">
                            <img :src="uploadedImageUrl" class="h-10 w-10 rounded object-cover border border-slate-200">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-mono text-slate-500 truncate" x-text="uploadedImageUrl"></p>
                            </div>
                            <button
                                type="button"
                                @click="insertImageTag(uploadedImageUrl, 'create')"
                                class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white text-[10px] font-bold rounded-lg shrink-0 cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[9px]"></i> Insert into HTML
                            </button>
                        </div>
                    </div>

                    <!-- Editor Header & Tabs -->
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-200 pb-1 mb-2">
                            <label class="text-xs font-bold text-slate-700">Template Body Content <span class="text-rose-500">*</span></label>
                            
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="createEditorTab = 'editor'"
                                    :class="createEditorTab === 'editor' ? 'text-sky-600 font-bold border-b-2 border-sky-600' : 'text-slate-400 font-medium'"
                                    class="text-xs pb-1 cursor-pointer"
                                >
                                    <i class="fa-solid fa-code text-[10px]"></i> Editor
                                </button>
                                <button
                                    type="button"
                                    @click="createEditorTab = 'preview'"
                                    :class="createEditorTab === 'preview' ? 'text-sky-600 font-bold border-b-2 border-sky-600' : 'text-slate-400 font-medium'"
                                    class="text-xs pb-1 cursor-pointer"
                                >
                                    <i class="fa-solid fa-eye text-[10px]"></i> Visual Preview
                                </button>
                            </div>
                        </div>

                        <!-- Code/Textarea Editor -->
                        <div x-show="createEditorTab === 'editor'">
                            <textarea
                                name="body"
                                rows="8"
                                required
                                x-model="createTemplate.body"
                                placeholder="Write HTML or plain text using dynamic variables like &#123;&#123;full_name&#125;&#125;, &#123;&#123;city&#125;&#125;, etc."
                                class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono text-slate-800 leading-relaxed"
                            ></textarea>
                        </div>

                        <!-- Visual HTML / Chat Preview -->
                        <div x-show="createEditorTab === 'preview'" class="p-4 bg-slate-100 rounded-xl border border-slate-300 min-h-48 overflow-y-auto">
                            <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Sample Preview (using Amar from Mumbai):</p>
                            
                            <!-- WhatsApp chat view -->
                            <div x-show="templateType === 'whatsapp'" class="bg-[#e5ddd5] p-4 rounded-xl flex justify-end">
                                <div class="max-w-md bg-[#d9fdd3] p-3 rounded-2xl shadow-sm text-xs text-slate-800 leading-relaxed space-y-2">
                                    <div x-show="createTemplate.media_url">
                                        <img :src="createTemplate.media_url" class="rounded-lg max-h-48 w-full object-cover">
                                    </div>
                                    <p class="whitespace-pre-line" x-text="getSamplePreview(createTemplate.body)"></p>
                                </div>
                            </div>

                            <!-- Email rendered HTML view -->
                            <div x-show="templateType === 'email'" class="bg-white p-4 rounded-xl shadow-xs border border-slate-200" x-html="getSamplePreview(createTemplate.body)">
                            </div>
                        </div>
                    </div>

                    <!-- Variable Chips -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <p class="text-[11px] font-bold text-slate-600 mb-1">Click to insert variables:</p>
                        <div class="flex flex-wrap gap-1 font-mono">
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;full_name&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;full_name&#125;&#125;</button>
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;phone&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;phone&#125;&#125;</button>
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;email&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;email&#125;&#125;</button>
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;city&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;city&#125;&#125;</button>
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;which_position_are_you_applying_for?&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;which_position_are_you_applying_for?&#125;&#125;</button>
                            <button type="button" @click="createTemplate.body += ' &#123;&#123;when_can_you_join?&#125;&#125;'" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[10px] hover:bg-slate-100">+ &#123;&#123;when_can_you_join?&#125;&#125;</button>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer">
                        Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div
        x-show="editModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="editModalOpen = false"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm sm:text-base font-bold text-slate-900" x-text="'Edit ' + (editTemplate.type === 'email' ? 'HTML Email' : 'WhatsApp') + ' Template'"></h3>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-700">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form :action="'{{ url('/meta-leads/templates') }}/' + editTemplate.id" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Template Name</label>
                        <input
                            type="text"
                            name="name"
                            required
                            x-model="editTemplate.name"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <div x-show="editTemplate.type === 'email'">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Subject</label>
                        <input
                            type="text"
                            name="subject"
                            x-model="editTemplate.subject"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <!-- WhatsApp Image Attachment in Edit -->
                    <div x-show="editTemplate.type === 'whatsapp'" class="p-3.5 bg-emerald-50/50 rounded-xl border border-emerald-200 space-y-2">
                        <label class="block text-xs font-bold text-emerald-900">Attach Image to WhatsApp Message</label>
                        
                        <div class="flex items-center gap-3">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadImage($event, 'edit')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <input
                            type="text"
                            name="media_url"
                            x-model="editTemplate.media_url"
                            placeholder="Or paste image URL here..."
                            class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 font-mono"
                        >

                        <div x-show="editTemplate.media_url" class="relative inline-block mt-2">
                            <img :src="editTemplate.media_url" class="h-20 rounded-lg border border-slate-300 object-cover">
                            <button type="button" @click="editTemplate.media_url = ''" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-500 text-white rounded-full flex items-center justify-center text-[10px]">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Email Image Upload in Edit -->
                    <div x-show="editTemplate.type === 'email'" class="p-3.5 bg-sky-50/60 rounded-xl border border-sky-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-sky-900">Upload Image to Insert into HTML Email</label>
                            <span class="text-[10px] text-sky-700 font-mono">PNG, JPG, WEBP (Max 10MB)</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                type="file"
                                accept="image/*"
                                @change="uploadImage($event, 'edit')"
                                class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-600 file:text-white hover:file:bg-sky-700 cursor-pointer"
                            >
                            <span x-show="imageUploading" class="text-xs text-sky-600 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-spinner animate-spin"></i> Uploading...
                            </span>
                        </div>

                        <div x-show="uploadedImageUrl" class="flex items-center gap-3 p-2 bg-white rounded-lg border border-sky-200">
                            <img :src="uploadedImageUrl" class="h-10 w-10 rounded object-cover border border-slate-200">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-mono text-slate-500 truncate" x-text="uploadedImageUrl"></p>
                            </div>
                            <button
                                type="button"
                                @click="insertImageTag(uploadedImageUrl, 'edit')"
                                class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white text-[10px] font-bold rounded-lg shrink-0 cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[9px]"></i> Insert into HTML
                            </button>
                        </div>
                    </div>

                    <!-- Editor Header & Tabs -->
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-200 pb-1 mb-2">
                            <label class="text-xs font-bold text-slate-700">Template Body Content</label>
                            
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="editEditorTab = 'editor'"
                                    :class="editEditorTab === 'editor' ? 'text-sky-600 font-bold border-b-2 border-sky-600' : 'text-slate-400 font-medium'"
                                    class="text-xs pb-1 cursor-pointer"
                                >
                                    <i class="fa-solid fa-code text-[10px]"></i> Editor
                                </button>
                                <button
                                    type="button"
                                    @click="editEditorTab = 'preview'"
                                    :class="editEditorTab === 'preview' ? 'text-sky-600 font-bold border-b-2 border-sky-600' : 'text-slate-400 font-medium'"
                                    class="text-xs pb-1 cursor-pointer"
                                >
                                    <i class="fa-solid fa-eye text-[10px]"></i> Visual Preview
                                </button>
                            </div>
                        </div>

                        <div x-show="editEditorTab === 'editor'">
                            <textarea
                                name="body"
                                rows="8"
                                required
                                x-model="editTemplate.body"
                                class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono text-slate-800 leading-relaxed"
                            ></textarea>
                        </div>

                        <div x-show="editEditorTab === 'preview'" class="p-4 bg-slate-100 rounded-xl border border-slate-300 min-h-48 overflow-y-auto">
                            <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Sample Preview (using Amar from Mumbai):</p>
                            
                            <!-- WhatsApp chat view -->
                            <div x-show="editTemplate.type === 'whatsapp'" class="bg-[#e5ddd5] p-4 rounded-xl flex justify-end">
                                <div class="max-w-md bg-[#d9fdd3] p-3 rounded-2xl shadow-sm text-xs text-slate-800 leading-relaxed space-y-2">
                                    <div x-show="editTemplate.media_url">
                                        <img :src="editTemplate.media_url" class="rounded-lg max-h-48 w-full object-cover">
                                    </div>
                                    <p class="whitespace-pre-line" x-text="getSamplePreview(editTemplate.body)"></p>
                                </div>
                            </div>

                            <!-- Email rendered HTML view -->
                            <div x-show="editTemplate.type === 'email'" class="bg-white p-4 rounded-xl shadow-xs border border-slate-200" x-html="getSamplePreview(editTemplate.body)">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Test Send Modal (Single Email & Number) -->
    <div
        x-show="testModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="if(!testSending) testModalOpen = false"></div>

        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <span :class="testType === 'email' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700'" class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold">
                        <i :class="testType === 'email' ? 'fa-solid fa-envelope' : 'fa-brands fa-whatsapp'"></i>
                    </span>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900" x-text="'Send Test ' + (testType === 'email' ? 'Email' : 'WhatsApp')"></h3>
                </div>
                <button type="button" @click="testModalOpen = false" :disabled="testSending" class="text-slate-400 hover:text-slate-700">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <!-- Result Alert Banner -->
                <div x-show="testResult" class="p-3.5 rounded-xl border text-xs flex items-start gap-2" :class="testResult?.success ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'">
                    <i :class="testResult?.success ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-circle-exclamation text-rose-600'" class="mt-0.5 shrink-0"></i>
                    <div>
                        <p class="font-bold" x-text="testResult?.success ? 'Success!' : 'Dispatch Failed'"></p>
                        <p class="mt-0.5" x-text="testResult?.message || testResult?.error"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1" x-text="testType === 'email' ? 'Send Test Email To (Your Inbox)' : 'Send Test WhatsApp To (Your Phone Number)'"></label>
                    <input
                        :type="testType === 'email' ? 'email' : 'text'"
                        x-model="testRecipient"
                        :placeholder="testType === 'email' ? 'e.g. amarvcode@gmail.com' : 'e.g. 9699867990 or 919699867990'"
                        class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Dynamic variables will be automatically replaced with sample lead data (Amar, Mumbai).</p>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-700">Template Preview</span>
                        <span class="text-[10px] text-slate-400 font-mono" x-text="testTemplate?.name"></span>
                    </div>

                    <div x-show="testTemplate?.media_url" class="rounded-lg overflow-hidden border border-slate-200 max-h-32 bg-white flex items-center justify-center">
                        <img :src="testTemplate?.media_url" class="max-h-32 w-auto object-cover">
                    </div>

                    <div x-show="testType === 'email'" class="text-xs">
                        <p class="font-bold text-slate-700 mb-1">Subject: <span class="font-normal" x-text="getSamplePreview(testTemplate?.subject)"></span></p>
                        <div class="p-3 bg-white rounded-lg border border-slate-200 max-h-40 overflow-y-auto text-xs" x-html="getSamplePreview(testTemplate?.body)"></div>
                    </div>

                    <div x-show="testType === 'whatsapp'" class="text-xs">
                        <div class="p-3 bg-[#d9fdd3] rounded-xl text-slate-800 whitespace-pre-line text-xs" x-text="getSamplePreview(testTemplate?.body)"></div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                <button type="button" @click="testModalOpen = false" :disabled="testSending" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                    Close
                </button>
                <button
                    type="button"
                    @click="dispatchTest()"
                    :disabled="testSending"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                >
                    <i class="fa-solid fa-spinner animate-spin" x-show="testSending"></i>
                    <i class="fa-solid fa-paper-plane" x-show="!testSending"></i>
                    <span x-text="testSending ? 'Sending Test...' : 'Send Test Now'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

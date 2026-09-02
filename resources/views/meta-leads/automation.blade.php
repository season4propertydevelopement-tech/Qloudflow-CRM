@extends('layouts.app')

@section('content')
<script>
window.automationDashboard = function() {
    const campaignsMap = @json($campaigns->keyBy('id'));

    return {
        createModalOpen: false,
        editModalOpen: false,
        editCampaign: { id: null, name: '', description: '', status: 'active' },
        campaignsData: campaignsMap || {},
        previewLoading: false,
        previewData: null,
        previewError: null,
        sheetUrl: 'https://docs.google.com/spreadsheets/d/1ni-dYXcAk-WMDWsF98nnv1an832xViwZoE6u9Sacnl8/edit?gid=0#gid=0',
        isSubmitting: false,

        testSheetConnection() {
            if (!this.sheetUrl) return;
            this.previewLoading = true;
            this.previewError = null;
            this.previewData = null;

            fetch('{{ route('meta-leads.campaigns.preview-sheet') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ sheet_url: this.sheetUrl })
            })
            .then(res => res.json())
            .then(data => {
                this.previewLoading = false;
                if (data.success) {
                    this.previewData = data;
                } else {
                    this.previewError = data.error || 'Failed to connect to Google Sheet.';
                }
            })
            .catch(err => {
                this.previewLoading = false;
                this.previewError = 'Network error while attempting to connect to Google Sheet.';
            });
        },

        openEditById(id) {
            const camp = this.campaignsData[id];
            if (camp) {
                this.editCampaign = { ...camp };
                this.editModalOpen = true;
            }
        }
    };
};
</script>

<div class="space-y-4 sm:space-y-6" x-data="automationDashboard()">

    <!-- Top Breadcrumb / Title Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-2 sm:pb-3 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-200">
                    <i class="fa-brands fa-meta text-xs"></i> Meta Leads Suite
                </span>
                <span class="text-xs font-semibold text-slate-400">/</span>
                <span class="text-xs font-bold text-slate-700">WhatsApp & Email Automation</span>
            </div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-1">
                <i class="fa-solid fa-robot text-sky-600 text-base sm:text-xl"></i>
                <span>Campaign Automation Dashboard</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
                Import Meta Leads dynamically from Google Sheets, customize personalized variables, and dispatch bulk WhatsApp & Email sequences.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <a
                href="{{ route('meta-leads.templates.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 shadow-2xs transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-file-lines text-indigo-600 text-xs"></i>
                <span>Manage Templates</span>
            </a>

            <!-- Create Campaign Button -->
            <button
                type="button"
                @click="createModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-sky-600/25 transition active:scale-95 cursor-pointer"
            >
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Create Campaign</span>
            </button>
        </div>
    </div>

    <!-- Metric Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <!-- Card 1: Total Campaigns -->
        <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate">Campaigns</p>
                <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{{ number_format($stats['total_campaigns']) }}</p>
            </div>
        </div>

        <!-- Card 2: Total Leads -->
        <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate">Total Leads</p>
                <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{{ number_format($stats['total_leads']) }}</p>
            </div>
        </div>

        <!-- Card 3: WhatsApp Sent -->
        <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-emerald-600 truncate">WhatsApp Sent</p>
                <div class="flex items-baseline gap-1.5 mt-0.5">
                    <p class="text-xl sm:text-2xl font-black text-slate-900">{{ number_format($stats['whatsapp_sent']) }}</p>
                    @if($stats['whatsapp_failed'] > 0)
                        <span class="text-[10px] text-rose-500 font-semibold">({{ $stats['whatsapp_failed'] }} fail)</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Card 4: Emails Sent -->
        <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-blue-600 truncate">Emails Sent</p>
                <div class="flex items-baseline gap-1.5 mt-0.5">
                    <p class="text-xl sm:text-2xl font-black text-slate-900">{{ number_format($stats['emails_sent']) }}</p>
                    @if($stats['emails_failed'] > 0)
                        <span class="text-[10px] text-rose-500 font-semibold">({{ $stats['emails_failed'] }} fail)</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Card 5: Delivery Rate -->
        <div class="col-span-2 lg:col-span-1 bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-purple-600 truncate">Delivery Success</p>
                <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{{ $stats['delivery_rate'] }}%</p>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-white p-2.5 sm:p-3 rounded-2xl border border-slate-200 shadow-xs">
        <!-- Status Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 sm:pb-0 -mx-1 px-1 flex-nowrap md:flex-wrap shrink-0">
            <a
                href="{{ route('meta-leads.automation', array_merge(request()->except('status', 'page'))) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ !request('status') ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                <span>All Campaigns</span>
            </a>

            <a
                href="{{ route('meta-leads.automation', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('status') === 'active' ? 'bg-sky-600 text-white shadow-xs' : 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100' }}"
            >
                <i class="fa-solid fa-circle-play text-[10px]"></i>
                <span>Active</span>
            </a>

            <a
                href="{{ route('meta-leads.automation', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('status') === 'completed' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' }}"
            >
                <i class="fa-solid fa-circle-check text-[10px]"></i>
                <span>Completed</span>
            </a>

            <a
                href="{{ route('meta-leads.automation', array_merge(request()->except('status', 'page'), ['status' => 'archived'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ request('status') === 'archived' ? 'bg-slate-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                <i class="fa-solid fa-box-archive text-[10px]"></i>
                <span>Archived</span>
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="{{ route('meta-leads.automation') }}" class="flex items-center gap-2 pt-1 md:pt-0">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search campaigns..."
                    class="w-full md:w-64 pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800 placeholder-slate-400"
                >
            </div>
            @if(request('search'))
                <a href="{{ route('meta-leads.automation', request()->except('search')) }}" class="p-2 text-xs text-slate-400 hover:text-slate-600 shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            @endif
        </form>
    </div>

    <!-- Campaigns List / Cards Grid -->
    <div class="space-y-3 sm:space-y-4">
        @forelse($campaigns as $campaign)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs hover:border-sky-300 transition-all group">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Left: Campaign Info -->
                    <div class="space-y-2 min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 truncate">
                                <a href="{{ route('meta-leads.campaigns.show', $campaign) }}" class="hover:text-sky-600 transition">
                                    {{ $campaign->name }}
                                </a>
                            </h3>

                            <!-- Status Badge -->
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full {{ match($campaign->status) {
                                'active' => 'bg-sky-50 text-sky-700 border border-sky-200',
                                'completed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                'paused' => 'bg-amber-50 text-amber-700 border border-amber-200',
                                default => 'bg-slate-100 text-slate-600 border border-slate-200'
                            } }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ match($campaign->status) {
                                    'active' => 'bg-sky-500 animate-pulse',
                                    'completed' => 'bg-emerald-500',
                                    'paused' => 'bg-amber-500',
                                    default => 'bg-slate-400'
                                } }}"></span>
                                {{ ucfirst($campaign->status) }}
                            </span>

                            <span class="text-xs text-slate-400 font-medium">
                                Created {{ $campaign->created_at->diffForHumans() }}
                            </span>
                        </div>

                        @if($campaign->description)
                            <p class="text-xs text-slate-600 line-clamp-1 font-medium">{{ $campaign->description }}</p>
                        @endif

                        @if($campaign->sheet_url)
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-mono truncate max-w-xl">
                                <i class="fa-solid fa-table-cells text-emerald-600"></i>
                                <span class="text-slate-400">Source Sheet:</span>
                                <span class="truncate">{{ $campaign->sheet_url }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Middle: Automation Metrics & Progress -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 shrink-0 py-2 sm:py-0 border-y sm:border-y-0 sm:border-x border-slate-100 px-0 sm:px-4">
                        <!-- Leads Count -->
                        <div class="text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Leads</p>
                            <p class="text-base sm:text-lg font-black text-slate-800 mt-0.5">{{ number_format($campaign->leads_count) }}</p>
                        </div>

                        <!-- WhatsApp Progress -->
                        <div class="text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 flex items-center justify-center gap-1">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </p>
                            <p class="text-base sm:text-lg font-black text-emerald-700 mt-0.5">
                                {{ $campaign->whatsapp_sent }}
                                <span class="text-[10px] font-semibold text-slate-400">/ {{ $campaign->leads_count }}</span>
                            </p>
                        </div>

                        <!-- Email Progress -->
                        <div class="text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 flex items-center justify-center gap-1">
                                <i class="fa-solid fa-envelope"></i> Email
                            </p>
                            <p class="text-base sm:text-lg font-black text-blue-700 mt-0.5">
                                {{ $campaign->emails_sent }}
                                <span class="text-[10px] font-semibold text-slate-400">/ {{ $campaign->leads_count }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Right: Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0 self-end lg:self-center">
                        <a
                            href="{{ route('meta-leads.campaigns.show', $campaign) }}"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95"
                        >
                            <span>Open Campaign</span>
                            <i class="fa-solid fa-arrow-right text-[11px]"></i>
                        </a>

                        <button
                            type="button"
                            @click="openEditById({{ $campaign->id }})"
                            class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                            title="Edit Campaign"
                        >
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>

                        <form
                            method="POST"
                            action="{{ route('meta-leads.campaigns.destroy', $campaign) }}"
                            onsubmit="return confirm('Are you sure you want to delete this campaign and all its lead records?');"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-xl transition"
                                title="Delete Campaign"
                            >
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center mx-auto text-2xl mb-4">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-slate-900">No campaigns found</h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1">
                    Get started by creating your first Meta Leads campaign. Import directly from Google Sheets to trigger WhatsApp and Email automations.
                </p>
                <div class="mt-5">
                    <button
                        type="button"
                        @click="createModalOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Create Your First Campaign</span>
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination Links -->
    <div class="pt-2">
        {{ $campaigns->links() }}
    </div>

    <!-- Create Campaign Modal -->
    <div
        x-show="createModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            x-show="createModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"
            @click="if(!isSubmitting) createModalOpen = false"
        ></div>

        <!-- Modal Card -->
        <div
            x-show="createModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 my-8"
        >
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Create New Meta Leads Campaign</h3>
                        <p class="text-xs text-slate-500">Connect Google Sheet, auto-detect lead columns, and start automations</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="createModalOpen = false"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                    :disabled="isSubmitting"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ route('meta-leads.campaigns.store') }}" @submit="isSubmitting = true">
                @csrf
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Campaign Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Campaign Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            required
                            placeholder="e.g. Bisani Rocketpay June Leads Campaign"
                            value="{{ old('name', 'Bisani Rocketpay Leads Campaign') }}"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Description / Goal (Optional)
                        </label>
                        <textarea
                            name="description"
                            rows="2"
                            placeholder="e.g. Area Sales Manager & Business Development hiring sequence"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >{{ old('description', 'Automated WhatsApp briefing and interview invitation email sequence for Meta Lead respondents.') }}</textarea>
                    </div>

                    <!-- Google Sheet URL -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700">
                                Google Sheet URL <span class="text-rose-500">*</span>
                            </label>
                            <button
                                type="button"
                                @click="testSheetConnection()"
                                class="text-[11px] font-bold text-sky-600 hover:text-sky-700 hover:underline flex items-center gap-1 cursor-pointer"
                                :disabled="previewLoading"
                            >
                                <i class="fa-solid fa-arrows-rotate text-[10px]" :class="previewLoading ? 'animate-spin' : ''"></i>
                                <span>Test & Preview Sheet</span>
                            </button>
                        </div>
                        <div class="relative">
                            <i class="fa-solid fa-table-cells absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="url"
                                name="sheet_url"
                                required
                                x-model="sheetUrl"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                class="w-full pl-8 pr-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono text-slate-800"
                            >
                        </div>
                        <p class="text-[11px] text-slate-400">
                            Ensure the sheet sharing is set to <strong>"Anyone with the link can view"</strong>.
                        </p>
                    </div>

                    <!-- Live Preview Feedback Box -->
                    <div x-show="previewLoading" class="p-3 bg-sky-50 rounded-xl border border-sky-200 text-sky-800 text-xs flex items-center gap-2">
                        <i class="fa-solid fa-spinner animate-spin text-sky-600"></i>
                        <span>Connecting to Google Sheet and parsing columns server-side...</span>
                    </div>

                    <div x-show="previewError" class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-rose-800 text-xs flex items-center gap-2" x-text="previewError">
                    </div>

                    <div x-show="previewData && previewData.success" class="p-3.5 bg-emerald-50/70 rounded-xl border border-emerald-200 space-y-2">
                        <div class="flex items-center justify-between text-xs text-emerald-900 font-bold">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                <span>Sheet Connected Successfully!</span>
                            </div>
                            <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded text-[11px]" x-text="previewData?.total_rows + ' Rows Detected'"></span>
                        </div>
                        <div class="text-[11px] text-slate-600">
                            <strong>Detected Headers (<span x-text="previewData?.headers?.length"></span>):</strong>
                            <div class="flex flex-wrap gap-1 mt-1 max-h-24 overflow-y-auto">
                                <template x-for="header in (previewData?.headers || [])" :key="header">
                                    <span class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px] font-mono text-slate-700" x-text="header"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Duplicate Handling Option -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <label class="block text-xs font-bold text-slate-700">Duplicate Leads Handling</label>
                        <div class="flex items-center gap-4 text-xs">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="duplicate_mode" value="skip" checked class="text-sky-600 focus:ring-sky-500">
                                <span class="font-medium text-slate-700">Skip duplicate phone/email</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="duplicate_mode" value="update" class="text-sky-600 focus:ring-sky-500">
                                <span class="font-medium text-slate-700">Update existing lead details</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="createModalOpen = false"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                        :disabled="isSubmitting"
                    >
                        <i class="fa-solid fa-spinner animate-spin" x-show="isSubmitting"></i>
                        <i class="fa-solid fa-cloud-arrow-down" x-show="!isSubmitting"></i>
                        <span x-text="isSubmitting ? 'Importing Leads...' : 'Create & Import Leads'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Campaign Modal -->
    <div
        x-show="editModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="editModalOpen = false"></div>

        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm sm:text-base font-bold text-slate-900">Edit Campaign Details</h3>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-700">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form :action="'{{ url('/meta-leads/campaigns') }}/' + editCampaign.id" method="POST">
                @csrf
                @method('PUT')
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Campaign Name</label>
                        <input
                            type="text"
                            name="name"
                            required
                            x-model="editCampaign.name"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
                        <textarea
                            name="description"
                            rows="2"
                            x-model="editCampaign.description"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                        <select
                            name="status"
                            x-model="editCampaign.status"
                            class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium text-slate-800"
                        >
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end gap-2.5">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Qloudflow') }} - WhatsApp Manager</title>

    <!-- Favicon & Website Icons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- Vite / Tailwind -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans antialiased flex h-screen h-[100dvh] overflow-hidden selection:bg-indigo-500 selection:text-white" x-data="{ mobileMenuOpen: false }">

    <!-- Desktop Sidebar (Hidden on < md, visible on md and up) -->
    <aside class="w-60 lg:w-64 bg-slate-950 text-slate-200 flex flex-col hidden md:flex border-r border-slate-800 shrink-0 z-30 select-none">
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-4 lg:px-5 border-b border-slate-800/80 bg-slate-900/60 shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-sky-400 via-indigo-500 to-emerald-400 flex items-center justify-center text-white shadow-md shadow-indigo-500/25 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-cloud text-sm"></i>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white tracking-tight flex items-center gap-1.5 leading-none">
                        <span>Qloudflow</span>
                        <span class="text-[9px] uppercase tracking-widest font-extrabold px-1.5 py-0.5 rounded bg-indigo-500/25 text-indigo-300 border border-indigo-500/30">Suite</span>
                    </h1>
                    <p class="text-[11px] text-slate-400 mt-1 font-medium leading-none">Workspace Hub</p>
                </div>
            </a>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto" x-data="{
            whatsappOpen: {{ (request()->routeIs('contacts.*') || request()->routeIs('conversations.*') || request()->routeIs('whatsapp.*')) ? 'true' : 'false' }},
            metaLeadsOpen: {{ request()->routeIs('meta-leads.*') ? 'true' : 'false' }}
        }">
            <!-- Workspace Overview -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    Workspace
                </div>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-xl transition-all {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30 font-bold' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                    <i class="fa-solid fa-gauge-high w-4 text-center text-xs {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400' }}"></i>
                    <span>Overview Hub</span>
                </a>
            </div>

            <!-- Apps & Modules Section -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                    <span>Apps & Modules</span>
                    <span class="text-[9px] bg-slate-800 text-slate-300 px-1.5 py-0.5 rounded font-mono font-medium">Ready</span>
                </div>

                <!-- WhatsApp Manager Dropdown -->
                <div class="space-y-1">
                    <button
                        type="button"
                        @click="whatsappOpen = !whatsappOpen"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer {{ (request()->routeIs('contacts.*') || request()->routeIs('conversations.*') || request()->routeIs('whatsapp.*')) ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'text-slate-200 hover:bg-slate-800/80 hover:text-white' }}"
                    >
                        <div class="flex items-center gap-2.5">
                            <div class="w-5 h-5 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">
                                <i class="fa-brands fa-whatsapp text-xs"></i>
                            </div>
                            <span class="text-xs truncate">WhatsApp Manager</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-200" :class="whatsappOpen ? 'transform rotate-180 text-emerald-400' : ''"></i>
                    </button>

                    <!-- Dropdown Sub-Items -->
                    <div
                        x-show="whatsappOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        class="pl-3.5 pr-1 py-1 space-y-1 border-l border-slate-800 ml-3 mt-1"
                    >
                        <a href="{{ route('dashboard') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-emerald-500/20 text-emerald-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-chart-pie w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('dashboard') ? 'text-emerald-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Dashboard & Stats</span>
                        </a>

                        <a href="{{ route('conversations.index') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('conversations.*') ? 'bg-emerald-500/20 text-emerald-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-comments w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('conversations.*') ? 'text-emerald-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Live Conversations</span>
                        </a>

                        <a href="{{ route('contacts.index') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('contacts.*') ? 'bg-emerald-500/20 text-emerald-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-address-book w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('contacts.*') ? 'text-emerald-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Contacts & Leads</span>
                        </a>

                        <a href="{{ route('whatsapp.connection') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('whatsapp.connection') ? 'bg-emerald-500/20 text-emerald-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-qrcode w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('whatsapp.connection') ? 'text-emerald-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Connection Status</span>
                        </a>

                        <a href="{{ route('whatsapp.settings') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('whatsapp.settings') ? 'bg-emerald-500/20 text-emerald-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-sliders w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('whatsapp.settings') ? 'text-emerald-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Bot Schedule & Settings</span>
                        </a>
                    </div>
                </div>

                <!-- Meta Leads Dropdown -->
                <div class="space-y-1 mt-2">
                    <button
                        type="button"
                        @click="metaLeadsOpen = !metaLeadsOpen"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer {{ request()->routeIs('meta-leads.*') ? 'bg-sky-500/15 text-sky-300 border border-sky-500/30' : 'text-slate-200 hover:bg-slate-800/80 hover:text-white' }}"
                    >
                        <div class="flex items-center gap-2.5">
                            <div class="w-5 h-5 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs">
                                <i class="fa-brands fa-meta text-xs"></i>
                            </div>
                            <span class="text-xs truncate">Meta Leads</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-200" :class="metaLeadsOpen ? 'transform rotate-180 text-sky-400' : ''"></i>
                    </button>

                    <!-- Dropdown Sub-Items -->
                    <div
                        x-show="metaLeadsOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        class="pl-3.5 pr-1 py-1 space-y-1 border-l border-slate-800 ml-3 mt-1"
                    >
                        <a href="{{ route('meta-leads.automation') }}" class="flex items-center justify-between px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ (request()->routeIs('meta-leads.automation') || request()->routeIs('meta-leads.campaigns.*')) ? 'bg-sky-500/20 text-sky-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <div class="flex items-center truncate">
                                <i class="fa-solid fa-robot w-3.5 text-center mr-2 text-[11px] {{ (request()->routeIs('meta-leads.automation') || request()->routeIs('meta-leads.campaigns.*')) ? 'text-sky-400' : 'text-slate-500' }}"></i>
                                <span class="truncate">WhatsApp Automation</span>
                            </div>
                            <span class="text-[9px] bg-sky-500/20 text-sky-300 px-1 py-0.2 rounded font-mono font-bold">New</span>
                        </a>

                        <a href="{{ route('meta-leads.templates.index') }}" class="flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('meta-leads.templates.*') ? 'bg-sky-500/20 text-sky-300 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60' }}">
                            <i class="fa-solid fa-file-lines w-3.5 text-center mr-2 text-[11px] {{ request()->routeIs('meta-leads.templates.*') ? 'text-sky-400' : 'text-slate-500' }}"></i>
                            <span class="truncate">Templates Library</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Integrated Modules -->
            <div class="pt-1">
                <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    AI & Automation
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-300 rounded-xl bg-slate-900/60 border border-slate-800/80">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-brain text-emerald-400 text-xs"></i>
                            <span>AI Smart Engine</span>
                        </div>
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800 font-bold">Trained</span>
                    </div>

                    <div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-300 rounded-xl bg-slate-900/60 border border-slate-800/80">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-indigo-400 text-xs"></i>
                            <span>Auto Workflows</span>
                        </div>
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 font-bold">Ready</span>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-950/60 shrink-0">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-bold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-colors cursor-pointer border border-red-500/20">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-100">
        <!-- Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-3.5 sm:px-6 shrink-0 z-20">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <!-- Mobile menu toggle button (min 44x44px touch target) -->
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="md:hidden w-10 h-10 flex items-center justify-center text-slate-700 hover:text-slate-950 focus:outline-none rounded-xl hover:bg-slate-100 active:bg-slate-200 transition shrink-0"
                    aria-label="Open Navigation Menu"
                >
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>

                <!-- Breadcrumb Path -->
                <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-bold truncate">
                    <span class="text-xs font-extrabold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-200/80 flex items-center shrink-0">
                        <i class="fa-solid fa-cloud text-indigo-600 mr-1 text-[10px]"></i>
                        <span class="hidden xs:inline">Qloudflow</span>
                    </span>
                    <span class="text-slate-400 shrink-0">/</span>
                    <span class="text-slate-800 font-bold text-xs sm:text-sm truncate">
                        @if(request()->routeIs('contacts.*'))
                            Contacts & Leads
                        @elseif(request()->routeIs('conversations.*'))
                            Live Conversations
                        @elseif(request()->routeIs('whatsapp.settings'))
                            Bot Schedule & Settings
                        @elseif(request()->routeIs('whatsapp.*'))
                            WhatsApp Connection
                        @elseif(request()->routeIs('meta-leads.automation') || request()->routeIs('meta-leads.campaigns.*'))
                            Meta Leads &bull; WhatsApp Automation
                        @elseif(request()->routeIs('meta-leads.templates.*'))
                            Meta Leads &bull; Automation Templates
                        @else
                            Workspace Overview
                        @endif
                    </span>
                </div>
            </div>

            <!-- User Menu Dropdown -->
            <div class="relative shrink-0 ml-2" x-data="{ open: false }" @click.outside="open = false">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex items-center space-x-2 p-1.5 rounded-xl hover:bg-slate-100 transition focus:outline-none cursor-pointer border border-transparent hover:border-slate-200"
                >
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-slate-900 leading-tight truncate max-w-[120px] md:max-w-none">
                            {{ Auth::user()->name ?? 'Admin' }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium truncate max-w-[120px] md:max-w-none">
                            {{ Auth::user()->email ?? 'amarvcode@gmail.com' }}
                        </div>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-emerald-500 text-white flex items-center justify-center font-bold text-xs shadow-sm shadow-indigo-600/30 shrink-0">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden xs:inline-block"></i>
                </button>

                <!-- Dropdown Menu -->
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-200 py-2 z-50"
                >
                    <div class="px-4 py-2.5 border-b border-slate-100">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Signed in as</p>
                        <p class="text-xs font-bold text-slate-900 truncate">{{ Auth::user()->name ?? 'admin' }}</p>
                        <p class="text-[11px] text-slate-500 truncate">{{ Auth::user()->email ?? 'amarvcode@gmail.com' }}</p>
                    </div>

                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-gauge-high text-slate-400 text-xs w-4 text-center"></i>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('whatsapp.connection') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-qrcode text-slate-400 text-xs w-4 text-center"></i>
                        <span>WhatsApp Connection</span>
                    </a>

                    <div class="pt-1 mt-1 border-t border-slate-100">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full flex items-center gap-2 px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition cursor-pointer text-left"
                            >
                                <i class="fa-solid fa-arrow-right-from-bracket text-xs w-4 text-center"></i>
                                <span>Sign Out</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Drawer with Smooth Backdrop & Slide-In Animation -->
        <div
            x-show="mobileMenuOpen"
            x-cloak
            class="md:hidden fixed inset-0 z-50 flex"
            role="dialog"
            aria-modal="true"
        >
            <!-- Backdrop -->
            <div
                x-show="mobileMenuOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/75 backdrop-blur-xs"
                @click="mobileMenuOpen = false"
            ></div>

            <!-- Drawer Container -->
            <div
                x-show="mobileMenuOpen"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="relative flex-1 flex flex-col max-w-[280px] sm:max-w-xs w-full bg-slate-950 text-slate-200 p-5 shadow-2xl z-50 h-full overflow-hidden"
            >
                <!-- Drawer Header -->
                <div class="flex items-center justify-between pb-4 mb-3 border-b border-slate-800 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-sky-400 to-indigo-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-600/30">
                            <i class="fa-solid fa-cloud text-xs"></i>
                        </div>
                        <div>
                            <span class="font-bold text-white text-sm block leading-none">Qloudflow</span>
                            <span class="text-[10px] text-slate-400 font-medium">WhatsApp Suite</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="mobileMenuOpen = false"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        aria-label="Close menu"
                    >
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <!-- Drawer Navigation Links -->
                <div class="space-y-4 overflow-y-auto flex-1 py-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2">Workspace</p>
                        <a
                            href="{{ route('dashboard') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                            @click="mobileMenuOpen = false"
                        >
                            <i class="fa-solid fa-gauge-high w-4 text-center"></i>
                            <span>Overview Hub</span>
                        </a>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2">WhatsApp Suite</p>
                        <div class="space-y-1">
                            <a
                                href="{{ route('conversations.index') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('conversations.*') ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-comments w-4 text-center"></i>
                                <span>Live Conversations</span>
                            </a>
                            <a
                                href="{{ route('contacts.index') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('contacts.*') ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-address-book w-4 text-center"></i>
                                <span>Contacts & Leads</span>
                            </a>
                            <a
                                href="{{ route('whatsapp.connection') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('whatsapp.connection') ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-qrcode w-4 text-center"></i>
                                <span>Connection Status</span>
                            </a>
                            <a
                                href="{{ route('whatsapp.settings') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('whatsapp.settings') ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-sliders w-4 text-center"></i>
                                <span>Bot Schedule & Settings</span>
                            </a>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2 flex items-center justify-between">
                            <span>Meta Leads</span>
                            <span class="text-[9px] bg-sky-500/20 text-sky-300 px-1.5 py-0.2 rounded font-mono font-bold">New</span>
                        </p>
                        <div class="space-y-1">
                            <a
                                href="{{ route('meta-leads.automation') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ (request()->routeIs('meta-leads.automation') || request()->routeIs('meta-leads.campaigns.*')) ? 'bg-sky-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-robot w-4 text-center"></i>
                                <span>WhatsApp Automation</span>
                            </a>
                            <a
                                href="{{ route('meta-leads.templates.index') }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('meta-leads.templates.*') ? 'bg-sky-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                @click="mobileMenuOpen = false"
                            >
                                <i class="fa-solid fa-file-lines w-4 text-center"></i>
                                <span>Templates Library</span>
                            </a>
                        </div>
                    </div>

                    <!-- AI Status Tag -->
                    <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between text-xs font-medium text-slate-300">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-brain text-emerald-400 text-xs"></i>
                                <span>AI Chatbot</span>
                            </div>
                            <span class="text-[9px] uppercase px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800 font-bold">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer & Logout -->
                <div class="pt-3 border-t border-slate-800/80 shrink-0">
                    <div class="flex items-center gap-2.5 px-2 py-2 mb-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                            <p class="text-[10px] text-slate-400 truncate">{{ Auth::user()->email ?? 'amarvcode@gmail.com' }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-bold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-colors cursor-pointer border border-red-500/20">
                            <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Page Content Scroll Area -->
        <div class="flex-1 overflow-y-auto p-3 sm:p-5 md:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-4 sm:space-y-6">
                @if(session('success'))
                    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3.5 sm:px-4 py-3 rounded-xl flex items-center gap-2.5 sm:gap-3 font-medium text-xs sm:text-sm shadow-2xs">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-sm sm:text-base shrink-0"></i>
                        <span class="leading-snug">{{ session('success') }}</span>
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 border border-red-300 text-red-800 px-3.5 sm:px-4 py-3 rounded-xl flex items-center gap-2.5 sm:gap-3 font-medium text-xs sm:text-sm shadow-2xs">
                        <i class="fa-solid fa-circle-exclamation text-red-600 text-sm sm:text-base shrink-0"></i>
                        <span class="leading-snug">{{ session('error') }}</span>
                    </div>
                @endif
                @if($errors->any())
                    <div class="bg-red-50 border border-red-300 text-red-800 p-3.5 sm:p-4 rounded-xl shadow-2xs">
                        <div class="flex items-center gap-2 mb-1.5 font-bold text-red-900 text-xs sm:text-sm">
                            <i class="fa-solid fa-triangle-exclamation text-red-600 shrink-0"></i>
                            <span>Please fix the following issues:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-xs text-red-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>

    <!-- Floating Live WhatsApp Bot Tester Simulator -->
    <x-floating-bot-tester />

    <!-- Real-time Live WhatsApp Background Message Poller & Auto-Responder Sync -->
    <script>
        (function() {
            function syncWhatsApp() {
                fetch('{{ url('/whatsapp/api/sync') }}')
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.synced > 0) {
                            console.log(`[Qloudflow WhatsApp] Synced and dispatched auto-replies for ${data.synced} incoming message(s).`);
                        }
                    })
                    .catch(() => {});
            }
            // Initial sync on page load, then poll every 4 seconds
            syncWhatsApp();
            setInterval(syncWhatsApp, 4000);
        })();
    </script>
</body>
</html>


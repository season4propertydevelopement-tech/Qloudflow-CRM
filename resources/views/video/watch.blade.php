<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>{{ $video['title'] }} | Season 4 Property — Growth City Naigaon</title>
    <meta name="description" content="{{ $video['description'] }}">

    <!-- OpenGraph Metadata for WhatsApp, Facebook, LinkedIn Link Previews -->
    <meta property="og:site_name" content="Season 4 Property — Growth City Naigaon">
    <meta property="og:title" content="📹 {{ $video['title'] }}">
    <meta property="og:description" content="{{ $video['description'] }}">
    <meta property="og:image" content="{{ $posterUrl }}">
    <meta property="og:image:secure_url" content="{{ $posterUrl }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:video" content="{{ $directVideoUrl }}">
    <meta property="og:video:secure_url" content="{{ $directVideoUrl }}">
    <meta property="og:video:type" content="video/mp4">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:type" content="video.other">

    <!-- Twitter Card Metadata -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="📹 {{ $video['title'] }}">
    <meta name="twitter:description" content="{{ $video['description'] }}">
    <meta name="twitter:image" content="{{ $posterUrl }}">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-deep: #060913;
            --bg-card: rgba(18, 26, 45, 0.75);
            --bg-card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #10b981;
            --primary-hover: #059669;
            --gold: #f59e0b;
            --gold-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --emerald-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
            --whatsapp-green: #25D366;
            --whatsapp-hover: #20bd5a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-deep);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Ambient Glow Background */
        .ambient-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 1000px;
            height: 500px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, rgba(245, 158, 11, 0.06) 40%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            padding: 1.25rem 1rem 3rem;
            position: relative;
            z-index: 1;
        }

        /* Header / Brand */
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0 1.25rem;
            border-bottom: 1px solid var(--bg-card-border);
            margin-bottom: 1.5rem;
        }

        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #fff;
        }

        .brand-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .rera-tag {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: right;
        }

        .rera-tag strong {
            color: #e2e8f0;
        }

        /* Video Player Card */
        .player-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--bg-card-border);
            border-radius: 1.25rem;
            backdrop-filter: blur(16px);
            padding: 0.85rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            margin-bottom: 1.75rem;
        }

        .video-box {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 0.9rem;
            overflow: hidden;
            aspect-ratio: 16 / 9;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8);
        }

        .video-box video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Video Meta Info */
        .video-info-bar {
            padding: 1.25rem 0.5rem 0.5rem;
        }

        .category-pill {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #fbbf24;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.25);
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            margin-bottom: 0.6rem;
            letter-spacing: 0.03em;
        }

        .video-title {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.25;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .video-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        /* Action Buttons Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        @media (min-width: 640px) {
            .actions-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.925rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            text-align: center;
        }

        .btn-whatsapp {
            background: var(--whatsapp-green);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);
        }

        .btn-whatsapp:hover {
            background: var(--whatsapp-hover);
            transform: translateY(-1px);
        }

        .btn-call {
            background: var(--emerald-gradient);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .btn-call:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-download {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-download:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Property Details Card */
        .property-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-card-border);
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-heading {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .highlights-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .highlights-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .highlight-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
        }

        .highlight-icon {
            font-size: 1.25rem;
            line-height: 1;
        }

        .highlight-text h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.2rem;
        }

        .highlight-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* More Videos Carousel / Grid */
        .more-tours-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-card-border);
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .tour-chips-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .tour-chips-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .tour-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.1rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            text-decoration: none;
            color: #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .tour-chip:hover {
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.35);
            color: #34d399;
            transform: translateX(4px);
        }

        .tour-chip.active {
            background: rgba(16, 185, 129, 0.18);
            border-color: #10b981;
            color: #34d399;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--bg-card-border);
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.6;
        }

        .footer strong {
            color: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>

    <div class="container">
        <!-- Brand Header -->
        <header class="brand-header">
            <a href="/" class="brand-logo-wrap">
                <span class="brand-badge">Season 4 Property</span>
                <span style="font-weight: 800; font-size: 1.05rem; letter-spacing: -0.02em;">Growth City Naigaon</span>
            </a>
            <div class="rera-tag">
                Project RERA: <strong>P99000081006</strong><br>
                <span>Official Channel Partner</span>
            </div>
        </header>

        <!-- Main Video Player -->
        <main class="player-wrapper">
            <div class="video-box">
                <video id="tourVideo" controls playsinline preload="metadata" poster="{{ $posterUrl }}" autoplay muted>
                    <source src="{{ $directVideoUrl }}" type="video/mp4">
                    Your browser does not support HTML5 video playback.
                </video>
            </div>

            <div class="video-info-bar">
                <span class="category-pill">{{ $video['category'] }} • {{ $video['duration'] }}</span>
                <h1 class="video-title">{{ $video['title'] }}</h1>
                <p class="video-desc">{{ $video['description'] }}</p>

                <!-- High Intent Action Grid -->
                <div class="actions-grid">
                    <a href="https://wa.me/919619747074?text={{ urlencode('Hi Raj Kumar Dubey, I watched the ' . $video['title'] . ' video tour. I want to book a VIP site visit!') }}" target="_blank" class="btn btn-whatsapp">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.2.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086s1.011.477 1.184.564.289.13.332.203c.043.072.043.419-.101.824z"/></svg>
                        Book VIP Site Visit
                    </a>

                    <a href="tel:+919619747074" class="btn btn-call">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        Call 9619747074
                    </a>

                    <a href="{{ route('video.download', $video['slug']) }}" class="btn btn-download" download>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download MP4 ({{ $fileSizeFormatted }})
                    </a>
                </div>
            </div>
        </main>

        <!-- Project Highlights -->
        <section class="property-card">
            <h3 class="section-heading">
                <span>🏢</span> Growth City Naigaon — Project Highlights
            </h3>
            <div class="highlights-grid">
                <div class="highlight-item">
                    <span class="highlight-icon">🚆</span>
                    <div class="highlight-text">
                        <h4>2 Minutes to Naigaon Station</h4>
                        <p>Fast corridor connectivity with dedicated station pickup for site visit guests.</p>
                    </div>
                </div>

                <div class="highlight-item">
                    <span class="highlight-icon">🏗️</span>
                    <div class="highlight-text">
                        <h4>Tallest 35-Storey Towers</h4>
                        <p>Scenic hillside & pavilion views by The House of Abhinandan Lodha (HoABL).</p>
                    </div>
                </div>

                <div class="highlight-item">
                    <span class="highlight-icon">🛋️</span>
                    <div class="highlight-text">
                        <h4>Free ₹1.5 Lakh Furniture Offer</h4>
                        <p>Complete 5-piece designer furniture package included free on 2 BHK bookings.</p>
                    </div>
                </div>

                <div class="highlight-item">
                    <span class="highlight-icon">🏊‍♂️</span>
                    <div class="highlight-text">
                        <h4>80+ Amenities & Zero Club Fee</h4>
                        <p>5 thematic Growth Centres with swimming pools, sports, and reading centres.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Other Walkthrough Videos -->
        <section class="more-tours-card">
            <h3 class="section-heading">
                <span>📹</span> Watch Other Property Video Tours
            </h3>
            <div class="tour-chips-list">
                <a href="{{ url('/watch/1bhk-tour') }}" class="tour-chip {{ $video['slug'] === '1bhk-tour' ? 'active' : '' }}">
                    <span>📐 1 BHK Virtual Walkthrough (323 sq.ft)</span>
                    <span>▶️</span>
                </a>
                <a href="{{ url('/watch/2bhk-tour') }}" class="tour-chip {{ $video['slug'] === '2bhk-tour' ? 'active' : '' }}">
                    <span>🏡 2 BHK Sample Flat Tour (485 & 621 sq.ft)</span>
                    <span>▶️</span>
                </a>
                <a href="{{ url('/watch/connectivity-tour') }}" class="tour-chip {{ $video['slug'] === 'connectivity-tour' ? 'active' : '' }}">
                    <span>🚆 Station Connectivity (2-Min Walk)</span>
                    <span>▶️</span>
                </a>
                <a href="{{ url('/watch/amenities-tour') }}" class="tour-chip {{ $video['slug'] === 'amenities-tour' ? 'active' : '' }}">
                    <span>🏊‍♂️ 80+ Amenities & Clubhouse Tour</span>
                    <span>▶️</span>
                </a>
                <a href="{{ url('/watch/elevation-tour') }}" class="tour-chip {{ $video['slug'] === 'elevation-tour' ? 'active' : '' }}">
                    <span>👑 35-Storey Tower Elevation Tour</span>
                    <span>▶️</span>
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p><strong>Season 4 Property</strong> — Authorized Channel Partner for Growth City Naigaon (HoABL).</p>
            <p>MahaRERA: <strong>A51900035533</strong> | Project MahaRERA: <strong>P99000081006</strong></p>
            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: #64748b;">
                Office: Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station (West), Mumbai - 400068.
            </p>
        </footer>
    </div>
</body>
</html>

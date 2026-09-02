<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoController extends Controller
{
    /**
     * Catalog of project walkthrough videos and metadata.
     */
    protected array $videoCatalog = [
        '1bhk-tour' => [
            'slug' => '1bhk-tour',
            'title' => '1 BHK Growth Home Walkthrough Tour (323 sq.ft)',
            'description' => 'Virtual walkthrough tour of the spacious 1 BHK Growth Home featuring 2 washrooms, zero wastage layout, and Vastu compliance at Growth City Naigaon.',
            'filename' => 'video-floor-plan-1bhk.mp4',
            'duration' => '0:45',
            'badge' => '1 BHK Walkthrough',
            'poster' => 'public-asset/floor-plan-1bhk-323-sqft-3d.jpeg',
            'category' => 'Floor Plan & Flat Tour',
            'price_starting' => '₹39.99 Lakh++',
        ],
        '2bhk-tour' => [
            'slug' => '2bhk-tour',
            'title' => '2 BHK Luxury Flat Walkthrough Tour (Sample Flat)',
            'description' => 'Explore the luxurious 2 BHK sample flat in 35-storey Growth City Naigaon. Includes Free ₹1.5L Premium Furniture Package on booking!',
            'filename' => 'video-floor-plan-2bhk.mp4',
            'duration' => '1:02',
            'badge' => '2 BHK Sample Flat',
            'poster' => 'public-asset/floor-plan-2bhk-485-sqft.jpeg',
            'category' => 'Sample Flat Tour',
            'price_starting' => '₹52.99 Lakh+',
        ],
        'connectivity-tour' => [
            'slug' => 'connectivity-tour',
            'title' => 'Naigaon Station & Location Connectivity Tour',
            'description' => 'See how close Growth City Naigaon is to Naigaon Railway Station (just 2 minutes walk), near Don Bosco School and Western Express Highway.',
            'filename' => 'video-connectivity-location.mp4',
            'duration' => '1:15',
            'badge' => '2-Min to Station',
            'poster' => 'public-asset/tower-elevation-exterior-view.jpeg',
            'category' => 'Location & Connectivity',
            'price_starting' => 'Prime Corridor',
        ],
        'amenities-tour' => [
            'slug' => 'amenities-tour',
            'title' => '80+ Lifestyle Amenities & Grand Clubhouse Tour',
            'description' => 'Explore the swimming pool, sports areas, gymnasium, and 5 Growth Centres across Growth City Naigaon.',
            'filename' => 'video-amenities-vvmc-naigaon.mp4',
            'duration' => '0:58',
            'badge' => '80+ Amenities',
            'poster' => 'public-asset/lifestyle-swimming-pool-2bhk.jpeg',
            'category' => 'Clubhouse & Lifestyle',
            'price_starting' => 'Club Membership Free',
        ],
        'elevation-tour' => [
            'slug' => 'elevation-tour',
            'title' => '35-Storey Tower Elevation & Sample Room Tour',
            'description' => 'Experience the tallest 35-storey residential towers in Naigaon East with hill and pavilion views by The House of Abhinandan Lodha.',
            'filename' => 'video-elevation-sample-room-tour.mp4',
            'duration' => '0:50',
            'badge' => '35-Storey Towers',
            'poster' => 'public-asset/tower-elevation-exterior-view.jpeg',
            'category' => 'Tower Elevation',
            'price_starting' => 'RERA: P99000081006',
        ],
    ];

    /**
     * Map common aliases to catalog keys.
     */
    protected array $aliases = [
        '1bhk' => '1bhk-tour',
        '2bhk' => '2bhk-tour',
        'connectivity' => 'connectivity-tour',
        'location' => 'connectivity-tour',
        'station' => 'connectivity-tour',
        'amenities' => 'amenities-tour',
        'elevation' => 'elevation-tour',
        'sample-flat' => '2bhk-tour',
        'video-floor-plan-1bhk' => '1bhk-tour',
        'video-floor-plan-2bhk' => '2bhk-tour',
        'video-connectivity-location' => 'connectivity-tour',
        'video-amenities-vvmc-naigaon' => 'amenities-tour',
        'video-elevation-sample-room-tour' => 'elevation-tour',
    ];

    /**
     * Find video item by slug, alias, or direct filename.
     */
    protected function resolveVideo(string $slug): ?array
    {
        $cleanSlug = strtolower(trim($slug));
        $cleanSlug = preg_replace('/\.mp4$/i', '', $cleanSlug);

        if (isset($this->videoCatalog[$cleanSlug])) {
            return $this->videoCatalog[$cleanSlug];
        }

        if (isset($this->aliases[$cleanSlug])) {
            $mapped = $this->aliases[$cleanSlug];
            return $this->videoCatalog[$mapped] ?? null;
        }

        // Check if file directly exists in public/public-asset
        $possibleFile = public_path('public-asset/' . $slug);
        if (!file_exists($possibleFile)) {
            $possibleFile = public_path('public-asset/' . $slug . '.mp4');
        }

        if (file_exists($possibleFile)) {
            $base = basename($possibleFile);
            return [
                'slug' => str_replace('.mp4', '', $base),
                'title' => ucwords(str_replace(['video-', '-', '_'], ' ', str_replace('.mp4', '', $base))),
                'description' => 'Growth City Naigaon property presentation video by The House of Abhinandan Lodha.',
                'filename' => $base,
                'duration' => 'Video',
                'badge' => 'Video Presentation',
                'poster' => 'public-asset/tower-elevation-exterior-view.jpeg',
                'category' => 'Property Video',
                'price_starting' => 'The House of Abhinandan Lodha',
            ];
        }

        return null;
    }

    /**
     * Display the mobile-responsive video player page.
     */
    public function watch(string $slug)
    {
        $video = $this->resolveVideo($slug);

        if (!$video) {
            return redirect()->route('login');
        }

        // Resolve absolute and public URLs
        $videoFileName = $video['filename'];
        $videoFilePath = public_path('public-asset/' . $videoFileName);
        
        $publicBase = rtrim(env('PUBLIC_APP_URL', config('app.url', 'https://season4property.qloudsoft.in')), '/');
        if (str_contains($publicBase, 'localhost') || str_contains($publicBase, '127.0.0.1')) {
            $publicBase = 'https://season4property.qloudsoft.in';
        }

        $directVideoUrl = $publicBase . '/public-asset/' . $videoFileName;
        $posterUrl = $publicBase . '/' . ltrim($video['poster'], '/');
        $pageUrl = $publicBase . '/watch/' . $video['slug'];

        $fileSizeFormatted = 'HD MP4';
        if (file_exists($videoFilePath)) {
            $bytes = filesize($videoFilePath);
            $fileSizeFormatted = round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return view('video.watch', compact(
            'video',
            'directVideoUrl',
            'posterUrl',
            'pageUrl',
            'fileSizeFormatted'
        ));
    }

    /**
     * Download the video file directly.
     */
    public function download(string $slug)
    {
        $video = $this->resolveVideo($slug);

        if (!$video) {
            abort(404, 'Video not found');
        }

        $filePath = public_path('public-asset/' . $video['filename']);
        if (!file_exists($filePath)) {
            abort(404, 'Video file not found on server');
        }

        return response()->download($filePath, $video['filename'], [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="' . $video['filename'] . '"'
        ]);
    }
}

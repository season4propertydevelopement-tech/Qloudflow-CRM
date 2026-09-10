<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\MetaCampaignController;

class AuthController extends Controller
{
    /**
     * Display the login view and check the 10-minute web-cron sync.
     */
    public function showLoginForm(Request $request)
    {
        // 10-Minute Web-Cron Sync: Checks Google Sheets and sends WhatsApp/Email welcome messages
        $cronResult = $this->runScheduledWebCron($request);

        // Optional inspection if ?cron_info=1 is passed
        if ($request->query('cron_info') === '1' || $request->query('json') === '1') {
            return response()->json([
                'status' => 'ok',
                'message' => 'Web-cron login check executed.',
                'last_run' => Cache::get('last_meta_leads_cron_sync'),
                'seconds_since_last_run' => now()->timestamp - (int) Cache::get('last_meta_leads_cron_sync', 0),
                'interval_seconds' => 600,
                'cron_result' => $cronResult,
            ]);
        }

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Run web-cron sync for Meta Campaigns & Google Sheets every 10 minutes (600 seconds).
     * Triggered automatically by cron-job.org or visitor traffic hitting the login page.
     */
    protected function runScheduledWebCron(Request $request): ?array
    {
        try {
            $force = $request->boolean('force_sync') || $request->query('cron') === '1';
            $cacheKey = 'last_meta_leads_cron_sync';
            $lockKey = 'meta_leads_cron_sync_lock';
            $interval = 600; // 10 minutes in seconds

            $lastRun = (int) Cache::get($cacheKey, 0);
            $timeSinceLastRun = now()->timestamp - $lastRun;

            // If 10 minutes have not elapsed yet and not forced, return immediately with zero delay
            if (!$force && $timeSinceLastRun < $interval) {
                return [
                    'executed' => false,
                    'reason' => 'Cooldown active. Next sync in ' . ($interval - $timeSinceLastRun) . ' seconds.',
                    'last_run' => $lastRun,
                    'seconds_remaining' => $interval - $timeSinceLastRun,
                ];
            }

            // Acquire an atomic lock for 90 seconds to prevent concurrent sync executions
            $acquired = Cache::add($lockKey, true, 90);
            if (!$acquired && !$force) {
                return [
                    'executed' => false,
                    'reason' => 'Sync already in progress by another request.',
                ];
            }

            try {
                // Update timestamp
                Cache::put($cacheKey, now()->timestamp, 86400 * 7);

                // Run master sync
                $summary = MetaCampaignController::runAllCampaignsSync();

                Log::info("Web-Cron Login Sync executed successfully.", [
                    'campaigns' => $summary['total_campaigns_checked'] ?? 0,
                    'new_leads' => $summary['total_new_leads'] ?? 0,
                    'welcomes_dispatched' => $summary['total_welcomes_dispatched'] ?? 0,
                ]);

                return [
                    'executed' => true,
                    'summary' => $summary,
                ];
            } finally {
                Cache::forget($lockKey);
            }
        } catch (\Throwable $e) {
            Log::error("Web-Cron Login Sync error: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'executed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited($request);

        $loginInput = $request->input('login');
        $loginField = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $credentials = [
            $loginField => $loginInput,
            'password' => $request->input('password'),
        ];

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($this->throttleKey($request));
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Welcome back, ' . (Auth::user()->name ?? 'User') . '!');
        }

        RateLimiter::hit($this->throttleKey($request));

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been successfully logged out.');
    }

    /**
     * Display the forgot password request view.
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle sending the password reset link.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    /**
     * Display the password reset view.
     */
    public function showResetPasswordForm(Request $request, $token = null)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Handle resetting the password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', __($status));
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    /**
     * Ensure the login request is not rate limited.
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('login')) . '|' . $request->ip());
    }
}

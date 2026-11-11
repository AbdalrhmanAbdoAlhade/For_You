<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Notifications\ResetPassword;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Apple\AppleExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Apple Socialite Provider
        Event::listen(
            SocialiteWasCalled::class,
            AppleExtendSocialite::class . '@handle'
        );

        // API Rate Limiting: 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Authentication Rate Limiting: 5 requests per minute
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip());
        });

        // Frontend Password Reset URL
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim(config('app.frontend_url'), '/')
                . '/reset-password?'
                . http_build_query([
                    'token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ]);
        });
    }
}

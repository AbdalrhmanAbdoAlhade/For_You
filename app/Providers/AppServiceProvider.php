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

        // ==================== API Rate Limiting ====================

        // API عام: 60 طلب/دقيقة لكل مستخدم (أو IP لو مش مسجل)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // ==================== Authentication Rate Limiting ====================
        // مبدأ عام: الـ IP سقف خلفي عريض (عشان مستخدمي NAT/شبكات الموبايل)،
        // والحماية الأساسية per-account على الإيميل.

        // Login: السقف الرئيسي على الإيميل، والـ IP سقف خلفي
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                // حماية الحساب: مهما غيّر المهاجم الـ IP، الإيميل نفسه محدود
                Limit::perMinute(5)->by('login:email:' . $email),
                Limit::perDay(30)->by('login:email-day:' . $email),
                // سقف خلفي للشبكة عشان ميتفحش بروت فورس على إيميلات كتير
                Limit::perMinute(30)->by('login:ip:' . $request->ip()),
            ];
        });

        // Register: سقف خلفي عريض + سقف يومي
        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(5)->by('reg:ip:' . $request->ip()),
                Limit::perDay(20)->by('reg:ip-day:' . $request->ip()),
            ];
        });

        // Forgot password: المهم هنا إيميل الضحية ميترمي عليهش سبام بريد
        RateLimiter::for('forgot-password', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perHour(3)->by('fp:email:' . $email),
                Limit::perMinute(10)->by('fp:ip:' . $request->ip()),
            ];
        });

        // Reset password: محدود على الـ IP والتوكن
        RateLimiter::for('reset-password', function (Request $request) {
            return [
                Limit::perMinute(10)->by('rp:ip:' . $request->ip()),
                Limit::perHour(20)->by('rp:token:' . $request->input('token')),
            ];
        });
        // Change password: محدود على المستخدم نفسه (التوكن المسروق مينفعش يخمّن الباسورد الحالي)
        RateLimiter::for('change-password', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(5)->by('cp:user:' . $key),
                Limit::perDay(30)->by('cp:user-day:' . $key),
            ];
        });

        // Social login: بتتحسب على الـ IP (مفيش email في الـ request)
        RateLimiter::for('social', function (Request $request) {
            return Limit::perMinute(20)->by('social:ip:' . $request->ip());
        });

        // Webhooks: سقف عالي لكنه محمي بالـ signature. الـ gateway نفسه بيبعت من IPs ثابتة.
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by('webhook:' . $request->ip());
        });
        // ==================== Frontend Password Reset URL ====================

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

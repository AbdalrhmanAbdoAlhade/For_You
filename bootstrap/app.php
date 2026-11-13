<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
     
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\SetLocale::class,
        \App\Http\Middleware\TranslateMessages::class,
    ]);

    $middleware->alias([
        'admin'      => \App\Http\Middleware\AdminMiddleware::class,
        'influencer' => \App\Http\Middleware\InfluencerMiddleware::class,
        'permission' => \App\Http\Middleware\CheckPermission::class,
        'active'     => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);

    /*
     * خلف Cloudflare / Load Balancer: بدون trustProxies، $request->ip()
     * بيرجّع IP البروكسي وكل الـ rate limiters المبنية على IP بتتشارك.
     * القيمة فاضية افتراضيًا (مفيش بروكسي موثوق) لحد ما تتضبط في .env.
     */
    $trustedProxies = env('TRUSTED_PROXIES');

    if ($trustedProxies !== null && trim((string) $trustedProxies) !== '') {
        $proxies = trim((string) $trustedProxies) === '*'
            ? '*'
            : array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $trustedProxies)
            )));

        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    }
})

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

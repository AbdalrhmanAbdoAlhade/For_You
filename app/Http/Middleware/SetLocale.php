<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['ar', 'en']; // الأولى هي الافتراضية

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', trim($response->headers->get('Vary', '') . ', Accept-Language', ', '));

        return $response;
    }

    protected function resolve(Request $request): string
    {
        $explicit = strtolower(substr((string) $request->query('lang'), 0, 2));

        if (in_array($explicit, self::SUPPORTED, true)) {
            return $explicit;
        }

        return $request->getPreferredLanguage(self::SUPPORTED) ?? self::SUPPORTED[0];
    }
}

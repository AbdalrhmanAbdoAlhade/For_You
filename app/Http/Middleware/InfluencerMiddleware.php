<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InfluencerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->type !== 'influencer') {
            return response()->json(['message' => 'غير مصرح - خاص بالمؤثرين فقط'], 403);
        }

        return $next($request);
    }
}

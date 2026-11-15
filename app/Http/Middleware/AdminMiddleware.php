<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !in_array($user->type, ['admin', 'employee'])) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return $next($request);
    }
}

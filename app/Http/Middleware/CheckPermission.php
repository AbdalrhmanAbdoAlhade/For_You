<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * الاستخدام: permission:products.view  أو  permission:orders.update,orders.manage (أي واحدة منهم تكفي)
     */
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'], 403);
    }
}

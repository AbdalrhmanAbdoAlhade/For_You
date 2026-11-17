<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /** تسجيل/تحديث جهاز */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => 'required|string|max:191',
            'platform'    => 'required|in:ios,android,web',
            'device_name' => 'nullable|string|max:100',
        ]);

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $data['platform'],
                'device_name'  => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => __('تم تسجيل الجهاز'),
            'device'  => [
                'id'           => $device->id,
                'platform'     => $device->platform,
                'device_name'  => $device->device_name,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ],
        ], 201);
    }

    /** إلغاء تسجيل جهاز (logout) */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:191',
        ]);

        $deleted = $request->user()->deviceTokens()
            ->where('token', $data['token'])
            ->delete();

        return response()->json([
            'message' => $deleted ? __('تم إلغاء تسجيل الجهاز') : __('الجهاز غير مسجل'),
        ]);
    }

    /** قائمة أجهزة المستخدم */
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->deviceTokens()
            ->latest('last_used_at')
            ->get()
            ->map(fn ($d) => [
                'id'           => $d->id,
                'platform'     => $d->platform,
                'device_name'  => $d->device_name,
                'last_used_at' => $d->last_used_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $devices]);
    }
}

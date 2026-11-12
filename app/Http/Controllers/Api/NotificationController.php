<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20);

        $notifications->getCollection()->transform(
            fn ($notification) => $this->present($notification)
        );

        return response()->json($notifications);
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($notification) => $this->present($notification));

        return response()->json($notifications);
    }

    /**
     * تجهيز الإشعار حسب لغة المستخدم الحالية.
     */
    protected function present($notification)
    {
        $data = $notification->data;

        foreach (['title', 'message'] as $key) {
            if (
                app()->getLocale() === 'en'
                && !empty($data[$key . '_en'])
            ) {
                $data[$key] = $data[$key . '_en'];
            }

            unset($data[$key . '_en']);
        }

        $notification->data = $data;

        return $notification;
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => __('تم تعليم الإشعار كمقروء'),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return response()->json([
            'message' => __('تم تعليم كل الإشعارات كمقروءة'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()
            ->notifications()
            ->where('id', $id)
            ->delete();

        return response()->json([
            'message' => __('تم حذف الإشعار'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get the latest notifications and unread count for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->limit(20)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}

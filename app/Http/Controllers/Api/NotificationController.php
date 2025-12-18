<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications
     */
    public function index(Request $request)
    {
        $hotelId = auth()->user()->current_hotel_id;
        $userId = auth()->id();

        $notifications = $this->notificationService->getUserNotifications(
            $hotelId, 
            $userId,
            $request->get('limit', 50)
        );

        $unreadCount = $this->notificationService->getUnreadCount($hotelId, $userId);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'required|uuid',
        ]);

        $userId = auth()->id();

        $this->notificationService->markAsRead($request->notification_ids, $userId);

        return response()->json([
            'message' => 'Notifications marquées comme lues',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $hotelId = auth()->user()->current_hotel_id;
        $userId = auth()->id();

        $this->notificationService->markAllAsRead($hotelId, $userId);

        return response()->json([
            'message' => 'Toutes les notifications marquées comme lues',
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount()
    {
        $hotelId = auth()->user()->current_hotel_id;
        $userId = auth()->id();

        $count = $this->notificationService->getUnreadCount($hotelId, $userId);

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(string $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification supprimée',
        ]);
    }
}
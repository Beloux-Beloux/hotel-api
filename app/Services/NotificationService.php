<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\WebSocketService;
use Illuminate\Support\Str;

class NotificationService
{
    protected WebSocketService $websocket;

    public function __construct(WebSocketService $websocket)
    {
        $this->websocket = $websocket;
    }

    /**
     * Create and broadcast a notification
     */
    public function createNotification(array $data): Notification
    {
        $notification = Notification::create([
            'id' => $data['id'] ?? Str::uuid(),
            'hotel_id' => $data['hotel_id'],
            'user_id' => $data['user_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'icon' => $data['icon'] ?? 'info',
            'priority' => $data['priority'] ?? 'normal',
            'read' => false,
            'sound_enabled' => $data['sound_enabled'] ?? true,
        ]);

        return $notification;
    }

    /**
     * Create notification for new assignment
     */
    public function notifyNewAssignment(string $hotelId, array $assignmentData, string $staffId): Notification
    {
        return $this->createNotification([
            'hotel_id' => $hotelId,
            'user_id' => $staffId,
            'type' => 'assignment',
            'title' => 'Nouvelle attribution',
            'message' => "Chambre {$assignmentData['room_number']} vous a été attribuée",
            'data' => [
                'assignment_id' => $assignmentData['id'],
                'room_number' => $assignmentData['room_number'],
                'room_type' => $assignmentData['room_type'],
                'floor' => $assignmentData['floor'],
            ],
            'icon' => 'assignment',
            'read' => false,
            'priority' => 'normal',
        ]);
    }

    /**
     * Create notification for priority change
     */
    public function notifyPriorityChange(string $hotelId, array $assignmentData, string $previousPriority, string $newPriority): Notification
    {
        return $this->createNotification([
            'hotel_id' => $hotelId,
            'user_id' => $assignmentData['staff_id'] ?? null,
            'type' => 'priority',
            'title' => 'Changement de priorité',
            'message' => "Priorité modifiée pour la chambre {$assignmentData['room_number']}",
            'data' => [
                'assignment_id' => $assignmentData['id'],
                'room_number' => $assignmentData['room_number'],
                'previous_priority' => $previousPriority,
                'new_priority' => $newPriority,
            ],
            'icon' => 'priority',
            'read' => false,
            'priority' => $newPriority === 'high' ? 'high' : 'normal',
        ]);
    }

    /**
     * Create notification for issue reported
     */
    public function notifyIssueReported(string $hotelId, array $issueData): Notification
    {
        return $this->createNotification([
            'hotel_id' => $hotelId,
            'type' => 'issue',
            'title' => 'Problème signalé',
            'message' => "Problème signalé dans la chambre {$issueData['room_number']}",
            'data' => [
                'room_number' => $issueData['room_number'],
                'issue' => $issueData['issue'],
                'severity' => $issueData['severity'],
                'reported_by' => $issueData['reported_by'],
            ],
            'icon' => 'warning',
            'priority' => $issueData['severity'] === 'high' ? 'urgent' : 'high',
        ]);
    }

    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount(string $hotelId, ?string $userId = null): int
    {
        return Notification::forHotel($hotelId)
            ->when($userId, fn($q) => $q->forUser($userId))
            ->unread()
            ->count();
    }

    /**
     * Get notifications for user
     */
    public function getUserNotifications(string $hotelId, ?string $userId = null, int $limit = 50)
    {
        return Notification::forHotel($hotelId)
            ->when($userId, fn($q) => $q->forUser($userId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead(array $notificationIds, ?string $userId = null): void
    {
        Notification::whereIn('id', $notificationIds)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(string $hotelId, ?string $userId = null): void
    {
        Notification::forHotel($hotelId)
            ->when($userId, fn($q) => $q->forUser($userId))
            ->unread()
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $days = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
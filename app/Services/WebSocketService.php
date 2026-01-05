<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->baseUrl = config('websocket.url', 'http://127.0.0.1:3001');
        $this->apiKey = config('websocket.api_key', 'votre-cle-api-websocket-2024');
    }

    /**
     * Broadcast an event to all connected clients of a hotel
     */
    public function broadcast(string $hotelId, string $event, array $data = []): bool
    {
        try {
            Log::info('WebSocket broadcast attempt', [
                'hotelId' => $hotelId,
                'event' => $event,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::timeout(5)->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/broadcast', [
                'hotelId' => $hotelId,
                'event' => $event,
                'data' => $data,
            ]);

            Log::info('WebSocket broadcast response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'key' => $this->apiKey
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'message' => $e->getMessage(),
                'hotelId' => $hotelId,
                'event' => $event
            ]);
            return false;
        }
    }

    protected function getNotificationService(): NotificationService
    {
        if (!isset($this->notificationService)) {
            $this->notificationService = app(NotificationService::class);
        }
        return $this->notificationService;
    }

    /**
     * Notify room status change
     */
    public function notifyRoomStatusChange(string $hotelId, array $roomData, string $previousStatus, string $newStatus): void
    {
        $this->broadcast($hotelId, 'room_status_changed', [
            'room' => $roomData,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
        ]);
    }

    /**
     * Notify room note added
     */
    public function notifyRoomNoteAdded(string $hotelId, string $roomId, array $noteData): void
    {
        $this->broadcast($hotelId, 'room_note_added', [
            'roomId' => $roomId,
            'note' => $noteData,
        ]);
    }

    /**
     * Notify room note deleted
     */
    public function notifyRoomNoteDeleted(string $hotelId, string $roomId, string $noteId): void
    {
        $this->broadcast($hotelId, 'room_note_deleted', [
            'roomId' => $roomId,
            'noteId' => $noteId,
        ]);
    }

    /**
     * Notify assignments updated
     */
    public function notifyAssignmentsUpdated(string $hotelId, $date): void
    {
        $this->broadcast($hotelId, 'assignments_updated', [
            'date' => $date->format('Y-m-d'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify assignment reassigned
     */
    public function notifyAssignmentReassigned(string $hotelId, string $assignmentId, string $newStaffId): void
    {
        $this->broadcast($hotelId, 'assignment_reassigned', [
            'assignmentId' => $assignmentId,
            'newStaffId' => $newStaffId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify assignment status changed
     */
    public function notifyAssignmentStatusChanged(string $hotelId, string $assignmentId, string $status): void
    {
        $this->broadcast($hotelId, 'assignment_status_changed', [
            'assignmentId' => $assignmentId,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ]);
    }


    /**
     * Notify assignment cancelled
     */
    public function notifyAssignmentCancelled(string $hotelId, string $assignmentId, string $reason): void
    {
        $this->broadcast($hotelId, 'assignment_cancelled', [
            'assignmentId' => $assignmentId,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify new reservation
     */
    public function notifyNewReservation(string $hotelId, array $reservationData): void
    {
        $this->broadcast($hotelId, 'reservation_created', [
            'reservation' => $reservationData,
        ]);
    }

    /**
     * Notify reservation status change
     */
    public function notifyReservationStatusChange(string $hotelId, string $reservationId, string $previousStatus, string $newStatus): void
    {
        $this->broadcast($hotelId, 'reservation_status_changed', [
            'reservationId' => $reservationId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
        ]);
    }

    /**
     * Check WebSocket server health
     */
    public function health(): array
    {
        try {
            $response = Http::get($this->baseUrl . '/health');
            
            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => 'error', 'message' => 'Health check failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // Modifiez les méthodes de notification pour utiliser NotificationService
    public function notifyNewAssignment(string $hotelId, array $assignmentData, string $staffId): bool
    {
        // Créer la notification via NotificationService
        $notificationService = app(NotificationService::class);
        $notification = $notificationService->createNotification([
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
            'priority' => 'normal',
            'sound_enabled' => true,
        ]);

        // Émettre l'événement de notification
        return $this->broadcast($hotelId, 'new_notification', [
            'notification' => $notification->toArray()
        ]);
    }

    /**
     * Notify priority change
     */
    public function notifyPriorityChanged(string $hotelId, array $assignmentData, string $previousPriority, string $newPriority): bool
    {
        return $this->broadcast($hotelId, 'priority_changed', [
            'assignment' => $assignmentData,
            'previous_priority' => $previousPriority,
            'new_priority' => $newPriority,
            'type' => 'priority',
            'title' => 'Changement de priorité',
            'message' => "Priorité modifiée pour la chambre {$assignmentData['room_number']}",
            'icon' => 'priority',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify validation completed
     */
    public function notifyValidationCompleted(string $hotelId, array $assignmentData, string $validatorName): bool
    {
        return $this->broadcast($hotelId, 'validation_completed', [
            'assignment' => $assignmentData,
            'validator_name' => $validatorName,
            'type' => 'validation',
            'title' => 'Validation effectuée',
            'message' => "Chambre {$assignmentData['room_number']} a été validée par {$validatorName}",
            'icon' => 'validation',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify task not started reminder
     */
    public function notifyTaskNotStartedReminder(string $hotelId, array $assignmentData): bool
    {
        return $this->broadcast($hotelId, 'task_not_started_reminder', [
            'assignment' => $assignmentData,
            'type' => 'reminder',
            'title' => 'Rappel de tâche',
            'message' => "La chambre {$assignmentData['room_number']} n'a pas encore été démarrée",
            'icon' => 'reminder',
            'priority' => 'medium',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify task completed congratulations
     */
    public function notifyTaskCompletedCongratulations(string $hotelId, array $assignmentData): bool
    {
        return $this->broadcast($hotelId, 'task_completed_congratulations', [
            'assignment' => $assignmentData,
            'type' => 'congratulations',
            'title' => 'Félicitations !',
            'message' => "Chambre {$assignmentData['room_number']} terminée avec succès !",
            'icon' => 'congratulations',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send notification to specific user
     */
    public function notifyUser(string $hotelId, string $userId, array $notificationData): bool
    {
        return $this->broadcast($hotelId, 'user_notification', [
            'user_id' => $userId,
            'notification' => $notificationData,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
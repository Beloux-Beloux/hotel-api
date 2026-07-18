<?php

namespace App\Listeners;

use App\Events\TaskNotStartedReminderEvent;
use Illuminate\Support\Facades\Log;
use App\Services\WebSocketService;

class BroadcastTaskNotStartedReminder
{

    public $queue = 'websocket';
    public $delay = 1;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected WebSocketService $websocketService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(TaskNotStartedReminderEvent $event): void
    {
        try {
            $assignment = $event->assignment;
            $staff = $event->staff;
            $room = $event->room;
            $minutesElapsed = $event->minutesElapsed;
            $reminderCount = $event->reminderCount;

            $broadcastData = $event->broadcastWith();
            
            // Ajouter des métadonnées
            $broadcastData['metadata'] = [
                'event_type' => 'task_reminder',
                'action_required' => true,
                'timestamp' => now()->toISOString(),
            ];

            // 1. Diffuser une notification push au personnel
            if ($staff->user_id) {
                $this->websocketService->sendToUser(
                    $staff->user_id,
                    'task_reminder',
                    [
                        'type' => 'task_not_started',
                        'title' => "Tâche non démarrée - Chambre {$room->number}",
                        'message' => "Veuillez démarrer le nettoyage (en attente depuis {$minutesElapsed} min)",
                        'data' => $broadcastData,
                        'reminder_count' => $reminderCount,
                        'requires_action' => true,
                        'action_button' => [
                            'text' => 'Démarrer',
                            'url' => "/assignments/{$assignment->id}/start",
                            'color' => $minutesElapsed >= 60 ? '#dc3545' : '#007bff',
                        ],
                        'sound' => 'reminder',
                        'vibration' => $reminderCount >= 2 ? [200, 100, 200] : [200],
                    ]
                );
            }

            // 2. Diffuser aux superviseurs si urgence élevée
            if ($minutesElapsed >= 60 || $reminderCount >= 2) {
                $this->websocketService->broadcastToChannel(
                    'supervisors.hotel.' . $assignment->hotel_id,
                    'delayed_task_alert',
                    [
                        'type' => 'task_delayed',
                        'assignment_id' => $assignment->id,
                        'staff_name' => $staff->display_name,
                        'room_number' => $room->number,
                        'floor' => $room->floor,
                        'minutes_delayed' => $minutesElapsed,
                        'reminder_count' => $reminderCount,
                        'priority' => $minutesElapsed >= 90 ? 'critical' : 'high',
                        'timestamp' => now()->toISOString(),
                    ]
                );
            }

            // 3. Mise à jour du tableau de bord en temps réel
            $this->websocketService->broadcast(
                $assignment->hotel_id,
                'dashboard_task_update',
                [
                    'type' => 'task_reminder_sent',
                    'assignment_id' => $assignment->id,
                    'staff_id' => $staff->id,
                    'room_id' => $room->id,
                    'reminder_count' => $reminderCount,
                    'status' => 'pending',
                    'is_delayed' => true,
                    'delay_minutes' => $minutesElapsed,
                    'timestamp' => now()->toISOString(),
                ]
            );

            // 4. Notification sonore pour les bureaux de supervision
            if ($minutesElapsed >= 60) {
                $this->websocketService->broadcast(
                    $assignment->hotel_id,
                    'alert_sound',
                    [
                        'type' => 'delayed_task',
                        'sound' => 'alert',
                        'duration' => 3,
                        'repeat' => $reminderCount >= 3 ? 3 : 1,
                        'data' => [
                            'assignment_id' => $assignment->id,
                            'room_number' => $room->number,
                        ],
                    ]
                );
            }

            Log::info('Task not started reminder broadcasted via WebSocket', [
                'assignment_id' => $assignment->id,
                'staff_id' => $staff->id,
                'room_number' => $room->number,
                'minutes_elapsed' => $minutesElapsed,
                'reminder_count' => $reminderCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast task not started reminder via WebSocket', [
                'error' => $e->getMessage(),
                'assignment_id' => $event->assignment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->release(30); // Réessaie après 30 secondes
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(TaskNotStartedReminderEvent $event, \Throwable $exception): void
    {
        Log::critical('Failed to broadcast task reminder after retries', [
            'assignment_id' => $event->assignment->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
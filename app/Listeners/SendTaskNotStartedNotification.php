<?php

namespace App\Listeners;

use App\Events\TaskNotStartedReminderEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Models\RoomAssignment;
use App\Models\Room;

class SendTaskNotStartedNotification
{
    use InteractsWithQueue;

    public $queue = 'notifications';
    public $delay = 2; // Petit délai pour s'assurer que l'événement est traité

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
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

            // Déterminer le niveau d'urgence
            $urgencyLevel = $this->determineUrgencyLevel($minutesElapsed, $reminderCount);
            
            // Message selon le niveau d'urgence
            $title = $this->getTitleByUrgency($urgencyLevel, $reminderCount);
            $message = $this->getMessageByUrgency($urgencyLevel, $room, $minutesElapsed, $reminderCount);

            // 1. Notification pour le personnel assigné
            if ($staff->user_id) {
                $staffNotification = $this->notificationService->createNotification([
                    'hotel_id' => $assignment->hotel_id,
                    'user_id' => $staff->user_id,
                    'type' => 'task_reminder',
                    'title' => $title,
                    'message' => $message,
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'room_id' => $room->id,
                        'room_number' => $room->number,
                        'floor' => $room->floor,
                        'room_type' => $room->roomType?->name ?? 'N/A',
                        'minutes_elapsed' => $minutesElapsed,
                        'reminder_count' => $reminderCount,
                        'assigned_at' => $assignment->assigned_at->format('H:i'),
                        'priority' => $urgencyLevel,
                        'action_required' => true,
                        'action_url' => "/assignments/{$assignment->id}/start",
                    ],
                    'icon' => $this->getIconByUrgency($urgencyLevel),
                    'priority' => $this->getPriorityByUrgency($urgencyLevel),
                    'sound_enabled' => true,
                    'vibration_enabled' => $reminderCount >= 2,
                    'requires_action' => true,
                    'expires_at' => now()->addHours(2), // Expire dans 2 heures
                ]);

                Log::info('Task not started reminder sent to staff', [
                    'notification_id' => $staffNotification->id,
                    'staff_id' => $staff->id,
                    'assignment_id' => $assignment->id,
                    'minutes_elapsed' => $minutesElapsed,
                    'reminder_count' => $reminderCount,
                ]);
            }

            // 2. Notification pour les superviseurs si urgence élevée
            if ($urgencyLevel === 'high' || $urgencyLevel === 'critical') {
                $supervisorMessage = "Le personnel {$staff->display_name} n'a pas démarré la chambre {$room->number} depuis {$minutesElapsed} minutes";
                
                $this->notificationService->notifySupervisors($assignment->hotel_id, [
                    'type' => 'task_delayed',
                    'title' => 'Tâche en retard',
                    'message' => $supervisorMessage,
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->display_name,
                        'room_number' => $room->number,
                        'floor' => $room->floor,
                        'minutes_delayed' => $minutesElapsed,
                        'reminder_count' => $reminderCount,
                        'assigned_at' => $assignment->assigned_at->format('H:i'),
                        'action_url' => "/assignments/{$assignment->id}",
                    ],
                    'icon' => 'warning',
                    'priority' => 'high',
                    'sound_enabled' => true,
                ]);

                Log::info('Supervisor notified about delayed task', [
                    'hotel_id' => $assignment->hotel_id,
                    'assignment_id' => $assignment->id,
                    'urgency_level' => $urgencyLevel,
                ]);
            }

            // 3. Mettre à jour le compteur de rappels dans l'assignation
            $this->updateReminderCount($assignment, $reminderCount);

        } catch (\Exception $e) {
            Log::error('Error sending task not started reminder', [
                'error' => $e->getMessage(),
                'assignment_id' => $event->assignment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->release(30); // Réessaie après 30 secondes
        }
    }

    /**
     * Déterminer le niveau d'urgence
     */
    private function determineUrgencyLevel(int $minutesElapsed, int $reminderCount): string
    {
        if ($minutesElapsed >= 120 || $reminderCount >= 4) {
            return 'critical';
        } elseif ($minutesElapsed >= 60 || $reminderCount >= 2) {
            return 'high';
        } elseif ($minutesElapsed >= 30) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Obtenir le titre selon l'urgence
     */
    private function getTitleByUrgency(string $urgencyLevel, int $reminderCount): string
    {
        $titles = [
            'critical' => '⚠️ TÂCHE CRITIQUE NON DÉMARRÉE',
            'high' => '⚠️ Tâche en retard importante',
            'medium' => 'Rappel: Tâche non démarrée',
            'low' => 'Rappel: Début de tâche',
        ];

        $title = $titles[$urgencyLevel] ?? $titles['medium'];
        
        // Ajouter le numéro de rappel si plus de 1
        if ($reminderCount > 1) {
            $title .= " (Rappel #{$reminderCount})";
        }

        return $title;
    }

    /**
     * Obtenir le message selon l'urgence
     */
    private function getMessageByUrgency(string $urgencyLevel, Room $room, int $minutesElapsed, int $reminderCount): string
    {
        $roomInfo = "Chambre {$room->number} (Étage {$room->floor})";
        
        switch ($urgencyLevel) {
            case 'critical':
                return "{$roomInfo} n'a pas été démarrée depuis {$minutesElapsed} minutes. Intervention immédiate requise!";
            
            case 'high':
                return "{$roomInfo} est en retard de {$minutesElapsed} minutes. Veuillez démarrer immédiatement.";
            
            case 'medium':
                return "{$roomInfo} attend votre intervention depuis {$minutesElapsed} minutes.";
            
            default:
                return "Veuillez démarrer le nettoyage de {$roomInfo} (assignée il y a {$minutesElapsed} minutes).";
        }
    }

    /**
     * Obtenir l'icône selon l'urgence
     */
    private function getIconByUrgency(string $urgencyLevel): string
    {
        return match($urgencyLevel) {
            'critical' => 'error',
            'high' => 'warning',
            'medium' => 'notification_important',
            'low' => 'notifications',
        };
    }

    /**
     * Obtenir la priorité selon l'urgence
     */
    private function getPriorityByUrgency(string $urgencyLevel): string
    {
        return match($urgencyLevel) {
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'normal',
            'low' => 'low',
        };
    }

    /**
     * Mettre à jour le compteur de rappels
     */
    private function updateReminderCount(RoomAssignment $assignment, int $reminderCount): void
    {
        $assignment->reminder_count = $reminderCount;
        $assignment->last_reminder_at = now();
        
        // Marquer comme problème si trop de rappels
        if ($reminderCount >= 3) {
            $assignment->has_issues = true;
            $assignment->issue_type = 'delayed_start';
        }
        
        $assignment->save();
    }

    /**
     * Handle a job failure.
     */
    public function failed(TaskNotStartedReminderEvent $event, \Throwable $exception): void
    {
        Log::critical('Failed to send task not started reminder after retries', [
            'assignment_id' => $event->assignment->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
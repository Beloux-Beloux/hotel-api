<?php

namespace App\Listeners;

use App\Events\NewAssignmentEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log; 

class SendAssignmentNotification
{
    use InteractsWithQueue;

    public $queue = 'notifications';

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(NewAssignmentEvent $event): void
    {
        try {
            // 1. Notification pour l'admin qui a fait l'assignation
            $adminNotification = $this->notificationService->createNotification([
                'hotel_id' => $event->assignment->hotel_id,
                'user_id' => $event->assignedBy,
                'type' => 'assignment_created',
                'title' => 'Assignation effectuée',
                'message' => "Chambre {$event->room->number} assignée à {$event->staff->display_name}",
                'data' => [
                    'assignment_id' => $event->assignment->id,
                    'room_number' => $event->room->number,
                    'room_type' => $event->room->roomType?->name ?? 'N/A',
                    'floor' => $event->room->floor,
                    'staff_name' => $event->staff->display_name,
                ],
                'icon' => 'assignment',
                'priority' => 'normal',
                'sound_enabled' => true,
            ]);

            Log::info('Admin notification created', [
                'notification_id' => $adminNotification->id,
                'user_id' => $adminNotification->user_id,
            ]);

            // 2. Notification pour le personnel assigné
            $staffNotification = $this->notificationService->createNotification([
                'hotel_id' => $event->assignment->hotel_id,
                'user_id' => $event->staff->id,
                'type' => 'assignment_received',
                'title' => 'Nouvelle chambre assignée',
                'message' => "Chambre {$event->room->number} vous a été attribuée",
                'data' => [
                    'assignment_id' => $event->assignment->id,
                    'room_number' => $event->room->number,
                    'room_type' => $event->room->roomType?->name ?? 'N/A',
                    'floor' => $event->room->floor,
                    'due_date' => $event->assignment->assigned_date->format('Y-m-d'),
                ],
                'icon' => 'room',
                'priority' => 'high',
                'sound_enabled' => true,
            ]);

            Log::info('Staff notification created', [
                'notification_id' => $staffNotification->id,
                'user_id' => $staffNotification->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending assignment notifications', [
                'error' => $e->getMessage(),
                'assignment_id' => $event->assignment->id,
            ]);
            
            // Relancer l'événement après un délai
            $this->release(30); // Réessaie après 30 secondes
        }
    }
}
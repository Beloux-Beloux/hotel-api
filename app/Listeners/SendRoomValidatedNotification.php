<?php

namespace App\Listeners;

use App\Events\RoomValidatedEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SendRoomValidatedNotification
{
    use InteractsWithQueue;

    public $queue = 'notifications';
    public $delay = 5;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(RoomValidatedEvent $event): void
    {
        try {
            $room = $event->room;
            $validatedBy = $event->validatedBy;
            $staff = $event->staff;
            $status = $event->status;
            $validationTime = $event->validationTime;

            
            $statusMessage = 'Chambre validée comme propre';
            
            // Message détaillé
            $message = "Chambre {$room->number} ({$room->floor}ème étage) : {$statusMessage}";
            if ($event->notes) {
                $message .= " - Note : {$event->notes}";
            }
            if ($validationTime) {
                $message .= " - Temps de validation : {$validationTime} minutes";
            }

            // 1. Notification pour le validateur (admin/superviseur)
            $validatorNotification = $this->notificationService->createNotification([
                'hotel_id' => $room->hotel_id,
                'user_id' => $validatedBy->id,
                'type' => 'room_validated',
                'title' => 'Validation enregistrée',
                'message' => $message,
                'data' => [
                    'room_id' => $room->id,
                    'room_number' => $room->number,
                    'floor' => $room->floor,
                    'room_type' => $room->roomType?->name ?? 'N/A',
                    'status' => $status,
                    'validated_by_name' => $validatedBy->name,
                    'validation_time' => $validationTime,
                    'notes' => $event->notes,
                ],
                'icon' => 'check_circle',
                'priority' => $status === 'dirty' ? 'high' : 'normal',
                'sound_enabled' => true,
            ]);

            Log::info('Validator notification created', [
                'notification_id' => $validatorNotification->id,
                'user_id' => $validatorNotification->user_id,
                'room_id' => $room->id,
            ]);

            // 2. Notification pour le personnel si assigné
            if ($staff && $staff->user_id) {
                $staffMessage = "Votre travail en chambre {$room->number} a été validé";
                if ($status === 'dirty') {
                    $staffMessage = "La chambre {$room->number} nécessite un retour : " . ($event->notes ?? 'Raison non spécifiée');
                } elseif ($validationTime) {
                    $staffMessage .= " en {$validationTime} minutes";
                }

                $staffNotification = $this->notificationService->createNotification([
                    'hotel_id' => $room->hotel_id,
                    'user_id' => $staff->user_id,
                    'type' => 'room_validation_status',
                    'title' => 'Validation effectuée',
                    'message' => $staffMessage,
                    'data' => [
                        'room_id' => $room->id,
                        'room_number' => $room->number,
                        'floor' => $room->floor,
                        'status' => $status,
                        'validation_time' => $validationTime,
                        'notes' => $event->notes,
                        'validated_by' => $validatedBy->name,
                    ],
                    'icon' => 'done_all',
                    'priority' => 'normal',
                    'sound_enabled' => true,
                ]);

                Log::info('Staff notification created', [
                    'notification_id' => $staffNotification->id,
                    'user_id' => $staffNotification->user_id,
                    'staff_id' => $staff->id,
                ]);
            }

            // 3. Notification pour tous les superviseurs de l'hôtel
            $adminUsers = User::where('hotel_id', $event->room->hotel_id)->hasRole(['admin', 'manager'])->get();
            
            foreach ($adminUsers as $admin) {
                $this->notificationService->createNotification([
                    'hotel_id' => $room->hotel_id,
                    'user_id' => $admin->id,
                    'type' => 'room_validation',
                    'title' => 'Chambre validée',
                    'message' => "{$validatedBy->name} a validé la chambre {$room->number} - Statut: {$status}",
                    'data' => [
                        'room_id' => $room->id,
                        'room_number' => $room->number,
                        'floor' => $room->floor,
                        'status' => $status,
                        'validated_by' => $validatedBy->name,
                        'validation_time' => $validationTime,
                        'staff_name' => $staff ? $staff->display_name : null,
                    ],
                    'icon' => 'supervisor_account',
                    'priority' => 'normal',
                    'sound_enabled' => false,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending room validation notifications', [
                'error' => $e->getMessage(),
                'room_id' => $event->room->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Relancer l'événement après un délai
            $this->release(30); // Réessaie après 30 secondes
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(RoomValidatedEvent $event, \Throwable $exception): void
    {
        Log::critical('Failed to send room validation notifications after retries', [
            'room_id' => $event->room->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
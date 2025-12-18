<?php

namespace App\Listeners;

use App\Events\RoomValidatedEvent;
use Illuminate\Support\Facades\Log;
use App\Services\WebSocketService;

class BroadcastRoomValidationToWebSocket
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected WebSocketService $websocketService
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

            // Données à diffuser
            $broadcastData = $event->broadcastWith();
            
            // Ajouter des métadonnées supplémentaires
            $broadcastData['metadata'] = [
                'event_type' => 'room_validation',
                'action' => 'update',
                'timestamp' => now()->toISOString(),
            ];

            // 1. Diffuser à l'hôtel (mise à jour en temps réel des états des chambres)
            $this->websocketService->broadcast(
                $room->hotel_id,
                'room_validated',
                [
                    'type' => 'room_validation',
                    'room' => $broadcastData['room'],
                    'validation' => $broadcastData['validation'],
                    'validated_by' => $broadcastData['validated_by'],
                    'timestamp' => now()->toISOString(),
                ]
            );


            // 3. Diffuser aux superviseurs
            $this->websocketService->broadcast(
                'hotel.' . $room->hotel_id,
                'supervisor_update',
                [
                    'type' => 'room_validation',
                    'room_id' => $room->id,
                    'room_number' => $room->number,
                    'status' => $status,
                    'validated_by' => $validatedBy->name,
                    'time' => $event->validationTime,
                    'timestamp' => now()->toISOString(),
                ]
            );

            // 4. Si un staff est concerné, lui envoyer une notification personnelle
            if ($staff && $staff->user_id) {

                $this->websocketService->broadcast(
                    $staff->user_id,
                    'staff_room_validated',
                    [
                        'type' => 'room_validation_result',
                        'room_id' => $room->id,
                        'room_number' => $room->number,
                        'status' => $status,
                        'message' => "Félicitations ! La chambre {$room->number} a été validée comme propre",
                        'notes' => $event->notes,
                        'validation_time' => $event->validationTime,
                        'timestamp' => now()->toISOString(),
                    ]
                );
            }

            Log::info('Room validation broadcasted via WebSocket', [
                'room_id' => $room->id,
                'room_number' => $room->number,
                'hotel_id' => $room->hotel_id,
                'status' => $status,
                'validated_by' => $validatedBy->id,
            ]);

            // Ajouter cette diffusion
            $this->websocketService->broadcast(
                $room->hotel_id,
                'new_notification',
                [
                    'notification' => [
                        'type' => 'room_validation',
                        'title' => $status === 'validated' ? 'Chambre validée' : 'Chambre non conforme',
                        'message' => "Chambre {$room->number} a été validée par {$validatedBy->name}",
                        'data' => $broadcastData,
                        'sound_enabled' => true,
                        'status' => $status,
                    ]
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to broadcast room validation via WebSocket', [
                'error' => $e->getMessage(),
                'room_id' => $event->room->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
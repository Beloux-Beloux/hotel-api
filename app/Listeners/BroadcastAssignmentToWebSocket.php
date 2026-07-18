<?php
namespace App\Listeners;
use App\Events\NewAssignmentEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use App\Services\WebSocketService;

class BroadcastAssignmentToWebSocket
{
    /**
     * Create the event listener.
     */
    public function __construct(protected WebSocketService $websocketService) {}
    /**
     * Handle the event.
     */
    public function handle(NewAssignmentEvent $event): void
    {
        try {
            // Convertir l'événement en array pour le WebSocket
            $data = [
                'type' => 'new_assignment',
                'assignment' => $event->broadcastWith(),
                'timestamp' => now()->toISOString(),
            ];
            // Diffuser à l'hôtel
            $this->websocketService->broadcast(
                $event->assignment->hotel_id,
                'new_assignment',
                $data
            );
            // Diffuser une notification générale
            $this->websocketService->broadcast(
                $event->assignment->hotel_id,
                'new_notification',
                [
                    'notification' => [
                        'type' => 'assignment',
                        'title' => 'Nouvelle assignation',
                        'message' => "Chambre {$event->room->number} assignée",
                        'data' => $event->broadcastWith(),
                        'sound_enabled' => true,
                    ]
                ]
            );
            Log::info('Assignment broadcasted via WebSocket', [
                'assignment_id' => $event->assignment->id,
                'hotel_id' => $event->assignment->hotel_id,
                'room_number' => $event->room->number
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast assignment via WebSocket', [
                'error' => $e->getMessage(),
                'assignment_id' => $event->assignment->id
            ]);
        }
    }
}
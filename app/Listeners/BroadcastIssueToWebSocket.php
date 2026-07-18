<?php

namespace App\Listeners;

use App\Events\IssueReportedEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use App\Services\WebSocketService;

class BroadcastIssueToWebSocket
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
    public function handle(IssueReportedEvent $event): void
    {
        try {
            // Convertir l'événement en array pour le WebSocket
            $data = [
                'type' => 'issue_reported',
                'issue' => $event->broadcastWith(),
                'timestamp' => now()->toISOString(),
            ];

            // Diffuser à l'hôtel
            $this->websocketService->broadcast(
                $event->hotelId,
                'issue_reported',
                $data
            );

            // Diffuser une notification générale
            $this->websocketService->broadcast(
                $event->hotelId,
                'new_notification',
                [
                    'notification' => [
                        'type' => 'issue',
                        'title' => 'Nouveau problème signalé',
                        'message' => "Problème dans la chambre {$event->room->number}",
                        'data' => $event->broadcastWith(),
                        'sound_enabled' => true,
                        'urgency' => $event->issue->urgency,
                    ]
                ]
            );

            Log::info('Issue broadcasted via WebSocket', [
                'issue_id' => $event->issue->id,
                'hotel_id' => $event->hotelId,
                'room_number' => $event->room->number,
                'urgency' => $event->issue->urgency
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast issue via WebSocket', [
                'error' => $e->getMessage(),
                'issue_id' => $event->issue->id
            ]);
        }
    }
}
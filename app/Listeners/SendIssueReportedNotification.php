<?php

namespace App\Listeners;

use App\Events\IssueReportedEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log; 
use App\Models\User;

class SendIssueReportedNotification
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
    public function handle(IssueReportedEvent $event): void
    {
        try {
            // 1. Notification pour la personne qui a signalé le problème
            $reporterNotification = $this->notificationService->createNotification([
                'hotel_id' => $event->hotelId,
                'user_id' => $event->issue->reported_by,
                'type' => 'issue_reported',
                'title' => 'Problème signalé',
                'message' => "Problème '{$event->issue->title}' signalé dans la chambre {$event->room->number}",
                'data' => [
                    'issue_id' => $event->issue->id,
                    'title' => $event->issue->title,
                    'description' => $event->issue->description,
                    'room_number' => $event->room->number,
                    'room_type' => $event->room->roomType?->name ?? 'N/A',
                    'floor' => $event->room->floor,
                    'urgency' => $event->issue->urgency?? 'low',
                    'status' => $event->issue->status,
                ],
                'icon' => 'warning',
                'priority' => $event->issue->urgency ?? 'low',
                'sound_enabled' => true,
            ]);

            Log::info('Reporter notification created', [
                'notification_id' => $reporterNotification->id,
                'user_id' => $reporterNotification->user_id,
                'urgency' => $event->issue->urgency ?? 'low'
            ]);

            // 2. Notification pour les administrateurs de l'hôtel
            $adminUsers = User::where('hotel_id', $event->hotelId)->hasRole(['admin', 'manager'])->get();
            
            foreach ($adminUsers as $admin) {
                $adminNotification = $this->notificationService->createNotification([
                    'hotel_id' => $event->hotelId,
                    'user_id' => $admin->id,
                    'type' => 'issue_reported_admin',
                    'title' => 'Nouveau problème signalé',
                    'message' => "Urgence {$event->issue->urgency}: {$event->issue->title} dans la chambre {$event->room->number} déclaré par {$event->issue->reported_by}",
                    'data' => [
                        'issue_id' => $event->issue->id,
                        'title' => $event->issue->title,
                        'description' => $event->issue->description,
                        'room_number' => $event->room->number,
                        'floor' => $event->room->floor,
                        'urgency' => $event->issue->urgency ?? 'low',
                        'status' => $event->issue->status,
                        'reported_by' => $event->issue->reported_by,
                    ],
                    'icon' => 'warning',
                    'priority' => $event->issue->urgency ?? 'low',
                    'sound_enabled' => true,
                ]);

                Log::info('Admin notification created', [
                    'notification_id' => $adminNotification->id,
                    'admin_id' => $admin->id,
                    'urgency' => $event->issue->urgency ?? 'low'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending issue notifications', [
                'error' => $e->getMessage(),
                'issue_id' => $event->issue->id,
            ]);
            
            // Relancer l'événement après un délai
            $this->release(30); // Réessaie après 30 secondes
        }
    }

}
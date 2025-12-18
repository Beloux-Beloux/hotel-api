<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\NewAssignmentEvent;
use App\Listeners\SendAssignmentNotification;
use App\Listeners\BroadcastAssignmentToWebSocket;
use App\Listeners\SendRoomValidatedNotification;
use App\Events\RoomValidatedEvent;
use App\Listeners\BroadcastRoom;   
use App\Listeners\BroadcastRoomValidationToWebSocket; 
use App\Events\IssueReportedEvent;
use App\Listeners\SendIssueReportedNotification;
use App\Listeners\BroadcastIssueToWebSocket;


class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        NewAssignmentEvent::class => [
            SendAssignmentNotification::class,
            BroadcastAssignmentToWebSocket::class,
        ],

        IssueReportedEvent::class => [
            SendIssueReportedNotification::class,
            BroadcastIssueToWebSocket::class,  
        ],
        

        /*TaskNotStartedReminderEvent::class => [
            SendTaskNotStartedNotification::class,
            BroadcastTaskNotStartedReminder::class,
        ],*/
        
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\RoomAssignment;
use App\Models\HousekeepingStaff;
use App\Models\Room;
use App\Models\User;

class TaskNotStartedReminderEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $assignment;
    public $staff;
    public $room;
    public $assignedBy;
    public $minutesElapsed;
    public $reminderCount;

    /**
     * Create a new event instance.
     */
    public function __construct(
        RoomAssignment $assignment,
        HousekeepingStaff $staff,
        Room $room,
        int $assignedByUserId,
        int $minutesElapsed,
        int $reminderCount = 1
    ) {
        $this->assignment = $assignment;
        $this->staff = $staff;
        $this->room = $room;
        $this->assignedBy = $assignedByUserId;
        $this->minutesElapsed = $minutesElapsed;
        $this->reminderCount = $reminderCount;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('hotel.' . $this->assignment->hotel_id),
        ];

        // Canal pour le personnel assigné
        if ($this->staff && $this->staff->user_id) {
            $channels[] = new PrivateChannel('user.' . $this->staff->user_id);
        }

        // Canal pour les superviseurs
        $channels[] = new PrivateChannel('supervisors.hotel.' . $this->assignment->hotel_id);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'task_not_started_reminder';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $assignedDate = $this->assignment->assigned_date instanceof \Carbon\Carbon
            ? $this->assignment->assigned_date
            : \Carbon\Carbon::parse($this->assignment->assigned_date);

        return [
            'assignment' => [
                'id' => $this->assignment->id,
                'hotel_id' => $this->assignment->hotel_id,
                'room_id' => $this->assignment->room_id,
                'staff_id' => $this->assignment->staff_id,
                'assigned_date' => $assignedDate->format('Y-m-d'),
                'assigned_time' => $this->assignment->assigned_at ? $this->assignment->assigned_at->format('H:i') : null,
                'status' => $this->assignment->status,
                'started_at' => $this->assignment->started_at,
                'completed_at' => $this->assignment->completed_at,
                'priority' => $this->assignment->priority ?? 'normal',
            ],
            'room' => [
                'id' => $this->room->id,
                'number' => $this->room->number,
                'floor' => $this->room->floor,
                'room_type' => $this->room->roomType ? $this->room->roomType->name : 'N/A',
                'status' => $this->room->status,
                'checkout_time' => $this->room->checkout_time,
                'checkin_time' => $this->room->checkin_time,
            ],
            'staff' => [
                'id' => $this->staff->id,
                'user_id' => $this->staff->user_id,
                'display_name' => $this->staff->display_name,
                'current_assignments' => $this->staff->todayAssignments()->count(),
            ],
            'reminder_info' => [
                'minutes_elapsed' => $this->minutesElapsed,
                'reminder_count' => $this->reminderCount,
                'reminder_time' => now()->format('H:i'),
                'next_reminder_minutes' => 30, // Prochain rappel dans 30 minutes
                'is_urgent' => $this->minutesElapsed >= 60, // Urgent après 1 heure
            ],
            'assigned_by' => $this->assignedBy,
            'timestamp' => now()->toISOString(),
        ];
    }
}
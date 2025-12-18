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

class NewAssignmentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $assignment;
    public $staff;
    public $room;
    public $assignedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        RoomAssignment $assignment,
        HousekeepingStaff $staff,
        Room $room,
        int $assignedByUserId
    ) {
        $this->assignment = $assignment;
        $this->staff = $staff;
        $this->room = $room;
        $this->assignedBy = $assignedByUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hotel.' . $this->assignment->hotel_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'new_assignment';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'assignment' => [
                'id' => $this->assignment->id,
                'hotel_id' => $this->assignment->hotel_id,
                'room_id' => $this->assignment->room_id,
                'staff_id' => $this->assignment->staff_id,
                'assigned_date' => $this->assignment->assigned_date->format('Y-m-d'),
                'status' => $this->assignment->status,
                'assigned_at' => $this->assignment->assigned_at,
            ],
            'room' => [
                'id' => $this->room->id,
                'number' => $this->room->number,
                'floor' => $this->room->floor,
                'room_type' => $this->room->roomType ? $this->room->roomType->name : 'N/A',
                'status' => $this->room->status,
            ],
            'staff' => [
                'id' => $this->staff->id,
                'display_name' => $this->staff->display_name,
                'max_rooms_per_day' => $this->staff->max_rooms_per_day,
            ],
            'assigned_by' => $this->assignedBy,
            'timestamp' => now()->toISOString(),
        ];
    }
}
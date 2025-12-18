<?php

namespace App\Events;

use App\Models\Issue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Room;
use Illuminate\Support\Facades\Log;

class IssueReportedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $issue;
    public $room;
    public $description;
    public $hotelId;
    /**
     * Create a new event instance.
     */
    public function __construct(Issue $issue, Room $room, string $description) 
    {
        $this->issue = $issue;
        $this->room = $room;
        $this->description = $description;
        $this->hotelId = $room->hotel_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        
        return [
            new PrivateChannel('hotel.' . $this->hotelId),
        ];
    }
    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'issue_reported';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'issue' => [
                'title' => $this->issue->title,
                'description' => $this->issue->description,
                'room' => $this->issue->room,
                'urgency' => $this->issue->urgency,
                'status' => $this->issue->status,
                'reported_by' => $this->issue->reported_by,
            ],
            'room' => [
                'id' => $this->room->id,
                'number' => $this->room->number,
                'floor' => $this->room->floor,
                'room_type' => $this->room->roomType ? $this->room->roomType->name : 'N/A',
                'status' => $this->room->status,
            ],
            'hotelId' => $this->hotelId,
            'timestamp' => now()->toISOString(),
        ];
    }
}

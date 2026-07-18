<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Room;
use App\Models\User;
use App\Models\HousekeepingStaff;

class RoomValidatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $validatedBy;
    public $staff;
    public $status;
    public $notes;
    public $validationTime;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Room $room,
        User $validatedBy,
        ?HousekeepingStaff $staff = null,
        string $status = 'validated',
        ?string $notes = null,
        ?int $validationTime = null
    ) {
        $this->room = $room;
        $this->validatedBy = $validatedBy;
        $this->staff = $staff;
        $this->status = $status;
        $this->notes = $notes;
        $this->validationTime = $validationTime;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('hotel.' . $this->room->hotel_id),
            new PrivateChannel('validation.updates'),
        ];


        // Channel pour le personnel si assigné
        if ($this->staff) {
            $channels[] = new PrivateChannel('staff.' . $this->staff->id);
        }

        // Channel pour les superviseurs
        $channels[] = new PrivateChannel('supervisors.hotel.' . $this->room->hotel_id);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'room_validated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'room' => [
                'id' => $this->room->id,
                'number' => $this->room->number,
                'floor' => $this->room->floor,
                'type' => $this->room->roomType->name ?? 'N/A',
                'status' => $this->room->status,
                'notes' => $this->room->notes,
            ],
            'validation' => [
                'status' => $this->status,
                'notes' => $this->notes,
            ],
            'validated_by' => [
                'id' => $this->validatedBy->id,
                'name' => $this->validatedBy->name,
                'role' => $this->validatedBy->role,
            ],
            'hotel' => [
                'id' => $this->room->hotel_id,
                'name' => $this->room->hotel->name ?? 'Hôtel',
            ],
        ];

        if ($this->staff) {
            $data['staff'] = [
                'id' => $this->staff->id,
                'name' => $this->staff->display_name,
            ];
        }

        return $data;
    }

}
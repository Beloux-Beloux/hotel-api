<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToHotel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; 

class RoomAssignment extends Model
{
    use HasFactory, HasUuids, BelongsToHotel;

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VALIDATED = 'validated';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'room_id',
        'staff_id',
        'assigned_date',
        'assigned_at',
        'started_at',
        'completed_at',
        'validated_at',
        'validated_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'status',
        'duration_minutes',
        'checklist_completed',
        'notes'
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checklist_completed' => 'array',
        'duration_minutes' => 'integer'
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING
    ];

    /**
     * Get the room for this assignment.
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the staff member for this assignment.
     */
    public function staff()
    {
        return $this->belongsTo(HousekeepingStaff::class, 'staff_id');
    }

    /**
     * Get the user who validated this assignment.
     */
    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Start the assignment.
     */
    public function start()
    {
        $this->update([
            'started_at' => now(),
            'status' => self::STATUS_IN_PROGRESS
        ]);

        // Update room status
        $this->room->update(['status' => 'en_nettoyage']);
    }

    /**
     * Complete the assignment.
     */
    public function complete($checklistCompleted = null, $notes = null)
    {
        $startedAt = $this->started_at ?? now();
        $duration = $startedAt->diffInMinutes(Carbon::now());
        Log::info("StartedAt : " . $this->started_at);
        Log::info("Now : " . Carbon::now());
        Log::info("Duration : ". $duration);

        $this->update([
            'completed_at' => now(),
            'status' => self::STATUS_COMPLETED,
            'duration_minutes' => $duration,
            'checklist_completed' => $checklistCompleted,
            'notes' => $notes
        ]);
    }

    /**
     * Validate the assignment.
     */
    public function validate($validatorId)
    {
        $this->update([
            'validated_at' => now(),
            'validated_by' => $validatorId,
            'status' => self::STATUS_VALIDATED
        ]);

        // Update room status to clean
        $roomStatus = $this->room->current_reservation ? 'occupee_propre' : 'libre_propre';
        $this->room->update(['status' => $roomStatus]);
    }

    /**
     * Cancel the assignment.
     */
    

    public function cancel($reason = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_CANCELLED => 'Annulé'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    
    
    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING => 'gray',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_COMPLETED => 'yellow',
            self::STATUS_VALIDATED => 'green',
            self::STATUS_CANCELLED => 'red'
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Checklist associée à cette tâche.
     */
    public function checklist()
    {
        return $this->hasOne(HousekeepingChecklist::class, 'assignment_id');
    }


    protected static function booted()
    {
        static::created(function ($assignment) {
            // Créer la checklist seulement si elle n'existe pas déjà
            if (!$assignment->checklist) {
                HousekeepingChecklist::create([
                    'assignment_id' => $assignment->id,
                    'items' => ChecklistTemplate::getDefaultItems(),
                    'progress' => 0,
                    'estimated_minutes' => 30,
                ]);
            }
        });

        
    }


    
    public function task()
    {
        return $this->hasOne(RoomAssignment::class, 'room_id', 'room_id')
                    ->where('staff_id', $this->staff_id)
                    ->where('assigned_date', $this->assigned_date);
    }

    public function completeChecklist(array $labels)
    {
        // Charger la checklist liée
        $checklist = $this->checklist()->first();

        if (!$checklist) {
            throw new \Exception("Checklist introuvable pour cet assignment.");
        }

        $items = $checklist->items;

        // Marquer comme completed selon les labels reçus
        foreach ($items as &$item) {
            $item['completed'] = in_array($item['label'], $labels);
        }

        // Calcul du progrès
        $completedCount = collect($items)->where('completed', true)->count();
        $progress = round(($completedCount / count($items)) * 100);

        // Sauvegarde
        $checklist->items = $items;
        $checklist->progress = $progress;
        $checklist->save();

        return $checklist;
    }

}
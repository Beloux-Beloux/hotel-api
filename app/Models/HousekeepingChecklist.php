<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousekeepingChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'items',
        'progress',
        'estimated_minutes',
    ];

    protected $casts = [
        'items' => 'array', // JSON → tableau PHP automatiquement
    ];

    /**
     * Calculer la progression (nombre d’items complétés / total).
     */
    public function computeProgress(): int
    {
        $items = $this->items ?? [];
        if (empty($items)) return 0;

        $completed = collect($items)->where('completed', true)->count();
        return round(($completed / count($items)) * 100);
    }

    public function assignment()
    {
        return $this->belongsTo(RoomAssignment::class, 'assignment_id');
    }

    

    /**
     * Crée une checklist à partir d'un template
     */
    public static function createFromTemplate($assignmentId, $hotelId, $roomTypeId)
    {
        $template = ChecklistTemplate::where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->first();
        if (!$template) {
            $template = ChecklistTemplate::getDefaultForRoomType($hotelId, $roomTypeId);
           
        }
         
        return static::create([
            'assignment_id' => $assignmentId,
            'room_type_id' => $roomTypeId,
            'items' => $template->items,
            'progress' => 0,
            'estimated_minutes' => $template->estimated_minutes,
        ]);
    }

    
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}

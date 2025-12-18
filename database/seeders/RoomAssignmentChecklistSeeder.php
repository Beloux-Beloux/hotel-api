<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RoomAssignment;
use App\Models\HousekeepingChecklist;
use App\Models\Room;
use App\Models\HousekeepingStaff;

class RoomAssignmentChecklistSeeder extends Seeder
{
    public function run()
    {
        $assignments = RoomAssignment::all();

        foreach ($assignments as $assignment) {
            // Vérifier si une checklist existe déjà
            if (!HousekeepingChecklist::where('assignment_id', $assignment->id)->exists()) {
                HousekeepingChecklist::create([
                    'assignment_id' => $assignment->id,
                    'items' => HousekeepingChecklist::getDefaultItems(),
                    'progress' => 0,
                    'estimated_minutes' => 30,
                ]);
                $this->command->info("Checklist créée pour l'assignment {$assignment->id}");
            }
        }
    }
}

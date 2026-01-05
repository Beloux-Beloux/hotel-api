<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingStaff;
use App\Models\StaffAbsence;
use App\Models\StaffEvaluation;
use App\Models\HousekeepingPerformance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\RoomAssignment;
use Carbon\Carbon;
use App\Models\Room;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\HousekeepingSetting;

class HousekeepingStaffController extends Controller
{
    /**
     * List all housekeeping staff.
     */
    public function index(Request $request)
    {
        $hotelId = auth()->user()->current_hotel_id;

        if (!$hotelId) {
            return response()->json(['error' => 'No hotel selected'], 400);
        }

        $users = User::whereHas('hotels', function ($q) use ($hotelId) {
            $q->where('hotels.id', $hotelId)
              ->where('user_hotels.role', 'housekeeping_staff');
        })->get();

        $staffList = [];
        foreach ($users as $user) {
            $staff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)
                ->firstOrCreate(
                    ['hotel_id' => $hotelId, 'user_id' => $user->id],
                    [
                        'code' => 'HSK' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                        'active' => true,
                        'max_rooms_per_day' => 15,
                        'floor_preferences' => [],
                        'skills' => []
                    ]
                );

            $staff->load('user');

            if ($request->has('active') && $request->boolean('active') !== $staff->active) {
                continue;
            }

            $staffList[] = $staff;
        }

        return response()->json(collect($staffList)->sortBy('code')->values());
    }

    /**
     * Create a new staff member.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'code' => 'sometimes|string|max:20',
            'phone' => 'nullable|string|max:255',
            'floor_preferences' => 'sometimes|array',
            'max_rooms_per_day' => 'sometimes|integer|min:1|max:50',
            'skills' => 'sometimes|array'
        ]);

        $code = $request->code ?? $this->generateUniqueCode();

        $maxRooms = HousekeepingSetting::where('hotel_id', auth()->user()->current_hotel_id)->value('max_rooms_per_staff') ?? 15;
        $staff = HousekeepingStaff::create([
            'hotel_id' => auth()->user()->current_hotel_id,
            'user_id' => $request->user_id,
            'code' => $code,
            'phone' => $request->phone,
            'floor_preferences' => $request->floor_preferences ?? [],
            'max_rooms_per_day' => $request->max_rooms_per_day ?? $maxRooms,
            'skills' => $request->skills ?? [],
            'active' => true
        ]);

        return response()->json($staff->load('user'), 201);
    }

    /**
     * Show a specific staff member.
     */
    public function show($id)
    {
        $staff = HousekeepingStaff::where('user_id', $id);
        return response()->json($staff);
    }

    /**
     * Update a staff member.
     */
    public function update(Request $request, $id)
    {
        $staff = HousekeepingStaff::findOrFail($id);

        $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'code' => 'sometimes|string|max:20|unique:housekeeping_staff,code,' . $staff->id,
            'phone' => 'nullable|string|max:255',
            'floor_preferences' => 'sometimes|array',
            'max_rooms_per_day' => 'sometimes|integer|min:1|max:50',
            'skills' => 'sometimes|array',
            'active' => 'sometimes|boolean'
        ]);

        $updateData = $request->only([
            'user_id', 'code', 'floor_preferences', 'max_rooms_per_day', 'skills', 'active'
        ]);

        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone ?: null;
        }

        $staff->update($updateData);
        $staff->refresh();

        return response()->json($staff->load('user'));
    }

    /**
     * Delete a staff member.
     */
    public function destroy($id)
    {
        $staff = HousekeepingStaff::findOrFail($id);

        if ($staff->assignments()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce membre du personnel car il a des tâches en cours'
            ], 409);
        }

        $staff->delete();

        return response()->json(['message' => 'Membre du personnel supprimé avec succès']);
    }

    /**
     * Get staff statistics.
     */
    public function statistics(Request $request)
    {
        $userId = auth()->user()->id;
        $staffId = HousekeepingStaff::where('user_id', $userId)
            ->value('id');
        $date = $request->query('date') ?? now()->toDateString();

        // 1. Total de chambres assigné
        $totalRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->count();

        // 2. Chambres propres, sales, en cours
        $cleanRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->where('status', 'validated')
            ->count();

        $dirtyRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->where('status', 'pending')
            ->count();

        $inProgress = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->whereIn('status',['in_progress','completed'])
            ->count();

        // 3. Taux de progression
        $completionRate = $totalRooms > 0 ? ($cleanRooms / $totalRooms) * 100 : 0;

        // 4. Nettoyées vs objectif (exemple : objectif = 80% des chambres)
        $targetRate = 80;
        $cleanedVsTarget = $totalRooms > 0 ? ($cleanRooms / ($totalRooms * $targetRate / 100)) * 100 : 0;

        // 5. Temps moyen par chambre
        $avgTimePerRoom = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->whereNotNull('completed_at')
            ->avg('duration_minutes') ?? 0;

        // 6. Temps moyen par type de chambre
        $avgTimeByRoomType = Room::all()->groupBy('type')->map(function($rooms) use ($date, $staffId) {
            $durations = RoomAssignment::whereDate('assigned_date', $date)
                ->where('staff_id', $staffId)
                ->whereIn('room_id', $rooms->pluck('id'))
                ->whereNotNull('completed_at')
                ->pluck('duration_minutes');
            return $durations->count() ? $durations->avg() : 0;
        });

        // 7. Taux de validation (1ʳᵉ fois)
        $validationRate = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->where('status', 'validated')
            ->whereColumn('started_at', '=', 'completed_at') // exemple: validé au premier passage
            ->count();
        $validationRate = $totalRooms > 0 ? ($validationRate / $totalRooms) * 100 : 0;

        // 8. Problèmes signalés (notes / incidents)
        $issuesReported = RoomAssignment::whereDate('assigned_date', $date)
            ->where('staff_id', $staffId)
            ->whereNotNull('notes')
            ->count();

        return response()->json([
            'date' => $date,
            'totalRooms' => $totalRooms,
            'cleanRooms' => $cleanRooms,
            'dirtyRooms' => $dirtyRooms,
            'inProgress' => $inProgress,
            'completionRate' => round($completionRate, 1),
            'cleanedVsTarget' => round($cleanedVsTarget, 1),
            'avgTimePerRoom' => round($avgTimePerRoom, 1),
            'avgTimeByRoomType' => $avgTimeByRoomType,
            'validationRate' => round($validationRate, 1),
            'issuesReported' => $issuesReported
        ]);
    }

    /**
     * Get staff task history.
     */
    public function history()
    {
        $userId = auth()->user()->id;
        $staffId = HousekeepingStaff::where('user_id', $userId)
            ->value('id');
        
        $tasks = RoomAssignment::where('staff_id', $staffId)
            ->with(['room', 'room.room_type'])
            ->orderBy('assigned_at', 'desc')
            ->get();
        
        Log::info('Tasks count: ' . $tasks->count());
        Log::info('Tasks: ' . $tasks->toJson());
        
        // Transformez les données pour correspondre au format attendu
        $formattedTasks = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'assigned_at' => $task->assigned_at,
                'status' => $task->status,
                'duration_minutes' => $task->duration_minutes ?? 0,
                'notes' => $task->notes,
                'room' => [
                    'number' => $task->room->number ?? 'N/A',
                    'room_type' => [
                        'name' => $task->room->room_type->name ?? 'N/A'
                    ]
                ]
            ];
        });
        
        return response()->json($formattedTasks->toArray());
    }

    /**
     * Get staff absences.
     */
    public function absences()
    {
        $userId = auth()->user()->id;
        $id = HousekeepingStaff::where('user_id', $userId)
            ->value('id');
        $absences = StaffAbsence::where('staff_id', $id)->get();
        return response()->json($absences);
    }

    /**
     * Get staff evaluations/notes.
     */
    public function evaluations()
    {
        $userId = auth()->user()->id;
        $staffId = HousekeepingStaff::where('user_id', $userId)
            ->value('id');
        $evaluations = StaffEvaluation::where('staff_id', $staffId)->get();
        return response()->json($evaluations);
    }

    /**
     * Get staff performance data for graph.
     */
    public function performance(Request $request)
    {
        $userId = auth()->user()->id;
        $staffId = HousekeepingStaff::where('user_id', $userId)
            ->value('id');

        $from = $request->query('from') 
            ? Carbon::parse($request->query('from'))->toDateString() 
            : now()->subWeek()->toDateString();
        $to = $request->query('to') 
            ? Carbon::parse($request->query('to'))->toDateString() 
            : now()->toDateString();

         // Récupérer les assignments pour le staff spécifique
        $assignments = RoomAssignment::with(['staff'])
            ->where('staff_id', $staffId) // Filtre par staff_id spécifique
            ->whereBetween('assigned_date', [$from, $to])
            ->where('hotel_id', 1)
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([]);
        }

        // Récupérer le nom du staff (plus besoin de grouper par staff)
        $staffName = $assignments->first()->staff->display_name ?? 'Inconnu';

        // Grouper par date pour évolution
        $groupedByDate = $assignments->groupBy(fn($a) => $a->assigned_date->toDateString());

        $result = [];

        foreach ($groupedByDate as $date => $dailyAssignments) {
            $cleaned = $dailyAssignments->whereIn('status', ['completed', 'validated'])->count();
            $validated = $dailyAssignments->where('status', 'validated')->count();
            $avgDuration = $dailyAssignments->avg('duration_minutes');

            $recleaned = $dailyAssignments->filter(fn($a) => $a->notes && str_contains(strtolower($a->notes), 'reclean'))->count();
            $validationRate = $validated > 0 
                ? round((($validated - $recleaned) / $validated) * 100, 1)
                : 0;

            $result[] = [
                'staff_name' => $staffName,
                'date' => $date,
                'cleaned_rooms' => $cleaned,
                'validated_rooms' => $validated,
                'avg_duration' => round($avgDuration, 1),
                'validation_rate' => $validationRate,
            ];
        }

        return response()->json($result);
    }

    /**
     * Generate unique staff code.
     */
    protected function generateUniqueCode()
    {
        do {
            $code = 'HSK' . strtoupper(Str::random(5));
        } while (HousekeepingStaff::where('code', $code)->exists());

        return $code;
    }


    /**
     * Get today's assignments for a staff member.
     */
    public function todayAssignments($id)
    {
        $staff = HousekeepingStaff::findOrFail($id);

        // On récupère les assignations dont la date est aujourd'hui
        $today = now()->toDateString(); // format YYYY-MM-DD

        $assignments = $staff->assignments()
            ->whereDate('assigned_date', $today)
            ->with('room', 'checklist.items') // charger la chambre et checklist si tu as une relation checklist
            ->get();

        return response()->json($assignments);
    }


     public function available()
    {
        $hotelId = auth()->user()->current_hotel_id;

        if (!$hotelId) {
            return response()->json(['error' => 'No hotel selected'], 400);
        }

        $staff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('active', true)
            ->get(['id', 'user_id']); // On prend l'id et le user_id pour le display_name

        // Ajouter display_name via relation user
        $staffList = $staff->map(function ($s) {
            return [
                'id' => $s->id,
                'display_name' => $s->display_name,
            ];
        });

        return response()->json($staffList);
    }

    public function getStaffId($userId)
    {
        $hotelId = auth()->user()->current_hotel_id;

        if (!$hotelId) {
            return response()->json(['error' => 'No hotel selected'], 400);
        }

        $staff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('user_id', $userId)
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'Staff member not found'], 404);
        }

        return response()->json(['staff_id' => $staff->id]);
    }


    public function getTechnician()
    {
       /* $hotelId = auth()->user()->current_hotel_id;

        if (!$hotelId) {
            return response()->json(['error' => 'No hotel selected'], 400);
        }
*/
        $users = User::whereHas('hotels', function ($q) {
            $q->where('role', 'technician');
        })->select('users.id', 'users.name')
        ->get();


        return response()->json(collect($users));
    }

}

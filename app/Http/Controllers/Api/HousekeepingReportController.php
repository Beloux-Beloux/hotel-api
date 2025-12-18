<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RoomAssignment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomNote;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HousekeepingReportController extends Controller
{
    /**
     * GET /api/housekeeping/reports/daily?date=YYYY-MM-DD
     */
    public function dailyReport(Request $request)
    {
        $date = $request->query('date') ?? now()->toDateString();

        // 1. Total de chambres assigné
        $totalRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->count();

        // 2. Chambres propres, sales, en cours
        $cleanRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->where('status', 'validated')
            ->count();

        $dirtyRooms = RoomAssignment::whereDate('assigned_date', $date)
            ->where('status', 'pending')
            ->count();

        $inProgress = RoomAssignment::whereDate('assigned_date', $date)
            ->whereIn('status',['in_progress','completed'])
            ->count();

        // 3. Taux de progression
        $completionRate = $totalRooms > 0 ? ($cleanRooms / $totalRooms) * 100 : 0;

        // 4. Nettoyées vs objectif (exemple : objectif = 80% des chambres)
        $targetRate = 80;
        $cleanedVsTarget = $totalRooms > 0 ? ($cleanRooms / ($totalRooms * $targetRate / 100)) * 100 : 0;

        // 5. Temps moyen par chambre
        $avgTimePerRoom = RoomAssignment::whereDate('assigned_date', $date)
            ->whereNotNull('completed_at')
            ->avg('duration_minutes') ?? 0;

        // 6. Temps moyen par type de chambre
        $avgTimeByRoomType = Room::all()->groupBy('type')->map(function($rooms) use ($date) {
            $durations = RoomAssignment::whereDate('assigned_date', $date)
                ->whereIn('room_id', $rooms->pluck('id'))
                ->whereNotNull('completed_at')
                ->pluck('duration_minutes');
            return $durations->count() ? $durations->avg() : 0;
        });

        // 7. Taux de validation (1ʳᵉ fois)
        $validationRate = RoomAssignment::whereDate('assigned_date', $date)
            ->where('status', 'validated')
            ->whereColumn('started_at', '=', 'completed_at') // exemple: validé au premier passage
            ->count();
        $validationRate = $totalRooms > 0 ? ($validationRate / $totalRooms) * 100 : 0;

        // 8. Problèmes signalés (notes / incidents)
        $issuesReported = RoomAssignment::whereDate('assigned_date', $date)
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

    public function staffPerformance(Request $request)
    {
        $from = $request->query('from') 
            ? Carbon::parse($request->query('from'))->toDateString() 
            : now()->subWeek()->toDateString();
        $to = $request->query('to') 
            ? Carbon::parse($request->query('to'))->toDateString() 
            : now()->toDateString();

        // Récupérer les assignments avec staff entre from et to
        $assignments = RoomAssignment::with(['staff'])
            ->whereBetween('assigned_date', [$from, $to])
            ->where('hotel_id', 1)
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([]);
        }

        // Grouper par staff
        $groupedByStaff = $assignments->groupBy(fn($a) => $a->staff_id);

        $result = [];

        foreach ($groupedByStaff as $staffId => $staffAssignments) {
            $staffName = $staffAssignments->first()->staff->display_name ?? 'Inconnu';

            // Grouper par date pour évolution
            $groupedByDate = $staffAssignments->groupBy(fn($a) => $a->assigned_date->toDateString());

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
        }

        // Optionnel : trier par staff puis par date
        $result = collect($result)
            ->sortBy(['staff_name', 'date'])
            ->values();

        return response()->json($result);
    }

    /**
     * Handle the export of housekeeping reports.
     * Endpoint: GET /api/housekeeping/reports/export?format=excel|pdf
     */
    public function exportModal(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->toDateString() : now()->startOfMonth()->toDateString();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->toDateString() : now()->toDateString();
        $staff = $request->query('staff');       // nom ou id du personnel
        $floor = $request->query('floor');       // étage
        $roomType = $request->query('roomType'); // type de chambre

        $query = RoomAssignment::with(['room.roomType', 'staff'])
            ->whereDate('assigned_date', '>=', $from)
            ->whereDate('assigned_date', '<=', $to)
            ->where('hotel_id', 1);

        if ($staff) {
            $query->whereHas('staff', fn($q) => $q->where('id', $staff));
        }

        if ($floor) {
            $query->whereHas('room', fn($q) => $q->where('floor', $floor));
        }

        if ($roomType) {
            $query->whereHas('room.roomType', fn($q) => $q->where('id', $roomType));
        }

        $assignments = $query->get();

        // Transformer les données pour l'export
        $data = $assignments->map(function($a) {
            return [
                'Date' => $a->assigned_date->format('Y-m-d'),
                'Chambre' => $a->room->number ?? 'Inconnu',
                'Étage' => $a->room->floor ?? 'Inconnu',
                'Type de chambre' => $a->room->roomType->name ?? 'Inconnu',
                'Personnel' => $a->staff->display_name ?? 'Inconnu',
                'Statut' => $a->status,
                'Durée (min)' => $a->duration_minutes,
                'Notes' => $a->notes ?? '',
            ];
        });

        return response()->json($data);
    }


     /**
     * Get room distribution per floor for a given date.
     * Endpoint: GET /api/housekeeping/reports/floor?date=YYYY-MM-DD
     */
    public function floorReport(Request $request)
    {
        $date = $request->query('date') 
        ? Carbon::parse($request->query('date'))->toDateString() 
        : now()->toDateString();

        $hotelId = 1;
        
        // Récupérer les attributions de chambres du jour avec room info
        $assignments = RoomAssignment::with('room')
            ->whereDate('assigned_date', $date)
            ->where('hotel_id', $hotelId)
            ->get();

        // Grouper par étage
        $floors = $assignments->groupBy(fn($a) => $a->room->floor ?? 'Inconnu');

        $result = [];
        foreach ($floors as $floor => $floorAssignments) {
            $roomsCount = $floorAssignments->count();
            $avgDuration = $floorAssignments->avg('duration_minutes');

            // Problèmes signalés dans cet étage
            $issuesCount = RoomNote::whereHas('room', function($q) use ($hotelId, $floor) {
                    $q->where('hotel_id', $hotelId)
                    ->where('floor', $floor);
                })
                ->whereDate('created_at', $date)
                ->count();

            $result[] = [
                'floor' => $floor,
                'roomsCount' => $roomsCount,
                'avgDuration' => round($avgDuration, 1),
                'issuesCount' => $issuesCount,
            ];
        }

        return response()->json($result);
    }


    public function floorHeatmap(Request $request)
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->toDateString()
            : now()->toDateString();

        $hotelId = 1;

        $heatmapData = RoomAssignment::select(
                DB::raw('room_assignments.assigned_date AS assigned_date'),
                DB::raw('COALESCE(room.floor, "Inconnu") AS floor'),
                DB::raw('SUM(CASE WHEN room_assignments.status IN ("completed", "validated") THEN 1 ELSE 0 END) AS clean'),
                DB::raw('SUM(CASE WHEN room_assignments.status = "dirty" THEN 1 ELSE 0 END) AS dirty'),
                DB::raw('SUM(CASE WHEN room_assignments.status = "in_progress" THEN 1 ELSE 0 END) AS in_progress')
            )
            ->join('rooms AS room', 'room.id', '=', 'room_assignments.room_id')
            ->where('room_assignments.hotel_id', $hotelId)
            ->whereBetween('room_assignments.assigned_date', [$from, $to])
            ->groupBy('room_assignments.assigned_date', 'room.floor')
            ->orderBy('room_assignments.assigned_date')
            ->orderBy('room.floor')
            ->get();

        return response()->json($heatmapData);
    }


}

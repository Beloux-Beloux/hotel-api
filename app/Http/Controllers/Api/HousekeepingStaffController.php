<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HousekeepingStaffController extends Controller
{
    /**
     * Display a listing of housekeeping staff.
     */
    public function index(Request $request)
    {
        $query = HousekeepingStaff::with('user');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $staff = $query->orderBy('code')->get();

        return response()->json($staff);
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'code' => 'sometimes|string|max:20',
            'floor_preferences' => 'sometimes|array',
            'max_rooms_per_day' => 'sometimes|integer|min:1|max:50',
            'skills' => 'sometimes|array'
        ]);

        // Generate code if not provided
        $code = $request->code ?? $this->generateUniqueCode();

        $staff = HousekeepingStaff::create([
            'hotel_id' => session('hotel_id'),
            'user_id' => $request->user_id,
            'code' => $code,
            'floor_preferences' => $request->floor_preferences ?? [],
            'max_rooms_per_day' => $request->max_rooms_per_day ?? 15,
            'skills' => $request->skills ?? [],
            'active' => true
        ]);

        return response()->json($staff->load('user'), 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show(HousekeepingStaff $staff)
    {
        return response()->json($staff->load('user'));
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, HousekeepingStaff $staff)
    {
        $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'code' => 'sometimes|string|max:20|unique:housekeeping_staff,code,' . $staff->id,
            'floor_preferences' => 'sometimes|array',
            'max_rooms_per_day' => 'sometimes|integer|min:1|max:50',
            'skills' => 'sometimes|array',
            'active' => 'sometimes|boolean'
        ]);

        $staff->update($request->only([
            'user_id',
            'code',
            'floor_preferences',
            'max_rooms_per_day',
            'skills',
            'active'
        ]));

        return response()->json($staff->load('user'));
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(HousekeepingStaff $staff)
    {
        // Check if staff has any active assignments
        if ($staff->assignments()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce membre du personnel car il a des tâches en cours'
            ], 409);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Membre du personnel supprimé avec succès'
        ]);
    }

    /**
     * Get staff statistics.
     */
    public function statistics(HousekeepingStaff $staff)
    {
        $stats = [
            'today' => [
                'total' => $staff->todayAssignments()->count(),
                'completed' => $staff->todayAssignments()
                    ->whereIn('status', ['completed', 'validated'])
                    ->count(),
                'average_duration' => $staff->todayAssignments()
                    ->whereNotNull('duration_minutes')
                    ->avg('duration_minutes')
            ],
            'week' => [
                'total' => $staff->assignments()
                    ->whereBetween('assigned_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'completed' => $staff->assignments()
                    ->whereBetween('assigned_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereIn('status', ['completed', 'validated'])
                    ->count(),
                'average_duration' => $staff->assignments()
                    ->whereBetween('assigned_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereNotNull('duration_minutes')
                    ->avg('duration_minutes')
            ],
            'month' => [
                'total' => $staff->assignments()
                    ->whereMonth('assigned_date', now()->month)
                    ->whereYear('assigned_date', now()->year)
                    ->count(),
                'completed' => $staff->assignments()
                    ->whereMonth('assigned_date', now()->month)
                    ->whereYear('assigned_date', now()->year)
                    ->whereIn('status', ['completed', 'validated'])
                    ->count(),
                'average_duration' => $staff->assignments()
                    ->whereMonth('assigned_date', now()->month)
                    ->whereYear('assigned_date', now()->year)
                    ->whereNotNull('duration_minutes')
                    ->avg('duration_minutes')
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Generate a unique code for staff member.
     */
    protected function generateUniqueCode()
    {
        do {
            $code = 'HSK' . strtoupper(Str::random(5));
        } while (HousekeepingStaff::where('code', $code)->exists());

        return $code;
    }
}
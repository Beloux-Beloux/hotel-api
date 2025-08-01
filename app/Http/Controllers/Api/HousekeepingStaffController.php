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
        $hotelId = auth()->user()->current_hotel_id;

        if (!$hotelId) {
            \Log::error('No current_hotel_id set for user', ['user_id' => auth()->id()]);
            return response()->json(['error' => 'No hotel selected'], 400);
        }

        \Log::info('HousekeepingStaff index called', [
            'user_id' => auth()->id(),
            'current_hotel_id' => $hotelId,
            'is_super_admin' => auth()->user()->is_super_admin ?? false,
            'request_params' => $request->all()
        ]);

        // Debug: Check all users for this hotel with their roles
        $allHotelUsers = User::whereHas('hotels', function ($q) use ($hotelId) {
            $q->where('hotels.id', $hotelId);
        })->with(['hotels' => function ($q) use ($hotelId) {
            $q->where('hotels.id', $hotelId);
        }])->get();

        $allHotelUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->hotels->first()->pivot->role ?? 'NO ROLE'
            ];
        })->toArray();

        // Get users who have the housekeeping_staff role for this hotel
        $usersQuery = User::whereHas('hotels', function ($q) use ($hotelId) {
            $q->where('hotels.id', $hotelId)
                ->where('user_hotels.role', 'housekeeping_staff');
        });

        $users = $usersQuery->get();

        // For each user, get or create their housekeeping_staff record
        $staffList = [];
        foreach ($users as $user) {
            $staff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)
                ->firstOrCreate(
                    [
                        'hotel_id' => $hotelId,
                        'user_id' => $user->id
                    ],
                    [
                        'code' => 'HSK' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                        'active' => true,
                        'max_rooms_per_day' => 15,
                        'floor_preferences' => [],
                        'skills' => []
                    ]
                );

            $staff->load('user');

            // Only include active staff if filter is applied
            if ($request->has('active') && $request->boolean('active') !== $staff->active) {
                continue;
            }

            $staffList[] = $staff;
        }

        error_log("Staffs : " . count($staffList));

        return response()->json(collect($staffList)->sortBy('code')->values());
    }

    /**
     * Store a newly created staff member.
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

        // Generate code if not provided
        $code = $request->code ?? $this->generateUniqueCode();

        $staff = HousekeepingStaff::create([
            'hotel_id' => auth()->user()->current_hotel_id,
            'user_id' => $request->user_id,
            'code' => $code,
            'phone' => $request->phone,
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
    public function show($staffId)
    {
        $staff = HousekeepingStaff::findOrFail($staffId);
        return response()->json($staff->load('user'));
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, $staffId)
    {
        // Manually find the staff member
        $staff = HousekeepingStaff::findOrFail($staffId);
        
        \Log::info('HousekeepingStaff update called', [
            'staff_id' => $staff->id,
            'request_data' => $request->all(),
            'staff_before' => $staff->toArray()
        ]);

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
            'user_id',
            'code',
            'floor_preferences',
            'max_rooms_per_day',
            'skills',
            'active'
        ]);

        // Handle phone field - convert empty string to null
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone ?: null;
        }

        \Log::info('Update data prepared', [
            'update_data' => $updateData
        ]);

        $staff->update($updateData);
        
        // Reload the model to get fresh data
        $staff->refresh();

        \Log::info('Staff after update', [
            'staff_after' => $staff->toArray()
        ]);

        return response()->json($staff->load('user'));
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy($staffId)
    {
        $staff = HousekeepingStaff::findOrFail($staffId);
        
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
    public function statistics($staffId)
    {
        $staff = HousekeepingStaff::findOrFail($staffId);
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

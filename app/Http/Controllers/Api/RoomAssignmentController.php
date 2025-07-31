<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomAssignment;
use App\Models\HousekeepingStaff;
use App\Services\RoomAssignmentService;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RoomAssignmentController extends Controller
{
    protected RoomAssignmentService $assignmentService;
    protected WebSocketService $websocket;

    public function __construct(RoomAssignmentService $assignmentService, WebSocketService $websocket)
    {
        $this->assignmentService = $assignmentService;
        $this->websocket = $websocket;
    }

    /**
     * Get assignments for a specific date.
     */
    public function index(Request $request)
    {
        $request->validate([
            'date' => 'sometimes|date'
        ]);

        $date = $request->has('date') 
            ? Carbon::parse($request->date)
            : today();

        $hotelId = session('hotel_id');
        $assignments = $this->assignmentService->getAssignmentsForDate($hotelId, $date);

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'assignments' => $assignments
        ]);
    }

    /**
     * Auto-assign rooms for a date.
     */
    public function autoAssign(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = Carbon::parse($request->date);
        $hotelId = session('hotel_id');

        $result = $this->assignmentService->autoAssign($hotelId, $date);

        return response()->json([
            'message' => 'Attribution automatique terminée',
            'result' => $result
        ]);
    }

    /**
     * Reassign a room to another staff member.
     */
    public function reassign(Request $request, RoomAssignment $assignment)
    {
        $request->validate([
            'staff_id' => 'required|exists:housekeeping_staff,id'
        ]);

        $newStaff = HousekeepingStaff::findOrFail($request->staff_id);
        
        try {
            $assignment = $this->assignmentService->reassignRoom($assignment, $newStaff);
            
            return response()->json([
                'message' => 'Chambre réassignée avec succès',
                'assignment' => $assignment->load(['room', 'staff'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Start an assignment.
     */
    public function start(RoomAssignment $assignment)
    {
        if ($assignment->status !== RoomAssignment::STATUS_PENDING) {
            return response()->json([
                'message' => 'Cette tâche ne peut pas être démarrée'
            ], 400);
        }

        $assignment->start();

        // Notify via WebSocket
        $this->websocket->notifyAssignmentStatusChanged(
            $assignment->hotel_id,
            $assignment->id,
            RoomAssignment::STATUS_IN_PROGRESS
        );

        return response()->json([
            'message' => 'Nettoyage démarré',
            'assignment' => $assignment
        ]);
    }

    /**
     * Complete an assignment.
     */
    public function complete(Request $request, RoomAssignment $assignment)
    {
        $request->validate([
            'checklist' => 'sometimes|array',
            'notes' => 'sometimes|string|max:500'
        ]);

        if ($assignment->status !== RoomAssignment::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Cette tâche ne peut pas être terminée'
            ], 400);
        }

        $assignment->complete(
            $request->checklist,
            $request->notes
        );

        // Notify via WebSocket
        $this->websocket->notifyAssignmentStatusChanged(
            $assignment->hotel_id,
            $assignment->id,
            RoomAssignment::STATUS_COMPLETED
        );

        return response()->json([
            'message' => 'Nettoyage terminé',
            'assignment' => $assignment
        ]);
    }

    /**
     * Validate an assignment.
     */
    public function validate(RoomAssignment $assignment)
    {
        if ($assignment->status !== RoomAssignment::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Cette tâche ne peut pas être validée'
            ], 400);
        }

        $assignment->validate(auth()->id());

        // Notify via WebSocket
        $this->websocket->notifyAssignmentStatusChanged(
            $assignment->hotel_id,
            $assignment->id,
            RoomAssignment::STATUS_VALIDATED
        );

        return response()->json([
            'message' => 'Nettoyage validé',
            'assignment' => $assignment
        ]);
    }

    /**
     * Get today's assignments for current staff.
     */
    public function myAssignments()
    {
        $staff = HousekeepingStaff::where('user_id', auth()->id())->first();
        
        if (!$staff) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre du personnel de nettoyage'
            ], 403);
        }

        $assignments = $staff->todayAssignments()
            ->with(['room.roomType', 'room.currentReservation'])
            ->get();

        return response()->json([
            'staff' => $staff,
            'assignments' => $assignments,
            'stats' => [
                'total' => $assignments->count(),
                'pending' => $assignments->where('status', RoomAssignment::STATUS_PENDING)->count(),
                'in_progress' => $assignments->where('status', RoomAssignment::STATUS_IN_PROGRESS)->count(),
                'completed' => $assignments->where('status', RoomAssignment::STATUS_COMPLETED)->count(),
                'validated' => $assignments->where('status', RoomAssignment::STATUS_VALIDATED)->count(),
            ]
        ]);
    }

    /**
     * Report an issue with a room.
     */
    public function reportIssue(Request $request, RoomAssignment $assignment)
    {
        $request->validate([
            'issue' => 'required|string|max:500',
            'severity' => 'sometimes|in:low,medium,high'
        ]);

        // Add note to assignment
        $existingNotes = $assignment->notes ?? '';
        $newNote = "[PROBLÈME] " . $request->issue;
        $assignment->update([
            'notes' => $existingNotes . "\n" . $newNote
        ]);

        // Create room note for the issue
        $assignment->room->notes()->create([
            'hotel_id' => $assignment->hotel_id,
            'note' => $request->issue,
            'priority' => $request->severity ?? 'normal',
            'created_by' => auth()->id()
        ]);

        // Notify via WebSocket
        $this->websocket->notifyRoomIssueReported(
            $assignment->hotel_id,
            $assignment->room_id,
            $request->issue
        );

        return response()->json([
            'message' => 'Problème signalé',
            'assignment' => $assignment
        ]);
    }
}
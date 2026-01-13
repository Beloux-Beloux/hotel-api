<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingChecklist;
use App\Models\RoomAssignment;
use App\Models\HousekeepingStaff;
use App\Models\Room;
use App\Services\RoomAssignmentService;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppService;
use App\Models\Issue;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log; 
use App\Events\NewAssignmentEvent;
use App\Events\RoomValidatedEvent;
use App\Events\IssueReportedEvent;

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

        $hotelId = auth()->user()->current_hotel_id;

        if ($request->has('date')) {
            $date = Carbon::parse($request->date);
            $assignments = $this->assignmentService->getAssignmentsForDate($hotelId, $date);
            $assignments = $this->enrichAssignmentsWithPhotos($assignments);
            
            // Log pour déboguer
            \Log::info('Assignments with photos:', [
                'date' => $date,
                'assignments_count' => $assignments->count(),
                'first_assignment_photos' => $assignments->first()?->first()?->photos ?? []
            ]);
            
            return response()->json([
                'date' => $date->format('Y-m-d'),
                'assignments' => $assignments,
            ]);
        }

        $assignments = $this->assignmentService->getAllAssignments($hotelId);
        $assignments = $this->enrichAssignmentsWithPhotos($assignments);
        
        \Log::info('All assignments with photos:', [
            'assignments_count' => $assignments->count(),
            'first_assignment_photos' => $assignments->first()?->first()?->photos ?? []
        ]);
        
        return response()->json([
            'assignments' => $assignments,
        ]);
    }

    public function getAllAssignments($hotelId)
    {
        return RoomAssignment::with(['room.roomType', 'staff.user', 'checklist'])
            ->where('hotel_id', $hotelId)
            ->orderBy('staff_id')
            ->orderBy('assigned_at')
            ->get()
            ->groupBy('staff_id');
    }

    // Nouvelle méthode pour enrichir les assignments avec les photos
    private function enrichAssignmentsWithPhotos($assignments)
    {
        return $assignments->map(function ($staffAssignments) {
            return $staffAssignments->map(function ($assignment) {
                $photos = [];
                
                // Récupérer les photos du checklist si disponible
                if ($assignment->checklist && isset($assignment->checklist->items)) {
                    $items = $assignment->checklist->items;
                    
                    // Si items est un tableau JSON, le décoder
                    if (is_string($items)) {
                        $items = json_decode($items, true);
                    }
                    
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (!empty($item['photo'])) {
                                // Construire l'URL complète de la photo
                                $photoUrl = $item['photo'];
                                if (!str_starts_with($photoUrl, 'http')) {
                                    $photoUrl = url($photoUrl);
                                }
                                
                                $photos[] = [
                                    'id' => $item['id'] ?? uniqid(),
                                    'url' => $photoUrl,
                                    'thumbnail_url' => $photoUrl,
                                    'created_at' => $assignment->checklist->updated_at ?? $assignment->updated_at,
                                    'type' => 'checklist',
                                    'item_label' => $item['label'] ?? 'Élément de checklist'
                                ];
                            }
                        }
                    }
                }
                
                // Ajouter les photos à l'assignment
                $assignment->photos = $photos;
                
                return $assignment;
            });
        });
    }

    // Ou si vous préférez modifier directement la méthode getAssignmentsForDate
    /*public function getAssignmentsForDate($hotelId, $date)
    {
        $assignments = RoomAssignment::with(['room.roomType', 'staff.user', 'checklist'])
            ->where('hotel_id', $hotelId)
            ->whereDate('assigned_at', $date)
            ->orderBy('staff_id')
            ->orderBy('assigned_at')
            ->get()
            ->groupBy('staff_id');
        
        // Enrichir avec les photos
        $assignments = $assignments->map(function ($staffAssignments) {
            return $staffAssignments->map(function ($assignment) {
                $photos = [];
                
                if ($assignment->checklist && isset($assignment->checklist->items)) {
                    $items = $assignment->checklist->items;
                    
                    if (is_string($items)) {
                        $items = json_decode($items, true);
                    }
                    
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (!empty($item['photo'])) {
                                $photoUrl = $item['photo'];
                                if (!str_starts_with($photoUrl, 'http')) {
                                    $photoUrl = url($photoUrl);
                                }
                                
                                $photos[] = [
                                    'id' => $item['id'] ?? uniqid(),
                                    'url' => $photoUrl,
                                    'thumbnail_url' => $photoUrl,
                                    'created_at' => $assignment->checklist->updated_at ?? $assignment->updated_at,
                                    'type' => 'checklist'
                                ];
                            }
                        }
                    }
                }
                
                $assignment->photos = $photos;
                return $assignment;
            });
        });
        
        return $assignments;
    }*/

    /**
     * Auto-assign rooms for a date.
     */
    public function autoAssign(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = Carbon::parse($request->date);
        $hotelId = auth()->user()->current_hotel_id;
        
        Log::info('Auto-assign request', [
            'hotel_id' => $hotelId,
            'date' => $date->format('Y-m-d'),
            'user' => auth()->user()->id
        ]);

        if (!$hotelId) {
            return response()->json([
                'message' => 'Aucun hôtel sélectionné'
            ], 422);
        }

        $result = $this->assignmentService->autoAssign($hotelId, $date);

        $this->websocket->notifyAssignmentReassigned(
                $hotelId,
                1,
                auth()->user()->id
            );
       
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
            
            // Send notification to staff
            // Notify via WebSocket
            $this->websocket->notifyAssignmentReassigned(
                $assignment->hotel_id,
                $assignment->id,
                $newStaff->id
            );

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

        // Send congratulations notification
        $this->websocket->notifyTaskCompletedCongratulations($assignment->hotel_id, [
            'id' => $assignment->id,
            'room_number' => $assignment->room->number,
            'staff_name' => $assignment->staff->display_name,
        ]);

        $wa = new WhatsAppService();    
        $wa->sendTemplateWithParams(
            to: env('WHATSAPP_GOUVERNANTE_PHONE_NUMBER'),
            templateName: "validation_action",
            params: [
                $assignment->id,              // {{1}} -> task_id
                RoomAssignment::STATUS_COMPLETED,          // {{2}} -> task_status
                $assignment->staff->display_name,            // {{3}} -> room_number
                $assignment->room->number        // {{4}} -> staff_name
            ]
        );

        return response()->json([
            'message' => 'Nettoyage terminé',
            'assignment' => $assignment
        ]);
    }

   public function completeChecklist(Request $request, RoomAssignment $assignment)
    {
        $validated = $request->validate([
            'checklist' => 'required|array',
        ]);

        $assignment->completeChecklist($validated['checklist']);

        return response()->json([
            'message' => 'Checklist mise à jour',
            'assignment' => $assignment->fresh()
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

        $room = Room::find($assignment->room_id);

        $staff = $assignment->staff->staff_id ? HousekeepingStaff::find($assignment->staff->staff_id) : null;
        
        event(new RoomValidatedEvent(
            $room,
            auth()->user(),
            $staff,
            RoomAssignment::STATUS_VALIDATED,
            $assignment->notes,
            now()->getTimestamp()
        ));

        return response()->json([
            'message' => 'Nettoyage validé',
            'assignment' => $assignment
        ]);
    }

    /**
     * Cancelled assignment
     */

    public function cancel(Request $request, RoomAssignment $assignment)
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500',
        ]);
        $reason = $request->input('reason', null);
    
        $assignment->cancel($reason);
        
        // Notify via WebSocket
        $this->websocket->notifyAssignmentStatusChanged(
            $assignment->hotel_id,
            $assignment->id,
            RoomAssignment::STATUS_CANCELLED
        );
        return response()->json([
            'message' => 'Tâche annulée',
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
                'cancelled' => $assignments->where('status', RoomAssignment::STATUS_CANCELLED)->count(),
            ]
        ]);
    }

    /**
     * Get unassigned rooms for a date.
     */
    public function getUnassignedRooms(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = Carbon::parse($request->date);
        $hotelId = auth()->user()->current_hotel_id;

        // Get dirty rooms
        $dirtyRooms = Room::where('hotel_id', $hotelId)
            ->whereIn('status', ['libre_sale', 'occupee_sale'])
            ->get();

        // Get assigned room IDs for this date
        $assignedRoomIds = RoomAssignment::where('hotel_id', $hotelId)
            ->where('assigned_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('room_id');

        // Filter unassigned rooms
        $unassignedRooms = $dirtyRooms->whereNotIn('id', $assignedRoomIds)
            ->values()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'number' => $room->number,
                    'floor' => $room->floor,
                    'status' => $room->status,
                    'room_type' => $room->roomType->name
                ];
            });

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'unassigned_rooms' => $unassignedRooms
        ]);
    }

    /**
     * Manually assign rooms to staff.
     */
    public function manualAssign(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'staff_id' => 'required|exists:housekeeping_staff,id',
            'room_ids' => 'required|array',
            'room_ids.*' => 'exists:rooms,id'
        ]);

        $date = Carbon::parse($request->date);
        $hotelId = auth()->user()->current_hotel_id;
        $staff = HousekeepingStaff::findOrFail($request->staff_id);

        // Check if staff belongs to the hotel
        if ($staff->hotel_id !== $hotelId) {
            return response()->json([
                'message' => 'Personnel non trouvé dans cet hôtel'
            ], 404);
        }

        // Check staff capacity
        $currentAssignments = RoomAssignment::where('hotel_id', $hotelId)
            ->where('staff_id', $staff->id)
            ->where('assigned_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $newAssignmentsCount = count($request->room_ids);
        $totalAssignments = $currentAssignments + $newAssignmentsCount;

        if ($totalAssignments > $staff->max_rooms_per_day) {
            return response()->json([
                'message' => "Ce membre du personnel ne peut pas accepter {$newAssignmentsCount} chambres supplémentaires. Capacité restante: " . ($staff->max_rooms_per_day - $currentAssignments)
            ], 422);
        }

        DB::beginTransaction();
        try {
            $assigned = [];
            $roomsData = [];
            
            foreach ($request->room_ids as $roomId) {
                $room = Room::findOrFail($roomId);

                
                // Check if room is already assigned
                $existingAssignment = RoomAssignment::where('hotel_id', $hotelId)
                    ->where('room_id', $roomId)
                    ->where('assigned_date', $date)
                    ->whereNotIn('status', ['cancelled'])
                    ->first();
                    
                if ($existingAssignment) {
                    continue;
                }
                
                // Create assignment
                $assignment = RoomAssignment::create([
                    'hotel_id' => $hotelId,
                    'room_id' => $roomId,
                    'staff_id' => $staff->id,
                    'assigned_date' => $date,
                    'assigned_at' => now(),
                    'status' => RoomAssignment::STATUS_PENDING
                ]);
                
                $assigned[] = $assignment;
                $roomsData[] = [
                    'room' => $room,
                    'assignment' => $assignment
                ];
                
            }

            DB::commit();

            
            event(new NewAssignmentEvent($assignment, $staff, $room, auth()->id()));

            return response()->json([
                'message' => count($assigned) . ' chambres attribuées avec succès',
                'assigned_count' => count($assigned)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Erreur lors de l\'attribution des chambres'
            ], 500);
        }
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

        
        $roomNumber = $request->roomNumber;
        $title = "Problème signalé dans la chambre $roomNumber";
        $issue = Issue::create([
            'title' => $title,
            'description' => $request->issue,
            'room' => $roomNumber,
            'reported_by' => auth()->user()->id
        ]);

        $room = Room::where('number', $assignment->room->number)->first();


        event(new IssueReportedEvent($issue, $room, $request->issue));


        return response()->json([
            'message' => 'Problème signalé',
            'assignment' => $assignment
        ]);
    }

    public function getAssignmentsByMonth(Request $request)
    {
        try {
            $month = $request->query('month');

            if (!$month) {
                return response()->json(['error' => 'Le paramètre "month" est requis'], 400);
            }

            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);

            $assignments = RoomAssignment::with(['staff', 'room.roomType'])
                ->whereYear('assigned_date', $year)
                ->whereMonth('assigned_date', $monthNum)
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'message' => 'Aucune assignation trouvée pour ce mois',
                    'assignments' => [],
                ], 200);
            }

            // ✅ Si le front demande une version "groupée" par date
            if ($request->query('grouped') === 'true') {
                $grouped = $assignments->groupBy(function ($item) {
                    return $item->assigned_date->format('Y-m-d');
                });

                return response()->json([
                    'assignments' => $grouped
                ]);
            }

            // ✅ Par défaut : retourne un tableau plat
            return response()->json([
                'assignments' => $assignments->values()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du chargement des assignations',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        $assignment = RoomAssignment::with(['staff', 'room.roomType'])
            ->findOrFail($id);

        return response()->json([
            'id' => $assignment->id,
            'roomNumber' => $assignment->room->number,
            'roomTypeId' => $assignment->room->roomType->id ?? null,
            'roomTypeName' => $assignment->room->roomType->name ?? null,
            'status' => $assignment->status,
            'staffName' => $assignment->staff->display_name ?? 'Non assigné'
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = RoomAssignment::findOrFail($id);
        $status = $request->input('status');
        $assignment->update(['status' => $status]);

        return response()->json(['message' => "Statut mis à jour en $status"]);
    }

    public function history(Request $request)
    {
        $query = RoomAssignment::with(['staff', 'room.roomType'])
            ->when($request->search, fn($q, $search) =>
                $q->whereHas('staff', fn($s) => $s->where('display_name', 'like', "%$search%"))
                ->orWhereHas('room', fn($r) => $r->where('number', 'like', "%$search%"))
            )
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->date, fn($q, $date) => $q->whereDate('assigned_at', $date))
            ->orderBy($request->sort_by ?? 'assigned_at', $request->sort_direction ?? 'desc');

        $assignments = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'data' => $assignments->items(),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'total' => $assignments->total(),
                'per_page' => $assignments->perPage()
            ]
        ]);
    }

    public function unassignRoom(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|uuid',
            'room_id' => 'required|integer',
        ]);

        $assignment = RoomAssignment::where('staff_id', $validated['staff_id'])
            ->where('room_id', $validated['room_id'])
            ->whereNull('completed_at')
            ->first();

        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'Aucune attribution trouvée'], 404);
        }

        $assignment->delete();

        return response()->json(['success' => true, 'message' => 'Chambre désassignée avec succès']);
    }

    /**
     * Delete a room assignment.
     */    
    public function destroy(string $id)
    {
        try {
            $assignment = RoomAssignment::find($id);

            if (!$assignment) {
                return response()->json(['message' => 'Assignation non trouvée'], 404);
            }

            // Vérifie que l'utilisateur a bien accès à l'hôtel concerné
            $userHotelId = auth()->user()->current_hotel_id;
            if ($assignment->hotel_id !== $userHotelId) {
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            $assignment->delete();

            Log::info('Assignment supprimé', [
                'id' => $id,
                'hotel_id' => $assignment->hotel_id,
                'deleted_by' => auth()->id(),
            ]);


            return response()->json([
                'message' => 'Assignation supprimée avec succès',
                'id' => $id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression assignment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

}
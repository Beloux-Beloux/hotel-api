<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffAbsenceRequest;
use App\Http\Requests\UpdateStaffAbsenceRequest;
use App\Http\Resources\StaffAbsenceResource;
use App\Models\StaffAbsence;
use App\Models\HousekeepingStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StaffAbsenceController extends Controller
{

    public function index(Request $request, $staffId)
    {
        $query = StaffAbsence::where('staff_id', $staffId);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from')) {
            $query->where('end_date', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->where('start_date', '<=', $request->query('to'));
        }

        $absences = $query->orderBy('start_date', 'desc')->paginate(20);

        return StaffAbsenceResource::collection($absences);
    }

    public function store(StoreStaffAbsenceRequest $request, $staffId)
    {
        $staff = HousekeepingStaff::find($staffId);

       
        if (!$staff) {
            return response()->json(['message' => 'Staff not found'], 404);
        }

        $data = $request->validated();
        $data['staff_id'] = $staffId;
        
        $data['status'] = 'pending';
        $absence = StaffAbsence::create($data);

        return response()->json([
            'message' => 'Absence créée avec succès',
            'data' => new StaffAbsenceResource($absence),
        ], 201);
    }

    public function update(UpdateStaffAbsenceRequest $request, $id)
    {
        $absence = StaffAbsence::find($id);
        if (!$absence) {
            return response()->json(['message' => 'Absence not found'], 404);
        }

        $user = auth()->user();


        if ($request->has('status')) {
            $absence->status = $request->input('status');
            $absence->approved_by = $user->id;

            if ($absence->status === 'rejected') {
                $absence->rejection_note = $request->input('rejection_note');
            }
        }

        $absence->fill($request->only(['start_date', 'end_date', 'reason']));
        $absence->save();
        return new StaffAbsenceResource($absence);
    }

    public function destroy($id)
    {
        $absence = StaffAbsence::find($id);
        if (!$absence) {
            return response()->json(['message' => 'Absence not found'], 404);
        }
        $absence->delete();

        return response()->json(['message' => 'Absence deleted']);
    }

    public function getAllAbsences()
    {
        // Utiliser eager loading avec les relations
        $absences = StaffAbsence::with(['staff.user:id,name'])
            ->orderBy('id', 'DESC')
            ->get()
            ->map(function ($absence) {
                // Ajouter le nom du staff aux données
                $absence->staff_name = $absence->staff->user->name ?? 'N/A';
                return $absence;
            });
        
        return response()->json($absences);
    }
}

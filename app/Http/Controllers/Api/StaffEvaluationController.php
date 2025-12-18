<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\StaffEvaluation;
use Illuminate\Http\Request;

class StaffEvaluationController extends Controller
{
    public function index()
    {
        return StaffEvaluation::with('staff')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:housekeeping_staff,id',
            'date' => 'required|date',
            'score' => 'required|integer|min:0|max:100',
            'comments' => 'nullable|string',
        ]);

        $evaluation = StaffEvaluation::create($validated);

        return response()->json($evaluation, 201);
    }

    public function show($id)
    {
        return StaffEvaluation::with('staff')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $evaluation = StaffEvaluation::findOrFail($id);

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'score' => 'sometimes|integer|min:0|max:100',
            'comments' => 'nullable|string',
        ]);

        $evaluation->update($validated);

        return response()->json($evaluation);
    }

    public function destroy($id)
    {
        $evaluation = StaffEvaluation::findOrFail($id);
        $evaluation->delete();

        return response()->json(['message' => 'Deleted Successfully']);
    }
}

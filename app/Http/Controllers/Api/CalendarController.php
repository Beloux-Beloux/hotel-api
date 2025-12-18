<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RoomAssignment;
use App\Models\Template;
use Carbon\Carbon;

class CalendarController extends Controller
{
    // Récupérer les attributions pour un mois donné
    public function getMonthAssignments(Request $request)
    {
        $year = $request->query('year', date('Y'));
        $month = $request->query('month', date('m'));

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $assignments = RoomAssignment::with(['staff', 'room'])
            ->whereBetween('assigned_date', [$start, $end])
            ->get()
            ->map(function($a) {
                return [
                    'id' => $a->id,
                    'date' => $a->assigned_date->format('Y-m-d'),
                    'staff_name' => $a->staff->name ?? '—',
                    'room_name' => $a->room->name ?? '—',
                    'status' => $a->status,
                    'status_color' => $a->status_color,
                ];
            });

        return response()->json($assignments);
    }

    // Récupérer les templates
    public function getTemplates()
    {
        $templates = Template::all();
        return response()->json($templates);
    }

    public function applyTemplate(Request $request)
    {
        $request->validate([
            'template_id' => 'required|uuid',
            'dates' => 'required|array|min:1'
        ]);

        $template = Template::findOrFail($request->template_id);

        foreach ($request->dates as $date) {
            // Exemple : dupliquer les attributions du modèle
            foreach ($template->items as $item) {
                RoomAssignment::create([
                    'hotel_id' => auth()->user()->current_hotel_id,
                    'room_id' => $item->room_id,
                    'staff_id' => $item->staff_id,
                    'assigned_date' => $date,
                    'status' => RoomAssignment::STATUS_PENDING
                ]);
            }
        }

        return response()->json(['message' => 'Modèle appliqué avec succès.']);
    }

}

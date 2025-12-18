<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Template;
use App\Models\RoomAssignment;
use Carbon\Carbon;
use DatePeriod;
use DateInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Events\AssignmentsUpdated;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    protected $validDays = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'
    ];

    /**
     * Liste des templates + items liés
     */
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id ?? null;

        $templates = Template::with(['items.room', 'items.staff'])
            ->when($hotelId, fn($q) => $q->where('hotel_id', $hotelId))
            ->get();

        return response()->json($templates);
    }

    /**
     * Création d’un template
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'hotel_id' => 'required',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.day_of_week' => ['required', Rule::in($this->validDays)],
            'items.*.room_id' => 'nullable',
            'items.*.staff_id' => 'nullable',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $template = Template::create([
                'hotel_id' => $data['hotel_id'] ?? ($request->user()->hotel_id ?? null),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $template->items()->create($item);
            }

            DB::commit();
            return response()->json($template->load('items'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Template Store Error: '.$e->getMessage());
            return response()->json(['error' => 'Erreur interne'], 500);
        }
    }

    /**
     * Mise à jour d’un template
     */
    public function update(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'array',
            'items.*.day_of_week' => [Rule::in($this->validDays)],
            'items.*.room_id' => 'nullable',
            'items.*.staff_id' => 'nullable',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (isset($data['items'])) {
                $template->items()->delete();
                foreach ($data['items'] as $item) {
                    $template->items()->create($item);
                }
            }

            DB::commit();
            return response()->json($template->load('items'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Template Update Error: '.$e->getMessage());
            return response()->json(['error' => 'Erreur interne'], 500);
        }
    }

    /**
     * Suppression d’un template
     */
    public function destroy($id)
    {
        $template = Template::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template supprimé avec succès ✅']);
    }

    /**
     * Application d’un template sur une plage de dates
     */
    public function applyTemplate(Request $request, $id)
    {
        $data = $request->validate([
            'start' => 'required_without:start_date|date',
            'end' => 'required_without:end_date|date',
            'start_date' => 'required_without:start|date',
            'end_date' => 'required_without:end|date',
        ]);

        $start = Carbon::parse($data['start'] ?? $data['start_date']);
        $end = Carbon::parse($data['end'] ?? $data['end_date']);

        if ($start->gt($end)) {
            return response()->json(['error' => 'La date de début doit être avant la date de fin'], 422);
        }

        $template = Template::with('items')->findOrFail($id);

        if ($template->items->isEmpty()) {
            return response()->json(['error' => 'Le template ne contient aucun élément'], 422);
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->copy()->addDay());

        DB::beginTransaction();
        try {
            $createdCount = 0;

            foreach ($period as $date) {
                $date = Carbon::instance($date); // ✅ Convertir en Carbon

                $dayOfWeek = strtolower($date->format('l'));

                foreach ($template->items as $item) {
                    if ($item->day_of_week !== $dayOfWeek) continue;

                    if (!$item->room_id || !$item->staff_id) continue;

                    $exists = RoomAssignment::where('room_id', $item->room_id)
                        ->where('staff_id', $item->staff_id)
                        ->where('assigned_date', $date->toDateString())
                        ->exists();

                    if (!$exists) {
                        RoomAssignment::create([
                            'hotel_id' => auth()->user()->current_hotel_id,
                            'room_id' => $item->room_id,
                            'staff_id' => $item->staff_id,
                            'assigned_date' => $date->toDateString(),
                            'status' => RoomAssignment::STATUS_PENDING,
                        ]);
                        $createdCount++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Template appliqué avec succès ✅',
                'created' => $createdCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ApplyTemplate Error: '.$e->getMessage());
            return response()->json(['error' => 'Erreur interne'], 500);
        }

    }

}

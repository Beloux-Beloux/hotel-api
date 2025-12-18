<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Hotel;
use App\Models\HousekeepingChecklist;
use Illuminate\Http\Request;

class ChecklistSettingController extends Controller
{
    public function index($hotelId) {
        $checklists = HousekeepingChecklist::whereHas('assignment', function($q) use ($hotelId){
            $q->where('hotel_id', $hotelId);
        })->get();

        return $checklists;
    }

    public function store(Request $request, $hotelId) {
        $data = $request->only('room_type_id', 'items');
        $data['hotel_id'] = $hotelId;

        // Convertir items en JSON
        if (is_array($data['items'])) {
            $data['items'] = json_encode($data['items']);
        }

        $checklist = HousekeepingChecklist::updateOrCreate(
            ['hotel_id' => $hotelId],
            $data
        );

        // Retourner l'objet avec items décodés
        $checklist->items = json_decode($checklist->items, true);
        return response()->json($checklist);
    }
}

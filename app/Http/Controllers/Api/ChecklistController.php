<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HousekeepingChecklist;
use App\Models\RoomAssignment;
use App\Models\ChecklistTemplate;
use App\Models\RoomType;
use Illuminate\Support\Facades\Log;

class ChecklistController extends Controller
{

    public function show($assignmentId)
    {
        $assignment = RoomAssignment::with(['room'])->findOrFail($assignmentId);
        
        $checklist = $assignment->checklist ?? HousekeepingChecklist::createFromTemplate(
            $assignment->id,
            $assignment->room->hotel_id, 
            $assignment->room->room_type_id
        );

        return response()->json($checklist);
    }

    // ChecklistController.php
    public function updateTemplate(Request $request, $templateId)
    {
        $validated = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'estimated_minutes' => 'required|integer|min:1',
        ]);

        $template = ChecklistTemplate::findOrFail($templateId);
        $template->update($validated);

        return response()->json([
            'message' => 'Template modifié avec succès',
            'template' => $template,
        ]);
    }

    /**
     * Récupère la checklist d’un type de chambre.
     */
    public function getChecklist($roomTypeId)
    {
        // Exemple de checklist par type de chambre
        $data = [
            'roomTypeId' => $roomTypeId,
            'estimatedMinutes' => 30,
            'items' => HousekeepingChecklist::getDefaultItems(),
        ];

        return response()->json($data);
    }

    /**
     * Sauvegarde ou met à jour la checklist associée à une tâche.
     */
    public function updateChecklist(Request $request, $assignmentId)
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
        ]);

        // Calcul automatique de la progression
        $completedCount = collect($validated['items'])->where('completed', true)->count();
        $totalCount = count($validated['items']);
        $progress = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

        // Met à jour ou crée une nouvelle checklist
        $checklist = HousekeepingChecklist::updateOrCreate(
            ['assignment_id' => $assignmentId],
            [
                'items' => $validated['items'],
                'progress' => $progress,
            ]
        );

        return response()->json([
            'message' => 'Checklist sauvegardée avec succès',
            'progress' => $progress,
            'checklist' => $checklist,
        ]);
    }
    public function uploadPhoto(Request $request, $assignmentId, $itemId)
{
    $checklist = HousekeepingChecklist::where('assignment_id', $assignmentId)->first();
    if(!$checklist)
    {
        return response()->json(['error' => 'Aucune checklist trouvée'], 400);
    }

    $file = $request->file('photo');
    if (!$file) {
        return response()->json(['error' => 'Aucune photo reçue'], 400);
    }

    $path = $file->store('checklists', 'public');

    $items = $checklist->items;
    
    // Si items est une chaîne JSON, le décoder
    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    
    // Parcourir avec référence pour modifier l'original
    foreach($items as &$item) {
        if($item['id'] == $itemId) {
            $item['photo'] = '/storage/'. $path;
        }
    }

    $checklist->items = $items;
    $checklist->save();

    // Retourner l'URL complète
    $fullUrl = url('/storage/' . $path);
    
    Log::info('Photo uploaded', [
        'assignment_id' => $assignmentId,
        'item_id' => $itemId,
        'path' => $path,
        'url' => $fullUrl
    ]);

    return response()->json([
        'success' => true,
        'url' => $fullUrl,
        'path' => '/storage/' . $path
    ]);
}

    public function updateItem(Request $request, $assignmentId, $itemId)
    {
        $checklist = HousekeepingChecklist::where('assignment_id', $assignmentId)->first();

        if (!$checklist) {
            return response()->json(['error' => 'Checklist introuvable'], 404);
        }

        $data = $request->validate([
            'completed' => 'required|boolean',
        ]);

        $items = $checklist->items;

        // Modifier l’item correspondant
        foreach ($items as &$item) {
            if ($item['id'] == $itemId) {
                $item['completed'] = $data['completed'];
            }
        }

        $checklist->items = $items;
        $checklist->save();

        return response()->json([
            'message' => 'Item mis à jour',
            'itemId' => $itemId,
            'completed' => $data['completed'],
        ]);
    }

     /**
     * Récupère les templates de checklist pour un hôtel
     */
    public function getHotelTemplates($hotelId)
    {
        $templates = ChecklistTemplate::with('roomType')
            ->where('hotel_id', $hotelId)
            ->get();

        $roomTypesIds = RoomType::where('hotel_id', $hotelId)->pluck('id')->toArray();

        foreach ($roomTypesIds as $roomTypeId) {
            if (!$templates->where('room_type_id', $roomTypeId)->first()) {
                $defaultItems = ChecklistTemplate::getDefaultItems();
                $defaultTemplate = ChecklistTemplate::create([
                    'hotel_id' => $hotelId,
                    'room_type_id' => $roomTypeId,
                    'name' => 'Template par défaut pour le type de chambre ' . $roomTypeId,
                    'items' => $defaultItems,
                    'estimated_minutes' => 30,
                ]);
                $templates->push($defaultTemplate);
            }
        }

        return response()->json($templates);
    }

    /**
     * Crée ou met à jour un template de checklist
     */
    public function saveTemplate(Request $request, $hotelId)
    {
        $validated = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'items' => 'array',
            'estimated_minutes' => 'required|integer|min:1',
        ]);

        $template = ChecklistTemplate::updateOrCreate(
            [
                'hotel_id' => $hotelId,
                'room_type_id' => $validated['room_type_id'],
            ],
            $validated
        );

        return response()->json([
            'message' => 'Template sauvegardé avec succès',
            'template' => $template,
        ]);
    }

    /**
     * Supprime un template de checklist
     */
    public function deleteTemplate($templateId)
    {
        $template = ChecklistTemplate::findOrFail($templateId);
        $template->delete();

        return response()->json(['message' => 'Template supprimé avec succès']);
    }

}

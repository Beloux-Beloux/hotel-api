<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\PriorityRule;
use App\Models\Room;
use App\Services\PriorityCalculationService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriorityRulesController extends Controller
{
    protected $priorityService;
    protected $reservationService;

    public function __construct(
        PriorityCalculationService $priorityService,
        ReservationService $reservationService
    ) {
        $this->priorityService = $priorityService;
        $this->reservationService = $reservationService;
    }

    public function index(Hotel $hotel)
    {
        $rules = PriorityRule::byHotel($hotel->id)
            ->orderBy('weight', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($rules);
    }

    public function store(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => ['required', Rule::in([
                PriorityRule::TYPE_STATUS,
                PriorityRule::TYPE_RESERVATION,
                PriorityRule::TYPE_TIME_BASED,
                PriorityRule::TYPE_GUEST_BASED
            ])],
            'conditions' => 'required|array',
            'priority_level' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'weight' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'description' => 'nullable|string'
        ]);

        $rule = PriorityRule::create([
            'hotel_id' => $hotel->id,
            ...$validated
        ]);

        return response()->json($rule, 201);
    }

    public function update(Request $request, Hotel $hotel, PriorityRule $priorityRule)
    {
        if ($priorityRule->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'rule_type' => ['sometimes', Rule::in([
                PriorityRule::TYPE_STATUS,
                PriorityRule::TYPE_RESERVATION,
                PriorityRule::TYPE_TIME_BASED,
                PriorityRule::TYPE_GUEST_BASED
            ])],
            'conditions' => 'sometimes|array',
            'priority_level' => ['sometimes', Rule::in(['normal', 'high', 'urgent'])],
            'weight' => 'sometimes|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string'
        ]);

        $priorityRule->update($validated);

        return response()->json($priorityRule);
    }

    public function destroy(Hotel $hotel, PriorityRule $priorityRule)
    {
        if ($priorityRule->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $priorityRule->delete();

        return response()->json(['message' => 'Rule deleted successfully']);
    }

    public function updateAll(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'sometimes|integer|exists:priority_rules,id',
            'rules.*.name' => 'required|string|max:255',
            'rules.*.rule_type' => ['required', Rule::in([
                PriorityRule::TYPE_STATUS,
                PriorityRule::TYPE_RESERVATION,
                PriorityRule::TYPE_TIME_BASED,
                PriorityRule::TYPE_GUEST_BASED
            ])],
            'rules.*.conditions' => 'required|array',
            'rules.*.priority_level' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'rules.*.weight' => 'required|integer|min:0|max:100',
            'rules.*.is_active' => 'sometimes|boolean',
            'rules.*.description' => 'nullable|string'
        ]);

        $rules = $validated['rules'];

        foreach ($rules as $ruleData) {
            if (isset($ruleData['id'])) {
                $rule = PriorityRule::where('id', $ruleData['id'])
                    ->where('hotel_id', $hotel->id)
                    ->first();
                
                if ($rule) {
                    $rule->update($ruleData);
                }
            } else {
                PriorityRule::create([
                    'hotel_id' => $hotel->id,
                    ...$ruleData
                ]);
            }
        }

        $updatedRules = PriorityRule::byHotel($hotel->id)->get();

        return response()->json([
            'message' => 'Rules updated successfully',
            'rules' => $updatedRules
        ]);
    }

    public function testRule(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'rule' => 'required|array',
            'rule.name' => 'required|string|max:255',
            'rule.rule_type' => ['required', Rule::in([
                PriorityRule::TYPE_STATUS,
                PriorityRule::TYPE_RESERVATION,
                PriorityRule::TYPE_TIME_BASED,
                PriorityRule::TYPE_GUEST_BASED
            ])],
            'rule.conditions' => 'required|array',
            'rule.priority_level' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'rule.weight' => 'required|integer|min:0|max:100',
            'sample_size' => 'sometimes|integer|min:1|max:50'
        ]);

        $rule = new PriorityRule($validated['rule']);
        $sampleSize = $validated['sample_size'] ?? 10;

        $sampleRooms = Room::byHotel($hotel->id)
            ->with(['currentReservation', 'nextReservation'])
            ->inRandomOrder()
            ->limit($sampleSize)
            ->get();

        $results = $sampleRooms->map(function ($room) use ($rule) {
            $matches = $rule->evaluate($room, $this->reservationService);
            
            return [
                'room' => [
                    'id' => $room->id,
                    'number' => $room->number,
                    'status' => $room->status,
                    'floor' => $room->floor
                ],
                'matches_rule' => $matches,
                'current_priority' => $this->priorityService->computeDynamicPriority($room)
            ];
        });

        return response()->json([
            'rule' => $rule,
            'test_results' => $results,
            'summary' => [
                'total_tested' => $sampleRooms->count(),
                'matches_count' => $results->where('matches_rule', true)->count(),
                'match_rate' => round(($results->where('matches_rule', true)->count() / $sampleRooms->count()) * 100, 2)
            ]
        ]);
    }

    public function toggle(Hotel $hotel, PriorityRule $priorityRule)
    {
        if ($priorityRule->hotel_id !== $hotel->id) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $priorityRule->update([
            'is_active' => !$priorityRule->is_active
        ]);

        return response()->json([
            'message' => 'Rule ' . ($priorityRule->is_active ? 'activated' : 'deactivated'),
            'rule' => $priorityRule
        ]);
    }
}
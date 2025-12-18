<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingSetting;
use App\Models\HousekeepingStaff;
use Illuminate\Http\Request;

class HousekeepingSettingsController extends Controller
{
    public function index($hotelId) {
        return HousekeepingSetting::where('hotel_id', $hotelId)->first();
    }
    

    public function update(Request $request, $hotelId)
    {
        $validatedData = $request->validate([
            'default_cleaning_times' => 'nullable|array',
            'default_cleaning_times.*' => 'integer|min:1|max:300',
            'max_rooms_per_staff' => 'required|integer|min:1|max:50',

            'working_hours' => 'nullable|array',
            'working_hours.start' => 'nullable|date_format:H:i',
            'working_hours.end' => 'nullable|date_format:H:i',
            'working_hours.break_start' => 'nullable|date_format:H:i',
            'working_hours.break_end' => 'nullable|date_format:H:i',

            'notifications_enabled' => 'required|boolean',

            'alert_thresholds' => 'nullable|array',
            'alert_thresholds.*' => 'integer|min:1|max:1440', // max minutes
        ]);

        $settings = HousekeepingSetting::updateOrCreate(
            ['hotel_id' => $hotelId],
            $validatedData
        );
        HousekeepingStaff::where('hotel_id', $hotelId)
        ->update(['max_rooms_per_day' => $request->max_rooms_per_staff]);


        return response()->json($settings);
    }

}


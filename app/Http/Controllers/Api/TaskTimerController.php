<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaskTimer;
use App\Models\TaskTimerLog;
use Illuminate\Support\Facades\Log;

class TaskTimerController extends Controller
{

    public function show($task_id)
    {
        try {
            $timer = TaskTimer::firstOrCreate(
                ['task_id' => $task_id],
                ['elapsed_seconds' => 0, 'status' => 'stopped', 'start_time' => null]
            );

            return response()->json([
                'success' => true,
                'data' => $timer
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans show timer : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du timer.'
            ], 500);
        }
    }

    public function start(Request $request)
    {
        $request->validate([
            'task_id' => 'required|string',
        ]);

        try {
            $timer = TaskTimer::updateOrCreate(
                ['task_id' => $request->task_id],
                ['status' => 'running', 'start_time' => now()]
            );

            return response()->json([
                'success' => true,
                'data' => $timer
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur start timer : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage du timer.'
            ], 500);
        }
    }

    public function stop(Request $request)
    {
        $request->validate([
            'task_id' => 'required|string',
            'elapsed_seconds' => 'required|integer',
        ]);

        try {
            $timer = TaskTimer::where('task_id', $request->task_id)->first();

            if (!$timer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timer non trouvé.'
                ], 404);
            }

            // Créer le log
            TaskTimerLog::create([
                'task_id' => $timer->task_id,
                'start_time' => $timer->start_time ?? now(),
                'end_time' => now(),
                'elapsed_seconds' => $request->elapsed_seconds,
            ]);

            // Mettre à jour le timer
            $timer->update([
                'elapsed_seconds' => $request->elapsed_seconds,
                'status' => 'stopped',
                'start_time' => null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $timer
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur stop timer : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'arrêt du timer.'
            ], 500);
        }
    }

    public function pause(Request $request)
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|string',
                'elapsed_seconds' => 'required|integer'
            ]);

            $timer = TaskTimer::where('task_id', $validated['task_id'])->first();

            if (!$timer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timer non trouvé.'
                ], 404);
            }

            $timer->update([
                'elapsed_seconds' => $validated['elapsed_seconds'],
                'status' => 'paused',
            ]);

            return response()->json([
                'success' => true,
                'data' => $timer
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur pause timer : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise en pause du timer.'
            ], 500);
        }
    } 
}

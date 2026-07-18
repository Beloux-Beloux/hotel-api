<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\RoomAssignment;
use App\Models\HousekeepingStaff;
use App\Models\Room;
use App\Models\User;
use App\Events\TaskNotStartedReminderEvent;
use Carbon\Carbon;

class CheckNotStartedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:check-not-started 
                            {--minutes=30 : Nombre de minutes après l\'assignation pour déclencher le rappel}
                            {--hotel= : ID de l\'hôtel spécifique}
                            {--test : Mode test - affiche les tâches sans envoyer de notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifie les tâches non démarrées et envoie des rappels';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $thresholdMinutes = (int) $this->option('minutes');
        $hotelId = $this->option('hotel');
        $testMode = $this->option('test');
        
        $this->info("Vérification des tâches non démarrées depuis {$thresholdMinutes} minutes...");

        // Construire la requête
        $query = RoomAssignment::query()
            ->with(['room', 'staff', 'staff.user'])
            ->where('status', 'pending')
            ->whereNull('started_at')
            ->where('assigned_date', Carbon::today()->toDateString())
            ->where('assigned_at', '<=', Carbon::now()->subMinutes($thresholdMinutes));

        // Filtrer par hôtel si spécifié
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }

        $assignments = $query->get();

        $this->info("Trouvé {$assignments->count()} tâche(s) non démarrée(s).");

        foreach ($assignments as $assignment) {
            try {
                // Calculer le temps écoulé
                $assignedAt = Carbon::parse($assignment->assigned_at);
                $minutesElapsed = $assignedAt->diffInMinutes(Carbon::now());
                
                // Récupérer les modèles associés
                $staff = HousekeepingStaff::find($assignment->staff_id);
                $room = Room::find($assignment->room_id);
                
                if (!$staff || !$room) {
                    $this->error("Données manquantes pour l'assignation #{$assignment->id}");
                    continue;
                }

                // Vérifier si le personnel est actuellement en service
                if (!$this->isStaffOnDuty($staff)) {
                    $this->warn("Le personnel {$staff->display_name} n'est pas en service - ignoré");
                    continue;
                }

                // Incrémenter le compteur de rappels
                $reminderCount = ($assignment->reminder_count ?? 0) + 1;
                
                // Ne pas envoyer plus de 5 rappels
                if ($reminderCount > 5) {
                    $this->warn("Trop de rappels pour l'assignation #{$assignment->id} - ignoré");
                    continue;
                }

                // Mode test - affichage seulement
                if ($testMode) {
                    $this->line("TEST - Rappel pour: Chambre {$room->number}, Personnel: {$staff->display_name}");
                    $this->line("       Temps écoulé: {$minutesElapsed} min, Rappel #{$reminderCount}");
                    continue;
                }

                // Déclencher l'événement
                event(new TaskNotStartedReminderEvent(
                    $assignment,
                    $staff,
                    $room,
                    $assignment->assigned_by ?? 1, // Utilisateur système par défaut
                    $minutesElapsed,
                    $reminderCount
                ));

                $this->info("Rappel envoyé: Chambre {$room->number} à {$staff->display_name} ({$minutesElapsed} min, rappel #{$reminderCount})");

                Log::info('Task not started reminder scheduled', [
                    'assignment_id' => $assignment->id,
                    'staff_id' => $staff->id,
                    'room_number' => $room->number,
                    'minutes_elapsed' => $minutesElapsed,
                    'reminder_count' => $reminderCount,
                ]);

            } catch (\Exception $e) {
                $this->error("Erreur avec l'assignation #{$assignment->id}: " . $e->getMessage());
                Log::error('Error processing task reminder', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Vérification terminée.');
    }

    /**
     * Vérifier si le personnel est en service
     */
    private function isStaffOnDuty(HousekeepingStaff $staff): bool
    {
        // Vérifier les horaires de travail
        $currentHour = Carbon::now()->hour;
        $currentDay = Carbon::now()->dayOfWeek;
        
        // Récupérer le planning du personnel
        $schedule = $staff->schedules()
            ->where('day_of_week', $currentDay)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            return false; // Pas de planning pour aujourd'hui
        }

        return $currentHour >= $schedule->start_hour && $currentHour <= $schedule->end_hour;
    }
}
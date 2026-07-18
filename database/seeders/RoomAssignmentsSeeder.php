<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RoomAssignmentsSeeder extends Seeder
{
    // IDs des staffs disponibles
    private array $staffIds = [
        '019a49ff-fa2d-7333-9cf7-9b4eec2efc74',
        '019b2123-7c62-7016-9a02-d7aeb3ce0ba4',
        '019b212f-899e-71d9-85de-14ae874b776a'
    ];

    // Checklist complète
    private array $checklist = [
        "Nettoyer la salle de bain",
        "Aspirer/Nettoyer le sol",
        "Dépoussiérer les meubles",
        "Vider les poubelles",
        "Vérifier/Remplacer les équipements",
        "Ranger la chambre",
        "Nettoyer les vitres",
        "Désinfecter les surfaces fréquemment touchées",
        "Vérifier les articles de toilette"
    ];

    // Notes possibles
    private array $possibleNotes = [
        "Chambre en bon état, nettoyage standard",
        "Chambre très sale, nécessitait un nettoyage approfondi",
        "Client exigeant, vérification supplémentaire effectuée",
        "Problème mineur avec le lavabo réparé",
        "Tous les équipements fonctionnels",
        "Remplacement des serviettes et du savon",
        "Aspirateur légèrement bruyant mais efficace",
        "Petite tache sur le tapis, traitée",
        "Fenêtre difficile à ouvrir, signalée à la maintenance",
        "Lingérie parfaitement arrangée",
        "Parfum d'ambiance ajouté",
        "Minibar réapprovisionné",
        "Télécommande TV remplacée (piles)",
        "Rien à signaler, nettoyage rapide",
        "Client a laissé un pourboire",
        "Chambre parfumée agréablement",
        "Miroir de salle de bain parfaitement nettoyé",
        "Rideaux légèrement poussiéreux, dépoussiérés",
        "Lit fait avec soin",
        "Coussins supplémentaires fournis"
    ];

    public function run(): void
    {
        // 1. Récupérer toutes les assignations existantes
        $existingAssignments = DB::table('room_assignments')->get();
        
        // 2. Structures pour suivre les créneaux occupés
        $occupiedRoomsByDay = []; // Pour éviter qu'une chambre soit assignée 2 fois le même jour
        $staffSchedulesByDay = []; // Pour suivre le planning des staffs
        
        // Initialiser avec les données existantes
        foreach ($existingAssignments as $assignment) {
            $day = $assignment->assigned_date;
            $roomId = $assignment->room_id;
            $staffId = $assignment->staff_id;
            
            // Marquer la chambre comme occupée ce jour-là
            $occupiedRoomsByDay[$day][$roomId] = true;
            
            // Ajouter au planning du staff si les heures existent
            if ($assignment->started_at && $assignment->completed_at) {
                $startedAt = Carbon::parse($assignment->started_at);
                $completedAt = Carbon::parse($assignment->completed_at);
                
                if (!isset($staffSchedulesByDay[$staffId][$day])) {
                    $staffSchedulesByDay[$staffId][$day] = [];
                }
                
                $staffSchedulesByDay[$staffId][$day][] = [
                    'start' => $startedAt,
                    'end' => $completedAt,
                    'room_id' => $roomId
                ];
            }
        }
        
        $newAssignments = [];
        $totalToGenerate = 100; // Environ 100 nouvelles assignations au total
        
        // 3. Pour chaque staff, générer des assignations
        foreach ($this->staffIds as $staffId) {
            $assignmentsPerStaff = floor($totalToGenerate / count($this->staffIds));
            
            for ($i = 0; $i < $assignmentsPerStaff; $i++) {
                // Choisir un jour aléatoire parmi les 20 derniers jours
                $dayOffset = rand(0, 19);
                $assignedDate = Carbon::now()->subDays($dayOffset)->startOfDay();
                $dayString = $assignedDate->format('Y-m-d');
                
                // Choisir une chambre disponible ce jour-là
                $availableRooms = [];
                for ($roomId = 1; $roomId <= 27; $roomId++) {
                    if (!isset($occupiedRoomsByDay[$dayString][$roomId])) {
                        $availableRooms[] = $roomId;
                    }
                }
                
                if (empty($availableRooms)) {
                    continue; // Pas de chambre disponible ce jour-là
                }
                
                $roomId = $availableRooms[array_rand($availableRooms)];
                
                // Générer des heures réalistes qui ne chevauchent pas
                $timeSlot = $this->findAvailableTimeSlot(
                    $staffId,
                    $dayString,
                    $assignedDate,
                    $staffSchedulesByDay[$staffId][$dayString] ?? []
                );
                
                if (!$timeSlot) {
                    continue; // Pas de créneau disponible
                }
                
                // Calculer la durée
                $durationMinutes = $timeSlot['started_at']->diffInMinutes($timeSlot['completed_at']);
                
                // Vérifier que la durée est > 5 minutes
                if ($durationMinutes <= 5) {
                    $timeSlot['completed_at'] = $timeSlot['started_at']->copy()->addMinutes(rand(10, 30));
                    $durationMinutes = $timeSlot['started_at']->diffInMinutes($timeSlot['completed_at']);
                }
                
                // Ajouter à la liste des nouvelles assignations
                $newAssignments[] = [
                    'id' => Str::uuid(),
                    'hotel_id' => 1,
                    'room_id' => $roomId,
                    'staff_id' => $staffId,
                    'assigned_date' => $dayString,
                    'assigned_at' => $timeSlot['assigned_at'],
                    'started_at' => $timeSlot['started_at'],
                    'completed_at' => $timeSlot['completed_at'],
                    'validated_at' => $timeSlot['validated_at'],
                    'validated_by' => 1,
                    'status' => 'validated',
                    'duration_minutes' => $durationMinutes,
                    'checklist_completed' => json_encode($this->checklist),
                    'notes' => $this->possibleNotes[array_rand($this->possibleNotes)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // Mettre à jour les structures de suivi
                $occupiedRoomsByDay[$dayString][$roomId] = true;
                
                if (!isset($staffSchedulesByDay[$staffId][$dayString])) {
                    $staffSchedulesByDay[$staffId][$dayString] = [];
                }
                
                $staffSchedulesByDay[$staffId][$dayString][] = [
                    'start' => $timeSlot['started_at'],
                    'end' => $timeSlot['completed_at'],
                    'room_id' => $roomId
                ];
            }
        }
        
        // 4. Insérer les nouvelles assignations
        foreach (array_chunk($newAssignments, 50) as $chunk) {
            DB::table('room_assignments')->insert($chunk);
        }
        
        $this->command->info(count($newAssignments) . ' nouvelles room assignments ajoutées avec succès!');
    }
    
    /**
     * Trouve un créneau horaire disponible pour un staff un jour donné
     */
    private function findAvailableTimeSlot(string $staffId, string $dayString, Carbon $day, array $existingSlots): ?array
    {
        // Heures de travail : 8h à 18h
        $workStart = $day->copy()->setTime(8, 0, 0);
        $workEnd = $day->copy()->setTime(18, 0, 0);
        
        // Essayer plusieurs créneaux aléatoires
        for ($attempt = 0; $attempt < 50; $attempt++) {
            // Heure d'assignation entre 8h et 17h
            $assignedHour = rand(8, 17);
            $assignedMinute = rand(0, 59);
            $assignedAt = $day->copy()->setTime($assignedHour, $assignedMinute, 0);
            
            // Démarrer 5-15 minutes après l'assignation
            $startDelay = rand(5, 15);
            $startedAt = $assignedAt->copy()->addMinutes($startDelay);
            
            // Durée du nettoyage (15-60 minutes)
            $cleaningDuration = rand(15, 60);
            $completedAt = $startedAt->copy()->addMinutes($cleaningDuration);
            
            // Validation 5-15 minutes après la fin
            $validationDelay = rand(5, 15);
            $validatedAt = $completedAt->copy()->addMinutes($validationDelay);
            
            // Vérifier que tout est dans la journée de travail
            if ($completedAt->greaterThan($workEnd) || $validatedAt->greaterThan($workEnd)) {
                continue;
            }
            
            // Vérifier que le créneau ne chevauche pas avec les existants
            $hasOverlap = false;
            foreach ($existingSlots as $slot) {
                if ($this->timeSlotsOverlap($startedAt, $completedAt, $slot['start'], $slot['end'])) {
                    $hasOverlap = true;
                    break;
                }
            }
            
            if (!$hasOverlap) {
                return [
                    'assigned_at' => $assignedAt,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'validated_at' => $validatedAt
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si deux créneaux horaires se chevauchent
     */
    private function timeSlotsOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lessThan($end2) && $start2->lessThan($end1);
    }
}
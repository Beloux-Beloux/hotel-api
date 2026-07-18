<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffAbsencesSeeder extends Seeder
{
    // IDs des staffs
    private array $staffIds = [
        '019a49ff-fa2d-7333-9cf7-9b4eec2efc74',
        '019b2123-7c62-7016-9a02-d7aeb3ce0ba4',
        '019b212f-899e-71d9-85de-14ae874b776a'
    ];

    // Raisons possibles
    private array $reasons = [
        "Maladie",
        "Congé annuel",
        "Congé familial",
        "Rendez-vous médical",
        "Problèmes personnels",
        "Formation professionnelle",
        "Vacances",
        "Congé maternité/paternité",
        "Démarches administratives",
        "Congé exceptionnel"
    ];

    // Notes de rejet
    private array $rejectionNotes = [
        "Demande rejetée car déjà demandé et approuvé",
        "Congé non accordé pour cause de surcharge du personnel",
        "Période de pointe, congé non autorisé",
        "Déjà trop d'absences programmées sur cette période",
        "Besoin en personnel trop important",
        "Congé refusé pour raison opérationnelle",
        "Période bloquée pour les congés",
        "Demande en conflit avec les besoins du service"
    ];

    public function run(): void
    {
        DB::table('staff_absences')->truncate();
        
        $absences = [];
        $approvedPeriods = []; // Pour suivre les périodes approuvées et éviter les chevauchements
        
        // Date de début : 1er janvier de cette année
        $startDate = Carbon::createFromDate(date('Y'), 1, 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        
        // Pour chaque staff
        foreach ($this->staffIds as $staffId) {
            $currentDate = $startDate->copy();
            
            // Parcourir chaque semaine
            while ($currentDate->lessThan($endDate)) {
                // Début et fin de la semaine
                $weekStart = $currentDate->copy();
                $weekEnd = $weekStart->copy()->addDays(6);
                
                // Générer entre 0 et 3 absences par semaine
                $absencesCount = rand(0, 3);
                
                for ($i = 0; $i < $absencesCount; $i++) {
                    // Choisir une date de début aléatoire dans la semaine
                    $daysOffset = rand(0, 6);
                    $absenceStart = $weekStart->copy()->addDays($daysOffset);
                    
                    // Durée de l'absence (entre 1 et 5 jours)
                    $durationDays = rand(1, 5);
                    $absenceEnd = $absenceStart->copy()->addDays($durationDays - 1);
                    
                    // S'assurer que l'absence ne dépasse pas aujourd'hui
                    if ($absenceEnd->greaterThan($endDate)) {
                        continue;
                    }
                    
                    // Vérifier les chevauchements avec les périodes déjà approuvées
                    $conflict = false;
                    $conflictWith = null;
                    
                    foreach ($approvedPeriods as $period) {
                        if ($this->periodsOverlap($absenceStart, $absenceEnd, $period['start'], $period['end'])) {
                            $conflict = true;
                            $conflictWith = $period['staff_id'];
                            break;
                        }
                    }
                    
                    // Déterminer le statut
                    $status = $conflict ? 'rejected' : 'approved';
                    
                    // Si conflit, on rejette cette demande
                    if ($conflict) {
                        $reason = $this->reasons[array_rand($this->reasons)];
                        $rejectionNote = "Demande rejetée car le staff " . substr($conflictWith, -8) . " a déjà un congé approuvé sur cette période";
                        
                        $absences[] = [
                            'staff_id' => $staffId,
                            'start_date' => $absenceStart->format('Y-m-d'),
                            'end_date' => $absenceEnd->format('Y-m-d'),
                            'reason' => $reason,
                            'status' => $status,
                            'approved_by' => null,
                            'rejection_note' => $rejectionNote,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } else {
                        // Pas de conflit, on approuve
                        $reason = $this->reasons[array_rand($this->reasons)];
                        
                        $absences[] = [
                            'staff_id' => $staffId,
                            'start_date' => $absenceStart->format('Y-m-d'),
                            'end_date' => $absenceEnd->format('Y-m-d'),
                            'reason' => $reason,
                            'status' => $status,
                            'approved_by' => 1, // Approved by user with id 1
                            'rejection_note' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        // Ajouter cette période aux périodes approuvées
                        $approvedPeriods[] = [
                            'staff_id' => $staffId,
                            'start' => $absenceStart,
                            'end' => $absenceEnd,
                        ];
                    }
                }
                
                // Passer à la semaine suivante
                $currentDate->addWeek();
            }
        }
        
        // Assurer une distribution équitable en limitant le nombre total d'absences
        // pour éviter qu'un seul staff ait toutes les absences approuvées
        $this->balanceAbsences($absences, $approvedPeriods);
        
        // Insérer les absences
        foreach (array_chunk($absences, 50) as $chunk) {
            DB::table('staff_absences')->insert($chunk);
        }
        
        $this->command->info(count($absences) . ' staff absences seeded successfully!');
        $this->command->info('Approved: ' . count(array_filter($absences, fn($a) => $a['status'] === 'approved')));
        $this->command->info('Rejected: ' . count(array_filter($absences, fn($a) => $a['status'] === 'rejected')));
    }
    
    /**
     * Vérifie si deux périodes se chevauchent
     */
    private function periodsOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lte($end2) && $start2->lte($end1);
    }
    
    /**
     * Équilibre les absences pour que chaque staff ait des congés approuvés
     */
    private function balanceAbsences(array &$absences, array &$approvedPeriods): void
    {
        // Compter les absences approuvées par staff
        $approvedCount = [];
        foreach ($this->staffIds as $staffId) {
            $approvedCount[$staffId] = 0;
        }
        
        foreach ($absences as &$absence) {
            if ($absence['status'] === 'approved') {
                $approvedCount[$absence['staff_id']]++;
            }
        }
        
        // Trouver le staff avec le moins d'absences approuvées
        $minApproved = min($approvedCount);
        
        // Pour chaque staff qui a moins d'absences approuvées que la moyenne
        foreach ($approvedCount as $staffId => $count) {
            if ($count < $minApproved + 2) { // On permet une certaine marge
                // Trouver des absences rejetées de ce staff qu'on pourrait approuver
                foreach ($absences as &$absence) {
                    if ($absence['staff_id'] === $staffId && $absence['status'] === 'rejected') {
                        // Vérifier si on peut l'approuver maintenant (pas de conflit)
                        $conflict = false;
                        $absenceStart = Carbon::parse($absence['start_date']);
                        $absenceEnd = Carbon::parse($absence['end_date']);
                        
                        foreach ($approvedPeriods as $period) {
                            if ($this->periodsOverlap($absenceStart, $absenceEnd, $period['start'], $period['end'])) {
                                $conflict = true;
                                break;
                            }
                        }
                        
                        if (!$conflict) {
                            // Approuver cette absence
                            $absence['status'] = 'approved';
                            $absence['approved_by'] = 1;
                            $absence['rejection_note'] = null;
                            
                            // Ajouter à la liste des périodes approuvées
                            $approvedPeriods[] = [
                                'staff_id' => $staffId,
                                'start' => $absenceStart,
                                'end' => $absenceEnd,
                            ];
                            
                            $approvedCount[$staffId]++;
                            break;
                        }
                    }
                }
            }
        }
    }
}
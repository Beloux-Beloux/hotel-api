<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('tasks:daily-summary')->dailyAt('18:00');
         // Vérifier les tâches non démarrées toutes les 30 minutes
        $schedule->command('tasks:check-not-started --minutes=30')
            ->everyThirtyMinutes()
            ->between('6:00', '20:00') // Pendant les heures d'ouverture
            ->runInBackground()
            ->name('check_not_started_tasks')
            ->withoutOverlapping(10); // Empêcher les chevauchements
        
        // Vérification urgente toutes les 15 minutes pour les hôtels occupés
        $schedule->command('tasks:check-not-started --minutes=15 --hotel=1') // ID de l'hôtel principal
            ->everyFifteenMinutes()
            ->between('9:00', '18:00')
            ->runInBackground()
            ->name('urgent_task_check')
            ->withoutOverlapping(5);
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}

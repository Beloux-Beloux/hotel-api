<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RoomAssignment;
use App\Services\WhatsAppService;

class SendDailyTaskSummary extends Command
{
    protected $signature = 'tasks:daily-summary';
    protected $description = 'Envoie le résumé journalier des tâches à l\'admin via WhatsApp';

    public function handle()
    {
        $tasks = RoomAssignment::whereDate('created_at', today())->get();
        if ($tasks->isEmpty()) {
            $this->info('Aucune tâche aujourd\'hui.');
            return 0;
        }

        $summary = "Résumé journalier des tâches :\n";
        foreach ($tasks as $t) {
            $summary .= "#{$t->id} - Chambre {$t->room_number} - {$t->status}\n";
        }

        $wa = new WhatsAppService();
        $wa->sendText('+261345844481', $summary);

        $this->info('Résumé journalier envoyé.');
        return 0;
    }
}

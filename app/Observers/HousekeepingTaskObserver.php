<?php

namespace App\Observers;

use App\Models\RoomAssignment;
use App\Services\WhatsAppService;

class HousekeepingTaskObserver
{
    public function created(RoomAssignment $task)
    {
        $this->sendUrgentAlert($task);
    }

    public function updated(RoomAssignment $task)
    {
        $this->sendUrgentAlert($task);
    }

    protected function sendUrgentAlert(RoomAssignment $task)
    {
        if ($task->is_urgent) {
            $wa = new WhatsAppService();
            $wa->sendTemplateWithParams(
                $task->staff->phone_number,
                'alert_urgent_task',
                [
                    $task->id,
                    $task->room_number,
                    $task->description
                ]
            );

            $wa->sendInteractiveButtons(
                $task->staff->phone_number,
                "Tâche #{$task->id} - Validez ou refusez",
                [
                    ["id" => "validate_task:{$task->id}", "title" => "OK"],
                    ["id" => "refuse_task:{$task->id}",   "title" => "Refuser"]
                ]
            );
        }
    }
}

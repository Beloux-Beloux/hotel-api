<?php

namespace App\Http\Controllers\Api;  // ✅ CORRECT

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\RoomAssignment;
use App\Services\WhatsAppService;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $token = env('WHATSAPP_VERIFY_TOKEN');
        if ($request->hub_mode && $request->hub_challenge && $request->hub_verify_token === $token) {
            return response($request->hub_challenge, 200);
        }
        return response('Invalid token', 403);
    }

    public function receive(Request $request)
    {
        $entry = $request->input('entry.0.changes.0.value.messages.0');
        if ($entry) {
            $text = $entry['text']['body'] ?? '';
            $from = $entry['from'];

            if (preg_match('/Valider (\d+)/i', $text, $matches)) {
                $taskId = $matches[1];
                $task = RoomAssignment::find($taskId);
                if ($task) {
                    $task->status = 'validated';
                    $task->save();

                    (new WhatsAppService())->sendText($from, "Tâche #{$taskId} validée ✅");
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }
}

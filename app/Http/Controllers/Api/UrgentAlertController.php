<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;


class UrgentAlertController extends Controller
{
protected $wa;


public function __construct(WhatsAppService $wa)
{
$this->wa = $wa;
}


// POST /api/housekeeping/urgent-alert
public function send(Request $request)
{
$request->validate([
'phone' => 'required|string',
'room' => 'required|string',
]);


$phone = $request->input('phone');
$room = $request->input('room');


// Utiliser un template pré-approuvé "urgent_alert" avec un param body (ex: nom de chambre)
try {
$components = [
[
'type' => 'body',
'parameters' => [
['type' => 'text', 'text' => $room],
],
],
];


$resp = $this->wa->sendTemplateMessage($phone, 'urgent_alert', 'fr', $components);


return response()->json(['success' => true, 'provider' => $resp]);
} catch (\Exception $e) {
Log::error('WhatsApp urgent alert failed: ' . $e->getMessage());
return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
}
}
}
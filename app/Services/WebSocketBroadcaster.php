<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketBroadcaster
{
    protected $serverUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->serverUrl = 'http://localhost:3001';
        $this->apiKey = config('app.websocket_api_key', 'your-secret-key');
    }

    /**
     * Émettre un événement à un hôtel spécifique
     */
    public function broadcast(string $hotelId, string $event, array $data = []): bool
    {
        try {
            // Dans un vrai serveur, vous enverriez via HTTP
            // Pour l'instant, nous allons simuler
            
            // Si le serveur est dans le même processus, utilisez directement la classe
            if (app()->has('websocket.server')) {
                $server = app('websocket.server');
                $server->broadcastToHotel($hotelId, [
                    'type' => $event,
                    'data' => $data,
                    'timestamp' => now()->toISOString()
                ]);
                return true;
            }
            
            // Sinon, utilisez HTTP
            $response = Http::post("{$this->serverUrl}/broadcast", [
                'api_key' => $this->apiKey,
                'hotel_id' => $hotelId,
                'event' => $event,
                'data' => $data
            ]);
            
            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'error' => $e->getMessage(),
                'hotel_id' => $hotelId,
                'event' => $event
            ]);
            return false;
        }
    }

    /**
     * Émettre un événement à un utilisateur spécifique
     */
    public function emitToUser(string $userId, string $event, array $data = []): bool
    {
        // Implémentez selon vos besoins
        return $this->broadcast('user_' . $userId, $event, $data);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('websocket.url', 'http://localhost:3001');
        $this->apiKey = config('websocket.api_key', '');
    }

    /**
     * Broadcast an event to all connected clients of a hotel
     */
    public function broadcast(string $hotelId, string $event, array $data = []): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/broadcast', [
                'hotelId' => $hotelId,
                'event' => $event,
                'data' => $data,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WebSocket broadcast failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast exception', [
                'message' => $e->getMessage(),
                'hotelId' => $hotelId,
                'event' => $event,
            ]);

            return false;
        }
    }

    /**
     * Notify room status change
     */
    public function notifyRoomStatusChange(string $hotelId, array $roomData, string $previousStatus, string $newStatus): void
    {
        $this->broadcast($hotelId, 'room_status_changed', [
            'room' => $roomData,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
        ]);
    }

    /**
     * Notify room note added
     */
    public function notifyRoomNoteAdded(string $hotelId, string $roomId, array $noteData): void
    {
        $this->broadcast($hotelId, 'room_note_added', [
            'roomId' => $roomId,
            'note' => $noteData,
        ]);
    }

    /**
     * Notify room note deleted
     */
    public function notifyRoomNoteDeleted(string $hotelId, string $roomId, string $noteId): void
    {
        $this->broadcast($hotelId, 'room_note_deleted', [
            'roomId' => $roomId,
            'noteId' => $noteId,
        ]);
    }

    /**
     * Notify assignments updated
     */
    public function notifyAssignmentsUpdated(string $hotelId, $date): void
    {
        $this->broadcast($hotelId, 'assignments_updated', [
            'date' => $date->format('Y-m-d'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify assignment reassigned
     */
    public function notifyAssignmentReassigned(string $hotelId, string $assignmentId, string $newStaffId): void
    {
        $this->broadcast($hotelId, 'assignment_reassigned', [
            'assignmentId' => $assignmentId,
            'newStaffId' => $newStaffId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify assignment status changed
     */
    public function notifyAssignmentStatusChanged(string $hotelId, string $assignmentId, string $status): void
    {
        $this->broadcast($hotelId, 'assignment_status_changed', [
            'assignmentId' => $assignmentId,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify room issue reported
     */
    public function notifyRoomIssueReported(string $hotelId, string $roomId, string $issue): void
    {
        $this->broadcast($hotelId, 'room_issue_reported', [
            'roomId' => $roomId,
            'issue' => $issue,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Notify new reservation
     */
    public function notifyNewReservation(string $hotelId, array $reservationData): void
    {
        $this->broadcast($hotelId, 'reservation_created', [
            'reservation' => $reservationData,
        ]);
    }

    /**
     * Notify reservation status change
     */
    public function notifyReservationStatusChange(string $hotelId, string $reservationId, string $previousStatus, string $newStatus): void
    {
        $this->broadcast($hotelId, 'reservation_status_changed', [
            'reservationId' => $reservationId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
        ]);
    }

    /**
     * Check WebSocket server health
     */
    public function health(): array
    {
        try {
            $response = Http::get($this->baseUrl . '/health');
            
            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => 'error', 'message' => 'Health check failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\HousekeepingReportController;

use App\Http\Controllers\Api\StaffAbsenceController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\TaskTimerController;
use App\Http\Controllers\Api\HousekeepingSettingsController;
use App\Http\Controllers\Api\ChecklistSettingController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\TemplateController;
use \App\Http\Controllers\Api\RoomAssignmentController;
use \App\Http\Controllers\Api\DebugAssignmentController;
use App\Http\Controllers\App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\StaffEvaluationController;
use App\Http\Controllers\Api\HousekeepingStaffController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\UrgentAlertController;
use App\Http\Controllers\WhatsAppWebhookController as ControllersWhatsAppWebhookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StaffEvaluationController as ApiStaffEvaluationController;
use App\Http\Controllers\Api\TaskController as ApiTaskController;
use App\Http\Controllers\NotificationController as ControllersNotificationController;
use App\Http\Controllers\Api\RoomAssignmentController as ApiRoomAssignmentController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\PriorityRulesController;
use Illuminate\Support\Facades\App;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
    /*
    Route::get('/test-whatsapp', function () {
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $accessToken   = env('WHATSAPP_ACCESS_TOKEN');

        $message = [
            "messaging_product" => "whatsapp",
            "to"                => "261345844481",
            "type"              => "template",
            "template"          => [
                "name"     => "hello_world",
                "language" => ["code" => "en_US"]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer $accessToken",
            'Content-Type'  => 'application/json'
        ])->post("https://graph.facebook.com/v22.0/{$phoneNumberId}/messages", $message);

        return $response->json();
    });
    */


    Route::get('/health', function () {
        return response()->json(['status' => 'ok'], 200);
    });

    Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'receive']);






    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-email', [\App\Http\Controllers\Api\VerificationController::class, 'verifyApi']);

    Route::prefix('housekeeping/assignments/timer')->group(function () {
        Route::get('/{task_id}', [TaskTimerController::class, 'show']);
        Route::post('/start', [TaskTimerController::class, 'start']);
        Route::post('/pause', [TaskTimerController::class, 'pause']);
        Route::post('/stop', [TaskTimerController::class, 'stop']);
        Route::get('/history/{task_id}', [TaskTimerController::class, 'history']); // <-- ajouté
    });

    Route::get('housekeeping/checklists/{hotelId}', [ChecklistSettingController::class, 'index']);
    Route::post('housekeeping/checklists/{hotelId}', [ChecklistSettingController::class, 'store']);

    Route::post('housekeeping/checklists/{assignmentId}/items/{itemId}/photo', [ChecklistController::class, 'uploadPhoto']);
    Route::put('/housekeeping/checklists/{assignmentId}/items/{itemId}', [ChecklistController::class, 'updateItem']);

    Route::get('housekeeping/checklist/{assignmentId}', [ChecklistController::class, 'show']);

    // Assignments (vue calendrier)
    Route::get('/assignments/by-month', [RoomAssignmentController::class, 'getAssignmentsByMonth']);




    Route::get('/housekeeping/reports/daily', [HousekeepingReportController::class, 'dailyReport']);
    Route::get('/housekeeping/reports/staff', [HousekeepingReportController::class, 'staffPerformance']);
    Route::get('/housekeeping/reports/export', [HousekeepingReportController::class, 'exportModal']);
    Route::get('/housekeeping/reports/floor', [HousekeepingReportController::class, 'floorReport']);
    Route::get('/housekeeping/reports/floorHeatmap', [HousekeepingReportController::class, 'floorHeatmap']);


    Route::apiResource('staff-evaluations', ApiStaffEvaluationController::class);




    Route::post('/housekeeping/urgent-alert', [UrgentAlertController::class, 'send']);




    Route::get('/housekeeping-settings/{hotelId}', [HousekeepingSettingsController::class, 'index']);
        
    // Mettre à jour ou créer les paramètres
    Route::put('/housekeeping-settings/{hotelId}', [HousekeepingSettingsController::class, 'update']);


    Route::get('/templates', [TemplateController::class, 'index']);
    
        // Templates d'attribution
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::put('/templates/{id}', [TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    Route::post('/templates/{id}/apply', [TemplateController::class, 'applyTemplate']);
    

    Route::get('/housekeeping-staff/technician', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'getTechnician']);

    Route::get('/housekeeping/issues', [IssueController::class, 'index']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Hotels - These routes don't require hotel selection
    Route::get('/hotels', [\App\Http\Controllers\Api\HotelController::class, 'index']);
    Route::get('/hotels/current', [\App\Http\Controllers\Api\HotelController::class, 'current']);
    Route::post('/hotels/switch', [\App\Http\Controllers\Api\HotelController::class, 'switch']);
    
    Route::get('/housekeeping-staff/allAbsences', [StaffAbsenceController::class, 'getAllAbsences']);
    Route::get('/housekeeping-staff/{id}/absences', [StaffAbsenceController::class, 'index']);
    Route::post('/housekeeping-staff/{id}/absences', [StaffAbsenceController::class, 'store']);
    Route::put('/housekeeping-staff/absences/{id}', [StaffAbsenceController::class, 'update']);
    Route::delete('/housekeeping-staff/absences/{id}', [StaffAbsenceController::class, 'destroy']);

        
    Route::get('/housekeeping/issues/{id}', [IssueController::class, 'show']);
    Route::put('/housekeeping/issues/{id}', [IssueController::class, 'update']);
    Route::post('/housekeeping/issues/{id}/assign', [IssueController::class, 'assign']);
    Route::post('/housekeeping/issues/store', [IssueController::class, 'store']);

    Route::get('/housekeeping/issues/{id}/history', [IssueController::class, 'history']);

  
    Route::get('/assignments/my-assignments', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'myAssignments']);

 
                // routes/api.php
    Route::get('/calendar/assignments', [CalendarController::class, 'getMonthAssignments']);
    Route::get('/calendar/templates', [CalendarController::class, 'getTemplates']);
    // routes/api.php
    Route::post('/calendar/apply-template', [CalendarController::class, 'applyTemplate']);


    Route::get('/staff/get-staff-id/{id}', [HousekeepingStaffController::class, 'getStaffId']);

   
    Route::get('housekeeping/assignments/history', [RoomAssignmentController::class, 'history']);


    
        // Statistiques
    Route::get('/housekeeping-staff/statistics', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'statistics']);
    // Graphique de performance
    Route::get('/housekeeping-staff/performance', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'performance']);


    // Routes that require hotel selection
    Route::middleware(['ensure.hotel.selected'])->group(function () {
        // Dashboard
        Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats'])
            ->middleware('permission:dashboard.view');
        Route::get('/dashboard/activities', [\App\Http\Controllers\Api\DashboardController::class, 'activities'])
            ->middleware('permission:dashboard.view');
        
        // Hotel management
        Route::get('/hotels/statistics', [\App\Http\Controllers\Api\HotelController::class, 'statistics']);
        Route::put('/hotels/settings', [\App\Http\Controllers\Api\HotelController::class, 'updateSettings']);
        
        // Room Types
        Route::apiResource('room-types', \App\Http\Controllers\Api\RoomTypeController::class)
            ->middleware(['permission:rooms.view'])
            ->except(['destroy']);
        Route::delete('/room-types/{roomType}', [\App\Http\Controllers\Api\RoomTypeController::class, 'destroy'])
            ->middleware('permission:rooms.edit');
        Route::get('/room-types/{roomType}/availability', [\App\Http\Controllers\Api\RoomTypeController::class, 'availability'])
            ->middleware('permission:rooms.view');
        
        // Rooms
        Route::get('/rooms/availability', [\App\Http\Controllers\Api\RoomController::class, 'availability'])
            ->middleware('permission:rooms.view');
        Route::get('/rooms/statistics', [\App\Http\Controllers\Api\RoomController::class, 'statistics'])
            ->middleware('permission:dashboard.view');
        Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class)
            ->middleware(['permission:rooms.view'])
            ->except(['destroy']);
        Route::delete('/rooms/{room}', [\App\Http\Controllers\Api\RoomController::class, 'destroy'])
            ->middleware('permission:rooms.edit');
        
        // Room history and notes
        Route::get('/rooms/{room}/history', [\App\Http\Controllers\Api\RoomController::class, 'history'])
            ->middleware('permission:rooms.view');
        Route::get('/rooms/{room}/notes', [\App\Http\Controllers\Api\RoomController::class, 'getNotes'])
            ->middleware('permission:rooms.view');
        Route::post('/rooms/{room}/notes', [\App\Http\Controllers\Api\RoomController::class, 'addNote'])
            ->middleware('permission:rooms.edit');
        Route::delete('/rooms/{room}/notes/{note}', [\App\Http\Controllers\Api\RoomController::class, 'deleteNote'])
            ->middleware('permission:rooms.edit');
        
        // Guests
        Route::get('/guests/search', [\App\Http\Controllers\Api\GuestController::class, 'search'])
            ->middleware('permission:reservations.view');
        Route::get('/guests/{guest}/statistics', [\App\Http\Controllers\Api\GuestController::class, 'statistics'])
            ->middleware('permission:reservations.view');
        Route::apiResource('guests', \App\Http\Controllers\Api\GuestController::class)
            ->middleware('permission:reservations.view');
        
        // Reservations
        Route::get('/reservations/arrivals', [\App\Http\Controllers\Api\ReservationController::class, 'arrivals'])
            ->middleware('permission:reservations.view');
        Route::get('/reservations/departures', [\App\Http\Controllers\Api\ReservationController::class, 'departures'])
            ->middleware('permission:reservations.view');
        Route::apiResource('reservations', \App\Http\Controllers\Api\ReservationController::class)
            ->middleware('permission:reservations.view');
        Route::post('/reservations/{reservation}/cancel', [\App\Http\Controllers\Api\ReservationController::class, 'cancel'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/check-in', [\App\Http\Controllers\Api\ReservationController::class, 'checkIn'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/no-show', [\App\Http\Controllers\Api\ReservationController::class, 'markAsNoShow'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/archive', [\App\Http\Controllers\Api\ReservationController::class, 'archive'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/unarchive', [\App\Http\Controllers\Api\ReservationController::class, 'unarchive'])
            ->middleware('permission:reservations.edit');
        
        // Hotel users management
        Route::get('/hotels/{hotel}/users', [\App\Http\Controllers\Api\HotelUserController::class, 'index']);
        Route::get('/hotels/{hotel}/users/me/permissions', [\App\Http\Controllers\Api\HotelUserController::class, 'currentUserPermissions']);
        Route::post('/hotels/{hotel}/users/invite', [\App\Http\Controllers\Api\HotelUserController::class, 'invite']);
        Route::post('/hotels/{hotel}/users/create-staff', [\App\Http\Controllers\Api\HotelUserController::class, 'createStaff']);
        Route::put('/hotels/{hotel}/users/{user}/role', [\App\Http\Controllers\Api\HotelUserController::class, 'updateRole']);
        Route::delete('/hotels/{hotel}/users/{user}', [\App\Http\Controllers\Api\HotelUserController::class, 'remove']);
        
        // Housekeeping staff management
        Route::apiResource('housekeeping-staff', \App\Http\Controllers\Api\HousekeepingStaffController::class);
        

        // Room assignments
        Route::get('/assignments', [RoomAssignmentController::class, 'index']);
        Route::delete('/assignments/{assignment}', [RoomAssignmentController::class, 'destroy']);
        Route::post('/assignments/auto-assign', [RoomAssignmentController::class, 'autoAssign']);
        Route::get('/assignments/unassigned-rooms', [RoomAssignmentController::class, 'getUnassignedRooms']);
        Route::post('/assignments/manual-assign', [RoomAssignmentController::class, 'manualAssign']);
        Route::get('/debug/assignments', [DebugAssignmentController::class, 'debug']);
        Route::put('/assignments/{assignment}/reassign', [RoomAssignmentController::class, 'reassign']);
        Route::post('/assignments/{assignment}/start', [RoomAssignmentController::class, 'start']);
        Route::post('/assignments/{assignment}/complete', [RoomAssignmentController::class, 'complete']);
        Route::post('/assignments/{assignment}/completeChecklist', [RoomAssignmentController::class, 'completeChecklist']);
        Route::post('/assignments/{assignment}/validate', [RoomAssignmentController::class, 'validate']);
        Route::post('/assignments/{assignment}/report-issue', [RoomAssignmentController::class, 'reportIssue']);
        Route::post('/assignments/unassign-room', [RoomAssignmentController::class, 'unassignRoom']);
        Route::post('/assignments/{assignment}/cancel', [RoomAssignmentController::class, 'cancel']);


        
        // Historique des tâches
        Route::get('/housekeeping-staff/history', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'history']);

        // Absences et congés
        Route::get('/housekeeping-staff/absences', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'absences']);

        // Évaluations et notes
        Route::get('/housekeeping-staff/evaluations', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'evaluations']);

        
        
        // Détails d'un membre du personnel
        Route::get('/housekeeping-staff/{staff}', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'show']);


        Route::get('/housekeeping/staff/{id}/today-assignments', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'todayAssignments']);


        // routes/api.php
        Route::get('/room-types/{id}/checklist', [ChecklistController::class, 'getChecklist']);
        Route::put('/assignments/{id}/checklist', [ChecklistController::class, 'updateChecklist']);



        Route::get('/assignments/{id}', [RoomAssignmentController::class, 'show']);
        Route::put('/assignments/{id}', [RoomAssignmentController::class, 'update']);


        Route::get('/housekeeping/staff/available', [HousekeepingStaffController::class, 'available']);


        
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/mark-as-read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });

         Route::prefix('hotels/{hotel}')->group(function () {
        Route::get('/priority-rules', [PriorityRulesController::class, 'index']);
        Route::post('/priority-rules', [PriorityRulesController::class, 'store']);
        Route::put('/priority-rules/{priorityRule}', [PriorityRulesController::class, 'update']);
        Route::delete('/priority-rules/{priorityRule}', [PriorityRulesController::class, 'destroy']);
        Route::put('/priority-rules', [PriorityRulesController::class, 'updateAll']);
        Route::post('/priority-rules/test', [PriorityRulesController::class, 'testRule']);
        Route::post('/priority-rules/{priorityRule}/toggle', [PriorityRulesController::class, 'toggle']);
    });
       
    // Routes pour les templates de checklist
    Route::prefix('checklists')->group(function () {
        // Templates par hôtel
        Route::get('/hotels/{hotelId}/templates', [ChecklistController::class, 'getHotelTemplates']);
        Route::post('/hotels/{hotelId}/templates', [ChecklistController::class, 'saveTemplate']);
        Route::delete('/templates/{templateId}', [ChecklistController::class, 'deleteTemplate']);
        
        // Routes existantes pour les checklists individuelles
        Route::get('/{assignmentId}', [ChecklistController::class, 'show']);
        Route::put('/{assignmentId}', [ChecklistController::class, 'updateChecklist']);
        Route::post('/{assignmentId}/items/{itemId}/photo', [ChecklistController::class, 'uploadPhoto']);
        Route::put('/{assignmentId}/items/{itemId}', [ChecklistController::class, 'updateItem']);
        Route::put('/templates/{templateId}', [ChecklistController::class, 'updateTemplate']);
        // Route existante pour récupérer checklist par type de chambre (optionnelle)
        Route::get('/room-types/{roomTypeId}', [ChecklistController::class, 'getChecklist']);
    });
    Route::get('/hotels/{hotelId}/room-types', [RoomTypeController::class, 'getByHotel']);
    Route::get('/rooms/{room}/priority-calculation', [RoomController::class, 'calculatePriority']);

    });


});
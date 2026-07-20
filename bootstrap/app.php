<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'access.mode' => \App\Http\Middleware\CheckAccessMode::class,
            'ensure.hotel.selected' => \App\Http\Middleware\EnsureHotelSelected::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\CheckAccessMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = 'Une erreur est survenue';
                
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    return response()->json([
                        'message' => 'Erreur de validation',
                        'errors' => $e->errors()
                    ], $status);
                }
                
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'Erreur HTTP';
                }
                
                // En développement, afficher plus de détails
                /*if (config('app.debug')) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace()
                    ], $status);
                }
                
                return response()->json([
                    'message' => $message
                ], $status);*/
                return response()->json([
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace()
                ], $status);
            }
        });
    })->create();

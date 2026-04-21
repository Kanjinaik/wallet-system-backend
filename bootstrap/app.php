<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'admin.web' => \App\Http\Middleware\AdminWebMiddleware::class,
        ]);
        
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            
            return $e instanceof \Illuminate\Auth\AuthenticationException ||
                   $e instanceof \Illuminate\Validation\ValidationException ||
                   $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
        });
        
        $exceptions->render(function (Request $request, \Throwable $e) {
            if ($request->is('api/*') && $e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated. Please login from frontend and call APIs with Bearer token.',
                    'status' => false,
                    'login_url' => rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/login',
                ], 401);
            }
        });
    })->create();

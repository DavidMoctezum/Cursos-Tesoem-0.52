<?php

use App\Support\Api\ManejadorErrores;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API que consume el frontend (public/app). Usa el grupo "web":
            // sesion por cookie + proteccion CSRF.
            Route::middleware('web')
                ->prefix('api/v1')
                ->group(base_path('routes/tesoem-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\CabecerasSeguridad::class);
        $middleware->alias([
            'rol' => \App\Http\Middleware\RequiereRol::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ManejadorErrores::responder($e);
        });
    })->create();

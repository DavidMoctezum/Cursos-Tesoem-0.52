<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\CertificadoController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\InscripcionController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\ProgresoController;
use App\Http\Controllers\Api\V1\ProyectoController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

// Prefijo /api/v1 y grupo "web" definidos en bootstrap/app.php.
// Limite base para toda la API: 120 solicitudes por minuto, por sesion o IP.
// Las rutas sensibles o costosas abajo tienen su propio limite, mas estricto.
Route::middleware('throttle:120,1')->group(function () {

    // ---- Publico ----
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/usuarios', [AuthController::class, 'registrar'])->middleware('throttle:10,1');
    Route::post('/olvide-contrasena', [AuthController::class, 'recuperar'])->middleware('throttle:5,1');
    Route::post('/restablecer-contrasena', [AuthController::class, 'restablecer'])->middleware('throttle:5,1');

    // Catalogo y busqueda: se permiten mas solicitudes que una escritura,
    // pero igual se limitan por ser consultas con LIKE, potencialmente costosas.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/categorias', [CategoriaController::class, 'index']);
        Route::get('/cursos', [CursoController::class, 'index']);
        Route::get('/cursos/{curso}', [CursoController::class, 'show']);
    });

    // ---- Requiere sesion ----
    Route::middleware('auth')->group(function () {
        Route::post('/categorias', [CategoriaController::class, 'store'])->middleware('throttle:20,1');

        Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('throttle:60,1');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('throttle:30,1');

        Route::post('/cursos', [CursoController::class, 'store'])->middleware('throttle:20,1');
        Route::put('/cursos/{curso}', [CursoController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/cursos/{curso}', [CursoController::class, 'destroy'])->middleware('throttle:30,1');
        Route::post('/cursos/{curso}/aprobar', [CursoController::class, 'aprobar'])->middleware('throttle:30,1');
        Route::post('/cursos/{curso}/rechazar', [CursoController::class, 'rechazar'])->middleware('throttle:30,1');

        Route::post('/inscripcion', [InscripcionController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/inscripciones', [InscripcionController::class, 'index'])->middleware('throttle:60,1');

        // Se registra avance leccion por leccion: limite mas generoso.
        Route::post('/progreso', [ProgresoController::class, 'store'])->middleware('throttle:60,1');

        Route::get('/perfil', [PerfilController::class, 'show'])->middleware('throttle:60,1');
        Route::put('/perfil', [PerfilController::class, 'update'])->middleware('throttle:10,1');

        Route::get('/proyectos', [ProyectoController::class, 'index'])->middleware('throttle:60,1');
        Route::post('/proyectos', [ProyectoController::class, 'store'])->middleware('throttle:10,1');

        Route::get('/certificados', [CertificadoController::class, 'index'])->middleware('throttle:60,1');
    });
});
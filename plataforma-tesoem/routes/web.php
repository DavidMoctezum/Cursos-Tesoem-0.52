<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// Desactivado: login, registro, "olvide mi contraseña" y verificacion de correo
// del starter kit de Livewire. El acceso real vive en /api/v1 (public/app).
// El formulario de registro de aqui creaba cuentas activas sin verificar correo
// y sin limite de intentos, evitando los controles del punto 3.
// require __DIR__.'/auth.php';

Route::get('/verificar-correo/{id}/{hash}', [\App\Http\Controllers\VerificacionCorreoController::class, 'verificar'])
    ->middleware('signed')
    ->name('verificacion.correo');
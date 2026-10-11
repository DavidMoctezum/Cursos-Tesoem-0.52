<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // Cada prueba de limites empieza en cero, sin importar lo que otra
    // prueba haya disparado antes para la misma IP de pruebas.
    RateLimiter::clear('login|correo-no-existe@tesoem.test|127.0.0.1');
});

test('el login bloquea despues de 5 intentos fallidos con el mismo correo e IP', function () {
    for ($i = 1; $i <= 5; $i++) {
        $r = $this->postJson('/api/v1/login', [
            'correo' => 'correo-no-existe@tesoem.test',
            'contrasena' => 'ClaveIncorrecta123',
        ]);
        $r->assertStatus(422);
    }

    $bloqueado = $this->postJson('/api/v1/login', [
        'correo' => 'correo-no-existe@tesoem.test',
        'contrasena' => 'ClaveIncorrecta123',
    ]);

    $bloqueado->assertStatus(422);
    expect($bloqueado->json('mensaje'))->toContain('Demasiados intentos');
});

test('registrar cuentas se bloquea despues de 10 solicitudes por minuto', function () {
    for ($i = 1; $i <= 10; $i++) {
        $this->postJson('/api/v1/usuarios', [
            'rol' => 'ALUMNO',
            'nombre' => 'Prueba Limite',
            'correo' => 'limite_' . $i . '_' . uniqid() . '@tesoem.test',
            'contrasena' => 'Clave-de-prueba-123',
        ]);
    }

    $onceavo = $this->postJson('/api/v1/usuarios', [
        'rol' => 'ALUMNO',
        'nombre' => 'Prueba Limite',
        'correo' => 'limite_sobrante_' . uniqid() . '@tesoem.test',
        'contrasena' => 'Clave-de-prueba-123',
    ]);

    $onceavo->assertStatus(429);
});

test('crear categorias como administrador se bloquea despues de 20 solicitudes por minuto', function () {
    $admin = new User();
    $admin->correo = 'admin_limite_' . uniqid() . '@tesoem.test';
    $admin->hash_contrasena = 'Clave-de-prueba-123';
    $admin->estado = 'activo';
    $admin->save();
    $admin->perfil()->create(['nombre' => 'Admin', 'apellidos' => 'Prueba']);
    DB::table('usuarios_roles')->insert([
        'usuario_id' => $admin->id,
        'rol_id' => DB::table('roles')->where('codigo', 'administrador')->value('id'),
    ]);

    $this->actingAs($admin);

    for ($i = 1; $i <= 20; $i++) {
        $this->postJson('/api/v1/categorias', ['nombre' => 'Categoria prueba ' . uniqid()]);
    }

    $veintiuno = $this->postJson('/api/v1/categorias', ['nombre' => 'Categoria prueba ' . uniqid()]);

    $veintiuno->assertStatus(429);
});
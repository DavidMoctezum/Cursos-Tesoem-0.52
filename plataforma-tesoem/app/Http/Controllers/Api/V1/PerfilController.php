<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    // Campo del frontend que se guarda en la columna "cargo" de perfiles, segun el rol.
    private const CAMPO_EXTRA = [
        'ALUMNO' => 'carrera',
        'PROFESOR' => 'especialidad',
        'EMPRESA' => 'giro',
    ];

    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $rol = Presentador::rol($usuario);
        $perfil = $usuario->perfil;

        $datos = [
            'id' => $usuario->id,
            'nombre' => Presentador::nombre($usuario),
            'correo' => $usuario->correo,
            'rol' => $rol,
            'validado' => Presentador::validado($usuario),
            'registro' => $usuario->creado_en->toIso8601String(),
        ];

        if (isset(self::CAMPO_EXTRA[$rol])) {
            $datos[self::CAMPO_EXTRA[$rol]] = $perfil->cargo ?? '';
        }

        return Respuesta::ok(['perfil' => $datos]);
    }

    public function update(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->tienePermiso('perfiles.actualizar_propios'), 403);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'carrera' => ['nullable', 'string', 'max:150'],
            'especialidad' => ['nullable', 'string', 'max:150'],
            'giro' => ['nullable', 'string', 'max:150'],
        ]);

        $rol = Presentador::rol($usuario);
        $campo = self::CAMPO_EXTRA[$rol] ?? null;

        $partes = explode(' ', trim((string) preg_replace('/\s+/', ' ', $datos['nombre'])));
        $apellidos = count($partes) > 1 ? array_pop($partes) : '-';
        $nombre = implode(' ', $partes);

        $perfil = $usuario->perfil ?? new Perfil();
        $perfil->fill([
            'nombre' => mb_substr($nombre, 0, 100),
            'apellidos' => mb_substr($apellidos, 0, 100),
            'nombre_visible' => mb_substr(trim($datos['nombre']), 0, 200),
        ]);

        if ($campo && array_key_exists($campo, $datos)) {
            $valor = trim((string) $datos[$campo]);
            $perfil->cargo = $valor !== '' ? mb_substr($valor, 0, 150) : null;
        }

        if (! $perfil->exists) {
            $perfil->usuario_id = $usuario->id;
        }

        $perfil->save();

        Auditoria::registrar('perfil.actualizado', 'usuario', $usuario->id, 'exitoso', $usuario);

        return Respuesta::ok([], 'Perfil actualizado.');
    }
}

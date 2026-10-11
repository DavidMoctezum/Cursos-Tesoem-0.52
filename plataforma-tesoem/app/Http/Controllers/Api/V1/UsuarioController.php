<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tienePermiso('usuarios.administrar'), 403);

        $filtroRol = strtoupper((string) $request->input('rol', ''));
        $busqueda = mb_strtolower(mb_substr(trim((string) $request->input('busqueda', '')), 0, 100));

        // Roles y solicitudes en dos consultas para evitar consultar usuario por usuario.
        $roles = DB::table('usuarios_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.rol_id')
            ->get(['ur.usuario_id', 'r.codigo'])
            ->groupBy('usuario_id')
            ->map(fn ($filas) => $filas->pluck('codigo')->all());

        $solicitudes = DB::table('eventos_auditoria')
            ->where('accion', 'rol.solicitado')
            ->where('tipo_recurso', 'usuario')
            ->orderBy('id')
            ->get(['recurso_id', 'metadatos'])
            ->mapWithKeys(fn ($e) => [$e->recurso_id => (json_decode($e->metadatos, true)['rol'] ?? null)]);

        $usuarios = User::with('perfil')
            ->where('correo_normalizado', '!=', 'sistema@tesoem.local')
            ->orderByDesc('creado_en')
            ->limit(500)
            ->get()
            ->map(function (User $u) use ($roles, $solicitudes) {
                [$rol, $validado] = Presentador::resolverRol($roles[$u->id] ?? [], $solicitudes[$u->id] ?? null);

                return [
                    'id' => $u->id,
                    'nombre' => Presentador::nombre($u),
                    'correo' => $u->correo,
                    'rol' => $rol,
                    'activo' => $u->estado === 'activo',
                    'validado' => $validado,
                    'registro' => Carbon::parse($u->creado_en)->toIso8601String(),
                ];
            })
            ->filter(function (array $u) use ($filtroRol, $busqueda) {
                if ($filtroRol !== '' && $u['rol'] !== $filtroRol) {
                    return false;
                }

                if ($busqueda !== '') {
                    return str_contains(mb_strtolower($u['nombre']), $busqueda)
                        || str_contains(mb_strtolower($u['correo']), $busqueda);
                }

                return true;
            })
            ->values()
            ->all();

        return Respuesta::ok(['usuarios' => $usuarios]);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->tienePermiso('usuarios.administrar'), 403);

        $datos = $request->validate([
            'activo' => ['sometimes', 'boolean'],
            'validado' => ['sometimes', 'boolean'],
        ]);

        if ($actor->id === $usuario->id) {
            throw new ReglaDeNegocioException('AUTOEDICION', 'No puedes modificar tu propia cuenta desde aquí.');
        }

        $mensaje = 'Sin cambios.';

        DB::transaction(function () use ($actor, $usuario, $datos, &$mensaje) {
            if (array_key_exists('activo', $datos)) {
                if (in_array($usuario->estado, ['bloqueado', 'eliminado'], true)) {
                    throw new ReglaDeNegocioException('ESTADO_INVALIDO', 'Esta cuenta no se puede modificar.');
                }

                $usuario->estado = filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN) ? 'activo' : 'suspendido';
                $usuario->save();

                Auditoria::registrar('usuario.estado_cambiado', 'usuario', $usuario->id, 'exitoso', $actor, [
                    'estado' => $usuario->estado,
                ]);
                $mensaje = 'Estado actualizado.';
            }

            if (! empty($datos['validado'])) {
                $rol = Presentador::rolPendiente($usuario);

                if (! $rol) {
                    throw new ReglaDeNegocioException('SIN_SOLICITUD', 'Esta cuenta no tiene una solicitud de rol pendiente.');
                }

                DB::table('usuarios_roles')->insertOrIgnore([
                    'usuario_id' => $usuario->id,
                    'rol_id' => DB::table('roles')->where('codigo', $rol)->value('id'),
                    'asignado_por' => $actor->id,
                ]);

                Auditoria::registrar('rol.asignado', 'usuario', $usuario->id, 'exitoso', $actor, ['rol' => $rol]);
                $mensaje = 'Cuenta validada.';
            }
        });

        return Respuesta::ok([], $mensaje);
    }
}

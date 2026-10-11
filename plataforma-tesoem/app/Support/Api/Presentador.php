<?php

namespace App\Support\Api;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\ProgresoLeccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Traduce los datos del esquema de BD a los nombres y valores que usa el frontend.
 */
class Presentador
{
    const NIVEL_A_TEXTO = [
        'principiante' => 'Básico',
        'intermedio' => 'Intermedio',
        'avanzado' => 'Avanzado',
    ];

    public static function textoANivel(string $texto): string
    {
        $mapa = array_flip(self::NIVEL_A_TEXTO);

        return $mapa[$texto] ?? 'principiante';
    }

    // ---------- Usuarios ----------

    /** Rol que el usuario pidio al registrarse (profesor o empresa) y aun no tiene. */
    public static function rolPendiente(User $u): ?string
    {
        $solicitado = self::rolSolicitado($u);

        if ($solicitado && ! $u->tieneRol($solicitado)) {
            return $solicitado;
        }

        return null;
    }

    public static function rolSolicitado(User $u): ?string
    {
        $meta = DB::table('eventos_auditoria')
            ->where('accion', 'rol.solicitado')
            ->where('tipo_recurso', 'usuario')
            ->where('recurso_id', $u->id)
            ->orderByDesc('id')
            ->value('metadatos');

        if (! $meta) {
            return null;
        }

        $rol = json_decode($meta, true)['rol'] ?? null;

        return in_array($rol, ['profesor', 'empresa'], true) ? $rol : null;
    }

    /**
     * Calcula [rol para el frontend, validado] a partir de los codigos de rol del usuario
     * y del rol que pidio al registrarse. Si esta pendiente de validar, muestra el solicitado.
     */
    public static function resolverRol(array $codigos, ?string $solicitado): array
    {
        foreach (['administrador', 'profesor', 'empresa'] as $codigo) {
            if (in_array($codigo, $codigos, true)) {
                return [strtoupper($codigo), true];
            }
        }

        if ($solicitado && ! in_array($solicitado, $codigos, true)) {
            return [strtoupper($solicitado), false];
        }

        return ['ALUMNO', true];
    }

    public static function rol(User $u): string
    {
        return self::resolverRol($u->codigosRoles(), self::rolSolicitado($u))[0];
    }

    public static function validado(User $u): bool
    {
        return self::resolverRol($u->codigosRoles(), self::rolSolicitado($u))[1];
    }

    public static function nombre(?User $u): string
    {
        if (! $u) {
            return '';
        }

        $p = $u->perfil;

        if ($p && $p->nombre_visible) {
            return $p->nombre_visible;
        }

        if ($p) {
            return trim($p->nombre . ' ' . ($p->apellidos === '-' ? '' : $p->apellidos));
        }

        return $u->correo;
    }

    // ---------- Cursos ----------

    public static function duracion(Curso $c): string
    {
        $min = (int) Leccion::where('curso_id', $c->id)->where('esta_publicada', true)->sum('minutos_estimados');

        if ($min <= 0) {
            return 'Por definir';
        }

        if ($min < 60) {
            return $min . ' min';
        }

        $horas = round($min / 60, 1);
        $horas = floor($horas) == $horas ? (int) $horas : $horas;

        return $horas . ($horas == 1 ? ' hora' : ' horas');
    }

    public static function curso(Curso $c, ?User $usuario = null, bool $detalle = false): array
    {
        $categoria = DB::table('cursos_categorias as cc')
            ->join('categorias as ca', 'ca.id', '=', 'cc.categoria_id')
            ->where('cc.curso_id', $c->id)
            ->first(['ca.id', 'ca.nombre']);

        $datos = [
            'id' => $c->id,
            'titulo' => $c->titulo,
            'resumen' => $c->descripcion_corta,
            'descripcion' => $c->descripcion,
            'nivel' => self::NIVEL_A_TEXTO[$c->nivel] ?? 'Básico',
            'duracion' => self::duracion($c),
            'estado' => $c->estado,
            'publicado' => in_array($c->estado, ['publicado', 'pendiente_revision'], true),
            'motivoRechazo' => $c->motivo_rechazo,
            'inscritos' => Inscripcion::where('curso_id', $c->id)->where('estado', '!=', 'cancelado')->count(),
            'idCategoria' => $categoria->id ?? null,
            'categoria' => $categoria->nombre ?? 'Sin categoría',
            'idProfesor' => $c->profesor_id,
            'profesor' => self::nombre($c->profesor),
        ];

        if ($detalle) {
            $datos['lecciones'] = Leccion::where('curso_id', $c->id)
                ->where('esta_publicada', true)
                ->orderBy('posicion')
                ->get(['id', 'titulo'])
                ->map(fn ($l) => ['id' => $l->id, 'titulo' => $l->titulo])
                ->all();
            $datos['materiales'] = [];
            $datos['inscrito'] = false;
            $datos['leccionesVistas'] = [];

            if ($usuario) {
                $inscripcion = Inscripcion::where('curso_id', $c->id)
                    ->where('alumno_id', $usuario->id)
                    ->whereIn('estado', ['activo', 'completado'])
                    ->first();

                if ($inscripcion) {
                    $datos['inscrito'] = true;
                    $datos['leccionesVistas'] = ProgresoLeccion::where('inscripcion_id', $inscripcion->id)
                        ->where('estado', 'completado')
                        ->pluck('leccion_id')
                        ->all();
                }
            }
        }

        return $datos;
    }

    /** [completadas, total, porcentaje] de una inscripcion, con datos guardados. */
    public static function conteo(Inscripcion $inscripcion): array
    {
        $publicadas = Leccion::where('curso_id', $inscripcion->curso_id)->where('esta_publicada', true);
        $total = (clone $publicadas)->count();

        $completadas = ProgresoLeccion::where('inscripcion_id', $inscripcion->id)
            ->where('estado', 'completado')
            ->whereIn('leccion_id', (clone $publicadas)->select('id'))
            ->count();

        $porcentaje = $total > 0 ? (int) floor($completadas * 100 / $total) : 0;

        return [$completadas, $total, $porcentaje];
    }
}

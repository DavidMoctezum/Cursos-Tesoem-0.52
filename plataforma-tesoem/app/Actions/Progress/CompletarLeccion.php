<?php

namespace App\Actions\Progress;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\ProgresoLeccion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CompletarLeccion
{
    /**
     * Marca una leccion como completada para el alumno.
     * Es idempotente: completar dos veces la misma leccion no cambia nada.
     */
    public function ejecutar(User $alumno, Leccion $leccion): array
    {
        if (! $alumno->tienePermiso('progreso.administrar_propios')) {
            throw new AuthorizationException('No tienes permiso para registrar progreso.');
        }

        return DB::transaction(function () use ($alumno, $leccion) {
            $leccion = Leccion::whereKey($leccion->getKey())->first();
            $curso = $leccion ? Curso::find($leccion->curso_id) : null;

            if (
                ! $leccion
                || ! $leccion->esta_publicada
                || ! $curso
                || $curso->estado !== 'publicado'
                || $curso->archivado_en !== null
            ) {
                throw new ReglaDeNegocioException('LECCION_NO_DISPONIBLE', 'La leccion no esta disponible.');
            }

            // La inscripcion se busca con el curso de la LECCION, nunca con un dato del cliente.
            // El bloqueo evita que dos peticiones simultaneas dupliquen o pisen el progreso.
            $inscripcion = Inscripcion::where('curso_id', $leccion->curso_id)
                ->where('alumno_id', $alumno->id)
                ->whereIn('estado', ['activo', 'completado'])
                ->lockForUpdate()
                ->first();

            if (! $inscripcion) {
                throw new ReglaDeNegocioException('NO_INSCRITO', 'No estas inscrito en el curso de esta leccion.');
            }

            $progreso = ProgresoLeccion::where('inscripcion_id', $inscripcion->id)
                ->where('leccion_id', $leccion->id)
                ->first() ?? new ProgresoLeccion();

            if ($progreso->estado !== 'completado') {
                $progreso->inscripcion_id = $inscripcion->id;
                $progreso->leccion_id = $leccion->id;
                $progreso->estado = 'completado';
                $progreso->iniciado_en = $progreso->iniciado_en ?? now();
                $progreso->completado_en = now();
                $progreso->save();
            }

            $porcentaje = $this->porcentaje($inscripcion);

            // Al llegar al 100% la inscripcion se marca como completada.
            // La emision del certificado se hara en un paso posterior.
            if ($porcentaje === 100 && $inscripcion->estado === 'activo') {
                $inscripcion->estado = 'completado';
                $inscripcion->completado_en = now();
                $inscripcion->save();
            }

            return [
                'porcentaje' => $porcentaje,
                'curso_completado' => $inscripcion->estado === 'completado',
                'progreso' => $progreso,
            ];
        });
    }

    // Porcentaje calculado solo con datos guardados en la base.
    public function porcentaje(Inscripcion $inscripcion): int
    {
        $publicadas = Leccion::where('curso_id', $inscripcion->curso_id)
            ->where('esta_publicada', true);

        $total = (clone $publicadas)->count();

        if ($total === 0) {
            return 0;
        }

        $completadas = ProgresoLeccion::where('inscripcion_id', $inscripcion->id)
            ->where('estado', 'completado')
            ->whereIn('leccion_id', (clone $publicadas)->select('id'))
            ->count();

        return (int) floor($completadas * 100 / $total);
    }
}
<?php

namespace App\Actions\Enrollments;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InscribirAlumno
{
    public function ejecutar(User $alumno, Curso $curso): Inscripcion
    {
        if (! $alumno->tienePermiso('inscripciones.crear')) {
            throw new AuthorizationException('No tienes permiso para inscribirte.');
        }

        try {
            return DB::transaction(function () use ($alumno, $curso) {
                // Bloquea la fila del curso: serializa inscripciones concurrentes.
                $curso = Curso::whereKey($curso->getKey())->lockForUpdate()->first();

                if (! $curso || $curso->estado !== 'publicado' || $curso->archivado_en !== null) {
                    throw new ReglaDeNegocioException(
                        'CURSO_NO_DISPONIBLE',
                        'El curso no esta disponible para inscripcion.'
                    );
                }

                $existente = Inscripcion::where('curso_id', $curso->id)
                    ->where('alumno_id', $alumno->id)
                    ->lockForUpdate()
                    ->first();

                if ($existente && $existente->estado !== 'cancelado') {
                    throw new ReglaDeNegocioException('YA_INSCRITO', 'Ya estas inscrito en este curso.');
                }

                if ($curso->cupo_maximo !== null) {
                    $ocupados = Inscripcion::where('curso_id', $curso->id)
                        ->where('estado', '!=', 'cancelado')
                        ->count();

                    if ($ocupados >= $curso->cupo_maximo) {
                        throw new ReglaDeNegocioException('CUPO_LLENO', 'El curso ya no tiene cupo disponible.');
                    }
                }

                // Reinscripcion despues de una cancelacion: se reactiva la misma fila.
                if ($existente) {
                    $existente->estado = 'activo';
                    $existente->cancelado_en = null;
                    $existente->inscrito_en = now();
                    $existente->save();

                    return $existente;
                }

                $inscripcion = new Inscripcion();
                $inscripcion->curso_id = $curso->id;
                $inscripcion->alumno_id = $alumno->id;
                $inscripcion->estado = 'activo';
                $inscripcion->save();

                return $inscripcion;
            });
        } catch (QueryException $e) {
            // Respaldo: el indice unico (curso_id, alumno_id) frena carreras concurrentes.
            if ($e->getCode() === '23000') {
                throw new ReglaDeNegocioException('YA_INSCRITO', 'Ya estas inscrito en este curso.');
            }

            throw $e;
        }
    }
}
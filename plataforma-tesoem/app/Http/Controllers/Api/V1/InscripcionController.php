<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Enrollments\InscribirAlumno;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscripcionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // Lista de alumnos de un curso (profesor dueno o revisor).
        if ($request->filled('idCurso')) {
            $curso = Curso::findOrFail((string) $request->input('idCurso'));

            $esDueno = $curso->profesor_id === $usuario->id && $usuario->tienePermiso('inscripciones.ver_curso');
            abort_unless($esDueno || $usuario->tienePermiso('cursos.revisar'), 403);

            $alumnos = Inscripcion::with('alumno.perfil')
                ->where('curso_id', $curso->id)
                ->where('estado', '!=', 'cancelado')
                ->orderBy('inscrito_en')
                ->get()
                ->map(function (Inscripcion $i) {
                    [$completadas, $total] = Presentador::conteo($i);

                    return [
                        'idInscripcion' => $i->id,
                        'nombre' => Presentador::nombre($i->alumno),
                        'correo' => $i->alumno->correo,
                        'fecha' => Carbon::parse($i->inscrito_en)->toIso8601String(),
                        'completadas' => $completadas,
                        'total' => $total,
                    ];
                })
                ->all();

            return Respuesta::ok(['alumnos' => $alumnos]);
        }

        // Mis inscripciones (alumno).
        abort_unless($usuario->tienePermiso('inscripciones.ver_propios'), 403);

        $inscripciones = Inscripcion::with('curso.profesor.perfil')
            ->where('alumno_id', $usuario->id)
            ->where('estado', '!=', 'cancelado')
            ->orderByDesc('inscrito_en')
            ->get()
            ->map(function (Inscripcion $i) {
                [$completadas, $total, $porcentaje] = Presentador::conteo($i);

                return [
                    'idInscripcion' => $i->id,
                    'fecha' => Carbon::parse($i->inscrito_en)->toIso8601String(),
                    'curso' => Presentador::curso($i->curso),
                    'completadas' => $completadas,
                    'total' => $total,
                    'porcentaje' => $porcentaje,
                ];
            })
            ->all();

        return Respuesta::ok(['inscripciones' => $inscripciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'idCurso' => ['required', 'string', 'size:26'],
        ]);

        $curso = Curso::findOrFail($datos['idCurso']);
        $inscripcion = app(InscribirAlumno::class)->ejecutar($request->user(), $curso);

        return Respuesta::ok(['id' => $inscripcion->id], 'Inscripción registrada.', 201);
    }
}

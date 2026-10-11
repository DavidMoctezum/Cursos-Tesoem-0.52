<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Certificates\EmitirAutomatico;
use App\Actions\Progress\CompletarLeccion;
use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgresoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // El curso se deduce de la leccion; el idCurso que mande el cliente no se usa.
        $datos = $request->validate([
            'idLeccion' => ['required', 'string', 'size:26'],
        ]);

        $leccion = Leccion::findOrFail($datos['idLeccion']);

        $resultado = app(CompletarLeccion::class)->ejecutar($request->user(), $leccion);
        $inscripcion = Inscripcion::findOrFail($resultado['progreso']->inscripcion_id);

        if ($resultado['curso_completado']) {
            app(EmitirAutomatico::class)->ejecutar($inscripcion);
        }

        [$completadas, $total, $porcentaje] = Presentador::conteo($inscripcion);

        return Respuesta::ok([
            'porcentaje' => $porcentaje,
            'completadas' => $completadas,
            'total' => $total,
        ], 'Progreso guardado.');
    }
}

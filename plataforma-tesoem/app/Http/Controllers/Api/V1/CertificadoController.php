<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    // Certificados vigentes del alumno con sesion iniciada.
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->tienePermiso('certificados.ver_propios'), 403);

        $certificados = Certificado::with('curso')
            ->where('alumno_id', $usuario->id)
            ->where('estado', 'activo')
            ->orderByDesc('emitido_en')
            ->get()
            ->map(fn (Certificado $c) => [
                'id' => $c->id,
                'folio' => $c->numero_certificado,
                'fecha' => $c->emitido_en->toIso8601String(),
                'curso' => $c->curso->titulo,
                'duracion' => Presentador::duracion($c->curso),
            ])
            ->all();

        return Respuesta::ok(['certificados' => $certificados]);
    }
}

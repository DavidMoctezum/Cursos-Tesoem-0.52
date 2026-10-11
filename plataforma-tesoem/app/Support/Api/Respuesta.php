<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class Respuesta
{
    public static function requestId(): string
    {
        $request = request();
        $id = $request->attributes->get('request_id');

        if (! $id) {
            $id = (string) Str::ulid();
            $request->attributes->set('request_id', $id);
        }

        return $id;
    }

    public static function ok(array $datos = [], ?string $mensaje = null, int $estado = 200): JsonResponse
    {
        $cuerpo = ['success' => true];

        if ($mensaje !== null) {
            $cuerpo['mensaje'] = $mensaje;
        }

        $cuerpo = array_merge($cuerpo, $datos, ['meta' => ['request_id' => self::requestId()]]);

        return response()->json($cuerpo, $estado);
    }

    public static function error(int $estado, string $codigo, string $mensaje, array $extra = []): JsonResponse
    {
        $cuerpo = array_merge(
            ['success' => false, 'codigo' => $codigo, 'mensaje' => $mensaje],
            $extra,
            ['meta' => ['request_id' => self::requestId()]]
        );

        return response()->json($cuerpo, $estado);
    }
}

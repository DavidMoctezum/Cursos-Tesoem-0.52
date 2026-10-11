<?php

namespace App\Support\Api;

use App\Exceptions\ReglaDeNegocioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Convierte cualquier excepcion en la respuesta JSON que espera el frontend:
 * {"success": false, "mensaje": "...", "codigo": "...", "meta": {"request_id": "..."}}
 * Nunca expone SQL, rutas internas ni trazas.
 */
class ManejadorErrores
{
    public static function responder(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            $errores = $e->errors();
            $primero = collect($errores)->flatten()->first();

            return Respuesta::error(
                422,
                'VALIDATION_ERROR',
                $primero ?: 'La solicitud contiene datos no válidos.',
                ['campos' => $errores]
            );
        }

        if ($e instanceof ReglaDeNegocioException) {
            return Respuesta::error(409, $e->codigo, $e->getMessage());
        }

        if ($e instanceof AuthenticationException) {
            return Respuesta::error(401, 'NO_AUTENTICADO', 'Inicia sesión para continuar.');
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            $origen = ($e instanceof AccessDeniedHttpException && $e->getPrevious()) ? $e->getPrevious() : $e;
            $mensaje = $origen->getMessage();

            if ($mensaje === '' || stripos($mensaje, 'unauthorized') !== false || stripos($mensaje, 'forbidden') !== false) {
                $mensaje = 'No tienes permiso para realizar esta acción.';
            }

            return Respuesta::error(403, 'NO_AUTORIZADO', $mensaje);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return Respuesta::error(404, 'NO_ENCONTRADO', 'El recurso no existe o no está disponible.');
        }

        if ($e instanceof TokenMismatchException) {
            return Respuesta::error(419, 'CSRF', 'La sesión expiró. Recarga la página e inténtalo de nuevo.');
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return Respuesta::error(429, 'DEMASIADAS_SOLICITUDES', 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $estado = $e->getStatusCode();

            // abort(403, 'mensaje propio') llega como HttpException con estado 403.
            if ($estado === 403) {
                $mensaje = $e->getMessage();

                if ($mensaje === '' || stripos($mensaje, 'unauthorized') !== false || stripos($mensaje, 'forbidden') !== false) {
                    $mensaje = 'No tienes permiso para realizar esta acción.';
                }

                return Respuesta::error(403, 'NO_AUTORIZADO', $mensaje);
            }

            if ($estado === 419) {
                return Respuesta::error(419, 'CSRF', 'La sesión expiró. Recarga la página e inténtalo de nuevo.');
            }

            if ($estado === 405) {
                return Respuesta::error(405, 'METODO_NO_PERMITIDO', 'Método no permitido.');
            }

            return Respuesta::error($estado, 'ERROR_HTTP', 'No se pudo completar la solicitud.');
        }

        // El detalle tecnico ya quedo en los logs (Laravel lo reporta antes de renderizar).
        return Respuesta::error(500, 'ERROR_INTERNO', 'Ocurrió un error inesperado. Inténtalo más tarde.');
    }
}

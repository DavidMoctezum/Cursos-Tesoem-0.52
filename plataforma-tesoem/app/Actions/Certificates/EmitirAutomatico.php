<?php

namespace App\Actions\Certificates;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emite el certificado cuando el alumno termina el curso, usando la cuenta
 * tecnica "sistema" (no puede iniciar sesion). Nunca rompe el registro de progreso.
 */
class EmitirAutomatico
{
    public function ejecutar(Inscripcion $inscripcion): void
    {
        try {
            $sistema = User::where('correo_normalizado', 'sistema@tesoem.local')->first();

            if (! $sistema) {
                Log::warning('Certificado automatico omitido: falta la cuenta de sistema (ejecuta SistemaSeeder).');

                return;
            }

            app(EmitirCertificado::class)->ejecutar($sistema, $inscripcion);
        } catch (ReglaDeNegocioException $e) {
            // YA_EMITIDO o NO_ELEGIBLE: no es un error.
        } catch (Throwable $e) {
            Log::error('No se pudo emitir el certificado automatico: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Actions\Certificates;

use App\Actions\Progress\CompletarLeccion;
use App\Exceptions\ReglaDeNegocioException;
use App\Models\Certificado;
use App\Models\Inscripcion;
use App\Models\User;
use App\Support\Security\Auditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EmitirCertificado
{
    /**
     * Devuelve el certificado y el codigo de verificacion EN CLARO.
     * El codigo solo existe aqui: en la base se guarda unicamente su hash.
     */
    public function ejecutar(User $emisor, Inscripcion $inscripcion): array
    {
        if (! $emisor->tienePermiso('certificados.emitir')) {
            throw new AuthorizationException('No tienes permiso para emitir certificados.');
        }

        try {
            return DB::transaction(function () use ($emisor, $inscripcion) {
                $inscripcion = Inscripcion::whereKey($inscripcion->getKey())->lockForUpdate()->first();

                // La elegibilidad se recalcula con datos guardados, no se confia solo en el estado.
                if (
                    ! $inscripcion
                    || $inscripcion->estado !== 'completado'
                    || app(CompletarLeccion::class)->porcentaje($inscripcion) < 100
                ) {
                    throw new ReglaDeNegocioException(
                        'NO_ELEGIBLE',
                        'La inscripcion no cumple los requisitos para emitir certificado.'
                    );
                }

                if (Certificado::where('inscripcion_id', $inscripcion->id)->exists()) {
                    throw new ReglaDeNegocioException('YA_EMITIDO', 'Ya existe un certificado para esta inscripcion.');
                }

                $codigo = strtoupper(bin2hex(random_bytes(10)));

                $certificado = new Certificado();
                $certificado->numero_certificado = 'TESOEM-' . now()->format('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $certificado->hash_codigo_verificacion = hash('sha256', $codigo);
                $certificado->alumno_id = $inscripcion->alumno_id;
                $certificado->curso_id = $inscripcion->curso_id;
                $certificado->inscripcion_id = $inscripcion->id;
                $certificado->emitido_por = $emisor->id;
                $certificado->emitido_en = now();
                $certificado->estado = 'activo';
                $certificado->save();

                Auditoria::registrar('certificado.emitido', 'certificado', $certificado->id, 'exitoso', $emisor, [
                    'inscripcion_id' => $inscripcion->id,
                    'curso_id' => $inscripcion->curso_id,
                ]);

                return ['certificado' => $certificado, 'codigo' => $codigo];
            });
        } catch (QueryException $e) {
            // Respaldo: el indice unico por inscripcion frena emisiones concurrentes.
            if ($e->getCode() === '23000') {
                throw new ReglaDeNegocioException('YA_EMITIDO', 'Ya existe un certificado para esta inscripcion.');
            }

            throw $e;
        }
    }
}
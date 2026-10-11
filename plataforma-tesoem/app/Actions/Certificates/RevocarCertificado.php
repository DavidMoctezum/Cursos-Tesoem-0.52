<?php

namespace App\Actions\Certificates;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Certificado;
use App\Models\User;
use App\Support\Security\Auditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RevocarCertificado
{
    public function ejecutar(User $actor, Certificado $certificado, string $motivo): Certificado
    {
        if (! $actor->tienePermiso('certificados.revocar')) {
            throw new AuthorizationException('No tienes permiso para revocar certificados.');
        }

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) > 500) {
            throw new ReglaDeNegocioException('MOTIVO_INVALIDO', 'El motivo es obligatorio (maximo 500 caracteres).');
        }

        return DB::transaction(function () use ($actor, $certificado, $motivo) {
            $certificado = Certificado::whereKey($certificado->getKey())->lockForUpdate()->first();

            if (! $certificado) {
                throw new ReglaDeNegocioException('CERTIFICADO_NO_ENCONTRADO', 'El certificado no existe.');
            }

            if ($certificado->estado === 'revocado') {
                throw new ReglaDeNegocioException('YA_REVOCADO', 'El certificado ya fue revocado.');
            }

            $certificado->estado = 'revocado';
            $certificado->revocado_en = now();
            $certificado->revocado_por = $actor->id;
            $certificado->motivo_revocacion = $motivo;
            $certificado->save();

            // Historial: la revocacion nunca se borra.
            DB::table('revocaciones_certificados')->insert([
                'certificado_id' => $certificado->id,
                'revocado_por' => $actor->id,
                'motivo' => $motivo,
            ]);

            Auditoria::registrar('certificado.revocado', 'certificado', $certificado->id, 'exitoso', $actor, [
                'curso_id' => $certificado->curso_id,
            ]);

            return $certificado;
        });
    }
}
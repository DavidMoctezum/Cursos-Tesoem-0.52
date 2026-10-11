<?php

namespace App\Actions\Certificates;

use App\Models\Certificado;

class VerificarCertificado
{
    /**
     * Verificacion publica: devuelve solo datos minimos, o null si el codigo no existe.
     */
    public function ejecutar(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));

        // Formato invalido: ni siquiera se consulta la base.
        if (! preg_match('/^[A-F0-9]{20}$/', $codigo)) {
            return null;
        }

        $certificado = Certificado::where('hash_codigo_verificacion', hash('sha256', $codigo))->first();

        if (! $certificado) {
            return null;
        }

        return [
            'numero_certificado' => $certificado->numero_certificado,
            'curso' => $certificado->curso?->titulo,
            'emitido_en' => $certificado->emitido_en->toIso8601String(),
            'estado' => $certificado->estado,
            'revocado_en' => $certificado->revocado_en?->toIso8601String(),
        ];
    }
}
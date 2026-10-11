<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Auditoria
{
    /**
     * Registra un evento en eventos_auditoria.
     * NUNCA incluir contrasenas, tokens ni codigos de verificacion en $metadatos.
     */
    public static function registrar(
        string $accion,
        ?string $tipoRecurso,
        ?string $recursoId,
        string $resultado,
        ?User $actor = null,
        array $metadatos = []
    ): void {
        $ip = request()->ip();
        $ipBinaria = $ip ? @inet_pton($ip) : null;
        $agente = request()->userAgent();

        DB::table('eventos_auditoria')->insert([
            'solicitud_id' => null,
            'actor_id' => $actor?->id,
            'accion' => $accion,
            'tipo_recurso' => $tipoRecurso,
            'recurso_id' => $recursoId,
            'resultado' => $resultado,
            'direccion_ip' => $ipBinaria === false ? null : $ipBinaria,
            'agente_usuario' => $agente ? mb_substr($agente, 0, 500) : null,
            'metadatos' => $metadatos ? json_encode($metadatos) : null,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Security\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ruta publica (fuera de /api/v1) a la que llega el enlace del correo de
 * verificacion. Devuelve una pagina HTML minima, no JSON, porque el navegador
 * la abre directamente.
 */
class VerificacionCorreoController extends Controller
{
    public function verificar(Request $request, string $id, string $hash): Response
    {
        $usuario = User::find($id);

        if (! $usuario || ! hash_equals($hash, sha1($usuario->correo_normalizado))) {
            return $this->pagina('Enlace no válido', 'Este enlace de verificación no es válido o ya fue usado.', false);
        }

        if ($usuario->correo_verificado_en === null) {
            $usuario->correo_verificado_en = now();

            if ($usuario->estado === 'pendiente') {
                $usuario->estado = 'activo';
            }

            $usuario->save();

            Auditoria::registrar('correo.verificado', 'usuario', $usuario->id, 'exitoso', $usuario);
        }

        return $this->pagina('Correo confirmado', 'Tu cuenta ya está activa. Ya puedes iniciar sesión.', true);
    }

    private function pagina(string $titulo, string $mensaje, bool $exito): Response
    {
        $color = $exito ? '#1F3A5F' : '#9C2643';

        return response(
            '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<title>' . e($titulo) . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;text-align:center;color:#1a1a1a;padding:0 1.5rem">'
            . '<h1 style="color:' . $color . '">' . e($titulo) . '</h1>'
            . '<p>' . e($mensaje) . '</p>'
            . '<p><a href="/app/pages/login.html">Ir a iniciar sesión</a></p>'
            . '</body></html>',
            $exito ? 200 : 404
        );
    }
}
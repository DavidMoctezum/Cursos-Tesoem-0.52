<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Mail\RestablecerContrasenaMail;
use App\Mail\VerificarCorreoMail;
use App\Models\User;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Sirve para que el navegador reciba la cookie XSRF-TOKEN antes del primer POST.
    public function csrf(): JsonResponse
    {
        return Respuesta::ok();
    }

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'string', 'max:254'],
            'contrasena' => ['required', 'string', 'max:255'],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        $clave = 'login|' . Str::transliterate($correo . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'correo' => 'Demasiados intentos. Inténtalo de nuevo en ' . $segundos . ' segundos.',
            ]);
        }

        $credenciales = [
            'correo_normalizado' => $correo,
            'password' => $datos['contrasena'],
            'estado' => 'activo',
        ];

        if (! Auth::guard('web')->attempt($credenciales)) {
            RateLimiter::hit($clave);
            $this->explicarSiLaCuentaNoEstaActiva($correo, $datos['contrasena']);

            Auditoria::registrar('sesion.fallida', 'usuario', null, 'fallido', null, ['origen' => 'api']);

            // Mensaje uniforme: no revela si el correo existe.
            throw ValidationException::withMessages([
                'correo' => 'Las credenciales no son válidas.',
            ]);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();

        /** @var User $usuario */
        $usuario = Auth::user();
        $usuario->forceFill(['ultimo_acceso_en' => now()])->save();
        Auditoria::registrar('sesion.iniciada', 'usuario', $usuario->id, 'exitoso', $usuario, ['origen' => 'api']);

        return Respuesta::ok([
            'id' => $usuario->id,
            'rol' => Presentador::rol($usuario),
            'nombre' => Presentador::nombre($usuario),
        ], 'Sesión iniciada.');
    }

    // Si la clave es correcta pero la cuenta no puede entrar, da un mensaje mas util.
    // Solo revela el estado a quien ya demostro conocer la contrasena: no hay fuga de datos.
    private function explicarSiLaCuentaNoEstaActiva(string $correo, string $contrasena): void
    {
        $candidato = User::where('correo_normalizado', $correo)->first();

        if (! $candidato || ! Hash::check($contrasena, $candidato->hash_contrasena)) {
            return;
        }

        if ($candidato->estado === 'pendiente') {
            throw ValidationException::withMessages([
                'correo' => 'Confirma tu correo antes de iniciar sesión. Revisa tu bandeja de entrada.',
            ]);
        }

        if (in_array($candidato->estado, ['suspendido', 'bloqueado'], true)) {
            throw ValidationException::withMessages([
                'correo' => 'Esta cuenta no puede iniciar sesión. Contacta a la coordinación.',
            ]);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Respuesta::ok([], 'Sesión cerrada.');
    }

    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rol' => ['required', 'string', Rule::in(['ALUMNO', 'PROFESOR', 'EMPRESA'])],
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'correo' => ['required', 'email', 'max:254'],
            'contrasena' => ['required', 'string', 'max:255', Password::min(12)->uncompromised()],
            'carrera' => ['nullable', 'string', 'max:150'],
            'especialidad' => ['nullable', 'string', 'max:150'],
            'giro' => ['nullable', 'string', 'max:150'],
        ]);

        $correoNormalizado = mb_strtolower(trim($datos['correo']));

        if (DB::table('usuarios')->where('correo_normalizado', $correoNormalizado)->exists()) {
            throw new ReglaDeNegocioException('CORREO_NO_DISPONIBLE', 'No se pudo completar el registro con ese correo.');
        }

        // Nunca se confia en el rol que manda el cliente: todos entran como alumno.
        // Profesor y empresa quedan como solicitud hasta que un administrador la valide.
        $rolElegido = strtolower($datos['rol']);
        $extra = match ($rolElegido) {
            'profesor' => $datos['especialidad'] ?? null,
            'empresa' => $datos['giro'] ?? null,
            default => $datos['carrera'] ?? null,
        };

        [$nombre, $apellidos] = $this->separarNombre($datos['nombre']);

        try {
            $usuario = DB::transaction(function () use ($datos, $rolElegido, $extra, $nombre, $apellidos) {
                $usuario = new User();
                $usuario->correo = $datos['correo'];
                $usuario->hash_contrasena = $datos['contrasena'];
                $usuario->estado = 'pendiente'; // pasa a 'activo' al confirmar el correo
                $usuario->save();

                $usuario->perfil()->create([
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'nombre_visible' => mb_substr(trim($datos['nombre']), 0, 200),
                    'cargo' => $extra ? mb_substr(trim($extra), 0, 150) : null,
                ]);

                DB::table('usuarios_roles')->insert([
                    'usuario_id' => $usuario->id,
                    'rol_id' => DB::table('roles')->where('codigo', 'alumno')->value('id'),
                ]);

                if ($rolElegido !== 'alumno') {
                    Auditoria::registrar('rol.solicitado', 'usuario', $usuario->id, 'exitoso', $usuario, [
                        'rol' => $rolElegido,
                    ]);
                }

                return $usuario;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new ReglaDeNegocioException('CORREO_NO_DISPONIBLE', 'No se pudo completar el registro con ese correo.');
            }

            throw $e;
        }

        $this->enviarVerificacion($usuario);

        return Respuesta::ok(
            ['id' => $usuario->id],
            'Cuenta creada. Revisa tu correo para confirmarla antes de iniciar sesión.',
            201
        );
    }

    private function enviarVerificacion(User $usuario): void
    {
        $url = URL::temporarySignedRoute(
            'verificacion.correo',
            now()->addHours(24),
            ['id' => $usuario->id, 'hash' => sha1($usuario->correo_normalizado)]
        );

        Mail::to($usuario->correo)->send(new VerificarCorreoMail($url));
    }

    // Pide un enlace para restablecer la contraseña. Mensaje identico exista o no la cuenta.
    public function recuperar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:254'],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        $usuario = User::where('correo_normalizado', $correo)->where('estado', '!=', 'eliminado')->first();

        if ($usuario) {
            $token = Str::random(64);

            DB::table('tokens_restablecimiento_contrasena')->updateOrInsert(
                ['correo_normalizado' => $correo],
                [
                    'hash_token' => hash('sha256', $token),
                    'creado_en' => now(),
                    'expira_en' => now()->addMinutes(60),
                ]
            );

            $url = url('/app/pages/restablecer-contrasena.html')
                . '?correo=' . urlencode($usuario->correo) . '&token=' . $token;

            Mail::to($usuario->correo)->send(new RestablecerContrasenaMail($url));

            Auditoria::registrar('contrasena.recuperacion_solicitada', 'usuario', $usuario->id, 'exitoso', $usuario);
        }

        return Respuesta::ok(
            [],
            'Si el correo está registrado, te enviamos instrucciones para restablecer tu contraseña.'
        );
    }

    public function restablecer(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:254'],
            'token' => ['required', 'string'],
            'contrasena' => ['required', 'string', 'max:255', Password::min(12)->uncompromised()],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        $fila = DB::table('tokens_restablecimiento_contrasena')->where('correo_normalizado', $correo)->first();

        if (
            ! $fila
            || ! hash_equals($fila->hash_token, hash('sha256', $datos['token']))
            || Carbon::parse($fila->expira_en)->isPast()
        ) {
            throw new ReglaDeNegocioException('TOKEN_INVALIDO', 'El enlace no es válido o ya expiró. Solicita uno nuevo.');
        }

        $usuario = User::where('correo_normalizado', $correo)->firstOrFail();

        DB::transaction(function () use ($usuario, $datos, $correo) {
            $usuario->hash_contrasena = $datos['contrasena'];
            $usuario->contrasena_cambiada_en = now();
            $usuario->save();

            DB::table('tokens_restablecimiento_contrasena')->where('correo_normalizado', $correo)->delete();
            // Cierra cualquier sesion abierta con la contrasena anterior.
            DB::table('sesiones')->where('usuario_id', $usuario->id)->delete();

            Auditoria::registrar('contrasena.restablecida', 'usuario', $usuario->id, 'exitoso', $usuario);
        });

        return Respuesta::ok([], 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    private function separarNombre(string $completo): array
    {
        $partes = explode(' ', trim((string) preg_replace('/\s+/', ' ', $completo)));

        if (count($partes) === 1) {
            return [mb_substr($partes[0], 0, 100), '-'];
        }

        $apellidos = array_pop($partes);

        return [mb_substr(implode(' ', $partes), 0, 100), mb_substr($apellidos, 0, 100)];
    }
}
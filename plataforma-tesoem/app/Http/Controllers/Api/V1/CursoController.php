<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Courses\GuardarCurso;
use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\User;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $consulta = Curso::query()->with('profesor.perfil');

        if ($request->boolean('mios')) {
            if (! $usuario) {
                throw new AuthenticationException();
            }

            $this->exigirProfesor($usuario);
            $consulta->where('profesor_id', $usuario->id);
        } elseif ($request->filled('estado')) {
            $estado = (string) $request->input('estado');

            abort_unless($usuario && $usuario->tienePermiso('cursos.revisar'), 403);
            abort_unless(in_array($estado, ['borrador', 'pendiente_revision', 'publicado', 'rechazado', 'archivado'], true), 422);

            $consulta->where('estado', $estado);
        } else {
            $consulta->publicados();
        }

        if ($request->filled('categoria')) {
            $consulta->whereIn('id', DB::table('cursos_categorias')
                ->where('categoria_id', (string) $request->input('categoria'))
                ->select('curso_id'));
        }

        if ($request->filled('busqueda')) {
            $texto = mb_substr(trim((string) $request->input('busqueda')), 0, 100);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';

            $consulta->where(function ($q) use ($like) {
                $q->where('titulo', 'like', $like)->orWhere('descripcion_corta', 'like', $like);
            });
        }

        $cursos = $consulta->orderByDesc('creado_en')->limit(100)->get();

        return Respuesta::ok([
            'cursos' => $cursos->map(fn ($c) => Presentador::curso($c))->all(),
            'total' => $cursos->count(),
        ]);
    }

    public function show(Request $request, Curso $curso): JsonResponse
    {
        $usuario = $request->user();
        $publico = $curso->estado === 'publicado' && $curso->archivado_en === null;

        if (! $publico && ! ($usuario && Gate::forUser($usuario)->allows('view', $curso))) {
            abort(404);
        }

        return Respuesta::ok(['curso' => Presentador::curso($curso, $usuario, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $this->exigirProfesor($usuario);

        $curso = app(GuardarCurso::class)->crear($usuario, $this->validar($request));

        $mensaje = $curso->estado === 'pendiente_revision'
            ? 'Curso enviado a revisión. Se publicará cuando la coordinación lo apruebe.'
            : 'Curso guardado como borrador.';

        return Respuesta::ok(['id' => $curso->id, 'estado' => $curso->estado], $mensaje, 201);
    }

    public function update(Request $request, Curso $curso): JsonResponse
    {
        $usuario = $request->user();
        $this->exigirProfesor($usuario);
        Gate::authorize('update', $curso);

        $curso = app(GuardarCurso::class)->actualizar($usuario, $curso, $this->validar($request));

        return Respuesta::ok(['id' => $curso->id, 'estado' => $curso->estado], 'Cambios guardados.');
    }

    // "Retirar del catalogo": el profesor lo regresa a borrador; un administrador lo archiva.
    public function destroy(Request $request, Curso $curso): JsonResponse
    {
        $usuario = $request->user();

        DB::transaction(function () use ($usuario, $curso) {
            $c = Curso::whereKey($curso->id)->lockForUpdate()->firstOrFail();

            if (Gate::forUser($usuario)->allows('withdraw', $c)) {
                $c->estado = 'borrador';
                $c->save();
                Auditoria::registrar('curso.retirado', 'curso', $c->id, 'exitoso', $usuario);
            } elseif (Gate::forUser($usuario)->allows('archive', $c)) {
                $c->estado = 'archivado';
                $c->archivado_en = now();
                $c->save();
                Auditoria::registrar('curso.archivado', 'curso', $c->id, 'exitoso', $usuario);
            } else {
                abort(403);
            }
        });

        return Respuesta::ok([], 'Curso retirado del catálogo.');
    }

    public function aprobar(Request $request, Curso $curso): JsonResponse
    {
        $usuario = $request->user();
        Gate::authorize('approve', $curso);

        DB::transaction(function () use ($usuario, $curso) {
            $c = Curso::whereKey($curso->id)->lockForUpdate()->firstOrFail();

            if ($c->estado !== 'pendiente_revision') {
                throw new ReglaDeNegocioException('ESTADO_INVALIDO', 'El curso ya no está pendiente de revisión.');
            }

            $c->estado = 'publicado';
            $c->publicado_en = now();
            $c->revisado_por = $usuario->id;
            $c->revisado_en = now();
            $c->motivo_rechazo = null;
            $c->save();

            Auditoria::registrar('curso.aprobado', 'curso', $c->id, 'exitoso', $usuario);
        });

        return Respuesta::ok([], 'Curso publicado.');
    }

    public function rechazar(Request $request, Curso $curso): JsonResponse
    {
        $usuario = $request->user();
        Gate::authorize('reject', $curso);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($usuario, $curso, $datos) {
            $c = Curso::whereKey($curso->id)->lockForUpdate()->firstOrFail();

            if ($c->estado !== 'pendiente_revision') {
                throw new ReglaDeNegocioException('ESTADO_INVALIDO', 'El curso ya no está pendiente de revisión.');
            }

            $c->estado = 'rechazado';
            $c->motivo_rechazo = trim($datos['motivo']);
            $c->revisado_por = $usuario->id;
            $c->revisado_en = now();
            $c->save();

            Auditoria::registrar('curso.rechazado', 'curso', $c->id, 'exitoso', $usuario);
        });

        return Respuesta::ok([], 'Curso rechazado. El profesor verá el motivo.');
    }

    private function exigirProfesor(User $usuario): void
    {
        if ($usuario->tienePermiso('cursos.crear')) {
            return;
        }

        if (Presentador::rolPendiente($usuario) === 'profesor') {
            abort(403, 'Tu cuenta de profesor está en revisión. Podrás publicar cursos cuando la coordinación la valide.');
        }

        abort(403);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'resumen' => ['required', 'string', 'max:500'],
            'descripcion' => ['required', 'string', 'max:20000'],
            'idCategoria' => ['required', 'string', Rule::exists('categorias', 'id')->where('estado', 'activo')],
            'nivel' => ['required', 'string', Rule::in(array_values(Presentador::NIVEL_A_TEXTO))],
            'publicado' => ['sometimes', 'boolean'],
            'lecciones' => ['required', 'array', 'min:1', 'max:100'],
            'lecciones.*.titulo' => ['required', 'string', 'max:180'],
            'lecciones.*.id' => ['nullable', 'string', 'size:26'],
        ]);
    }
}

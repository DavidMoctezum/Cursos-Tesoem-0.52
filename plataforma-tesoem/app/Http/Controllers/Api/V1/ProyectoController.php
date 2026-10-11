<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Support\Api\Presentador;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProyectoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $consulta = Proyecto::query()->with('alumno.perfil');

        if ($request->boolean('mios')) {
            abort_unless($usuario->tienePermiso('proyectos.administrar_propios'), 403);
            $consulta->where('alumno_id', $usuario->id)->where('estado', '!=', 'archivado');
        } else {
            abort_unless($usuario->tienePermiso('proyectos.ver_publicos'), 403);
            $consulta->where('estado', 'publicado')
                ->where('visibilidad', 'publico')
                ->whereNull('archivado_en');
        }

        if ($request->filled('busqueda')) {
            $texto = mb_substr(trim((string) $request->input('busqueda')), 0, 100);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';

            $consulta->where(function ($q) use ($like) {
                $q->where('titulo', 'like', $like)->orWhere('descripcion', 'like', $like);
            });
        }

        $proyectos = $consulta->orderByDesc('creado_en')->limit(100)->get();

        $tecnologias = DB::table('proyectos_tecnologias as pt')
            ->join('tecnologias as t', 't.id', '=', 'pt.tecnologia_id')
            ->whereIn('pt.proyecto_id', $proyectos->pluck('id'))
            ->orderBy('t.nombre')
            ->get(['pt.proyecto_id', 't.nombre'])
            ->groupBy('proyecto_id')
            ->map(fn ($filas) => $filas->pluck('nombre')->implode(', '));

        $lista = $proyectos->map(fn (Proyecto $p) => [
            'id' => $p->id,
            'titulo' => $p->titulo,
            'descripcion' => $p->descripcion,
            'tecnologias' => $tecnologias[$p->id] ?? '',
            'enlace' => $p->url_repositorio ?: '#',
            'alumno' => Presentador::nombre($p->alumno),
            'carrera' => $p->alumno->perfil->cargo ?? '',
            'fecha' => Carbon::parse($p->publicado_en ?? $p->creado_en)->toIso8601String(),
        ])->all();

        return Respuesta::ok(['proyectos' => $lista]);
    }

    // Se publica de inmediato; un administrador puede moderarlo despues.
    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->tienePermiso('proyectos.administrar_propios'), 403);

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'tecnologias' => ['required', 'string', 'max:300'],
            'enlace' => ['nullable', 'url:http,https', 'max:500'],
        ]);

        $nombresTec = collect(explode(',', $datos['tecnologias']))
            ->map(fn ($t) => mb_substr(trim($t), 0, 100))
            ->filter()
            ->unique(fn ($t) => mb_strtolower($t))
            ->take(10)
            ->values();

        $proyecto = DB::transaction(function () use ($usuario, $datos, $nombresTec) {
            $proyecto = new Proyecto();
            $proyecto->fill([
                'titulo' => trim($datos['titulo']),
                'slug' => Str::limit(Str::slug($datos['titulo']) ?: 'proyecto', 200, '') . '-' . Str::lower(Str::random(6)),
                'resumen' => Str::limit(trim($datos['descripcion']), 500, ''),
                'descripcion' => trim($datos['descripcion']),
                'url_repositorio' => ! empty($datos['enlace']) ? $datos['enlace'] : null,
            ]);
            $proyecto->alumno_id = $usuario->id;
            $proyecto->estado = 'publicado';
            $proyecto->visibilidad = 'publico';
            $proyecto->publicado_en = now();
            $proyecto->save();

            foreach ($nombresTec as $nombre) {
                $slug = Str::slug($nombre) ?: Str::lower(Str::random(8));
                $tec = DB::table('tecnologias')->where('slug', $slug)->first();

                if (! $tec) {
                    $id = (string) Str::ulid();
                    DB::table('tecnologias')->insert(['id' => Str::lower($id), 'nombre' => $nombre, 'slug' => $slug]);
                    $tecId = Str::lower($id);
                } else {
                    $tecId = $tec->id;
                }

                DB::table('proyectos_tecnologias')->insertOrIgnore([
                    'proyecto_id' => $proyecto->id,
                    'tecnologia_id' => $tecId,
                ]);
            }

            Auditoria::registrar('proyecto.publicado', 'proyecto', $proyecto->id, 'exitoso', $usuario);

            return $proyecto;
        });

        return Respuesta::ok(['id' => $proyecto->id], 'Proyecto publicado.', 201);
    }
}

<?php

namespace App\Actions\Courses;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\ProgresoLeccion;
use App\Models\User;
use App\Support\Api\Presentador;
use App\Support\Security\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardarCurso
{
    /**
     * Un profesor nunca publica directo: si pide "publicado", el curso pasa a
     * pendiente_revision y un revisor lo aprueba.
     */
    public function crear(User $profesor, array $d): Curso
    {
        return DB::transaction(function () use ($profesor, $d) {
            $curso = new Curso();
            $curso->fill([
                'titulo' => trim($d['titulo']),
                'slug' => $this->slugCurso($d['titulo']),
                'descripcion_corta' => trim($d['resumen']),
                'descripcion' => trim($d['descripcion']),
                'nivel' => Presentador::textoANivel($d['nivel']),
            ]);
            $curso->profesor_id = $profesor->id;
            $curso->estado = $this->quierePublicar($d) ? 'pendiente_revision' : 'borrador';
            $curso->save();

            $this->asignarCategoria($curso, $d['idCategoria']);
            $this->sincronizarLecciones($curso, $d['lecciones']);

            Auditoria::registrar('curso.creado', 'curso', $curso->id, 'exitoso', $profesor, [
                'estado' => $curso->estado,
            ]);

            return $curso;
        });
    }

    public function actualizar(User $actor, Curso $curso, array $d): Curso
    {
        return DB::transaction(function () use ($actor, $curso, $d) {
            $curso = Curso::whereKey($curso->getKey())->lockForUpdate()->firstOrFail();

            $curso->fill([
                'titulo' => trim($d['titulo']),
                'descripcion_corta' => trim($d['resumen']),
                'descripcion' => trim($d['descripcion']),
                'nivel' => Presentador::textoANivel($d['nivel']),
            ]);

            if (array_key_exists('publicado', $d)) {
                if ($this->quierePublicar($d) && in_array($curso->estado, ['borrador', 'rechazado'], true)) {
                    $curso->estado = 'pendiente_revision';
                    $curso->motivo_rechazo = null;
                } elseif (! $this->quierePublicar($d) && in_array($curso->estado, ['pendiente_revision', 'publicado'], true)) {
                    $curso->estado = 'borrador';
                }
            }

            $curso->save();

            $this->asignarCategoria($curso, $d['idCategoria']);
            $this->sincronizarLecciones($curso, $d['lecciones']);

            Auditoria::registrar('curso.actualizado', 'curso', $curso->id, 'exitoso', $actor, [
                'estado' => $curso->estado,
            ]);

            return $curso;
        });
    }

    private function quierePublicar(array $d): bool
    {
        return filter_var($d['publicado'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function slugCurso(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo) ?: 'curso', 200, '');

        return $base . '-' . Str::lower(Str::random(6));
    }

    private function asignarCategoria(Curso $curso, string $idCategoria): void
    {
        DB::table('cursos_categorias')->where('curso_id', $curso->id)->delete();
        DB::table('cursos_categorias')->insert([
            'curso_id' => $curso->id,
            'categoria_id' => $idCategoria,
        ]);
    }

    /**
     * Deja las lecciones del curso exactamente como las envio el cliente (orden incluido).
     * No permite quitar una leccion que ya tiene avance de algun alumno.
     */
    private function sincronizarLecciones(Curso $curso, array $lecciones): void
    {
        $existentes = Leccion::where('curso_id', $curso->id)->get()->keyBy('id');
        $enviadas = array_values($lecciones);

        $idsEnviados = [];
        foreach ($enviadas as $item) {
            if (! empty($item['id'])) {
                $idsEnviados[] = $item['id'];
            }
        }

        foreach ($existentes as $id => $leccion) {
            if (in_array($id, $idsEnviados, true)) {
                continue;
            }

            if (ProgresoLeccion::where('leccion_id', $id)->exists()) {
                throw new ReglaDeNegocioException(
                    'LECCION_CON_AVANCE',
                    'No puedes quitar una lección que ya tiene avance de alumnos.'
                );
            }

            $leccion->delete();
        }

        // Libera las posiciones para poder reordenar sin chocar con el indice unico.
        DB::table('lecciones')->where('curso_id', $curso->id)->update([
            'posicion' => DB::raw('posicion + 10000'),
        ]);

        foreach ($enviadas as $i => $item) {
            $titulo = trim($item['titulo']);
            $id = $item['id'] ?? null;

            if ($id && $existentes->has($id)) {
                Leccion::whereKey($id)->update([
                    'titulo' => $titulo,
                    'posicion' => $i + 1,
                    'esta_publicada' => true,
                ]);

                continue;
            }

            $nueva = new Leccion();
            $nueva->fill([
                'titulo' => $titulo,
                'slug' => Str::limit(Str::slug($titulo) ?: 'leccion', 200, '') . '-' . Str::lower(Str::random(6)),
                'posicion' => $i + 1,
            ]);
            $nueva->curso_id = $curso->id;
            $nueva->esta_publicada = true;
            $nueva->save();
        }
    }
}

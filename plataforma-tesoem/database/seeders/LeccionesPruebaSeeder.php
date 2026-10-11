<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\Leccion;
use Illuminate\Database\Seeder;

class LeccionesPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $cursos = Curso::whereIn('slug', ['introduccion-a-php', 'introduccion-a-laravel'])->get();

        foreach ($cursos as $curso) {
            for ($i = 1; $i <= 4; $i++) {
                $slug = 'leccion-' . $i;

                if (Leccion::where('curso_id', $curso->id)->where('slug', $slug)->exists()) {
                    continue;
                }

                $leccion = new Leccion();
                $leccion->fill([
                    'titulo' => 'Leccion ' . $i,
                    'slug' => $slug,
                    'descripcion' => 'Contenido de prueba de la leccion ' . $i,
                    'posicion' => $i,
                    'minutos_estimados' => 15,
                ]);
                $leccion->curso_id = $curso->id;
                // La leccion 4 queda sin publicar a proposito, para probar el rechazo.
                $leccion->esta_publicada = ($i <= 3);
                $leccion->save();
            }
        }
    }
}
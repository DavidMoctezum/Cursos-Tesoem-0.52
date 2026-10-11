<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $profesor = $this->crearUsuario('profesor@tesoem.test', 'Profesor-Prueba-123', 'Profe', 'Prueba', 'profesor');
        $this->crearUsuario('admin@tesoem.test', 'Admin-Prueba-12345', 'Admin', 'Prueba', 'administrador');

        if (! DB::table('categorias')->where('slug', 'programacion')->exists()) {
            DB::table('categorias')->insert([
                'id' => (string) Str::ulid(),
                'nombre' => 'Programacion',
                'slug' => 'programacion',
                'descripcion' => 'Cursos de desarrollo de software',
                'creado_por' => $profesor->id,
            ]);
        }

        $this->crearCurso($profesor, 'Introduccion a PHP', 'introduccion-a-php', 'publicado');
        $this->crearCurso($profesor, 'Introduccion a Laravel', 'introduccion-a-laravel', 'borrador');
    }

    private function crearUsuario(string $correo, string $clave, string $nombre, string $apellidos, string $rol): User
    {
        $existente = User::where('correo_normalizado', mb_strtolower($correo))->first();

        if ($existente) {
            return $existente;
        }

        $user = new User();
        $user->correo = $correo;
        $user->hash_contrasena = $clave;
        $user->estado = 'activo';
        $user->save();

        $user->perfil()->create([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
        ]);

        DB::table('usuarios_roles')->insert([
            'usuario_id' => $user->id,
            'rol_id' => DB::table('roles')->where('codigo', $rol)->value('id'),
        ]);

        return $user;
    }

    private function crearCurso(User $profesor, string $titulo, string $slug, string $estado): void
    {
        if (Curso::where('slug', $slug)->exists()) {
            return;
        }

        $curso = new Curso();
        $curso->fill([
            'titulo' => $titulo,
            'slug' => $slug,
            'descripcion_corta' => 'Curso de prueba: ' . $titulo,
            'descripcion' => 'Descripcion completa del curso de prueba: ' . $titulo,
            'nivel' => 'principiante',
        ]);
        $curso->profesor_id = $profesor->id;
        $curso->estado = $estado;

        if ($estado === 'publicado') {
            $curso->publicado_en = now();
        }

        $curso->save();
    }
}
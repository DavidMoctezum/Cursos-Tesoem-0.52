<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SistemaSeeder extends Seeder
{
    /**
     * Cuenta tecnica que firma los certificados automaticos.
     * Estado "bloqueado": no puede iniciar sesion. Contrasena aleatoria descartada.
     */
    public function run(): void
    {
        if (User::where('correo_normalizado', 'sistema@tesoem.local')->exists()) {
            return;
        }

        $user = new User();
        $user->correo = 'sistema@tesoem.local';
        $user->hash_contrasena = Str::random(64);
        $user->estado = 'bloqueado';
        $user->save();

        $user->perfil()->create([
            'nombre' => 'Sistema',
            'apellidos' => 'TESOEM',
        ]);

        DB::table('usuarios_roles')->insert([
            'usuario_id' => $user->id,
            'rol_id' => DB::table('roles')->where('codigo', 'administrador')->value('id'),
        ]);
    }
}

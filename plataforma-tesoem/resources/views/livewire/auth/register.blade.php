<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $nombre = '';
    public string $apellidos = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:254',
                function ($attribute, $value, $fail) {
                    $existe = DB::table('usuarios')
                        ->where('correo_normalizado', mb_strtolower(trim($value)))
                        ->exists();

                    if ($existe) {
                        $fail('No se pudo completar el registro con ese correo.');
                    }
                },
            ],
            // Agregar ->uncompromised() cuando quieras rechazar contrasenas filtradas (requiere internet)
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
        ]);

        $user = DB::transaction(function () {
            $user = new User();
            $user->correo = $this->email;
            $user->hash_contrasena = $this->password;
            $user->estado = 'activo'; // cambiar a 'pendiente' cuando se implemente la verificacion de correo
            $user->save();

            $user->perfil()->create([
                'nombre' => trim($this->nombre),
                'apellidos' => trim($this->apellidos),
            ]);

            DB::table('usuarios_roles')->insert([
                'usuario_id' => $user->id,
                'rol_id' => DB::table('roles')->where('codigo', 'alumno')->value('id'),
            ]);

            return $user;
        });

        Auth::login($user);
        Session::regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Crear cuenta" description="Completa tus datos para registrarte" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <flux:input wire:model="nombre" label="Nombre" type="text" required autofocus autocomplete="given-name" placeholder="Nombre" />

        <flux:input wire:model="apellidos" label="Apellidos" type="text" required autocomplete="family-name" placeholder="Apellidos" />

        <flux:input wire:model="email" label="Correo electronico" type="email" required autocomplete="email" placeholder="correo@ejemplo.com" />

        <flux:input wire:model="password" label="Contrasena (minimo 12 caracteres)" type="password" required autocomplete="new-password" placeholder="Contrasena" />

        <flux:input wire:model="password_confirmation" label="Confirmar contrasena" type="password" required autocomplete="new-password" placeholder="Confirmar contrasena" />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">Crear cuenta</flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Ya tienes cuenta?
        <x-text-link href="{{ route('login') }}">Inicia sesion</x-text-link>
    </div>
</div>
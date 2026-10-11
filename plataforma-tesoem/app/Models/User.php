<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasUlids, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    // Cache por peticion para no repetir consultas de roles y permisos.
    protected ?array $rolesCache = null;
    protected ?array $permisosCache = null;

    // Solo estos campos se pueden asignar en masa.
    // estado, mfa_obligatorio y roles NUNCA deben venir del cliente.
    protected $fillable = [
        'correo',
        'hash_contrasena',
    ];

    protected $hidden = [
        'hash_contrasena',
        'token_recordar',
    ];

    protected function casts(): array
    {
        return [
            'hash_contrasena' => 'hashed',
            'correo_verificado_en' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
            'contrasena_cambiada_en' => 'datetime',
            'mfa_obligatorio' => 'boolean',
        ];
    }

    // Guarda siempre el correo normalizado junto al original.
    public function setCorreoAttribute(string $value): void
    {
        $this->attributes['correo'] = trim($value);
        $this->attributes['correo_normalizado'] = mb_strtolower(trim($value));
    }

    public function getAuthPassword(): string
    {
        return $this->hash_contrasena;
    }

    // Laravel usa este nombre al re-calcular hashes (rehash) durante el login.
    public function getAuthPasswordName(): string
    {
        return 'hash_contrasena';
    }

    public function getRememberTokenName(): string
    {
        return 'token_recordar';
    }

    public function getEmailAttribute(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    public function perfil(): HasOne
    {
        return $this->hasOne(Perfil::class, 'usuario_id');
    }

    // ---------- Roles y permisos ----------

    public function codigosRoles(): array
    {
        return $this->rolesCache ??= DB::table('usuarios_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.rol_id')
            ->where('ur.usuario_id', $this->id)
            ->pluck('r.codigo')
            ->all();
    }

    public function codigosPermisos(): array
    {
        return $this->permisosCache ??= DB::table('usuarios_roles as ur')
            ->join('roles_permisos as rp', 'rp.rol_id', '=', 'ur.rol_id')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('ur.usuario_id', $this->id)
            ->distinct()
            ->pluck('p.codigo')
            ->all();
    }

    public function tieneRol(string $codigo): bool
    {
        return in_array($codigo, $this->codigosRoles(), true);
    }

    public function tieneAlgunRol(array $codigos): bool
    {
        return count(array_intersect($codigos, $this->codigosRoles())) > 0;
    }

    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->codigosPermisos(), true);
    }

    // ---------- Compatibilidad con vistas del starter kit ----------

    public function getNameAttribute(): string
    {
        return $this->perfil?->nombre_visible
            ?? trim(($this->perfil?->nombre ?? '') . ' ' . ($this->perfil?->apellidos ?? ''))
            ?: $this->correo;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}

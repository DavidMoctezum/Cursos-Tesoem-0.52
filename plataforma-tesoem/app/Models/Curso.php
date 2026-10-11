<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curso extends Model
{
    use HasUlids;

    protected $table = 'cursos';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    // estado, profesor_id y los campos de revision NUNCA se asignan en masa.
    protected $fillable = [
        'titulo',
        'slug',
        'descripcion_corta',
        'descripcion',
        'nivel',
        'cupo_maximo',
    ];

    protected function casts(): array
    {
        return [
            'cupo_maximo' => 'integer',
            'publicado_en' => 'datetime',
            'revisado_en' => 'datetime',
            'archivado_en' => 'datetime',
        ];
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    // Cursos visibles para el publico.
    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado')->whereNull('archivado_en');
    }

    // Cursos de un profesor concreto.
    public function scopeDelProfesor(Builder $query, User $profesor): Builder
    {
        return $query->where('profesor_id', $profesor->id);
    }
}
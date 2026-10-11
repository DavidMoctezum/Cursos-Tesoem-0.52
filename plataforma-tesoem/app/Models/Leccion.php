<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leccion extends Model
{
    use HasUlids;

    protected $table = 'lecciones';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    // curso_id y esta_publicada los decide el backend, no el cliente.
    protected $fillable = [
        'titulo',
        'slug',
        'descripcion',
        'posicion',
        'minutos_estimados',
    ];

    protected function casts(): array
    {
        return [
            'esta_publicada' => 'boolean',
            'posicion' => 'integer',
            'minutos_estimados' => 'integer',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }
}
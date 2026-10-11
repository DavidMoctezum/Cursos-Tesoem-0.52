<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proyecto extends Model
{
    use HasUlids;

    protected $table = 'proyectos';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    // alumno_id, estado y visibilidad los decide el backend.
    protected $fillable = [
        'titulo',
        'slug',
        'resumen',
        'descripcion',
        'url_repositorio',
    ];

    protected function casts(): array
    {
        return [
            'publicado_en' => 'datetime',
            'revisado_en' => 'datetime',
            'archivado_en' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumno_id');
    }
}

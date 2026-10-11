<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgresoLeccion extends Model
{
    use HasUlids;

    protected $table = 'progreso_lecciones';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    // Nada se asigna en masa: todo lo decide el backend.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'iniciado_en' => 'datetime',
            'completado_en' => 'datetime',
            'ultima_posicion_segundos' => 'integer',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class, 'leccion_id');
    }
}
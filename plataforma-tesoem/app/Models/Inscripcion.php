<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscripcion extends Model
{
    use HasUlids;

    protected $table = 'inscripciones';

    const CREATED_AT = 'inscrito_en';
    const UPDATED_AT = null;

    // Nada se asigna en masa: todo lo decide el backend.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'inscrito_en' => 'datetime',
            'completado_en' => 'datetime',
            'cancelado_en' => 'datetime',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumno_id');
    }
}
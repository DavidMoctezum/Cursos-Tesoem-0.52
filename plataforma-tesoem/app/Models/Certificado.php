<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    use HasUlids;

    protected $table = 'certificados';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    // Nada se asigna en masa: todo lo decide el backend.
    protected $fillable = [];

    protected $hidden = [
        'hash_codigo_verificacion',
    ];

    protected function casts(): array
    {
        return [
            'emitido_en' => 'datetime',
            'revocado_en' => 'datetime',
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

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }
}
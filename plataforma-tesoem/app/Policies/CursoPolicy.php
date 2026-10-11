<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\User;

class CursoPolicy
{
    private function esDueno(User $user, Curso $curso): bool
    {
        return $curso->profesor_id === $user->id;
    }

    // Ver el catalogo de cursos publicados.
    public function viewAny(User $user): bool
    {
        return $user->tienePermiso('cursos.ver_publicados');
    }

    // Ver un curso: publicado, propio, o si eres revisor.
    public function view(User $user, Curso $curso): bool
    {
        if ($curso->estado === 'publicado' && $curso->archivado_en === null) {
            return $user->tienePermiso('cursos.ver_publicados');
        }

        return $this->esDueno($user, $curso) || $user->tienePermiso('cursos.revisar');
    }

    public function create(User $user): bool
    {
        return $user->tienePermiso('cursos.crear');
    }

    // Editar: solo el profesor dueno, salvo cursos archivados.
    // Si edita un curso publicado, el curso sigue publicado.
    public function update(User $user, Curso $curso): bool
    {
        return $this->esDueno($user, $curso)
            && $user->tienePermiso('cursos.actualizar_propios')
            && $curso->estado !== 'archivado';
    }

    // Retirar del catalogo (vuelve a borrador): dueno con curso publicado o en revision.
    public function withdraw(User $user, Curso $curso): bool
    {
        return $this->esDueno($user, $curso)
            && $user->tienePermiso('cursos.actualizar_propios')
            && in_array($curso->estado, ['publicado', 'pendiente_revision'], true);
    }

    // Aprobar o rechazar: revisor, y el curso debe estar pendiente.
    public function approve(User $user, Curso $curso): bool
    {
        return $user->tienePermiso('cursos.revisar')
            && $user->tienePermiso('cursos.publicar')
            && $curso->estado === 'pendiente_revision';
    }

    public function reject(User $user, Curso $curso): bool
    {
        return $user->tienePermiso('cursos.revisar')
            && $curso->estado === 'pendiente_revision';
    }

    public function archive(User $user, Curso $curso): bool
    {
        return $user->tienePermiso('cursos.archivar')
            && $curso->archivado_en === null;
    }
}

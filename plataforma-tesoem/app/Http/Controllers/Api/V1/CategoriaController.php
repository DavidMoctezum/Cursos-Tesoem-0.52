<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Support\Api\Respuesta;
use App\Support\Security\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = Categoria::where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])
            ->all();

        return Respuesta::ok(['categorias' => $categorias]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->tienePermiso('categorias.administrar'), 403);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $nombre = trim($datos['nombre']);
        $slug = Str::slug($nombre);

        if ($slug === '' || Categoria::where('slug', $slug)->exists()) {
            throw new ReglaDeNegocioException('CATEGORIA_EXISTENTE', 'Ya existe una categoría con ese nombre.');
        }

        $categoria = new Categoria();
        $categoria->fill(['nombre' => $nombre, 'slug' => $slug]);
        $categoria->creado_por = $usuario->id;
        $categoria->estado = 'activo';
        $categoria->save();

        Auditoria::registrar('categoria.creada', 'categoria', $categoria->id, 'exitoso', $usuario);

        return Respuesta::ok(
            ['categoria' => ['id' => $categoria->id, 'nombre' => $categoria->nombre]],
            'Categoría agregada.',
            201
        );
    }
}

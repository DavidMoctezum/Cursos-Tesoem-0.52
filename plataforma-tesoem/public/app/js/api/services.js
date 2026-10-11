/* Un objeto por cada área del backend.
   Si mañana cambia un endpoint, se corrige aquí y en ningún otro lado. */

window.AuthService = {
  login: (correo, contrasena) => Api.post("/login", { correo, contrasena }),
  logout: () => Api.post("/logout", {}),
  registrar: (datos) => Api.post("/usuarios", datos),
};

window.CursoService = {
  listar: (filtros) => Api.get("/cursos", filtros),
  obtener: (id) => Api.get("/cursos/" + id),
  crear: (curso) => Api.post("/cursos", curso),
  actualizar: (id, curso) => Api.put("/cursos/" + id, curso),
  eliminar: (id) => Api.del("/cursos/" + id),
  mios: () => Api.get("/cursos", { mios: true }),
  alumnosInscritos: (idCurso) => Api.get("/inscripciones", { idCurso }),
  porRevisar: () => Api.get("/cursos", { estado: "pendiente_revision" }),
  aprobar: (id) => Api.post("/cursos/" + id + "/aprobar", {}),
  rechazar: (id, motivo) => Api.post("/cursos/" + id + "/rechazar", { motivo }),
};

window.CategoriaService = {
  listar: () => Api.get("/categorias"),
  crear: (nombre) => Api.post("/categorias", { nombre }),
};

window.InscripcionService = {
  inscribir: (idCurso) => Api.post("/inscripcion", { idCurso }),
  mias: () => Api.get("/inscripciones"),
};

window.ProgresoService = {
  marcarLeccion: (idCurso, idLeccion) => Api.post("/progreso", { idCurso, idLeccion }),
};

window.ProyectoService = {
  listar: (filtros) => Api.get("/proyectos", filtros),
  publicar: (proyecto) => Api.post("/proyectos", proyecto),
  actualizar: (id, proyecto) => Api.put("/proyectos/" + id, proyecto),
};

window.PerfilService = {
  obtener: () => Api.get("/perfil"),
  actualizar: (datos) => Api.put("/perfil", datos),
};

window.MaterialService = {
  registrar: (material) => Api.post("/materiales", material),
};

window.CertificadoService = {
  mios: () => Api.get("/certificados"),
};

window.UsuarioService = {
  listar: (filtros) => Api.get("/usuarios", filtros),
  cambiarEstado: (id, activo) => Api.put("/usuarios/" + id, { activo }),
  validarProfesor: (id) => Api.put("/usuarios/" + id, { validado: true }),
};

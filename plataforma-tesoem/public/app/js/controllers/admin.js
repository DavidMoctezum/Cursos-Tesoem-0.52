(function () {
  if (!Sesion.exigir(["ADMINISTRADOR"])) return;

  const zonaUsuarios = $("#usuarios");
  const zonaCategorias = $("#lista-categorias");

  async function cargarUsuarios() {
    Vista.cargando(zonaUsuarios, "Consultando usuarios…");
    try {
      const r = await UsuarioService.listar({
        rol: $("#rol").value,
        busqueda: $("#q").value.trim(),
      });
      if (!r.usuarios.length) {
        Vista.vacio(zonaUsuarios, "Sin coincidencias", "Ajusta el filtro o la búsqueda.");
        return;
      }
      zonaUsuarios.innerHTML =
        '<div class="tabla-envoltura"><table class="tabla"><thead><tr>' +
        "<th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Alta</th><th></th>" +
        "</tr></thead><tbody>" +
        r.usuarios
          .map((u) => {
            const estado = !u.activo
              ? '<span class="etiqueta etiqueta-roja">Desactivada</span>'
              : !u.validado
              ? '<span class="etiqueta etiqueta-amber">Por validar</span>'
              : '<span class="etiqueta">Activa</span>';
            const acciones =
              (!u.validado
                ? '<button class="btn btn-sm btn-primario" data-validar="' + u.id + '">Validar</button> '
                : "") +
              (u.activo
                ? '<button class="btn btn-sm btn-peligro" data-estado="' + u.id + '" data-valor="false">Desactivar</button>'
                : '<button class="btn btn-sm btn-linea" data-estado="' + u.id + '" data-valor="true">Reactivar</button>');
            return (
              "<tr><td>" + esc(u.nombre) + "</td><td>" + esc(u.correo) + "</td><td>" +
              esc(Formato.rol(u.rol)) + "</td><td>" + estado + "</td><td>" +
              Formato.fecha(u.registro) + '</td><td class="fila">' + acciones + "</td></tr>"
            );
          })
          .join("") +
        "</tbody></table></div>";

      $$("[data-estado]").forEach((b) =>
        b.addEventListener("click", async () => {
          b.disabled = true;
          try {
            await UsuarioService.cambiarEstado(b.dataset.estado, b.dataset.valor === "true");
            Toast.ok(b.dataset.valor === "true" ? "Cuenta reactivada." : "Cuenta desactivada.");
            cargarUsuarios();
          } catch (e) {
            Toast.error(e.message);
            b.disabled = false;
          }
        })
      );

      $$("[data-validar]").forEach((b) =>
        b.addEventListener("click", async () => {
          b.disabled = true;
          try {
            await UsuarioService.validarProfesor(b.dataset.validar);
            Toast.ok("Cuenta validada.");
            cargarUsuarios();
          } catch (e) {
            Toast.error(e.message);
            b.disabled = false;
          }
        })
      );
    } catch (e) {
      Vista.error(zonaUsuarios, e.message);
    }
  }

  async function cargarCategorias() {
    try {
      const r = await CategoriaService.listar();
      zonaCategorias.innerHTML = r.categorias
        .map((c) => '<span class="etiqueta">' + esc(c.nombre) + "</span>")
        .join("");
    } catch (e) {
      Vista.error(zonaCategorias, e.message);
    }
  }

  $("#form-filtro").addEventListener("submit", (ev) => {
    ev.preventDefault();
    cargarUsuarios();
  });

  $("#form-categoria").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const nombre = $("#nombre-categoria").value.trim();
    if (!nombre) { Toast.error("Escribe el nombre de la categoría."); return; }
    try {
      await CategoriaService.crear(nombre);
      $("#nombre-categoria").value = "";
      Toast.ok("Categoría agregada.");
      cargarCategorias();
    } catch (e) {
      Toast.error(e.message);
    }
  });

  /* ---- Cursos enviados a revision por los profesores ---- */
  const zonaRevision = $("#cursos-revision");

  async function cargarRevision() {
    Vista.cargando(zonaRevision, "Consultando cursos por revisar…");
    try {
      const r = await CursoService.porRevisar();
      if (!r.cursos.length) {
        Vista.vacio(zonaRevision, "Nada por revisar", "Cuando un profesor envíe un curso aparecerá aquí.");
        return;
      }
      zonaRevision.innerHTML =
        '<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Curso</th><th>Profesor</th><th>Categoría</th><th></th></tr></thead><tbody>' +
        r.cursos
          .map(
            (c) =>
              "<tr><td>" + esc(c.titulo) + "</td><td>" + esc(c.profesor) + "</td><td>" + esc(c.categoria) + "</td>" +
              '<td class="fila"><a class="btn btn-sm btn-linea" href="' + ruta("/pages/curso.html") + "?id=" + encodeURIComponent(c.id) + '">Ver</a>' +
              '<button class="btn btn-sm btn-primario" data-aprobar="' + esc(c.id) + '">Aprobar</button>' +
              '<button class="btn btn-sm btn-peligro" data-rechazar="' + esc(c.id) + '">Rechazar</button></td></tr>'
          )
          .join("") +
        "</tbody></table></div>";

      $$("[data-aprobar]").forEach((b) =>
        b.addEventListener("click", async () => {
          b.disabled = true;
          try {
            await CursoService.aprobar(b.dataset.aprobar);
            Toast.ok("Curso publicado.");
            cargarRevision();
          } catch (e) {
            Toast.error(e.message);
            b.disabled = false;
          }
        })
      );

      $$("[data-rechazar]").forEach((b) =>
        b.addEventListener("click", async () => {
          const motivo = prompt("Motivo del rechazo (el profesor lo verá):");
          if (!motivo || !motivo.trim()) return;
          b.disabled = true;
          try {
            await CursoService.rechazar(b.dataset.rechazar, motivo.trim());
            Toast.ok("Curso rechazado.");
            cargarRevision();
          } catch (e) {
            Toast.error(e.message);
            b.disabled = false;
          }
        })
      );
    } catch (e) {
      Vista.error(zonaRevision, e.message);
    }
  }

  cargarUsuarios();
  cargarRevision();
  cargarCategorias();
})();

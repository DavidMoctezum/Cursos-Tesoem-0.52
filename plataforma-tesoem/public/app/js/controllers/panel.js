(function () {
  if (!Sesion.exigir()) return;

  const s = Sesion.actual();
  const panel = $("#panel");
  const rail = $("#rail");

  const RAIL = {
    ALUMNO: [
      { t: "Mis cursos", u: "/pages/panel.html" },
      { t: "Mis proyectos", u: "/pages/proyectos.html?mios=1" },
      { t: "Mi perfil", u: "/pages/perfil.html" },
      { t: "Buscar cursos", u: "/pages/cursos.html" },
    ],
    PROFESOR: [
      { t: "Mis cursos", u: "/pages/panel.html" },
      { t: "Nuevo curso", u: "/pages/curso-editor.html" },
      { t: "Mi perfil", u: "/pages/perfil.html" },
    ],
    EMPRESA: [
      { t: "Resumen", u: "/pages/panel.html" },
      { t: "Capacitaciones", u: "/pages/cursos.html" },
      { t: "Proyectos", u: "/pages/proyectos.html" },
      { t: "Mi perfil", u: "/pages/perfil.html" },
    ],
    ADMINISTRADOR: [
      { t: "Resumen", u: "/pages/panel.html" },
      { t: "Usuarios", u: "/pages/admin.html" },
      { t: "Categorías", u: "/pages/admin.html#categorias" },
      { t: "Mi perfil", u: "/pages/perfil.html" },
    ],
  };

  rail.innerHTML =
    "<h4>" + esc(Formato.rol(s.rol)) + "</h4>" +
    RAIL[s.rol]
      .map((x, i) => '<a href="' + ruta(x.u) + '"' + (i === 0 ? ' aria-current="page"' : "") + ">" + esc(x.t) + "</a>")
      .join("");

  const encabezado = (titulo, sub) =>
    '<div class="cabecera-pagina"><h1>' + esc(titulo) + "</h1>" +
    (sub ? '<p class="silencio" style="margin:0">' + esc(sub) + "</p>" : "") + "</div>";

  /* ---------------- ALUMNO ---------------- */
  async function panelAlumno() {
    panel.innerHTML =
      encabezado("Hola, " + s.nombre.split(" ")[0], "Retoma donde te quedaste.") +
      '<div id="cursos"><p class="cargando">Cargando tus cursos…</p></div>' +
      '<h2 style="margin-top:var(--s7)">Certificados</h2><div id="certs"></div>';

    try {
      const r = await InscripcionService.mias();
      const zona = $("#cursos");
      if (!r.inscripciones.length) {
        Vista.vacio(zona, "Todavía no tienes cursos", "Explora el catálogo y elige tu primera capacitación.",
          '<a class="btn btn-accion" style="margin-top:var(--s4)" href="' + ruta("/pages/cursos.html") + '">Ver catálogo</a>');
      } else {
        zona.innerHTML =
          '<div class="rejilla">' +
          r.inscripciones
            .map(
              (i) =>
                '<div class="tarjeta">' +
                '<span class="etiqueta">' + esc(i.curso.categoria) + "</span>" +
                "<h3 style='margin:var(--s2) 0'>" + esc(i.curso.titulo) + "</h3>" +
                BarraProgreso(i.completadas, i.total) +
                '<a class="btn btn-linea btn-sm" style="margin-top:var(--s3)" href="' +
                ruta("/pages/curso.html") + "?id=" + i.curso.id + '">' +
                (i.porcentaje === 100 ? "Repasar" : "Continuar") + "</a></div>"
            )
            .join("") +
          "</div>";
      }
    } catch (e) {
      Vista.error($("#cursos"), e.message);
    }

    try {
      const c = await CertificadoService.mios();
      const zona = $("#certs");
      if (!c.certificados.length) {
        Vista.vacio(zona, "Aún sin certificados", "Termina el 100% de un curso y el certificado aparece aquí.");
      } else {
        zona.innerHTML =
          '<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Curso</th><th>Folio</th><th>Fecha</th><th></th></tr></thead><tbody>' +
          c.certificados
            .map(
              (x) =>
                "<tr><td>" + esc(x.curso) + "</td><td>" + esc(x.folio) + "</td><td>" +
                Formato.fecha(x.fecha) + '</td><td><button class="btn btn-sm btn-linea">Descargar</button></td></tr>'
            )
            .join("") +
          "</tbody></table></div>";
      }
    } catch (e) {
      Vista.error($("#certs"), e.message);
    }
  }

  /* ---------------- PROFESOR ---------------- */
  async function panelProfesor() {
    panel.innerHTML =
      encabezado("Tus cursos", "Administra el contenido y revisa quién está inscrito.") +
      '<a class="btn btn-accion" href="' + ruta("/pages/curso-editor.html") + '">Crear curso</a>' +
      '<div id="cursos" style="margin-top:var(--s5)"><p class="cargando">Cargando…</p></div>' +
      '<div id="detalle-alumnos" style="margin-top:var(--s7)"></div>';

    try {
      const r = await CursoService.mios();
      const zona = $("#cursos");
      if (!r.cursos.length) {
        Vista.vacio(zona, "Aún no publicas cursos", "Crea el primero y agrégale lecciones y material.");
        return;
      }
      zona.innerHTML =
        '<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Curso</th><th>Categoría</th><th>Estado</th><th>Inscritos</th><th></th></tr></thead><tbody>' +
        r.cursos
          .map(
            (c) =>
              "<tr><td>" + esc(c.titulo) + "</td><td>" + esc(c.categoria) + "</td><td>" +
              ('<span class="etiqueta' +
                ({ publicado: "", pendiente_revision: " etiqueta-amber", rechazado: " etiqueta-roja" }[c.estado] || " etiqueta-gris") +
                '">' +
                ({ publicado: "Publicado", pendiente_revision: "En revisión", rechazado: "Rechazado", archivado: "Archivado" }[c.estado] || "Borrador") +
                "</span>") +
              "</td><td>" + esc(c.inscritos) + "</td>" +
              '<td class="fila"><a class="btn btn-sm btn-linea" href="' + ruta("/pages/curso-editor.html") +
              "?id=" + c.id + '">Editar</a>' +
              '<button class="btn btn-sm btn-linea" data-alumnos="' + c.id + '" data-titulo="' + esc(c.titulo) + '">Alumnos</button></td></tr>'
          )
          .join("") +
        "</tbody></table></div>";

      $$("[data-alumnos]").forEach((b) =>
        b.addEventListener("click", () => verAlumnos(b.dataset.alumnos, b.dataset.titulo))
      );
    } catch (e) {
      Vista.error($("#cursos"), e.message);
    }
  }

  async function verAlumnos(idCurso, titulo) {
    const zona = $("#detalle-alumnos");
    Vista.cargando(zona, "Consultando alumnos inscritos…");
    try {
      const r = await CursoService.alumnosInscritos(idCurso);
      if (!r.alumnos.length) {
        zona.innerHTML =
          "<h2>Inscritos en “" + esc(titulo) + "”</h2>" +
          '<div class="vacio"><h3>Sin alumnos inscritos</h3>' +
          "<p>En cuanto alguien se inscriba aparecerá en esta lista.</p></div>";
        return;
      }
      zona.innerHTML =
        "<h2>Inscritos en “" + esc(titulo) + "”</h2>" +
        '<div class="tabla-envoltura"><table class="tabla"><thead><tr><th>Alumno</th><th>Correo</th><th>Inscripción</th><th>Avance</th></tr></thead><tbody>' +
        r.alumnos
          .map(
            (a) =>
              "<tr><td>" + esc(a.nombre) + "</td><td>" + esc(a.correo) + "</td><td>" +
              Formato.fecha(a.fecha) + "</td><td>" + a.completadas + "/" + a.total + "</td></tr>"
          )
          .join("") +
        "</tbody></table></div>";
      zona.scrollIntoView({ behavior: "smooth", block: "start" });
    } catch (e) {
      Vista.error(zona, e.message);
    }
  }

  /* ---------------- EMPRESA ---------------- */
  async function panelEmpresa() {
    panel.innerHTML =
      encabezado("Capacitación para tu equipo", "Busca por área y revisa el trabajo de los alumnos.") +
      '<div class="campo" style="max-width:320px"><label for="cat">Área de especialidad</label><select id="cat"></select></div>' +
      '<div id="cursos" class="rejilla"></div>' +
      '<h2 style="margin-top:var(--s7)">Proyectos recientes de alumnos</h2><div id="proys" class="rejilla"></div>';

    try {
      const c = await CategoriaService.listar();
      $("#cat").innerHTML =
        '<option value="">Todas las áreas</option>' +
        c.categorias.map((x) => '<option value="' + x.id + '">' + esc(x.nombre) + "</option>").join("");
      $("#cat").addEventListener("change", () => cargarCursosEmpresa($("#cat").value));
    } catch (e) { /* el selector queda vacío */ }

    cargarCursosEmpresa("");

    try {
      const p = await ProyectoService.listar();
      $("#proys").innerHTML = p.proyectos
        .slice(0, 6)
        .map(
          (pr) =>
            '<div class="tarjeta"><h3 style="font-size:var(--t-md)">' + esc(pr.titulo) + "</h3>" +
            '<p class="silencio" style="margin:0 0 var(--s2)">' + esc(pr.alumno) + " · " + esc(pr.carrera || "") + "</p>" +
            '<p class="resumen" style="font-family:var(--font-read)">' + esc(pr.descripcion) + "</p>" +
            '<span class="etiqueta etiqueta-gris">' + esc(pr.tecnologias) + "</span></div>"
        )
        .join("");
    } catch (e) {
      Vista.error($("#proys"), e.message);
    }
  }

  async function cargarCursosEmpresa(categoria) {
    const zona = $("#cursos");
    Vista.cargando(zona, "Buscando capacitaciones…");
    try {
      const r = await CursoService.listar({ categoria });
      if (!r.cursos.length) {
        Vista.vacio(zona, "Sin capacitaciones en esta área", "Prueba con otra área de especialidad.");
        return;
      }
      zona.innerHTML = r.cursos.map(TarjetaCurso).join("");
    } catch (e) {
      Vista.error(zona, e.message);
    }
  }

  /* ---------------- ADMINISTRADOR ---------------- */
  async function panelAdmin() {
    panel.innerHTML =
      encabezado("Resumen de la plataforma", "Lo que necesita tu atención hoy.") +
      '<div id="resumen" class="rejilla"></div>';
    try {
      const [u, c, p] = await Promise.all([
        UsuarioService.listar(),
        CursoService.listar(),
        ProyectoService.listar(),
      ]);
      const porValidar = u.usuarios.filter((x) => !x.validado).length;
      const inactivos = u.usuarios.filter((x) => !x.activo).length;
      const tarjeta = (titulo, dato, pie, url) =>
        '<div class="tarjeta"><p class="silencio" style="margin:0">' + esc(titulo) + "</p>" +
        '<p style="font-size:var(--t-2xl);font-weight:700;margin:var(--s1) 0 0">' + dato + "</p>" +
        '<p class="silencio" style="margin:0">' + esc(pie) + "</p>" +
        (url ? '<a class="btn btn-sm btn-linea" style="margin-top:var(--s3)" href="' + ruta(url) + '">Revisar</a>' : "") +
        "</div>";
      $("#resumen").innerHTML =
        tarjeta("Cuentas por validar", porValidar, "esperan revisión", "/pages/admin.html") +
        tarjeta("Cuentas desactivadas", inactivos, "sin acceso a la plataforma", "/pages/admin.html") +
        tarjeta("Cursos publicados", c.cursos.length, "visibles en el catálogo", "/pages/cursos.html") +
        tarjeta("Proyectos publicados", p.proyectos.length, "de alumnos", "/pages/proyectos.html");
    } catch (e) {
      Vista.error($("#resumen"), e.message);
    }
  }

  ({
    ALUMNO: panelAlumno,
    PROFESOR: panelProfesor,
    EMPRESA: panelEmpresa,
    ADMINISTRADOR: panelAdmin,
  })[s.rol]();
})();

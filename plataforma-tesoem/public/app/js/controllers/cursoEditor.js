(function () {
  if (!Sesion.exigir(["PROFESOR", "ADMINISTRADOR"])) return;

  const id = new URLSearchParams(location.search).get("id");
  const form = $("#form-curso");
  const aviso = $("#aviso");
  const zonaLecciones = $("#lecciones");
  let lecciones = [];

  function pintarLecciones() {
    if (!lecciones.length) lecciones = [{ titulo: "" }];
    zonaLecciones.innerHTML = lecciones
      .map(
        (l, i) =>
          '<div class="fila-leccion"><input type="text" value="' + esc(l.titulo) +
          '" placeholder="Lección ' + (i + 1) + '" data-i="' + i + '">' +
          '<button type="button" class="btn btn-sm btn-linea" data-quitar="' + i + '" aria-label="Quitar lección">Quitar</button></div>'
      )
      .join("");

    $$("[data-i]", zonaLecciones).forEach((inp) =>
      inp.addEventListener("input", () => { lecciones[inp.dataset.i].titulo = inp.value; })
    );
    $$("[data-quitar]", zonaLecciones).forEach((b) =>
      b.addEventListener("click", () => {
        lecciones.splice(Number(b.dataset.quitar), 1);
        pintarLecciones();
      })
    );
  }

  $("#btn-agregar").addEventListener("click", () => {
    lecciones.push({ titulo: "" });
    pintarLecciones();
    const inputs = $$("[data-i]", zonaLecciones);
    inputs[inputs.length - 1].focus();
  });

  async function cargarCategorias(seleccion) {
    const r = await CategoriaService.listar();
    $("#idCategoria").innerHTML =
      '<option value="">Elige una categoría</option>' +
      r.categorias
        .map((c) => '<option value="' + c.id + '"' + (String(c.id) === String(seleccion) ? " selected" : "") + ">" + esc(c.nombre) + "</option>")
        .join("");
  }

  async function cargarCurso() {
    const r = await CursoService.obtener(id);
    const c = r.curso;
    document.title = "Editar: " + c.titulo + " · TESOEM";
    $("#titulo-pagina").textContent = "Editar curso";
    $("#titulo").value = c.titulo;
    $("#resumen").value = c.resumen || "";
    $("#descripcion").value = c.descripcion || "";
    $("#nivel").value = c.nivel || "Básico";
    $("#publicado").value = String(!!c.publicado);
    lecciones = (c.lecciones || []).map((l) => ({ id: l.id, titulo: l.titulo }));
    pintarLecciones();
    await cargarCategorias(c.idCategoria);
    $("#btn-retirar").classList.remove("oculto");
    if (c.estado === "rechazado" && c.motivoRechazo) {
      aviso.innerHTML = '<div class="aviso aviso-error">La coordinación rechazó este curso: ' + esc(c.motivoRechazo) + "</div>";
    }
    pintarMateriales(c.materiales || []);
  }

  function pintarMateriales(materiales) {
    const zona = $("#lista-materiales");
    if (!materiales.length) {
      zona.innerHTML = '<p class="silencio">Este curso aún no tiene material de apoyo.</p>';
      return;
    }
    zona.innerHTML = materiales
      .map((m) => '<div class="leccion"><span class="titulo">' + esc(m.titulo) +
        '</span><span class="etiqueta etiqueta-gris">' + esc(m.tipo) + "</span></div>")
      .join("");
  }

  const reglas = {
    titulo: (v) => (Validacion.requerido(v) ? true : "El curso necesita un título."),
    resumen: (v) => (Validacion.requerido(v) ? true : "Escribe un resumen corto."),
    descripcion: (v) => (Validacion.requerido(v) ? true : "Describe de qué trata el curso."),
    idCategoria: (v) => (Validacion.requerido(v) ? true : "Elige una categoría."),
  };

  form.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    aviso.innerHTML = "";
    if (!Validacion.aplicar(form, reglas)) return;

    const limpias = lecciones.filter((l) => l.titulo.trim());
    if (!limpias.length) {
      aviso.innerHTML = '<div class="aviso aviso-error">Agrega al menos una lección antes de guardar.</div>';
      return;
    }

    const datos = {
      titulo: $("#titulo").value.trim(),
      resumen: $("#resumen").value.trim(),
      descripcion: $("#descripcion").value.trim(),
      idCategoria: $("#idCategoria").value,
      nivel: $("#nivel").value,
      publicado: $("#publicado").value === "true",
      lecciones: limpias,
    };

    const boton = $("#btn-guardar");
    boton.disabled = true;
    boton.textContent = "Guardando…";
    try {
      if (id) {
        await CursoService.actualizar(id, datos);
        Toast.ok("Cambios guardados.");
      } else {
        const r = await CursoService.crear(datos);
        Toast.ok("Curso creado.");
        location.href = ruta("/pages/curso-editor.html") + "?id=" + r.id;
        return;
      }
    } catch (e) {
      aviso.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
    } finally {
      boton.disabled = false;
      boton.textContent = "Guardar curso";
    }
  });

  $("#btn-retirar").addEventListener("click", async () => {
    if (!confirm("El curso dejará de aparecer en el catálogo. ¿Continuar?")) return;
    try {
      await CursoService.eliminar(id);
      Toast.ok("Curso retirado del catálogo.");
      $("#publicado").value = "false";
    } catch (e) {
      Toast.error(e.message);
    }
  });

  $("#form-material").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const titulo = $("#mat-titulo").value.trim();
    if (!titulo) { Toast.error("El material necesita un título."); return; }
    try {
      await MaterialService.registrar({
        idCurso: id, titulo, tipo: $("#mat-tipo").value, url: $("#mat-url").value.trim(),
      });
      Toast.ok("Material agregado.");
      $("#mat-titulo").value = "";
      $("#mat-url").value = "";
      const r = await CursoService.obtener(id);
      pintarMateriales(r.curso.materiales || []);
    } catch (e) {
      Toast.error(e.message);
    }
  });

  (async function iniciar() {
    try {
      if (id) await cargarCurso();
      else {
        await cargarCategorias();
        pintarLecciones();
      }
    } catch (e) {
      aviso.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
    }
  })();
})();

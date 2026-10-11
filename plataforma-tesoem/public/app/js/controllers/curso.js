(function () {
  const filtros = $("#filtros");
  const lista = $("#lista-cursos");
  let categoriaActiva = "";

  function tarjeta(c) {
    return (
      '<a class="tarjeta tarjeta-curso" href="' + ruta("/pages/curso.html") + "?id=" + encodeURIComponent(c.id) + '">' +
      '<span class="etiqueta">' + esc(c.duracion) + "</span>" +
      '<h3 style="margin:var(--s2) 0">' + esc(c.titulo) + "</h3>" +
      '<p class="silencio" style="margin:0">' + esc(c.resumen) + "</p>" +
      '<p class="silencio" style="margin:var(--s2) 0 0;font-size:var(--t-xs)">' + esc(c.categoria) + " · " + esc(c.nivel) + "</p>" +
      "</a>"
    );
  }

  async function cargar() {
    Vista.cargando(lista, "Cargando cursos…");
    try {
      const r = await CursoService.listar(categoriaActiva ? { categoria: categoriaActiva } : {});
      if (!r.cursos.length) {
        Vista.vacio(lista, "No hay cursos todavía", "Vuelve pronto, estamos preparando nuevas capacitaciones.");
        return;
      }
      lista.innerHTML = '<div class="rejilla">' + r.cursos.map(tarjeta).join("") + "</div>";
    } catch (e) {
      Vista.error(lista, e.message);
    }
  }

  async function cargarFiltros() {
    try {
      const r = await CategoriaService.listar();
      r.categorias.forEach((cat) => {
        const b = document.createElement("button");
        b.className = "pastilla-filtro";
        b.dataset.categoria = cat.id;
        b.textContent = cat.nombre;
        filtros.appendChild(b);
      });
    } catch (e) {
      // Si fallan las categorias, el catalogo sigue usable sin filtro.
    }

    filtros.addEventListener("click", (ev) => {
      const b = ev.target.closest("[data-categoria]");
      if (!b) return;
      $$(".pastilla-filtro", filtros).forEach((x) => x.classList.remove("activo"));
      b.classList.add("activo");
      categoriaActiva = b.dataset.categoria;
      cargar();
    });
  }

  cargarFiltros();
  cargar();
})();
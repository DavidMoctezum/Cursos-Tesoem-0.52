(function () {
  const params = new URLSearchParams(location.search);
  const soloMios = params.get("mios") === "1";
  const lista = $("#lista");
  const editor = $("#editor-proyecto");
  const form = $("#form-proyecto");
  let busqueda = "";

  if (soloMios) {
    if (!Sesion.exigir(["ALUMNO"])) return;
    $("#titulo-pagina").textContent = "Mis proyectos";
  }

  if (Sesion.rol() === "ALUMNO") $("#btn-nuevo").classList.remove("oculto");

  function tarjeta(pr) {
    return (
      '<article class="tarjeta">' +
      '<h3 style="font-size:var(--t-md);margin-bottom:var(--s1)">' + esc(pr.titulo) + "</h3>" +
      '<p class="silencio" style="margin:0 0 var(--s3)">' + esc(pr.alumno) +
      (pr.carrera ? " · " + esc(pr.carrera) : "") + " · " + Formato.fecha(pr.fecha) + "</p>" +
      '<p class="lectura" style="font-size:var(--t-sm);margin-bottom:var(--s3)">' + esc(pr.descripcion) + "</p>" +
      '<span class="etiqueta etiqueta-gris">' + esc(pr.tecnologias) + "</span>" +
      (pr.enlace && pr.enlace !== "#"
        ? ' <a class="etiqueta" href="' + esc(pr.enlace) + '" target="_blank" rel="noopener">Ver proyecto</a>'
        : "") +
      "</article>"
    );
  }

  async function cargar() {
    Vista.cargando(lista, "Cargando proyectos…");
    try {
      const r = await ProyectoService.listar({ mios: soloMios ? true : undefined, busqueda });
      if (!r.proyectos.length) {
        Vista.vacio(
          lista,
          soloMios ? "Todavía no publicas nada" : "Sin proyectos por ahora",
          soloMios
            ? "Publica lo que construiste en tus cursos para que las empresas lo vean."
            : "En cuanto los alumnos publiquen su trabajo aparecerá aquí."
        );
        return;
      }
      lista.innerHTML = r.proyectos.map(tarjeta).join("");
    } catch (e) {
      Vista.error(lista, e.message);
    }
  }

  $("#form-buscar").addEventListener("submit", (ev) => {
    ev.preventDefault();
    busqueda = $("#q").value.trim();
    cargar();
  });

  const btnNuevo = $("#btn-nuevo");
  btnNuevo.addEventListener("click", () => {
    editor.classList.remove("oculto");
    $("#titulo").focus();
  });
  $("#btn-cancelar").addEventListener("click", () => {
    editor.classList.add("oculto");
    Validacion.limpiar(form);
    form.reset();
  });

  const reglas = {
    titulo: (v) => (Validacion.requerido(v) ? true : "Tu proyecto necesita un título."),
    descripcion: (v) => (Validacion.requerido(v) ? true : "Cuenta qué problema resuelve."),
    tecnologias: (v) => (Validacion.requerido(v) ? true : "Anota al menos una tecnología."),
  };

  form.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    $("#aviso").innerHTML = "";
    if (!Validacion.aplicar(form, reglas)) return;

    const boton = $("#btn-publicar");
    boton.disabled = true;
    boton.textContent = "Publicando…";
    try {
      await ProyectoService.publicar({
        titulo: $("#titulo").value.trim(),
        descripcion: $("#descripcion").value.trim(),
        tecnologias: $("#tecnologias").value.trim(),
        enlace: $("#enlace").value.trim(),
      });
      Toast.ok("Proyecto publicado.");
      form.reset();
      editor.classList.add("oculto");
      cargar();
    } catch (e) {
      $("#aviso").innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
    } finally {
      boton.disabled = false;
      boton.textContent = "Publicar";
    }
  });

  cargar();
})();

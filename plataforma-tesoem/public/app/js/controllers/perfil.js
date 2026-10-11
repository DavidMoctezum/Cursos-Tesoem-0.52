(function () {
  if (!Sesion.exigir()) return;

  const form = $("#form-perfil");
  const aviso = $("#aviso");

  /* Campos editables por rol. El backend decide qué devuelve GET /perfil. */
  const CAMPOS = {
    ALUMNO: [
      { id: "nombre", etiqueta: "Nombre completo" },
      { id: "carrera", etiqueta: "Carrera" },
    ],
    PROFESOR: [
      { id: "nombre", etiqueta: "Nombre completo" },
      { id: "especialidad", etiqueta: "Área de especialidad" },
    ],
    EMPRESA: [
      { id: "nombre", etiqueta: "Razón social" },
      { id: "giro", etiqueta: "Giro" },
    ],
    ADMINISTRADOR: [{ id: "nombre", etiqueta: "Nombre" }],
  };

  function campo(c, valor) {
    return (
      '<div class="campo"><label for="' + c.id + '">' + esc(c.etiqueta) + "</label>" +
      '<input type="' + (c.tipo || "text") + '" id="' + c.id + '" value="' + esc(valor || "") + '">' +
      '<span class="error-campo"></span></div>'
    );
  }

  async function cargar() {
    try {
      const r = await PerfilService.obtener();
      const p = r.perfil;
      $("#sub-perfil").textContent =
        Formato.rol(p.rol) + " · cuenta creada el " + Formato.fecha(p.registro);

      form.innerHTML =
        CAMPOS[p.rol].map((c) => campo(c, p[c.id])).join("") +
        '<div class="campo"><label for="correo">Correo</label>' +
        '<input type="email" id="correo" value="' + esc(p.correo) + '" disabled>' +
        '<span class="ayuda">El correo identifica tu cuenta y no se puede cambiar aquí.</span></div>' +
        (p.validado === false
          ? '<div class="aviso aviso-error">Tu cuenta está en revisión. La coordinación la validará para habilitar todas las funciones de tu rol.</div>'
          : "") +
        '<button class="btn btn-primario" type="submit" id="btn-guardar">Guardar cambios</button>';

      form.addEventListener("submit", (ev) => guardar(ev, p.rol));
    } catch (e) {
      form.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
    }
  }

  async function guardar(ev, rol) {
    ev.preventDefault();
    aviso.innerHTML = "";
    const reglas = { nombre: (v) => (Validacion.requerido(v) ? true : "Este dato es obligatorio.") };
    if (!Validacion.aplicar(form, reglas)) return;

    const datos = {};
    CAMPOS[rol].forEach((c) => { datos[c.id] = $("#" + c.id).value.trim(); });

    const boton = $("#btn-guardar");
    boton.disabled = true;
    boton.textContent = "Guardando…";
    try {
      await PerfilService.actualizar(datos);
      const s = Sesion.actual();
      s.nombre = datos.nombre;
      Sesion.guardar(s);
      Navbar.pintar();
      Toast.ok("Perfil actualizado.");
    } catch (e) {
      aviso.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
    } finally {
      boton.disabled = false;
      boton.textContent = "Guardar cambios";
    }
  }

  cargar();
})();

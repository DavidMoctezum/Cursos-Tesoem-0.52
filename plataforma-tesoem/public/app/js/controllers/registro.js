(function () {
  const form = $("#form-registro");
  const boton = $("#btn-crear");
  const aviso = $("#aviso");
  const rol = $("#rol");

  const AYUDA = {
    ALUMNO: "Podrás inscribirte a cursos y publicar tus proyectos.",
    PROFESOR: "Podrás crear cursos y subir material una vez que la coordinación valide tu cuenta.",
    EMPRESA: "Podrás buscar capacitaciones y revisar proyectos de los alumnos una vez que la coordinación valide tu cuenta.",
  };
  const ETIQUETA = {
    ALUMNO: "Nombre completo",
    PROFESOR: "Nombre completo",
    EMPRESA: "Razón social",
  };

  function ajustarFormulario() {
    const r = rol.value;
    $("#ayuda-rol").textContent = AYUDA[r];
    $("#etiqueta-nombre").textContent = ETIQUETA[r];
    $("#extra-alumno").classList.toggle("oculto", r !== "ALUMNO");
    $("#extra-profesor").classList.toggle("oculto", r !== "PROFESOR");
    $("#extra-empresa").classList.toggle("oculto", r !== "EMPRESA");
  }

  const reglas = {
    nombre: (v) => (Validacion.requerido(v) ? true : "Este dato es obligatorio."),
    correo: (v) => (Validacion.correo(v) ? true : "Escribe un correo válido."),
    contrasena: (v) =>
      Validacion.contrasena(v) ? true : "Usa al menos 12 caracteres. Puede ser una frase larga.",
    confirmar: (v) => (v === $("#contrasena").value ? true : "Las contraseñas no coinciden."),
  };

  rol.addEventListener("change", ajustarFormulario);
  ajustarFormulario();

  form.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    aviso.innerHTML = "";
    if (!Validacion.aplicar(form, reglas)) return;

    const datos = {
      rol: rol.value,
      nombre: $("#nombre").value.trim(),
      correo: $("#correo").value.trim().toLowerCase(),
      contrasena: $("#contrasena").value,
    };
    if (rol.value === "ALUMNO") {
      datos.carrera = $("#carrera").value.trim();
    }
    if (rol.value === "PROFESOR") datos.especialidad = $("#especialidad").value.trim();
    if (rol.value === "EMPRESA") {
      datos.giro = $("#giro").value.trim();
    }

    boton.disabled = true;
    boton.textContent = "Creando cuenta…";
        try {
      const r = await AuthService.registrar(datos);
      form.reset();
      aviso.innerHTML =
        '<div class="aviso aviso-exito">' +
        esc(r.mensaje || "Cuenta creada. Revisa tu correo para confirmarla antes de iniciar sesión.") +
        "</div>";
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      aviso.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
      window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      boton.disabled = false;
      boton.textContent = "Crear cuenta";
    }
  });
})();

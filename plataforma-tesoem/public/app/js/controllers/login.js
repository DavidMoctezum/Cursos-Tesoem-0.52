(function () {
  const form = $("#form-login");
  const boton = $("#btn-entrar");
  const aviso = $("#aviso");

  const reglas = {
    correo: (v) => (Validacion.correo(v) ? true : "Escribe un correo válido."),
    contrasena: (v) => (Validacion.requerido(v) ? true : "Escribe tu contraseña."),
  };

  form.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    aviso.innerHTML = "";
    if (!Validacion.aplicar(form, reglas)) return;

    boton.disabled = true;
    boton.textContent = "Entrando…";
    try {
      const r = await AuthService.login($("#correo").value.trim(), $("#contrasena").value);
      Sesion.guardar({ id: r.id, rol: r.rol, nombre: r.nombre, correo: $("#correo").value.trim() });
      const volver = new URLSearchParams(location.search).get("volver");
      location.href = volver ? decodeURIComponent(volver) : Sesion.inicioDeRol(r.rol);
    } catch (e) {
      aviso.innerHTML = '<div class="aviso aviso-error">' + esc(e.message) + "</div>";
      $("#contrasena").value = "";
      $("#contrasena").focus();
    } finally {
      boton.disabled = false;
      boton.textContent = "Entrar";
    }
  });
})();

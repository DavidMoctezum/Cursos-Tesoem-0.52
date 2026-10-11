/* Validación en el navegador para dar respuesta inmediata.
   No sustituye a la del backend: ahí se valida siempre otra vez. */

window.Validacion = {
  correo(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((v || "").trim());
  },

  contrasena(v) {
    return typeof v === "string" && v.length >= 12;
  },

  requerido(v) {
    return String(v || "").trim().length > 0;
  },

  /* reglas: { idDelCampo: fn(valor) -> true | "mensaje de error" } */
  aplicar(form, reglas) {
    let valido = true;
    Object.entries(reglas).forEach(([id, fn]) => {
      const input = form.querySelector("#" + id);
      if (!input) return;
      const campo = input.closest(".campo");
      const resultado = fn(input.value, form);
      const ok = resultado === true;
      campo.classList.toggle("invalido", !ok);
      const zona = campo.querySelector(".error-campo");
      if (zona) zona.textContent = ok ? "" : resultado;
      if (!ok && valido) input.focus();
      if (!ok) valido = false;
    });
    return valido;
  },

  limpiar(form) {
    $$(".campo", form).forEach((c) => c.classList.remove("invalido"));
  },
};

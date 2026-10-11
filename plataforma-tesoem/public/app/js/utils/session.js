/* La sesión real vive en el servidor (HttpSession + cookie JSESSIONID).
   Aquí solo se guarda lo mínimo para decidir qué menú pintar.
   Esta protección es visual: la de verdad la hace el backend. */

window.Sesion = {
  CLAVE: "tesoem_sesion",

  guardar(datos) {
    sessionStorage.setItem(this.CLAVE, JSON.stringify(datos));
  },

  actual() {
    try {
      return JSON.parse(sessionStorage.getItem(this.CLAVE));
    } catch (e) {
      return null;
    }
  },

  rol() {
    const s = this.actual();
    return s ? s.rol : null;
  },

  activa() {
    return this.actual() !== null;
  },

  limpiar() {
    sessionStorage.removeItem(this.CLAVE);
  },

  /* Bloquea la vista si no hay sesión o si el rol no está permitido.
     Uso: Sesion.exigir(["PROFESOR"]) al inicio del controlador. */
  exigir(rolesPermitidos) {
    const s = this.actual();
    if (!s) {
      const destino = encodeURIComponent(location.pathname + location.search);
      location.replace(ruta(CONFIG.RUTA_LOGIN) + "?volver=" + destino);
      return false;
    }
    if (rolesPermitidos && rolesPermitidos.length && !rolesPermitidos.includes(s.rol)) {
      location.replace(ruta("/pages/panel.html"));
      return false;
    }
    return true;
  },

  /* A dónde mandar a cada rol después del login. */
  inicioDeRol(rol) {
    return ruta("/pages/panel.html");
  },
};

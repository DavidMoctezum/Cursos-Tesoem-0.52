/* Punto único de salida hacia el backend.
    Ninguna pantalla debe llamar a fetch directamente: todo pasa por aquí.
   Equivale a la regla del backend de no escribir SQL dentro de los Servlets. */

(function () {
  const ERR_RED = "No se pudo conectar con el servidor. Revisa tu conexión.";

  class ErrorApi extends Error {
    constructor(mensaje, estado, datos) {
      super(mensaje);
      this.estado = estado;
      this.datos = datos || null;
    }
  }

  function url(recurso, params) {
    let u = window.CONFIG.API_BASE + recurso;
    if (params) {
      const q = new URLSearchParams();
      Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== "") q.append(k, v);
      });
      const s = q.toString();
      if (s) u += "?" + s;
    }
    return u;
  }

  /* ---- CSRF de Laravel: la cookie XSRF-TOKEN se devuelve como cabecera X-XSRF-TOKEN ---- */
  function leerCookie(nombre) {
    const m = document.cookie.match(new RegExp("(?:^|; )" + nombre + "=([^;]*)"));
    return m ? decodeURIComponent(m[1]) : null;
  }

  async function asegurarCsrf(forzar) {
    if (!forzar && leerCookie("XSRF-TOKEN")) return;
    try {
      await fetch(window.CONFIG.API_BASE + "/csrf", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
    } catch (e) {
      /* si falla, la peticion principal reportara el error de red */
    }
  }

  async function peticion(metodo, recurso, { cuerpo, params, archivo } = {}) {
    const opciones = {
      method: metodo,
      credentials: "same-origin", // cookie de sesion de Laravel
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    };

    if (archivo instanceof FormData) {
      opciones.body = archivo; // el navegador pone el Content-Type con boundary
    } else if (cuerpo !== undefined) {
      opciones.headers["Content-Type"] = "application/json";
      opciones.body = JSON.stringify(cuerpo);
    }

    const escribe = metodo !== "GET";
    if (escribe) {
      await asegurarCsrf(false);
      opciones.headers["X-XSRF-TOKEN"] = leerCookie("XSRF-TOKEN") || "";
    }

    let respuesta;
    try {
      respuesta = await fetch(url(recurso, params), opciones);

      // Token vencido: se pide uno nuevo y se reintenta una sola vez.
      if (escribe && respuesta.status === 419) {
        await asegurarCsrf(true);
        opciones.headers["X-XSRF-TOKEN"] = leerCookie("XSRF-TOKEN") || "";
        respuesta = await fetch(url(recurso, params), opciones);
      }
    } catch (e) {
      throw new ErrorApi(ERR_RED, 0);
    }

    // Sesión caída o permisos: el backend manda 401/403.
    if (respuesta.status === 401) {
      Sesion.limpiar();
      const destino = encodeURIComponent(location.pathname + location.search);
      location.href = ruta(window.CONFIG.RUTA_LOGIN) + "?volver=" + destino;
      throw new ErrorApi("Tu sesión terminó. Inicia sesión otra vez.", 401);
    }

    let datos = null;
    const texto = await respuesta.text();
    if (texto) {
      try {
        datos = JSON.parse(texto);
      } catch (e) {
        throw new ErrorApi("El servidor respondió en un formato inesperado.", respuesta.status);
      }
    }

    // Contrato documentado: {"success": bool, "mensaje": "...", ...}
    if (!respuesta.ok || (datos && datos.success === false)) {
      const msg = (datos && datos.mensaje) || "Ocurrió un error al procesar la solicitud.";
      throw new ErrorApi(msg, respuesta.status, datos);
    }

    return datos;
  }

  window.ErrorApi = ErrorApi;
  window.Api = {
    get: (r, params) => peticion("GET", r, { params }),
    post: (r, cuerpo) => peticion("POST", r, { cuerpo }),
    put: (r, cuerpo) => peticion("PUT", r, { cuerpo }),
    del: (r) => peticion("DELETE", r),
    subir: (r, formData) => peticion("POST", r, { archivo: formData }),
  };
})();

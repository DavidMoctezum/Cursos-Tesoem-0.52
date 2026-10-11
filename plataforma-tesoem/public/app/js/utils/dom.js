window.$ = (sel, ctx) => (ctx || document).querySelector(sel);
window.$$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

/* Escapa texto que viene del servidor antes de meterlo al HTML. */
window.esc = function (valor) {
  if (valor === null || valor === undefined) return "";
  return String(valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
};

window.Formato = {
  fecha(iso) {
    if (!iso) return "—";
    const d = new Date(iso);
    if (isNaN(d)) return esc(iso);
    return d.toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
  },
  rol(r) {
    const m = { ALUMNO: "Alumno", PROFESOR: "Profesor", EMPRESA: "Empresa", ADMINISTRADOR: "Administrador" };
    return m[r] || r || "";
  },
  porcentaje(n) {
    return Math.round(Number(n) || 0) + "%";
  },
};

/* Bloques de estado para listados. */
window.Vista = {
  cargando(contenedor, texto) {
    contenedor.innerHTML = '<p class="cargando">' + esc(texto || "Cargando…") + "</p>";
  },
  vacio(contenedor, titulo, mensaje, accionHtml) {
    contenedor.innerHTML =
      '<div class="vacio"><h3>' +
      esc(titulo) +
      "</h3><p>" +
      esc(mensaje) +
      "</p>" +
      (accionHtml || "") +
      "</div>";
  },
  error(contenedor, mensaje) {
    contenedor.innerHTML = '<div class="aviso aviso-error">' + esc(mensaje) + "</div>";
  },
};

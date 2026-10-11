/* ============================================================
   COMPONENTES COMPARTIDOS
   ============================================================ */
window.Toast = {
  mostrar(mensaje, tipo) {
    let zona = $("#toasts");
    if (!zona) {
      zona = document.createElement("div");
      zona.id = "toasts";
      document.body.appendChild(zona);
    }
    const t = document.createElement("div");
    t.className = "toast " + (tipo || "");
    t.setAttribute("role", "status");
    t.textContent = mensaje;
    zona.appendChild(t);
    setTimeout(() => t.remove(), 4000);
  },
  ok(m) { this.mostrar(m, "ok"); },
  error(m) { this.mostrar(m, "error"); },
};

/* ============================================================
   MENÚ PRINCIPAL
   ============================================================ */
const ENLACES_IZQUIERDA = [
  { texto: "Inicio", url: "/index.html", icono: "⌂", clase: "nav-inicio" },
  { texto: "Cursos", url: "/pages/cursos.html", icono: "▣", desplegable: true },
  { texto: "Proyectos", url: "/pages/proyectos.html", icono: "♧" },
  { texto: "Empresas", url: "#empresas", icono: "▥" },
  { texto: "Nosotros", url: "#nosotros", icono: "♙" },
  { texto: "Contacto", url: "#contacto", icono: "✉" },
];

window.Navbar = {
  pintar() {
    const host = $("#nav");
    if (!host) return;

    const s = Sesion.actual();
    const aqui = location.pathname;

    let enlaces = ENLACES_IZQUIERDA.slice();
    if (s) enlaces = enlaces.concat([{ texto: "Panel", url: "/pages/panel.html", icono: "▦" }]);

    const links = enlaces.map((e) => {
      const activo = aqui.endsWith(e.url) || (e.texto === "Inicio" && (aqui.endsWith("/") || aqui.endsWith("/index.html")))
        ? ' aria-current="page"' : "";
      const icono = '<span class="nav-icono" aria-hidden="true">' + esc(e.icono || "") + "</span>";

      if (e.desplegable) {
        return '<div class="nav-dropdown">' +
          '<a href="' + ruta(e.url) + '"' + activo + ' aria-haspopup="true">' +
          icono + esc(e.texto) + '<span class="nav-flecha" aria-hidden="true">▾</span></a>' +
          '<div class="nav-dropdown-menu">' +
          '<a href="' + ruta("/pages/cursos.html") + '">▤ Todos los cursos</a>' +
          '<a href="' + ruta("/pages/cursos.html") + '#cursos-destacados">✦ Cursos destacados</a>' +
          '</div></div>';
      }

      const href = e.url.startsWith("#") ? e.url : ruta(e.url);
      return '<a href="' + href + '"' + activo + '>' + icono + esc(e.texto) + "</a>";
    }).join("");

    const zonaUsuario = s
      ? '<div class="nav-usuario"><span class="nav-rol">' +
        esc(Formato.rol(s.rol)) +
        '</span><a href="' + ruta("/pages/perfil.html") + '">' + esc(s.nombre || "Mi perfil") + "</a>" +
        '<button class="btn btn-sm btn-linea" id="btn-salir" style="color:#f8edf1;border-color:rgba(255,255,255,.2)">Salir</button></div>'
      : '<div class="nav-usuario"><a href="' + ruta("/pages/login.html") + '">Iniciar sesión</a>' +
        '<a class="btn btn-sm btn-accion" href="' + ruta("/pages/registro.html") + '">Crear cuenta</a></div>';

    host.innerHTML = '<div class="shell fila fila-entre">' +
      '<nav class="nav-links" aria-label="Navegación principal">' + links + "</nav>" +
      zonaUsuario +
      "</div>";

    const salir = $("#btn-salir");
    if (salir) {
      salir.addEventListener("click", async () => {
        try {
          await AuthService.logout();
        } catch (e) {
          /* Se limpia la sesión local aunque falle la petición. */
        }
        Sesion.limpiar();
        location.href = ruta("/index.html");
      });
    }

    /* En pantallas táctiles permite abrir/cerrar el menú de Cursos. */
    const dropdown = host.querySelector(".nav-dropdown");
    const trigger = dropdown && dropdown.querySelector(":scope > a");
    if (dropdown && trigger) {
      trigger.addEventListener("click", (event) => {
        if (window.matchMedia("(hover: none)").matches) {
          event.preventDefault();
          dropdown.classList.toggle("abierto");
        }
      });
      document.addEventListener("click", (event) => {
        if (!dropdown.contains(event.target)) dropdown.classList.remove("abierto");
      });
    }
  },
};

/* Tarjeta de curso usada en el catálogo y en los paneles. */
window.TarjetaCurso = function (curso) {
  return (
    '<a class="tarjeta-curso" href="' + ruta("/pages/curso.html") + "?id=" + encodeURIComponent(curso.id) + '">' +
    '<span class="etiqueta">' + esc(curso.categoria) + "</span>" +
    "<h3>" + esc(curso.titulo) + "</h3>" +
    '<p class="resumen">' + esc(curso.resumen || "") + "</p>" +
    '<span class="meta">' + esc(curso.profesor || "Por asignar") +
    " · " + esc(curso.duracion || "") +
    " · " + esc(curso.inscritos || 0) + " inscritos</span>" +
    "</a>"
  );
};

/* Barra de progreso segmentada: un tramo por lección. */
window.BarraProgreso = function (completadas, total) {
  const t = Math.max(total || 0, 1);
  let html = '<div class="progreso" role="img" aria-label="' + completadas + " de " + t + ' lecciones">';
  for (let i = 0; i < t; i++) html += "<i" + (i < completadas ? ' class="hecho"' : "") + "></i>";
  html += "</div>";
  html += '<p class="progreso-dato">' + completadas + " de " + t + " lecciones · " +
    Formato.porcentaje((completadas / t) * 100) + "</p>";
  return html;
};

document.addEventListener("DOMContentLoaded", () => Navbar.pintar());

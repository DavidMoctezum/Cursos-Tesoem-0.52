/* Servidor simulado.
   Intercepta fetch y responde con el mismo JSON que documentaste, para poder
   desarrollar y demostrar el frontend antes de que existan los Servlets.
   Se apaga poniendo CONFIG.USAR_MOCK = false. No se sube a producción. */

(function () {
  if (!window.CONFIG || !window.CONFIG.USAR_MOCK) return;

  const CLAVE = "tesoem_mock_db";
  const CLAVE_SESION = "tesoem_mock_sesion";

  const semilla = () => ({
    secuencia: 100,
    categorias: [
      { id: 1, nombre: "Desarrollo web" },
      { id: 2, nombre: "Bases de datos" },
      { id: 3, nombre: "Redes y seguridad" },
      { id: 4, nombre: "Automatización industrial" },
      { id: 5, nombre: "Analítica de datos" },
    ],
    usuarios: [
      { id: 1, nombre: "Ana Rivera", correo: "alumno@tesoem.mx", contrasena: "tesoem2026", rol: "ALUMNO", activo: true, carrera: "Ingeniería en Sistemas", semestre: "7", registro: "2026-02-10" },
      { id: 2, nombre: "Ing. Marco Delgado", correo: "profesor@tesoem.mx", contrasena: "tesoem2026", rol: "PROFESOR", activo: true, validado: true, especialidad: "Java y bases de datos", registro: "2025-08-01" },
      { id: 3, nombre: "Industrias Nortec", correo: "empresa@tesoem.mx", contrasena: "tesoem2026", rol: "EMPRESA", activo: true, giro: "Manufactura", rfc: "INO040115AB3", registro: "2026-01-20" },
      { id: 4, nombre: "Coordinación Académica", correo: "admin@tesoem.mx", contrasena: "tesoem2026", rol: "ADMINISTRADOR", activo: true, registro: "2025-06-15" },
      { id: 5, nombre: "Mtra. Lucía Fuentes", correo: "lucia@tesoem.mx", contrasena: "tesoem2026", rol: "PROFESOR", activo: true, validado: false, especialidad: "Ciencia de datos", registro: "2026-09-02" },
      { id: 6, nombre: "Diego Cortés", correo: "diego@tesoem.mx", contrasena: "tesoem2026", rol: "ALUMNO", activo: false, carrera: "Ingeniería Industrial", semestre: "5", registro: "2026-03-30" },
    ],
    cursos: [
      {
        id: 10, titulo: "Java para aplicaciones web con Servlets", idCategoria: 1, idProfesor: 2,
        resumen: "Construye una aplicación web completa con Servlets, JDBC y patrón DAO.",
        descripcion: "Curso práctico que recorre el ciclo completo de una petición web: recepción en el Servlet, validación, lógica de negocio, acceso a datos con DAO y respuesta en JSON. Se trabaja sobre un proyecto real desplegado en Tomcat.",
        duracion: "24 horas", nivel: "Intermedio", publicado: true, inscritos: 42,
        lecciones: [
          { id: 101, titulo: "Instalación de Tomcat y primer Servlet" },
          { id: 102, titulo: "Ciclo de vida: doGet y doPost" },
          { id: 103, titulo: "Sesiones con HttpSession" },
          { id: 104, titulo: "Conexión JDBC centralizada" },
          { id: 105, titulo: "Patrón DAO paso a paso" },
          { id: 106, titulo: "Respuestas JSON y manejo de errores" },
        ],
        materiales: [
          { id: 201, titulo: "Guía de instalación (PDF)", tipo: "PDF", url: "#" },
          { id: 202, titulo: "Repositorio base del proyecto", tipo: "Enlace", url: "#" },
        ],
      },
      {
        id: 11, titulo: "Modelado y normalización en MySQL", idCategoria: 2, idProfesor: 2,
        resumen: "Del diagrama entidad-relación a un esquema normalizado y con índices.",
        descripcion: "Diseño de bases de datos relacionales aplicando formas normales, llaves foráneas y consultas preparadas.",
        duracion: "18 horas", nivel: "Básico", publicado: true, inscritos: 31,
        lecciones: [
          { id: 111, titulo: "Entidades, atributos y relaciones" },
          { id: 112, titulo: "Primera, segunda y tercera forma normal" },
          { id: 113, titulo: "Llaves foráneas e integridad" },
          { id: 114, titulo: "Índices y rendimiento" },
        ],
        materiales: [],
      },
      {
        id: 12, titulo: "Seguridad en aplicaciones web", idCategoria: 3, idProfesor: 5,
        resumen: "Contraseñas cifradas, control de roles y prevención de inyección SQL.",
        descripcion: "Buenas prácticas de autenticación, autorización por roles y protección de endpoints.",
        duracion: "12 horas", nivel: "Intermedio", publicado: true, inscritos: 27,
        lecciones: [
          { id: 121, titulo: "Hash de contraseñas" },
          { id: 122, titulo: "Autorización por roles" },
          { id: 123, titulo: "Consultas preparadas" },
        ],
        materiales: [],
      },
      {
        id: 13, titulo: "Automatización con PLC: nivel inicial", idCategoria: 4, idProfesor: 2,
        resumen: "Programación en escalera aplicada a líneas de producción.",
        descripcion: "Fundamentos de control lógico programable con ejercicios de planta.",
        duracion: "30 horas", nivel: "Básico", publicado: true, inscritos: 15,
        lecciones: [
          { id: 131, titulo: "Componentes de un PLC" },
          { id: 132, titulo: "Lógica de escalera" },
          { id: 133, titulo: "Sensores y actuadores" },
          { id: 134, titulo: "Práctica: banda transportadora" },
        ],
        materiales: [],
      },
      {
        id: 14, titulo: "Análisis de datos con Python", idCategoria: 5, idProfesor: 5,
        resumen: "Limpieza, agregación y visualización de datos operativos.",
        descripcion: "Uso de pandas y matplotlib para responder preguntas de negocio con datos reales.",
        duracion: "20 horas", nivel: "Intermedio", publicado: false, inscritos: 0,
        lecciones: [
          { id: 141, titulo: "Lectura de archivos y tipos de datos" },
          { id: 142, titulo: "Limpieza de datos faltantes" },
          { id: 143, titulo: "Agrupaciones y resúmenes" },
        ],
        materiales: [],
      },
    ],
    inscripciones: [
      { id: 50, idUsuario: 1, idCurso: 10, fecha: "2026-08-14", leccionesVistas: [101, 102, 103] },
      { id: 51, idUsuario: 1, idCurso: 11, fecha: "2026-09-01", leccionesVistas: [111, 112, 113, 114] },
    ],
    proyectos: [
      { id: 70, idAlumno: 1, titulo: "Control de inventario para taller escolar", descripcion: "Aplicación web con Servlets y MySQL para registrar entradas y salidas de herramienta.", tecnologias: "Java, Servlets, MySQL, Bootstrap", enlace: "#", estado: "PUBLICADO", fecha: "2026-07-22" },
      { id: 71, idAlumno: 6, titulo: "Monitor de consumo eléctrico", descripcion: "Tablero que grafica el consumo por línea de producción a partir de lecturas horarias.", tecnologias: "Python, pandas, Chart.js", enlace: "#", estado: "PUBLICADO", fecha: "2026-05-11" },
    ],
    certificados: [
      { id: 90, idAlumno: 1, idCurso: 11, folio: "TES-2026-0031", fecha: "2026-09-10" },
    ],
  });

  function db() {
    let d = localStorage.getItem(CLAVE);
    if (!d) {
      d = JSON.stringify(semilla());
      localStorage.setItem(CLAVE, d);
    }
    return JSON.parse(d);
  }
  function guardar(d) { localStorage.setItem(CLAVE, JSON.stringify(d)); }
  function nuevoId(d) { d.secuencia += 1; return d.secuencia; }

  function sesion() {
    const v = sessionStorage.getItem(CLAVE_SESION);
    return v ? JSON.parse(v) : null;
  }

  const ok = (extra) => Object.assign({ success: true }, extra || {});
  const resp = (datos, estado) =>
    new Response(JSON.stringify(datos), {
      status: estado || 200,
      headers: { "Content-Type": "application/json" },
    });
  const error = (mensaje, estado) => resp({ success: false, mensaje }, estado || 400);

  /* Enriquecimiento: el backend devolvería estos campos ya resueltos. */
  function cursoPublico(d, c) {
    const cat = d.categorias.find((x) => x.id === c.idCategoria);
    const prof = d.usuarios.find((x) => x.id === c.idProfesor);
    return {
      id: c.id, titulo: c.titulo, resumen: c.resumen, descripcion: c.descripcion,
      duracion: c.duracion, nivel: c.nivel, publicado: c.publicado, inscritos: c.inscritos,
      idCategoria: c.idCategoria, categoria: cat ? cat.nombre : "Sin categoría",
      idProfesor: c.idProfesor, profesor: prof ? prof.nombre : "Por asignar",
      lecciones: c.lecciones, materiales: c.materiales,
    };
  }

  function manejar(metodo, ruta, params, cuerpo) {
    const d = db();
    const s = sesion();
    const partes = ruta.split("/").filter(Boolean); // ej. ["cursos","10"]
    const recurso = partes[0];
    const id = partes[1] ? Number(partes[1]) : null;
    const protegido = () => (s ? null : error("Tu sesión no es válida. Inicia sesión otra vez.", 401));

    /* --- Autenticación --- */
    if (recurso === "login" && metodo === "POST") {
      const u = d.usuarios.find((x) => x.correo === String(cuerpo.correo || "").toLowerCase().trim());
      if (!u || u.contrasena !== cuerpo.contrasena) return error("Correo o contraseña incorrectos.", 401);
      if (!u.activo) return error("Esta cuenta está desactivada. Contacta a la coordinación.", 403);
      sessionStorage.setItem(CLAVE_SESION, JSON.stringify({ id: u.id, rol: u.rol, nombre: u.nombre }));
      return resp(ok({ id: u.id, rol: u.rol, nombre: u.nombre, mensaje: "Inicio de sesión correcto" }));
    }
    if (recurso === "logout") {
      sessionStorage.removeItem(CLAVE_SESION);
      return resp(ok({ mensaje: "Sesión cerrada" }));
    }

    /* --- Usuarios --- */
    if (recurso === "usuarios" && metodo === "POST") {
      const correo = String(cuerpo.correo || "").toLowerCase().trim();
      if (d.usuarios.some((x) => x.correo === correo)) return error("Ese correo ya está registrado.", 409);
      const u = {
        id: nuevoId(d), nombre: cuerpo.nombre, correo, contrasena: cuerpo.contrasena,
        rol: cuerpo.rol, activo: true, validado: cuerpo.rol !== "PROFESOR",
        registro: new Date().toISOString().slice(0, 10),
      };
      d.usuarios.push(u);
      guardar(d);
      return resp(ok({ mensaje: "Cuenta creada", id: u.id }), 201);
    }
    if (recurso === "usuarios" && metodo === "GET") {
      const p = protegido(); if (p) return p;
      if (s.rol !== "ADMINISTRADOR") return error("No tienes permisos para ver esta información.", 403);
      let lista = d.usuarios.map(({ contrasena, ...r }) => r);
      if (params.rol) lista = lista.filter((u) => u.rol === params.rol);
      if (params.busqueda) {
        const q = params.busqueda.toLowerCase();
        lista = lista.filter((u) => u.nombre.toLowerCase().includes(q) || u.correo.includes(q));
      }
      return resp(ok({ usuarios: lista }));
    }
    if (recurso === "usuarios" && metodo === "PUT") {
      const p = protegido(); if (p) return p;
      if (s.rol !== "ADMINISTRADOR") return error("No tienes permisos para esta operación.", 403);
      const u = d.usuarios.find((x) => x.id === id);
      if (!u) return error("El usuario no existe.", 404);
      if ("activo" in cuerpo) u.activo = cuerpo.activo;
      if ("validado" in cuerpo) u.validado = cuerpo.validado;
      guardar(d);
      return resp(ok({ mensaje: "Usuario actualizado" }));
    }

    /* --- Perfil --- */
    if (recurso === "perfil") {
      const p = protegido(); if (p) return p;
      const u = d.usuarios.find((x) => x.id === s.id);
      if (metodo === "PUT") {
        Object.assign(u, cuerpo);
        guardar(d);
        sessionStorage.setItem(CLAVE_SESION, JSON.stringify({ id: u.id, rol: u.rol, nombre: u.nombre }));
        return resp(ok({ mensaje: "Perfil actualizado" }));
      }
      const { contrasena, ...limpio } = u;
      return resp(ok({ perfil: limpio }));
    }

    /* --- Categorías --- */
    if (recurso === "categorias") {
      if (metodo === "POST") {
        const p = protegido(); if (p) return p;
        if (s.rol !== "ADMINISTRADOR") return error("No tienes permisos para esta operación.", 403);
        if (!cuerpo.nombre) return error("Escribe el nombre de la categoría.");
        const c = { id: nuevoId(d), nombre: cuerpo.nombre };
        d.categorias.push(c);
        guardar(d);
        return resp(ok({ categoria: c }), 201);
      }
      return resp(ok({ categorias: d.categorias }));
    }

    /* --- Cursos --- */
    if (recurso === "cursos" && metodo === "GET" && id) {
      const c = d.cursos.find((x) => x.id === id);
      if (!c) return error("El curso no existe.", 404);
      const salida = cursoPublico(d, c);
      if (s) {
        const i = d.inscripciones.find((x) => x.idUsuario === s.id && x.idCurso === id);
        salida.inscrito = !!i;
        salida.leccionesVistas = i ? i.leccionesVistas : [];
      }
      return resp(ok({ curso: salida }));
    }
    if (recurso === "cursos" && metodo === "GET") {
      let lista = d.cursos.map((c) => cursoPublico(d, c));
      if (params.mios === "true") {
        const p = protegido(); if (p) return p;
        lista = lista.filter((c) => c.idProfesor === s.id);
      } else {
        lista = lista.filter((c) => c.publicado);
      }
      if (params.categoria) lista = lista.filter((c) => String(c.idCategoria) === String(params.categoria));
      if (params.busqueda) {
        const q = params.busqueda.toLowerCase();
        lista = lista.filter((c) =>
          (c.titulo + " " + c.resumen + " " + c.categoria).toLowerCase().includes(q));
      }
      return resp(ok({ cursos: lista, total: lista.length }));
    }
    if (recurso === "cursos" && metodo === "POST") {
      const p = protegido(); if (p) return p;
      if (s.rol !== "PROFESOR") return error("Solo un profesor puede crear cursos.", 403);
      const c = {
        id: nuevoId(d), titulo: cuerpo.titulo, idCategoria: Number(cuerpo.idCategoria),
        idProfesor: s.id, resumen: cuerpo.resumen, descripcion: cuerpo.descripcion,
        duracion: cuerpo.duracion, nivel: cuerpo.nivel, publicado: !!cuerpo.publicado,
        inscritos: 0, lecciones: cuerpo.lecciones || [], materiales: [],
      };
      c.lecciones = c.lecciones.map((l, i) => ({ id: c.id * 100 + i, titulo: l.titulo || l }));
      d.cursos.push(c);
      guardar(d);
      return resp(ok({ mensaje: "Curso creado", id: c.id }), 201);
    }
    if (recurso === "cursos" && metodo === "PUT") {
      const p = protegido(); if (p) return p;
      const c = d.cursos.find((x) => x.id === id);
      if (!c) return error("El curso no existe.", 404);
      if (c.idProfesor !== s.id && s.rol !== "ADMINISTRADOR") return error("Este curso no es tuyo.", 403);
      Object.assign(c, {
        titulo: cuerpo.titulo ?? c.titulo,
        idCategoria: cuerpo.idCategoria ? Number(cuerpo.idCategoria) : c.idCategoria,
        resumen: cuerpo.resumen ?? c.resumen,
        descripcion: cuerpo.descripcion ?? c.descripcion,
        duracion: cuerpo.duracion ?? c.duracion,
        nivel: cuerpo.nivel ?? c.nivel,
        publicado: "publicado" in cuerpo ? !!cuerpo.publicado : c.publicado,
      });
      if (cuerpo.lecciones) {
        c.lecciones = cuerpo.lecciones.map((l, i) => ({ id: l.id || c.id * 100 + i, titulo: l.titulo || l }));
      }
      guardar(d);
      return resp(ok({ mensaje: "Cambios guardados" }));
    }
    if (recurso === "cursos" && metodo === "DELETE") {
      const p = protegido(); if (p) return p;
      const c = d.cursos.find((x) => x.id === id);
      if (!c) return error("El curso no existe.", 404);
      if (c.idProfesor !== s.id && s.rol !== "ADMINISTRADOR") return error("Este curso no es tuyo.", 403);
      c.publicado = false;
      guardar(d);
      return resp(ok({ mensaje: "Curso retirado del catálogo" }));
    }

    /* --- Inscripciones --- */
    if (recurso === "inscripcion" && metodo === "POST") {
      const p = protegido(); if (p) return p;
      if (s.rol !== "ALUMNO") return error("Solo los alumnos pueden inscribirse.", 403);
      const idCurso = Number(cuerpo.idCurso);
      const c = d.cursos.find((x) => x.id === idCurso);
      if (!c) return error("El curso no existe.", 404);
      if (d.inscripciones.some((x) => x.idUsuario === s.id && x.idCurso === idCurso))
        return error("Ya estás inscrito en este curso.", 409);
      d.inscripciones.push({
        id: nuevoId(d), idUsuario: s.id, idCurso,
        fecha: new Date().toISOString().slice(0, 10), leccionesVistas: [],
      });
      c.inscritos += 1;
      guardar(d);
      return resp(ok({ mensaje: "Inscripción registrada" }), 201);
    }
    if (recurso === "inscripciones" && metodo === "GET") {
      const p = protegido(); if (p) return p;
      if (params.idCurso) {
        const idCurso = Number(params.idCurso);
        const alumnos = d.inscripciones
          .filter((x) => x.idCurso === idCurso)
          .map((x) => {
            const u = d.usuarios.find((y) => y.id === x.idUsuario);
            const c = d.cursos.find((y) => y.id === idCurso);
            return {
              idInscripcion: x.id, nombre: u ? u.nombre : "Alumno", correo: u ? u.correo : "",
              fecha: x.fecha, completadas: x.leccionesVistas.length, total: c.lecciones.length,
            };
          });
        return resp(ok({ alumnos }));
      }
      const mias = d.inscripciones
        .filter((x) => x.idUsuario === s.id)
        .map((x) => {
          const c = d.cursos.find((y) => y.id === x.idCurso);
          return {
            idInscripcion: x.id, fecha: x.fecha, curso: cursoPublico(d, c),
            completadas: x.leccionesVistas.length, total: c.lecciones.length,
            porcentaje: Math.round((x.leccionesVistas.length / Math.max(c.lecciones.length, 1)) * 100),
          };
        });
      return resp(ok({ inscripciones: mias }));
    }

    /* --- Progreso --- */
    if (recurso === "progreso" && metodo === "POST") {
      const p = protegido(); if (p) return p;
      const idCurso = Number(cuerpo.idCurso);
      const i = d.inscripciones.find((x) => x.idUsuario === s.id && x.idCurso === idCurso);
      if (!i) return error("No estás inscrito en este curso.", 403);
      const idLeccion = Number(cuerpo.idLeccion);
      if (!i.leccionesVistas.includes(idLeccion)) i.leccionesVistas.push(idLeccion);
      const c = d.cursos.find((x) => x.id === idCurso);
      const porcentaje = Math.round((i.leccionesVistas.length / Math.max(c.lecciones.length, 1)) * 100);
      if (porcentaje === 100 && !d.certificados.some((x) => x.idAlumno === s.id && x.idCurso === idCurso)) {
        d.certificados.push({
          id: nuevoId(d), idAlumno: s.id, idCurso,
          folio: "TES-2026-" + String(nuevoId(d)).padStart(4, "0"),
          fecha: new Date().toISOString().slice(0, 10),
        });
      }
      guardar(d);
      return resp(ok({ porcentaje, completadas: i.leccionesVistas.length, total: c.lecciones.length }));
    }

    /* --- Proyectos --- */
    if (recurso === "proyectos" && metodo === "GET") {
      let lista = d.proyectos.map((pr) => {
        const u = d.usuarios.find((x) => x.id === pr.idAlumno);
        return Object.assign({}, pr, { alumno: u ? u.nombre : "Alumno", carrera: u ? u.carrera : "" });
      });
      if (params.mios === "true") {
        const p = protegido(); if (p) return p;
        lista = lista.filter((pr) => pr.idAlumno === s.id);
      }
      if (params.busqueda) {
        const q = params.busqueda.toLowerCase();
        lista = lista.filter((pr) => (pr.titulo + " " + pr.tecnologias).toLowerCase().includes(q));
      }
      return resp(ok({ proyectos: lista }));
    }
    if (recurso === "proyectos" && metodo === "POST") {
      const p = protegido(); if (p) return p;
      if (s.rol !== "ALUMNO") return error("Solo los alumnos pueden publicar proyectos.", 403);
      const pr = {
        id: nuevoId(d), idAlumno: s.id, titulo: cuerpo.titulo, descripcion: cuerpo.descripcion,
        tecnologias: cuerpo.tecnologias, enlace: cuerpo.enlace || "#", estado: "PUBLICADO",
        fecha: new Date().toISOString().slice(0, 10),
      };
      d.proyectos.push(pr);
      guardar(d);
      return resp(ok({ mensaje: "Proyecto publicado", id: pr.id }), 201);
    }

    /* --- Materiales --- */
    if (recurso === "materiales" && metodo === "POST") {
      const p = protegido(); if (p) return p;
      const c = d.cursos.find((x) => x.id === Number(cuerpo.idCurso));
      if (!c) return error("El curso no existe.", 404);
      if (c.idProfesor !== s.id) return error("Este curso no es tuyo.", 403);
      c.materiales.push({ id: nuevoId(d), titulo: cuerpo.titulo, tipo: cuerpo.tipo, url: cuerpo.url || "#" });
      guardar(d);
      return resp(ok({ mensaje: "Material agregado" }), 201);
    }

    /* --- Certificados --- */
    if (recurso === "certificados" && metodo === "GET") {
      const p = protegido(); if (p) return p;
      const lista = d.certificados
        .filter((x) => x.idAlumno === s.id)
        .map((x) => {
          const c = d.cursos.find((y) => y.id === x.idCurso);
          return { folio: x.folio, fecha: x.fecha, curso: c ? c.titulo : "Curso", duracion: c ? c.duracion : "" };
        });
      return resp(ok({ certificados: lista }));
    }

    return error("El recurso solicitado no existe: " + metodo + " " + ruta, 404);
  }

  const fetchReal = window.fetch.bind(window);

  window.fetch = async function (entrada, opciones) {
    const urlTexto = typeof entrada === "string" ? entrada : entrada.url;
    if (urlTexto.indexOf(window.CONFIG.API_BASE) === -1) {
      return fetchReal(entrada, opciones);
    }
    const u = new URL(urlTexto, location.origin);
    const ruta = u.pathname.replace(window.CONFIG.API_BASE, "");
    const params = Object.fromEntries(u.searchParams.entries());
    const metodo = ((opciones && opciones.method) || "GET").toUpperCase();
    let cuerpo = {};
    if (opciones && opciones.body && typeof opciones.body === "string") {
      try { cuerpo = JSON.parse(opciones.body); } catch (e) { cuerpo = {}; }
    }
    await new Promise((r) => setTimeout(r, 180)); // latencia simulada
    try {
      return manejar(metodo, ruta, params, cuerpo);
    } catch (e) {
      console.error("[mock]", e);
      return error("Error interno del servidor simulado.", 500);
    }
  };

  window.reiniciarMock = function () {
    localStorage.removeItem(CLAVE);
    sessionStorage.clear();
    location.reload();
  };

  console.info("[TESOEM] Modo simulado activo. Cuentas: alumno@tesoem.mx, profesor@tesoem.mx, empresa@tesoem.mx, admin@tesoem.mx · contraseña: tesoem2026");
})();

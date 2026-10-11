/* Configuracion global.
   El frontend se sirve desde Laravel (carpeta public/app) y usa la API /api/v1
   con sesion por cookie. Como comparte origen con Laravel, no requiere CORS. */

window.CONFIG = {
  API_BASE: "/api/v1",
  USAR_MOCK: false,
  RUTA_LOGIN: "/pages/login.html",
  // Prefijo de la app dentro del servidor (Laravel sirve public/app en /app).
  BASE_URL: "/app",
};

window.ruta = function (p) {
  return window.CONFIG.BASE_URL + p;
};

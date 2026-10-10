# App de campo (PWA)

Copia servible en `public/campo/` y `C:\laragon\www\Zentra_Supervision`. Sin BD. Login: usuario (`nombre.apellido.0000`) o correo legado + contraseña. API: si el host tiene `zentra_supervision` → `zentra` + `/api`; si el puerto es `8085` (Tailscale) → mismo host `:8084/api`; en `{APP_URL}/campo` → mismo origen `/api`.

Revista: cliente con Supervisión → **puesto** (`supervisor_posts`, no puertas de Accesos) → vigilante → foto → GPS. Los módulos del puesto (inventario, libros, carpetas, armamento, recomendaciones) van en borrador hasta **Guardar revista**.

Recomendaciones: 1 a 3 tarjetas. Tipo de riesgo (catálogo Ajustes → Riesgos), texto del riesgo, P×I, consecuencia, tratamiento y 3 fotos. No hay título ni fecha límite ni ciclo abierto/cerrado.

Armamento: novedad Con/Sin, aseo Sí/No (foto de aseo solo si sí) y 5 fotos de identificación.

Alarmas: tipo (Ajustes → Alarmas), prueba o atención, resultado y GPS. Apoyos: tipo (Ajustes → Apoyos), motivo, cliente y GPS.

Mis fichas: lista del supervisor y carta HTML para imprimir.

**Offline:** login y abrir turno con internet. Después, revistas/módulos/GPS/cierre se guardan por usuario (`zentra-sup-u{id}`) y se suben al reconectar con el token de quien las generó. El ping manda `pending_outbox`. No borre datos del sitio si hay pendientes.

Fotos: la cámara no arranca sola. Trasera/Frontal o Tomar foto. Solo HTTPS (Tailscale Serve). En HTTP no hay foto de prueba.

Caché SW: `zentra-sup-v49`. Hard-refresh tras cambios. Cola ajena se sube en segundo plano y no bloquea al de turno. Login y cambio de clave: icono de ojo para ver la contraseña. APK v1.7: una sesión (cierra la otra si confirmas), revista a 40 m. GPS con pantalla apagada.

Tailscale: cámara = `https://sjpcanaope.tail5fcfbc.ts.net/` (Serve). No `http://IP:8085`. API: puerto `8085` → `:8084/api`; host `.ts.net` → mismo origen `/api`. `.env` local sigue `http://zentra.test`. En el APK la API es siempre `https://zentra.wcodex.cloud/api`.

Instalación: **Descargar APK** en `/company/descargas` y `/admin/descargas` (`public/downloads/zentra-supervision.apk`). Login igual. Web PWA de respaldo: local `zentra_supervision.test`; hosting `{APP_URL}/campo`. Rebuild: `npm install` → `npm run sync` → `JAVA_HOME` = JDK 21 → `npm run apk`.

Ver [`docs/SUPERVISION-CAMPO.md`](../docs/SUPERVISION-CAMPO.md) · [`docs/CLIENTES-Y-ESTRUCTURA.md`](../docs/CLIENTES-Y-ESTRUCTURA.md).

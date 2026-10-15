# Supervisión de campo

Zentra es la fuente de verdad. La PWA (`field-app/` y el host `zentra_supervision.test`) solo captura.

No choca con: minuta de portería (`/access/supervision`), `SupervisorReview`, `PlatformDocument`, zonas/vehículos de Accesos, `locations` (accesos) ni correspondencia.

**Última actualización:** 13 septiembre 2026

Árbol del cliente (instalación → puesto): [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md). La app **no** usa puntos de Accesos como puesto.

---

## Empresa — Ajustes

Mismo bloque que Cargos/Tipos (`company.settings.view` / `manage`; el colaborador solo si tiene grant Ajustes). El supervisor **no** entra al panel: middleware `EnsureSupervisorUsesFieldApp` → `/supervision/app`.

| Pestaña | Ruta | Tabla |
|---------|------|--------|
| Cargos | `/company/job-titles` | `company_job_titles` |
| Tipos | `/company/collaborator-types` | `company_collaborator_types` |
| **Estructuras** | `/company/structure-types` | `structure_types` (por empresa; alta de cliente) |
| **Zonas** | `/company/supervision-zones` | `supervisor_zones` (rutas de Supervisión, no `common_zones`). Correo corporativo de avisos **por zona** (`email`); puede repetirse entre zonas. No se pega al usuario. |
| **Turnos** | `/company/supervision-shifts` | `supervisor_shift_templates` (nombre + horario) |
| **Modalidades** | `/company/supervision-post-modalities` | `supervisor_post_modalities` (horas 1–24 del puesto; semilla 8/12/24). No es el turno del supervisor |
| **Preoperacional** | `/company/supervision-preop` | `supervisor_checklist_items` (`ppe` / `vehicle`) |
| **Documentos** | `/company/supervision-document-types` | `supervisor_document_types` (entregados/pendientes del turno; vacío hasta que la empresa los cree) |
| **Libros** | `/company/supervision-control-book-types` | `supervisor_control_book_types` (libros del puesto; vacío hasta que la empresa los cree) |
| **Tipos de arma** | `/company/supervision-weapon-types` | `supervisor_weapon_types` |
| **Marcas** | `/company/supervision-weapon-brands` | `supervisor_weapon_brands` |
| **Riesgos** | `/company/supervision-risk-types` | `supervisor_risk_types` (tipos de la recomendación; vacío hasta que la empresa los cree) |
| **Alarmas** | `/company/supervision-alarm-types` | `supervisor_alarm_types` (pánico, incendio, etc.; vacío hasta que la empresa los cree) |
| **Apoyos** | `/company/supervision-support-types` | `supervisor_support_types` (refuerzo, escolta, etc.; vacío hasta que la empresa los cree) |

Si Zonas/Turnos/Modalidades/Preoperacional están vacíos, se siembran defaults (Norte/Sur/Centro; Día 06:00–18:00 y Noche 18:00–06:00; 8/12/24 h; EPP y vehículo de `ShiftIntakeCatalog`). **Documentos**, **Libros**, **Tipos de arma**, **Marcas**, **Riesgos**, **Alarmas** y **Apoyos** no se siembran: la empresa define los tipos. Solo los **activos** salen en la app.

Flota: `supervisor_fleet_vehicles` (placa/marca la primera vez). **No** es `vehicles` de Accesos.

---

## App de campo

PWA en `field-app/` (copia `public/campo/` y Laragon `Zentra_Supervision`). Caché SW `zentra-sup-v53`. Hosting: `https://zentra.wcodex.cloud/campo`. APK Android v2.1 (Capacitor) en `field-app/android/`; el binario se publica en `public/downloads/zentra-supervision.apk`. Selfie de inicio en el círculo de perfil.

**Cambio de dispositivo:** el supervisor queda atado a un celular (identificador `zentra_device_id` en el almacenamiento de la app; APK y PWA igual). El primer login lo registra solo. Desde otro celular el login responde `423` y crea **una** solicitud (modelo + GPS si hay) que vence en **10 min**; la app muestra «Cambio de celular» en espera. La aprueba o rechaza quien tenga el módulo **Cambio de dispositivo** (`company.devices.approve`) o el admin de empresa: alerta flotante con sonido en el panel y menú `/company/dispositivos`. Al aprobar, el celular viejo pierde el token y el turno abierto sigue en el nuevo. Borrar datos de la app cuenta como celular nuevo.

**Georreferencia:** entrada del hub para corregir el punto de una instalación desde el sitio. Busca el cliente y, si tiene varias instalaciones, la instalación (con una sola, la elige sola). Exige turno abierto, GPS **±30 m** o mejor y conexión (no entra a la cola). **Enviar georreferenciación actual** crea una solicitud **pendiente**; la instalación no cambia hasta que la empresa apruebe. Un nuevo envío para la misma instalación reemplaza el pendiente anterior. Panel: alerta abajo a la derecha con **Revisar** y Supervisión → **Georreferenciaciones** (`/company/supervision/georreferencias`, Pendientes / Historial). El modal muestra dos mapas (actual y solicitado), distancia y precisión; **Aprobar** mueve la latitud/longitud de la instalación (queda en auditoría) y **Rechazar** acepta un motivo opcional. Revisa quien tenga `company.geo.approve` o el admin de empresa.

Login: **usuario** (`nombre.apellido.####`, igual que el resto de usuarios de empresa) o el correo de cuentas antiguas, más contraseña. En login y primer cambio de clave, icono de ojo para verla. Alta: **Usuarios** → nombre y cédula del empleado → generar usuario y clave; primera entrada pide cambiar clave. El correo corporativo **no** es el login: está en la zona y se resuelve al abrir turno. API **siempre** Zentra: host `zentra_supervision` → mismo esquema + host `zentra` + `/api`; puerto `8085` → mismo host `:8084/api`. No hay campo de API. Instalación: **Descargas** en empresa (`/company/descargas`) y plataforma (`/admin/descargas`); QR + enlace (`SUPERVISION_PWA_URL`). Hard-refresh tras cambios de PWA.

Fotos: no se enciende la cámara al abrir. **Trasera** / **Frontal** o **Tomar foto** piden `getUserMedia` (hace falta HTTPS o localhost). Tras capturar se apaga. Galería solo si no hay contexto seguro.

1. Login (`POST /api/supervision/login`) → rito de **apertura**: turno y zona del catálogo, EPP/vehículo plegables, km + foto odómetro + selfie (cámara, no galería).
2. Hub: ficha de perfil + **Cerrar**. Entradas: **Revista**, **Alarmas**, **Apoyos**, **Documentos**, **Observatorio**, **Georreferencia**, **Mis fichas**. Ping GPS cada **15 s**. Cada ping manda `screen_on`. El mapa: **En línea** (GPS &lt; 90 s y pantalla encendida), **Pantalla apagada** (GPS &lt; 90 s y pantalla off) o **Sin señal** (más de 90 s sin GPS). **APK corte 2:** servicio en primer plano (notificación “Turno de supervisión activo”) sigue mandando GPS con la pantalla apagada. La PWA sigue pausando el JS al bloquear el teléfono.
3. **Revista** (al clic): cliente, puesto, vigilante, foto. Los módulos del puesto (inventario, libros de control, etc.) se registran en borrador. **Guardar revista** (abajo) envía GPS + foto + módulos juntos. El GPS debe estar a **≤ 80 m** del pin del puesto o, si no tiene pin, de la **instalación**. Crear o editar el puesto no pide GPS; un pin lejos de la instalación se anota y no bloquea el alta. Mientras envía: botón bloqueado y texto *Guardando revista…* (mismo candado en Entrar, iniciar/cerrar turno y Registrar). Un `client_event_id` por intento: el API no duplica. Si cancela, no queda nada.
4. Alarmas y apoyos: cada uno abre su formulario (cliente + GPS obligatorios). Documentos: sin cliente ni GPS.
5. Cierre: km final + odómetro + selfie → sesión cerrada.

No hay usuario supervisor de semilla. El supervisor se crea en **Usuarios** a partir de un **empleado** de la empresa (`nombre.apellido.####` + clave generada; primera entrada cambia clave). Panel empresa: `empresa@sj-seguridad.test` / `Empresa123!`. Palmas tiene `has_supervision`. Vigilante piloto: cédula `1144001122`.

Sin puestos de Supervisión en la ficha del cliente no se guarda revista. Los `locations` (puertas) no entran en el combo.

**Cumplimiento por puesto:** la unidad es el **puesto** (cliente → instalación → puesto), no el cliente. Cada puesto activo espera **al menos una revista por día operativo** (06:00 a 06:00 del día siguiente; la fecha es la del inicio) en sus **días de servicio** (Lun..Dom, atajos Lun a Vie / Lun a Sáb) y, si es festivo de Colombia, solo si «¿Presta servicio en festivos?» = Sí. Revistas en días sin servicio se guardan pero no cuentan. Un periodo espera puestos × días de servicio. Lo usan el mapa en vivo, la tira del día, Resumen/PPTX y el panel Mi empresa (`BuildPostComplianceService`).

**Mapa en vivo:** sin clientes de fondo. Vista general: motos, revistas/eventos de los turnos abiertos y pines de cliente o instalación (selector **Clientes | Instalaciones**) con el número de puestos revisados dentro (verde todos, ámbar parcial). Interruptor **Pendientes**: solo puntos con puestos sin revista, número = pendientes (rojo ninguno revisado). Hover/clic: instalaciones, puestos, revisados, pendientes y lista de puestos sin revista. Clic en un supervisor (fila): solo su recorrido en vivo; otro clic o **Ver todos** vuelve. Iconos de evento (24 px, mismos SVG en mapa y leyenda, `partials/pin-icon`): inicio = bandera verde, cierre = bandera a cuadros, revista = tablita con ✓ verde, con novedad = tablita con «!» naranja, alarma = sirena roja, parada = octágono STOP granate, apoyo = escudo azul. Puestos: círculo con número 26 px. Grupo de eventos: icono del más relevante + contador. Señal (En línea / Pantalla apagada / Sin señal) va como texto, no como pin. Columna derecha (En vivo e Historial): **Convenciones** arriba, línea, luego la lista con contador y botón **?** (ayuda semitransparente; sale sola la primera vez, `localStorage`). Instalaciones sin coordenadas: aviso para registrar su ubicación.

---

## Offline (sin cobertura)

Login y **abrir turno** requieren internet. Al iniciar turno (o al entrar con turno abierto) la PWA baja un **paquete** (`GET /api/supervision/offline-pack`: clientes, puestos, vigilantes, catálogo) a IndexedDB.

Sin red puede registrar revistas, alarmas, apoyos, documentos y pings GPS: quedan en una **cola por supervisor** (`zentra-sup-u{id}`). Con red, la PWA sube **todas** las colas del teléfono usando el token guardado de quien las generó. El supervisor de turno sigue trabajando: un cierre/cola ajena no bloquea Registrar ni GPS. Solo su propio cierre pendiente traba su sesión. Al abrir turno se limpia ese candado.

Cerrar turno sin red también se encola (fotos incluidas). No borrar datos del sitio hasta que el banner desaparezca. iPhone: hay que **abrir la app** al recuperar señal.

**Hora de captura:** revista, módulos, observatorio y GPS mandan `recorded_at` (hora del celular al capturar). El servidor guarda esa hora (acotada al turno y nunca en el futuro, `SupervisorCaptureTime`), no la de llegada. Si el sistema cerró el turno mientras no había red, lo capturado **dentro** del horario del turno se acepta en ese turno cerrado hasta 72 h después; se recalcula km GPS (si cerró el sistema sin odómetro) y la ficha congelada (`RefreshLateSyncedShiftService`). Un cierre encolado después de un cierre del sistema se adjunta a ese turno (km y fotos) sin cambiar la hora de cierre.

**Rechazados:** si el servidor no acepta algo de la cola (4xx distinto de 401), la app **no lo borra**: queda en una caja roja con el motivo y botones **Reintentar** / **Descartar**. Solo los pings GPS rechazados se descartan solos.

**APK 2.0 (GPS nativo):** GPS primero; la red (celdas/wifi) solo si el GPS lleva 60 s sin posición. No reenvía la misma lectura: cada punto lleva la hora real del fix. Lecturas peores de 50 m solo salen como latido cada 2 min (para «en línea»). Sin red guarda los puntos en el celular (archivo por supervisor, máx. 72 h) y los sube por lotes a `POST /api/supervision/shifts/pings` (hasta 300). 401 detiene; 422 sin turno abierto descarta el lote y detiene.

**Recorrido en el mapa:** solo lecturas ≤ 50 m dibujan la línea (si el turno solo tiene red, tope 150 m). Precisión 0 = desconocida. Se quitan los «ir y volver» (punto que se aleja más de 25 m y el siguiente vuelve cerca del anterior). Salir de una parada exige confirmación (dos puntos fuera de 75 m o un salto grande al final). Detenida, la moto queda fija en el centro de la parada.

---

## Módulos

| Clave | Captura | Notas |
|-------|---------|--------|
| `reviews` | `POST /reviews` | Revista en `supervisor_shift_reviews`. No llena minuta Accesos. |
| `inventory` | `POST /logs` | Cuelga de la revista. Varios elementos (tipo, estado, observación) |
| `control_books` | `POST /logs` | Cuelga de la revista. Tipos del catálogo empresa; con/sin novedad |
| `folders` | `POST /logs` | Cuelga de la revista |
| `weapons` | `POST /logs` | Cuelga de la revista. Tipo/marca, permiso, novedad, aseo opcional (foto de aseo solo si sí) y 5 fotos de identificación |
| `recommendations` | `POST /logs` | Cuelga de la revista. 1 a 3 riesgos (tipo de catálogo, P×I, consecuencia, 3 fotos). Registro, no ticket |
| `alarms` | `POST /logs` | Formulario propio. Requiere cliente y GPS. Tipo, modalidad (prueba/atención) y resultado |
| `supports` | `POST /logs` | Formulario propio. Requiere cliente y GPS. Tipo + motivo |
| `documents` | `POST /logs` | Del turno. Sin cliente ni GPS. Tipos + entregado/pendiente |
| Observatorio | `POST /observatory/reports` | No es un log de revista. Colegio + tipo + texto; anónimo opcional. Colegios en el paquete offline |
| Pánico | `POST /panic` | Turno abierto. Sin confirmar. Botón rojo al final, bajo fichas. Avisa a la empresa; este teléfono no suena |

Contrato de campos: `GET /api/supervision/catalog` (`FieldModuleCatalog`). Logs append-only en `supervisor_field_logs` (`supervisor_shift_review_id` si cuelga de revista). Recomendaciones: `supervisor_recommendations` (registro inmutable del turno; `GET /recommendations` lista recientes).

El mapa `/company/supervision` usa **satélite** por defecto (toggle Terreno). Pinta **cliente** (índigo) e **instalación** (cian) con GPS; si la instalación es “el mismo cliente” y el pin coincide, no se duplica. El mapa de **Mi empresa** usa la misma pareja de pines. En vivo e Historial: **dos columnas** (mapa alto + lista). **En vivo** no lleva filtros en el header: lista todos los turnos abiertos. Por defecto solo motos (sin polilíneas); clic en la fila pinta la ruta GPS de ese supervisor. **Ver** abre la ficha de turno en curso (no se guarda hasta el cierre). Estado En vivo: en ruta / detenido con horas (`Se detuvo a las HH:mm · lleva N min`); paradas cerradas `de HH:mm a HH:mm`. Pin de moto: **En línea**, **Pantalla apagada** o **Sin señal**. Poll 10 s, sin Roads. Pines a ≤50 m se agrupan. La moto queda detrás (`pointer-events: none` en hover: tooltip nombre + señal). Clic en la moto o en el grupo abre la lista de eventos (sin la moto). Fichas de pin: OverlayView compacto (no InfoWindow blanco de Google). Apoyo (cian) y alarma (ámbar) tienen pin. Leyenda en la columna. **Historial:** solo turnos **cerrados**; filtros Desde / Hasta / Supervisor; el mapa espera el clic (una ruta en calle, Snap to Roads; nunca varias a la vez). **Ver** abre la ficha ya guardada. Turno cerrado por el sistema: texto **Cierre por el sistema** y, si el teléfono reportó cola en el GPS, **N registros en cola**. Trail: `BuildSupervisorTrailService` (filtra precisión &gt; 150 m, saltos más rápidos que ~130 km/h y picos aislados; luego ~28 m; parada 75 m y ≥120 s; minutos de detenido actual contra el reloj). El mapa En vivo sigue sin Roads: el filtro evita la polilínea en “sismógrafo”.

Cierre automático (`supervision:auto-close-shifts`, cada 5 min en el scheduler): fin de **plantilla** (`starts_at`/`ends_at`) + **30 min** de gabela. Ej. 06:00–14:00 cierra a las 14:30; noche 18:00–06:00 cierra a las 06:30 del día siguiente. Sin fotos de km; nota en el turno. Sin plantilla: **3 h** desde el último GPS o `started_at`. El turno sale de En vivo. En Windows hace falta `php artisan schedule:work` (o Tarea programada con `schedule:run`).

---

## Fichas de campo

No es el Historial GPS ni el PPTX. Cada captura (revista, alarma, apoyo, documentos) genera una **ficha inmutable** en HTML carta (`window.print()` / Guardar como PDF). Folio `FC-{año}-{R|A|S|D}{id}` (ej. `FC-2026-R000042`). La **ficha de turno** (`kind=shift`, folio `…-T…`) se arma al abrir turno y **solo se guarda** (`supervisor_shifts.sheet_snapshot`) al cerrar; en En vivo/Historial el botón **Ver**. No se guarda un archivo PDF: se arma al abrirla. Cabecera: logo, nombre y NIT de la **empresa** (Mis datos). Sin marca de la plataforma. La revista copia el **encabezado** al guardar (`sheet_intro`). Sitio: cliente, instalación, puesto.

| Origen | Contenido | Cliente | GPS |
|--------|-----------|---------|-----|
| Revista | Marco normativo, cliente / instalación / puesto, vigilante, foto, novedad y módulos | Sí | Sí (el de la revista) |
| Alarma | Tipo, modalidad, resultado | Sí | Sí |
| Apoyo | Tipo y motivo | Sí | Sí |
| Documentos | Entregados / pendientes | No | No |

Panel empresa: pestaña **Fichas** en `/company/supervision?tab=sheets` (filtros de fecha/zona/supervisor + tipo, cliente, novedad). `GET /company/supervision/fichas/{kind}/{id}` abre la carta. PWA: **Mis fichas** (`GET /api/supervision/sheets` y la misma carta autenticada). No mezcla minuta de portería ni `PlatformDocument`.

---

## Recomendaciones (riesgo)

No son tickets. El supervisor **registra y envía** en la revista; el tratamiento es interno (fuera de la app). Sin fecha límite y sin abierto / en proceso / cerrado.

Hasta **3 tarjetas** por puesto (un solo registro de módulo). Cada tarjeta:

| Campo | Origen |
|-------|--------|
| Tipo de riesgo | Select del catálogo empresa (`supervisor_risk_types`) |
| Riesgo | Texto: qué identificó |
| Probabilidad | 1–5 (muy baja → muy alta) |
| Impacto | 1–5 (insignificante → catastrófico) |
| Nivel | Calculado P×I (bajo / medio / alto / extremo). No lo elige el supervisor |
| Consecuencia | Texto: qué pasaría |
| Recomendación | Texto: qué hacer |
| Evidencia | 3 fotos obligatorias |

Matriz (ISO 31000 operacionalizada 5×5): score = probabilidad × impacto. 1–4 bajo, 5–9 medio, 10–16 alto, 17–25 extremo. El `priority` interno (baja/normal/alta/urgente) sale del nivel.

Panel: KPIs de **volumen y nivel**, no de tickets abiertos. Tira de hoy: recomendaciones del día.

---

## Panel empresa — operación

`/company/supervision`: En vivo / Historial / Resumen / **Fichas**. En vivo: sin filtros de header; mapa + tabla (inicio, señal, km, revistas, **Ver**); clic en fila = ruta GPS; `GET /company/supervision/live.json`. Historial: Desde / Hasta / Supervisor; mapa vacío hasta clic; callejero (`GET /company/supervision/turnos/{shift}/ruta`); **Ver** ficha guardada. Fechas (Historial, Resumen, Fichas): un solo botón **Periodo** (ícono de calendario + periodo escrito) abre un panel con atajos (Hoy, Ayer, Este mes, Mes anterior, Este año), un calendario (un día) y el check **Rango** (dos meses: desde y hasta); **Aplicar** filtra. Zona, Supervisor y **Filtrar** siguen aparte (`supervisionPeriodPicker`, `partials/period-picker`). Resumen y Fichas de campo conservan filtros y **Descargar PPTX**. Resumen: 5 KPIs (Cumplimiento por puesto, **Puestos cumplidos** = puestos con todos sus días de servicio revisados, Revistas + con novedad, Km, Recomendaciones); chips **Atención** solo si hay alarmas reales/en falla, recomendaciones extremas, turnos abiertos > 12 h o documentos pendientes; gráficos; tabla **Clientes** a todo el ancho (todos los clientes con puestos en servicio: instalaciones, puestos, cumplidos, revistas, novedad, %) con semáforo rojo = ninguna revista, ámbar = pendientes, verde = cumplido, orden rojo → ámbar → verde y alfabético; clic en la fila = puestos pendientes; tabla **Por supervisor** a todo el ancho (turnos, revistas, km, alarmas, apoyos, registros). El PPTX usa los mismos KPIs, la tabla de clientes con semáforo (14 por lámina) y la lámina Atención solo si hay algo. PPTX: `GET /company/supervision/informe.pptx`. Compositor IA: § Informe PPTX (pendiente).

Servicios: `BuildSupervisionMapService`, `BuildSupervisorShiftSheetService` (ficha de turno; snapshot al cierre), `BuildSupervisorTrailService` (antes de simplificar: descarta `accuracy` > 150 m, saltos más rápidos que ~130 km/h y un ping que el siguiente no confirma; el pin y los km usan esa traza, la señal usa el último ping crudo), `SnapSupervisorTrailToRoadsService`, `ResolveSupervisorShiftDeadlineService` (gabela 30 min), `AutoCloseExpiredSupervisorShiftsService` (comando `supervision:auto-close-shifts`). El cierre manual de la PWA sigue exigiendo fotos (`CloseSupervisorShiftService`).

`/company/descargas` y `/admin/descargas`: **Descargar APK** + web de respaldo (QR). Una sola app para todas las empresas; el login identifica la empresa. No es la app de residentes de Accesos.

### Distribución

**APK (corte 2) + PWA de respaldo.** El `.apk` se baja de Descargas y se instala (orígenes desconocidos). Habla con `https://zentra.wcodex.cloud/api`. Mismo usuario/clave. No Play Store. No hay un binario por empresa. Con turno abierto el APK muestra una notificación persistente y el GPS sigue con la pantalla apagada (`ShiftTrackingService`, `source=apk`). El plugin pide ubicación y notificaciones **y espera** el resultado; si faltan, no arranca el servicio (evita el cierre en Android 14+) y la app sigue con ping del WebView. La PWA no tiene ese servicio. Web de respaldo: `{APP_URL}/campo` (no un subdominio `zentra_supervision` en el VPS). Pánico: último botón, rojo `#b91c1c`, envío inmediato.

Rebuild: `field-app/` → `npm run sync` → `JAVA_HOME` = JDK 21 → `npm run apk` → copiar `android/app/build/outputs/apk/debug/app-debug.apk` a `public/downloads/zentra-supervision.apk`.

**Tailscale (verificado en consola 4 sep 2026, plan Gratis: MagicDNS sí, registros A custom no).** Vhosts `00-aad-zentra-tailscale.conf`: API `:8084`, PWA `:8085`, alias `100.75.176.11`. Cámara: **HTTPS** `https://sjpcanaope.tail5fcfbc.ts.net/` (`tailscale serve`: `/` → 8085, `/api` → 8084). No usar `http://IP:8085` para fotos.

Mi empresa muestra la tira **Supervisión de campo (hoy)** aparte de las revistas de portería (incluye recomendaciones del día).

---

## Informe PPTX

**Hoy (un clic, `ExportSupervisionExecutiveReportService`):** gráficos nativos editables y un párrafo por lámina armado con plantillas a partir de las cifras (sin IA). Láminas:

1. **Portada:** logo Zentra (`resources/images/branding/logo-zentra.png`) y logo de la empresa (Mis datos; PNG/JPG directo, WebP se convierte a PNG; sin logo va el nombre).
2. **Indicadores:** 5 KPIs + párrafo; los **puntos de atención** (alarmas reales o en falla, recomendaciones extremas, turnos > 12 h, documentos pendientes) van al final del párrafo, ya no en lámina aparte.
3. **Actividad diaria/mensual:** revistas por día (apiladas con/sin novedad) y km por día (línea).
4. **Puesto:** donas de inventario, libros y carpetas; documentos en el párrafo.
5. **Armamento y recomendaciones:** barras de armamento y dona por nivel de riesgo.
6. **Actividad por supervisor:** revistas y km en dos gráficos (top 12) + resumen.
7. **Alarmas y apoyos:** alarmas por tipo, resultado (dona) y apoyos por tipo.
8. **Cumplimiento por cliente:** tabla con semáforo (14 filas por lámina) y conteo rojo/ámbar/verde.
9. **Gracias.**

### Compositor con IA (pendiente — no implementado)

El siguiente corte **no descarga de un clic**: abre un **compositor** (preview HTML, no el `.pptx` en el navegador). Los números no se editan. Un textarea por lámina de contenido; DeepSeek (API servidor, `DEEPSEEK_API_KEY`) propone redacción; el usuario acepta o corrige y recién entonces se genera el archivo.

### Mazo objetivo

| # | Lámina | Editable |
|---|--------|----------|
| 1 | Portada (Zentra, título, empresa, periodo) | No |
| 2 | KPIs (cobertura, revistas, km, recomendaciones) + **introducción** | Párrafo intro |
| 3 | Actividad (grano día/mes) | Un párrafo de esa lámina |
| 4 | Puesto: inventario, libros, carpetas, documentos | Un párrafo |
| 5 | Actividad por supervisor (barras) | Un párrafo |
| 6 | Alarmas y apoyos | Un párrafo |
| 7 | Sitios y recomendaciones | Un párrafo |
| 8 | Alertas del periodo | Un párrafo (lectura; no repetir el listado) |
| 9 | Cierre: **GRACIAS** grande al centro; línea corta opcional (empresa / periodo) | Línea opcional |

IA: no inventa KPIs. El prompt lleva solo el snapshot de **esa** empresa y filtro. El chat de **ayuda a redactar** vive en el compositor (ver § Chatbot y PQRS). El canal PQRS es persistido y distinto.

Rutas previstas (cuando se implemente): compositor con el mismo query que el resumen; `POST` del PPTX con los textos. El `GET …/informe.pptx` actual se sustituye o queda como atajo sin narrativa.

---

## Chatbot y PQRS (pendiente — no implementado)

Mismo proveedor: **DeepSeek** en servidor (`DEEPSEEK_API_KEY`). El navegador no ve la clave. El prompt **nunca** incluye datos de otra empresa.

Son **dos usos** de IA, no un solo widget genérico.

### A. Ayuda (chat de producto)

Panel lateral en el **compositor del informe** (y, si aplica, en Supervisión). Responde dudas de uso: filtros, qué es cobertura, por qué Accesos no entra en el PPTX, cómo leer alarmas, qué hace cada lámina.

- No sustituye los textareas: puede **proponer** un párrafo; el usuario lo pega o usa «Mejorar redacción» del campo.
- No persiste tickets. Historial de chat: sesión o descarte al cerrar (definir en implementación).
- Si no sabe, dice que no consta; no inventa cifras ni políticas.

### B. PQRS (preguntas, quejas, sugerencias)

Canal de **registro**, no solo conversación. El usuario elige tipo (pregunta / queja / sugerencia), escribe, y puede pedir a la IA que **aclare o redacte** el mensaje antes de enviar.

Al enviar se guarda un ticket scoped a la empresa (`security_company_id`, usuario, tipo, asunto, cuerpo, estado abierto). La IA puede clasificar y devolver un acuse; **no cierra** quejas sola.

Bandeja prevista: panel empresa (listado de los tickets de **esa** empresa). Plataforma (`/admin`): ver todos es un extra posterior, no el corte mínimo.

Fuera de alcance de este corte: chatbot en la PWA de campo, app de residentes, WhatsApp, mail automático al cliente.

---

## API (`auth:sanctum` + `supervisor.pro`)

| Método | Ruta | Uso |
|--------|------|-----|
| POST | `/api/supervision/login` | Token: `login` o `email` + clave + `device_id` (obligatorio), `device_label`, `latitude`/`longitude` opcionales. `423` si el celular no es el registrado (`device_change_requested` / `_pending` / `_busy`). Respuesta incluye `must_change_password` |
| POST | `/api/supervision/password` | Primer ingreso (sanctum): `username` nuevo + clave. `must_change_password` queda en false |
| GET | `/api/supervision/intake` | Zonas, turnos, EPP, vehículo, flota |
| GET | `/api/supervision/shifts/current` | Turno abierto + `current_review` + actividad |
| GET | `/api/supervision/shift-photo/start-selfie` | Selfie de apertura |
| POST | `/api/supervision/shifts/open` | Multipart: `shift_template_id`, `zone_id`, checklists, fotos |
| POST | `/api/supervision/shifts/ping` | GPS silencioso. `client_event_id` opcional. `pending_outbox` = cola IndexedDB del supervisor (el panel muestra “N registros en cola” si el turno se cierra por el sistema) |
| POST | `/api/supervision/shifts/close` | Multipart km + fotos. `client_event_id` opcional (idempotente) |
| GET | `/api/supervision/offline-pack` | Snapshot para captura sin red: sitios, puestos, vigilantes, catálogo |
| GET | `/api/supervision/sites` | Clientes con `has_supervision` |
| GET | `/api/supervision/posts` | Puestos de Supervisión del cliente (`supervisor_posts`), no `locations` |
| GET | `/api/supervision/guards` | Vigilantes por cédula |
| GET | `/api/supervision/catalog` | 8 módulos |
| POST | `/api/supervision/reviews` | Multipart: cliente, puesto, vigilante, foto, GPS, novedad. `client_event_id` opcional (idempotente) |
| POST | `/api/supervision/panic` | Pánico. JSON lat/lng/note. Requiere turno abierto |
| POST | `/api/supervision/logs` | Módulos de campo. `client_event_id` opcional |
| GET | `/api/supervision/sheets` | Fichas del supervisor autenticado |
| GET | `/api/supervision/sheets/{kind}/{id}` | Carta HTML de la ficha |
| GET | `/api/supervision/recommendations` | Recomendaciones registradas |

Apertura: todos los ítems EPP y vehículo en sí; si falta uno la API responde en español (`Debe confirmar: Guantes`). Validación 422 y fallos HTTP (401/403/419) también en español. La PWA muestra el texto **al centro ~2,8 s** (`#feedback`) además de `#status`. Cola offline por `user_id` (celular compartido). Panel: mismos avisos (`docs/DISENO-UI-ZENTRA.md`).

---

## Migraciones

Las columnas que eran `add_*` (username, email de zona, ruta, cola de cierre, branding, riesgos) viven en el `create_*` o en `2026_08_25_230000_add_supervision_packages_and_tracking`. Ese archivo **crea** las tablas de campo (zonas, turnos, flota, posts, revistas). Puestos↔empleados: `2026_09_10_220100_add_modality_and_employees_to_posts` (`supervisor_post_employee`).

- `2026_07_06_080400_create_installations_table` (incluye `code`, `kind`, geo, rector)
- `2026_07_06_080500_create_locations_table` (`installation_id`)
- `2026_08_25_230000_add_supervision_packages_and_tracking`
- `2026_08_26_110000_create_supervisor_field_ops_tables` (recomendaciones y logs, con campos de riesgo)
- `2026_08_26_223500_create_supervisor_document_types_table`
- `2026_08_26_224800_create_supervisor_control_book_types_table`
- `2026_08_26_230200_create_supervisor_weapon_catalogs_table`
- `2026_08_26_234500_create_supervisor_risk_types_table` (`supervisor_risk_type_id` en recomendaciones)
- `2026_09_10_220100_add_modality_and_employees_to_posts`

Tipos de estructura por empresa: `security_company_id` en `create_structure_types`. `users.username` y `email` nullable: `create_users`.

```bash
php artisan migrate
```

Tests contra `zentra_test` (`phpunit.xml`). No usar `migrate:fresh` en la BD `zentra`.

Tests: `SupervisorShiftApiTest`, `SupervisorFieldLogApiTest`, `SupervisorOfflineSyncTest`, `CompanySupervisionCatalogTest`, `CompanySupervisionFieldSheetTest`, `CompanySupervisionMapTest`, `AutoCloseExpiredSupervisorShiftsTest`, `BuildSupervisorTrailTest`, `ResolveSupervisorShiftDeadlineTest`, `SnapSupervisorTrailToRoadsTest`, `CompanyClientSiteTreeTest` (BD `zentra_test`).

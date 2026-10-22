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
| **Zonas** | `/company/supervision-zones` | `supervisor_zones` (rutas de Supervisión, no `common_zones`). Sin correo. Cada cliente tiene **una** zona principal (`clients.supervisor_zone_id`) y puede **compartirse** con otras (`supervisor_zone_shared_clients`). Modal **Asignar clientes a zonas** (buscador, filtros Todos / Sin zona / zona, tabla Cliente · Zona principal · Compartido con · Estado, lotes) y botón **Clientes** por zona. Aviso «N clientes sin zona». No se pega al usuario. |
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

PWA en `field-app/` (copia `public/campo/` y Laragon `Zentra_Supervision`). Caché SW `zentra-sup-v61`; en cada versión subir también `?v=` de `app.js` y `offline.js` en `index.html` (si no, el navegador sirve el JS viejo de su caché HTTP con el HTML nuevo). Hosting: `https://zentra.wcodex.cloud/campo`. APK Android v2.7 (versionCode 17, Capacitor) en `field-app/android/`; el binario se publica en `public/downloads/zentra-supervision.apk`. Selfie de inicio en el círculo de perfil.

**Horario del turno (21 oct 2026):** el fin programado es la franja de la plantilla que contiene la hora de apertura; se puede abrir hasta **2 h antes** del inicio (relevo anticipado). Fuera de su franja la plantilla no se abre y el mensaje dice cuál corresponde («Ahora corresponde Noche (18:00 – 06:00)»). El cierre automático (fin + 30 min, cada 5 min) nunca pone fin antes del inicio. Antes, un Noche abierto a las 17:30 o un Día abierto después de las 18:00 nacían vencidos y el sistema los cerraba a los pocos minutos.

**Volver a la app sin perder nada (21 oct 2026):** al abrir con sesión guardada sale «Cargando…» (no el login) y entra directo al hub con el turno del teléfono; el servidor se consulta por detrás. La app reabre la pantalla donde quedó (revista, módulo, Observatorio o cierre de turno) y lo escrito: campos, tarjetas repetibles y fotos de **todos** los módulos, el reporte del Observatorio y km/fotos del cierre. Se guarda en el teléfono mientras escribe y al minimizar; se borra al guardar, enviar o cancelar. Si el servidor ya no tiene el turno (cierre por horario o desde el panel), la app lo detecta (ping, revista o al volver) y lleva a **Abrir turno**; lo que estaba llenando se recupera al abrir el turno nuevo. El cierre encolado manda `shift_id` y no cierra un turno más nuevo.

**Cambio de dispositivo:** el supervisor queda atado a un celular (identificador `zentra_device_id` en el almacenamiento de la app; APK y PWA igual). El primer login lo registra solo. Desde otro celular el login responde `423` y crea **una** solicitud (modelo + GPS si hay) que vence en **10 min**; la app muestra «Cambio de celular» en espera. La aprueba o rechaza quien tenga el módulo **Cambio de dispositivo** (`company.devices.approve`) o el admin de empresa: alerta flotante con sonido en el panel y menú `/company/dispositivos`. Al aprobar, el celular viejo pierde el token y el turno abierto sigue en el nuevo. Borrar datos de la app cuenta como celular nuevo.

**Georreferencia:** entrada del hub para corregir el punto de una instalación desde el sitio. Busca el cliente y, si tiene varias instalaciones, la instalación (con una sola, la elige sola). Exige turno abierto, GPS **±30 m** o mejor y conexión (no entra a la cola). **Enviar georreferenciación actual** crea una solicitud **pendiente**; la instalación no cambia hasta que la empresa apruebe. Un nuevo envío para la misma instalación reemplaza el pendiente anterior. Panel: alerta abajo a la derecha con **Revisar** y Supervisión → **Georreferenciaciones** (`/company/supervision/georreferencias`, Pendientes / Historial). El modal muestra dos mapas (actual y solicitado), distancia y precisión; **Aprobar** mueve la latitud/longitud de la instalación (queda en auditoría) y **Rechazar** acepta un motivo opcional. Revisa quien tenga `company.geo.approve` o el admin de empresa.

Login: **usuario** (`nombre.apellido.####`, igual que el resto de usuarios de empresa) o el correo de cuentas antiguas, más contraseña. En login y primer cambio de clave, icono de ojo para verla. Alta: **Usuarios** → nombre y cédula del empleado → generar usuario y clave; primera entrada pide cambiar clave. El correo corporativo de avisos ya no va en la zona: lo lleva el responsable del cliente (admin de cliente interno, ver más abajo). API **siempre** Zentra: host `zentra_supervision` → mismo esquema + host `zentra` + `/api`; puerto `8085` → mismo host `:8084/api`. No hay campo de API. Instalación: **Descargas** en empresa (`/company/descargas`) y plataforma (`/admin/descargas`); QR + enlace (`SUPERVISION_PWA_URL`). Hard-refresh tras cambios de PWA.

Fotos: no se enciende la cámara al abrir. **Trasera** / **Frontal** o **Tomar foto** piden `getUserMedia` (hace falta HTTPS o localhost). Tras capturar se apaga. Galería solo si no hay contexto seguro.

1. Login (`POST /api/supervision/login`) → rito de **apertura**: turno y zona del catálogo, EPP/vehículo plegables, km + foto odómetro + selfie (cámara, no galería).
2. Hub: ficha de perfil + **Cerrar**. Entradas: **Revista**, **Alarmas**, **Apoyos**, **Documentos**, **Observatorio**, **Georreferencia**, **Mis fichas**. Ping GPS cada **15 s**. Cada ping manda `screen_on`. El mapa: **En línea** (GPS &lt; 90 s y pantalla encendida), **Pantalla apagada** (GPS &lt; 90 s y pantalla off) o **Sin señal** (más de 90 s sin GPS). **APK corte 2:** servicio en primer plano (notificación “Turno de supervisión activo”) sigue mandando GPS con la pantalla apagada. La PWA sigue pausando el JS al bloquear el teléfono.
3. **Revista** (al clic): cliente, puesto, vigilante, foto. Los módulos del puesto (inventario, libros de control, etc.) se registran en borrador. **Guardar revista** (abajo) envía GPS + foto + módulos juntos. El GPS debe estar a **≤ 80 m** del pin del puesto o, si no tiene pin, de la **instalación**. Crear o editar el puesto no pide GPS; un pin lejos de la instalación se anota y no bloquea el alta. Mientras envía: botón bloqueado y texto *Guardando revista…* (mismo candado en Entrar, iniciar/cerrar turno y Registrar). Un `client_event_id` por intento: el API no duplica. Si cancela, no queda nada.
4. Alarmas y apoyos: cada uno abre su formulario (cliente + GPS obligatorios). Apoyos pide **«¿El apoyo fue por una novedad?»** (sin/con novedad); solo «con novedad» cuenta como novedad (apps viejas sin la casilla siguen contando como novedad). Documentos: sin cliente ni GPS.
5. Cierre: km final + odómetro + selfie → sesión cerrada.

### Presentación personal y Capacitación en la revista (18 oct 2026)

Solo para la app **2.3+** (la app manda `app_build=23` en `offline-pack`, `catalog` y la revista; las apps viejas no ven estos módulos ni se les exige).

- **Presentación personal** (`uniform`, obligatoria si la empresa tiene prendas activas en **Ajustes → Presentación personal**, sale de primera en «En este puesto»): tabla Prenda | Novedad Sí / No | No aplica, una marca por fila, **sin valor por defecto**; no deja guardar la revista sin ella. Alguna prenda en Sí = novedad: observación obligatoria + 1 a 3 fotos (`uniform_1..3`). Estados `yes` / `no` / `na`; los registros viejos se leen Regular/Malo = Sí y Bueno = No (`UniformInspection`). Con novedad entra al correo de la revista.
- **Novedad automática de la revista (app 2.4):** si un módulo de la revista trae novedad (prenda en Sí, inventario Regular/Malo, libro con novedad, carpeta con faltantes, arma con novedad o permiso vencido, cualquier recomendación) la casilla «Con novedad» se marca sola, se bloquea y dice en qué módulos. Capacitación nunca. El servidor también la marca (`RecordSupervisorProReviewService`: log con resultado distinto de OK).
- **Campos compactos (app 2.4):** las opciones de una sola elección son listas que empiezan en «Seleccione…» (también cliente de alarmas y apoyos, tipos de catálogo); las preguntas Sí/No van en una fila. Fotos de turno, cierre y vigilante: recuadro con ícono que abre la cámara nativa del teléfono (flash, frontal/trasera dentro de la cámara); se reduce a 1600 px. Encabezado sin subtítulo, estado junto al título; la tarjeta del supervisor solo en el menú principal (2.5: foto tipo documento rectangular vertical, nombre y Turno / Zona / Placa / Km inicio). Los recuadros de fotos de módulos y Observatorio llevan ícono de cámara.
- **Capacitación** (`trainings`, opcional, una por revista, ligada al vigilante de la revista, nunca es novedad): Mensual/Personalizada · Virtual/En puesto · **Tema** del catálogo (**Ajustes → Temas de capacitación**) con buscador; si no existe el supervisor lo **crea** desde la app (`POST /api/supervision/training-topics`, sin duplicar por tildes ni mayúsculas; marca «Creado en campo»), no edita ni borra. Campo Tema (app 2.6): al tocarlo la primera opción es **«+ Crear tema»** (pasa a «Escribe tu tema» + botón **Agregar**, muestra los temas parecidos); al escribir para buscar la primera opción cambia a **«+ Agregar tema «…»»**; si ya existe igual solo se ofrece seleccionarlo; sin temas dice «Aún no hay temas. Cree el primero». En el panel (Ajustes → Temas de capacitación) se edita y borra; un tema con capacitaciones no se borra, se **desactiva/activa**; filtros Todos · Creados en campo · Inactivos. Personalizada pide motivo/falencia. En puesto pide descripción y **cronómetro** (Iniciar / Terminar): mientras corre no se puede salir del módulo, cerrar turno ni volver atrás (solo Terminar o Cancelar; pánico sí), sobrevive al cierre de la app y avisa a las 2 h. La duración la calcula el servidor con inicio y fin. 1 a 3 fotos (`training_1..3`). Tabla `supervisor_trainings` (único vigilante + tema + mes `period`).
- **Tema repetido en el mes:** con señal la app consulta `GET /api/supervision/trainings/check` y bloquea el tema; el paquete offline trae las capacitaciones del mes para bloquear sin señal. Si no hay forma de validar, avisa y al sincronizar el servidor **descarta** la capacitación repetida (no guarda fotos ni datos) y guarda el resto de la revista; la app muestra el aviso (`discarded_trainings`).
- **Revista en curso guardada en el teléfono:** cliente, puesto, vigilante, foto, módulos y el módulo abierto (con cronómetro) se guardan en IndexedDB; al reabrir la app se recupera. Si cambia el vigilante, presentación personal y capacitación se registran de nuevo.
- **Resumen y PPTX:** sección **Capacitaciones** (indicadores: total, personal capacitado, mensuales, personalizadas, virtual, en puesto, tiempo en puesto; serie por tipo y modalidad; por tema; cobertura mensual = vigilantes con revista en el periodo con capacitación mensual; tabla de personalizadas) , tabla **Personal capacitado** filtrable por vigilante, tema, tipo y modalidad) y **Presentación personal con novedad** (prendas con más novedades, vigilantes con más novedades, tabla vigilante · prendas · observación del supervisor). PPTX: diapositivas *Capacitaciones*, *Temas de capacitación*, *Personal capacitado*, *Presentación personal con novedad* y la tabla de novedades. La ficha imprimible muestra ambos módulos con sus fotos.

### Zonas, responsables y correos (18 oct 2026)

- **Qué clientes ve el supervisor:** los de la zona con la que abrió turno y los de las zonas añadidas en vivo, propios (zona principal) o compartidos (`SupervisorClientScope`). Se aplica a `sites`, `offline-pack`, `posts` y se valida al guardar revista, alarma y apoyo (422 «Este cliente no está en tus zonas…»). **Transición:** mientras la empresa no tenga ningún cliente con zona, ve todos. Sin turno abierto y con zonas en uso no ve ninguno. La app vuelve a pedir los clientes al abrir Revista o un módulo con cliente (cada 20 s como mínimo).
- **En vivo → Zonas:** por supervisor en turno; la zona del turno queda fija y se marcan zonas extra solo para ese turno (`supervisor_shift_zones`: quién, cuándo, quitada). Lo puede hacer quien tenga Ajustes en Gestionar (`company.settings.manage`). `GET/PUT /company/supervision/turnos/{shift}/zonas`. La fila muestra «Zona Sur + Norte».
- **Zona del registro:** revista, ficha y recomendación guardan `supervisor_zone_id` = zona principal del cliente (sin zona: la del turno). Filtro Zona de Resumen y Fichas usa esa zona; el cumplimiento por puesto cuenta los puestos de los clientes de esa zona. Registros viejos quedaron con la zona de su turno.
- **Responsable del cliente** = administrador de cliente **interno** con **correo corporativo** (`users.corporate_email`, no es login) y sus clientes asignados (varios responsables por cliente).
- **Correos de novedades** (`NotifySupervisionNoveltyService`, en cola): solo con novedad. Revista: **un** correo con la observación y todos sus módulos con novedad. Alarma y apoyo con novedad: uno cada uno. Documentos no. Llevan un enlace firmado de 30 días a la ficha (`/fichas/{empresa}/{tipo}/{id}`), sin iniciar sesión. Requiere correo de salida (`MAIL_*`) y un worker de cola en el servidor.

No hay usuario supervisor de semilla. El supervisor se crea en **Usuarios** a partir de un **empleado** de la empresa (`nombre.apellido.####` + clave generada; primera entrada cambia clave). Panel empresa: `empresa@sj-seguridad.test` / `Empresa123!`. Palmas tiene `has_supervision`. Vigilante piloto: cédula `1144001122`.

Sin puestos de Supervisión en la ficha del cliente no se guarda revista. Los `locations` (puertas) no entran en el combo.

**Cumplimiento por puesto:** la unidad es el **puesto** (cliente → instalación → puesto), no el cliente. Cada puesto activo espera sus **revistas contratadas por día** (`supervisor_posts.reviews_per_day`, por defecto 1, máximo 12; se fija al crear o editar el puesto según el acuerdo con el cliente) en cada **día operativo** (06:00 a 06:00 del día siguiente; la fecha es la del inicio) de sus **días de servicio** (Lun..Dom, atajos Lun a Vie / Lun a Sáb) y, si es festivo de Colombia, solo si «¿Presta servicio en festivos?» = Sí. Cuenta la **cantidad**, a cualquier hora (no hay franjas día/noche). Revistas de más en el día no suman; revistas en días sin servicio se guardan pero no cuentan. Un periodo espera Σ (revistas contratadas × días de servicio) = **revistas esperadas**; un puesto está **cumplido** si hizo todas sus revistas todos sus días. En el mapa en vivo un puesto a medias se ve como pendiente con «(1/2)». Lo usan el mapa en vivo, la tira del día, Resumen/PPTX y el panel Mi empresa (`BuildPostComplianceService`).

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

No es el Historial GPS ni el PPTX. Cada captura (revista, alarma, apoyo, documentos) genera una **ficha inmutable** en HTML carta (modal con PDF en el panel; ver abajo). Folio `FC-{año}-{R|A|S|D}{id}` (ej. `FC-2026-R000042`). La **ficha de turno** (`kind=shift`, folio `…-T…`) se arma al abrir turno y **solo se guarda** (`supervisor_shifts.sheet_snapshot`) al cerrar; en En vivo/Historial el botón **Ver**. No se guarda un archivo PDF: se arma al abrirla. Cabecera: logo, nombre y NIT de la **empresa** (Mis datos). Sin marca de la plataforma. La revista copia el **encabezado** al guardar (`sheet_intro`). Sitio: cliente, instalación, puesto.

| Origen | Contenido | Cliente | GPS |
|--------|-----------|---------|-----|
| Revista | Marco normativo, cliente / instalación / puesto, vigilante, foto, novedad y módulos | Sí | Sí (el de la revista) |
| Alarma | Tipo, modalidad, resultado | Sí | Sí |
| Apoyo | Tipo y motivo | Sí | Sí |
| Documentos | Entregados / pendientes | No | No |

Panel empresa: pestaña **Fichas** en `/company/supervision?tab=sheets` (filtros de fecha/zona/supervisor + tipo, cliente, novedad). `GET /company/supervision/fichas/{kind}/{id}` abre la carta. PWA: **Mis fichas** (`GET /api/supervision/sheets` y la misma carta autenticada). No mezcla minuta de portería ni `PlatformDocument`.

**Modal y PDF (18 oct 2026):** en el panel todo enlace a una ficha (Fichas, En vivo, Historial, mapa) abre un **modal** (`resources/js/sheet-modal.js`, la carta en iframe con `?embed=1`) con **Descargar PDF** (`?format=pdf`, `dompdf`, carta), **Imprimir** y **Cerrar** (Esc o clic afuera); sin pestañas nuevas. En la app la ficha se abre a pantalla completa dentro de la app, **solo para ver** (Cerrar o botón atrás); sin descargar ni imprimir (modo `app` de la vista). El enlace firmado del correo solo imprime. Tocar una foto o el mapa la amplía al centro (Esc o tocar para cerrar). Una sola plantilla (`ficha.blade.php`, en tablas para que el PDF salga igual); las fotos se recortan al recuadro en el servidor (`SheetImage`, GD) porque el PDF no entiende `object-fit`.

- **Revista:** foto del vigilante vertical tipo documento (210×280) con vigilante, cédula, cliente, instalación, puesto, supervisor, zona/turno, fecha y GPS a la derecha; chips de **módulos** con o sin novedad; presentación personal primero en tabla Prenda | Novedad Sí | Novedad No | No aplica con la observación resaltada; capacitación en bloque (tema, tipo, modalidad, duración con horas, motivo, descripción); inventario, libros, documentos y recomendaciones en tablas; armamento, alarma y apoyo en etiqueta/valor; firmas del supervisor y del vigilante. El estado de la cabecera se calcula con los módulos (también en revistas viejas).
- **Turno:** fila 1 selfie de inicio y de cierre («Pendiente cierre» si sigue abierto, «Cierre por el sistema») con supervisor, turno, zona, placa, inicio y cierre; fila 2 odómetro inicio y final con km inicio, final y **recorridos**; fila 3 **Recorrido del turno**: mapa híbrido con los mismos puntos del Historial (`BuildSupervisorTrailService`), ruta ámbar, I/F y revistas numeradas, más km por GPS, duración, revistas, alarmas/apoyos/documentos y paradas (`BuildShiftRouteMapService`, Maps Static API). Turno cerrado: la imagen se descarga una vez y queda en `storage/app/supervision/route-maps/shift-{id}.png`; abierto: se renueva cada 5 min («En curso: recorrido hasta las HH:mm»). Sin API o sin GPS: «Mapa no disponible por el momento». La selfie ya no se repite; preoperacional en cuadrícula y actividad en tabla.

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

`/company/supervision`: En vivo / Historial / Resumen / **Fichas**. En vivo: sin filtros de header; mapa + tabla (inicio, señal, km, revistas, **Ver**); clic en fila = ruta GPS; `GET /company/supervision/live.json`. Historial: Desde / Hasta / Supervisor; mapa vacío hasta clic; callejero (`GET /company/supervision/turnos/{shift}/ruta`); **Ver** ficha guardada. Fechas (Historial, Resumen, Fichas): un solo botón **Periodo** (ícono de calendario + periodo escrito) abre un panel con atajos (Hoy, Ayer, Este mes, Mes anterior, Este año), un calendario (un día) y el check **Rango** (dos meses: desde y hasta); **Aplicar** filtra. Historial, Resumen y Fichas: header con **Periodo** + botón **Filtros** (contador de activos) que abre un panel; **Aplicar** filtra todo de una vez y **Limpiar** vacía el borrador (`supervisionFilters`, `partials/filters-panel`). Campos: Historial = Supervisor; Resumen = Zona y Supervisor; Fichas = Zona, Supervisor, Tipo, Novedad y Cliente. Los filtros activos se ven como chips arriba del contenido; la ✕ quita uno y «Limpiar todo» deja solo el periodo. **Descargar PPTX** solo en Resumen. Tarjeta **Cumplimiento por puesto**: «N de M revistas esperadas» + nota de cómo se calcula; el PPTX dice lo mismo y explica las revistas esperadas. Resumen: 5 KPIs (Cumplimiento por puesto, **Puestos cumplidos** = puestos con todos sus días de servicio revisados, Revistas + con novedad, Km, Recomendaciones); chips **Atención** solo si hay alarmas reales/en falla, recomendaciones extremas, turnos abiertos > 12 h o documentos pendientes; gráficos; tabla **Clientes** a todo el ancho (todos los clientes con puestos en servicio: instalaciones, puestos, cumplidos, revistas, novedad, %) con semáforo rojo = ninguna revista, ámbar = pendientes, verde = cumplido, orden rojo → ámbar → verde y alfabético; clic en la fila = puestos pendientes; tabla **Por supervisor** a todo el ancho (turnos, revistas, km, alarmas, apoyos, registros). El PPTX usa los mismos KPIs, la tabla de clientes con semáforo (14 por lámina) y la lámina Atención solo si hay algo. PPTX: `GET /company/supervision/informe.pptx`. Compositor IA: § Informe PPTX (pendiente).

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
| GET | `/api/supervision/intake` | Zonas (id, nombre), turnos, EPP, vehículo, flota |
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

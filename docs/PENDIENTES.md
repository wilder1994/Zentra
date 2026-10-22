# Pendientes (Zentra)

Lista viva de lo definido y **no implementado**. Al implementar, mover a «Hecho» con fecha y commit.

## Abiertos

### 6. Integraciones API: cerrar el login personal y aviso en empresa
Definido 21 oct 2026. Las API keys ya existen (ver Hecho 21 oct · Integraciones API).

- Evaluar que la API del Observatorio solo acepte API keys y no el login personal (`POST /api/auth/login`); hoy acepta ambos.
- En el panel empresa: «¿Necesitas conectar el Observatorio con otro sistema? Contacta a soporte».
- Al revocar o regenerar se borra el token y con él su «último uso»; si se quiere historial, guardar el último uso en `api_integration_keys`.

### 7. Siete tests viejos fallan (portería con turno de guarda y panel empresa)
Visto 21 oct 2026: fallan igual en el commit `f94c887` (antes de los cambios del día), en `zentra_test`.

- `CorrespondenceTest` (3), `OperateAsRoleTest::real_guard_cannot_open_admin_porteria_urls`, `ClientMemberMinorProtectionTest::client_admin_sees_minor_document_but_vigilante_does_not_at_door`, `CompanyClientExpedienteTest::doors_module_blocks_client_admin_but_not_vigilante`: el vigilante recibe 302 (lo manda a iniciar turno de portería desde el 15 oct) o 422.
- `TenantIsolationTest::company_admin_can_access_company_panel`: espera el texto «Cartera de clientes», que ya no está en Mi empresa.
- Falta: abrir turno de guarda en esos tests (como `PorteriaRevistaTest`) y actualizar el texto esperado.

### 4. Mapa del recorrido en la ficha de turno: activar Maps Static API
Definido 18 oct 2026. Código listo (`BuildShiftRouteMapService`).

- 18 oct: el usuario activó **Maps Static API**. Falta ver una ficha de turno con el mapa en producción (turno con GPS).
- Si la clave está restringida por sitio web, incluir el dominio de `APP_URL` (la petición sale del servidor con ese `Referer`); si es por IP, `82.25.66.93`.

### 5. App de campo en tema claro: textos ilegibles
Definido 18 oct 2026 (visto en producción). Código: `field-app/index.html` (CSS).

- En tema claro, `input, select, textarea, button` tienen `color: #fff` fijo: el valor de las listas y campos (p. ej. «Día», «Norte») sale blanco sobre fondo claro. El botón «Tema» casi no se ve y los subtítulos de los botones del menú tienen poco contraste.
- Arreglo: usar `var(--ink)` en tema claro para campos y `.theme-btn`; revisar contraste de `.entry span`. Requiere PWA nueva y la APK siguiente (la 2.7 del 21 oct no lo incluye).

### 2. Recorte de planilla PILA pesa 1,14 MB por empleado
Definido 15 oct 2026. Código: `app/Services/Personnel/PilaPlanillaClipper.php`.

- Causa: el recorte copia el xlsx original completo; la imagen de fondo `xl/media/image1.png` (1,1 MB) se repite en cada empleado. SJ: 1.065 recortes ≈ 1,15 GB.
- Arreglo: quitar la imagen pesada (o reducirla) y limpiar `sharedStrings.xml` para que no queden textos de otros cotizantes (privacidad). Meta ~50 KB por recorte.
- Después: comando para regenerar o adelgazar los recortes existentes.
- Detalle: [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md) (Parafiscales).

## Hecho

### 21 oct 2026 · Turnos del supervisor: relevo anticipado y turno cerrado por el sistema (sin commit)
Reporte de campo: «abro a las 17:30 y a las 18:00 me cierra» y «cierro sesión, entro con otra zona y me sale sin turno pero me deja pasar revista, luego no aparece». Causa común: `ResolveSupervisorShiftDeadlineService` calculaba el fin del turno en la mañana del mismo día (Noche abierto antes de las 18:00) o en el pasado (Día abierto después de las 18:00) → el cierre automático (cada 5 min) lo cerraba al instante. Casos en datos: Giors 12 oct (Noche 17:53), Esneyder 14 oct (Día 20:10), Jorge 14 oct (Día 18:18 y 23:17, 0 revistas).

- Fin programado = franja de la plantilla que contiene la hora de apertura, con relevo hasta 2 h antes del inicio. Fuera de franja: no se puede abrir («Ahora corresponde Noche (18:00 – 06:00)»); el cierre automático nunca pone fin antes del inicio.
- App: si el servidor ya no tiene el turno (ping, revista o al volver a entrar), lleva a «Abrir turno» con aviso y conserva la revista en curso, que se recupera al abrir el turno nuevo. El cierre guardado en el teléfono manda `shift_id` y no cierra un turno más nuevo.
- Tests: `SupervisorShiftScheduleTest`, `ResolveSupervisorShiftDeadlineTest`.
- Además (mismo día): arranque con «Cargando…» en vez del login, reabre la pantalla donde quedó y guarda en el teléfono lo escrito en cualquier módulo, Observatorio y cierre de turno. **PWA v61 + APK 2.7 (versionCode 17).**

### 21 oct 2026 · PPTX del Observatorio al nivel de Supervisión (sin commit)
`ExportObservatoryBoardService` (estilo de `ExportSupervisionExecutiveReportService`) + `BuildObservatoryExportDataService` + `RenderObservatoryHeatMapService`. Test: `ObservatoryCompanyManageTest::test_board_pptx_follows_scope_and_filters`.

- Portada con logos y periodo → Resumen (5 tarjetas, donas/barras, texto) → Mapa de calor (Static Maps oscuro + manchas por tipo + folios; sin clave dibuja sobre fondo liso) → una diapositiva por tipo (barras por día/mes, dona de estados, texto) → Instalaciones por riesgo (tabla con semáforo + barras + texto, hasta 3 páginas) → Gracias.
- Respeta alcance y filtros: empresa, cliente o instalación; fechas, estado, tipo (solo ese tipo), comuna y texto. Colaborador con clientes puntuales solo ve los suyos (tablero, en vivo y PPTX).

### 21 oct 2026 · Integraciones API en la ficha de empresa (súper admin) (sin commit)
Ruta `/admin/companies/{id}/integraciones`. Migración `2026_10_21_200000` (tabla `api_integration_keys` + `personal_access_tokens.is_integration`). Tests: `ApiIntegrationKeysTest`. Detalle: `OBSERVATORIO.md` (API).

- API key = token Sanctum de **un usuario** de la empresa (existente o externo nuevo: colaborador con Observatorio en Ver, en toda la empresa o en clientes elegidos, clave aleatoria).
- Permisos por módulo (Observatorio, Portería): Sin acceso / Lectura / Lectura y escritura / Personalizado por endpoint. Cada ruta de `routes/api.php` declara `api.ability:<clave>` (`ApiAbilities`); la app de campo y cerrar sesión son `api.ability:human` (una key no entra).
- Listar, regenerar (la anterior deja de servir) y revocar. La key se muestra una vez. Ayuda «?» tipo manual en la pestaña.
- El login personal y la app de campo ya no borran las API keys del usuario.
- «Entrar como empresa» deja de ser pestaña: botón verde **Entrar** con ícono de puerta junto a «← Empresas».
- Dos manuales descargables en PDF (súper admin y técnico externo): **Manual de API Key** y **Manual de Integración** (+ Swagger y respuestas 200/201/401/403/422). Ruta `admin.companies.integrations.manual` (`api-key` | `integracion`); contenido en `views/modules/admin/companies/manuals/`.
- `/docs/observatory` rediseñada: pasos Autorizar → endpoint → Ejecutar, etiquetas de Swagger en español, más contraste, el `curl` muestra `TU_API_KEY` y el contrato OpenAPI habla de API key (no del login personal).

### 21 oct 2026 · Observatorio: filtros en el encabezado, empresa gestiona y tema claro (sin commit)
Detalle: `OBSERVATORIO.md` (Tablero y Eventos, Estados, Tipos). Tests: `ObservatoryCompanyManageTest` + Observatorio (51 en `zentra_test`).

- Encabezado empresa y cliente: Tablero `[Periodo] [Filtros (n)] [Compartir link] [Descargar PPTX]`; Eventos `[Periodo] [Filtros (n)]`. Fuera: Cliente, buscador, Filtrar, Líneas y API.
- Modal Filtros: buscador cliente/instalación, Estado, Tipo, Comuna y (Eventos) «Texto o folio». Chips. Filtra tarjetas, mapa, gráficas, en vivo y PPTX. Grano automático (≤62 días por día, más por mes). Periodo por defecto: 1 ene → hoy (arregla «Día a día» en cero).
- Empresa con Gestionar: cambia estado, une y saca reportes; Tipos por cliente (crear, editar, eliminar) y «Copiar estos tipos a otros clientes».
- Tema claro del tablero (tarjetas, leyenda, gráficas). Leyenda del mapa en la ficha con los tipos del folio.

### 18 oct 2026 · Correos de novedades en producción (Brevo)
Worker `zentra-queue.service` + SMTP Brevo (`smtp-relay.brevo.com:587`, TLS). Remitente `Zentra <noreply@zentra.wcodex.cloud>`, dominio autenticado en Brevo (TXT `brevo-code`, DKIM `brevo1/brevo2._domainkey.zentra`, DMARC `_dmarc.zentra`) en el DNS de Hostinger. Probado: envío directo y por cola (sin fallidos). Plan gratis: 300 correos/día. Detalle: `HOSTING-VPS.md`.

### 18 oct 2026 · Fichas en modal + PDF, ficha renovada y temas de capacitación (commit 92e9be8, desplegado 18 oct)
APK 2.6 (versionCode 16) + PWA v59. Dependencia `barryvdh/laravel-dompdf`. Tests: `SupervisionSheetModalTest`. Detalle: `SUPERVISION-CAMPO.md` (Fichas de campo).

- Panel: la ficha abre en modal (Descargar PDF, Imprimir, Cerrar) desde Fichas, En vivo, Historial y mapa. App: pantalla completa solo para ver. Zoom al tocar fotos y mapa.
- Revista: foto del vigilante tipo documento con datos a la derecha, chips de módulos, tablas por módulo, observación resaltada, firmas; novedad calculada con los módulos.
- Turno: selfies inicio/cierre («Pendiente cierre»), odómetros con km recorridos, mapa del recorrido (falta activar la API, ver Abiertos 4), preoperacional en cuadrícula y actividad en tabla. La selfie no se repite.
- App: Tema con «+ Crear tema» / «Agregar tema». Panel: tema usado se desactiva; filtros Todos · Creados en campo · Inactivos.

### 18 oct 2026 · App 2.4–2.5: campos compactos y novedad automática de la revista (commit 92e9be8, desplegado 18 oct)
APK 2.5 (versionCode 15) + PWA v58. En 2.5: tarjeta del supervisor con foto tipo documento y Turno / Zona / Placa / Km inicio (sin texto del GPS); ícono de cámara en los recuadros de fotos de módulos y Observatorio. Tests: `SupervisorUniformAndTrainingTest`. Detalle: `SUPERVISION-CAMPO.md`.

- Presentación personal primero, en tabla Prenda | Novedad Sí / No | No aplica (estados `yes`/`no`/`na`; lo viejo Regular/Malo = Sí, Bueno = No).
- «Con novedad» de la revista se marca y bloquea sola si algún módulo trae novedad (recomendaciones cuentan, capacitación no); el servidor también la marca.
- Listas desplegables con «Seleccione…» en vez de tarjetas; Sí/No en una fila; fotos de turno/vigilante con ícono de cámara nativa; encabezado sin subtítulo y tarjeta del supervisor solo en el menú principal.
- Resumen y PPTX: prendas y vigilantes con más novedades, tabla con observación del supervisor, «Personal capacitado» filtrable.
- Corregido: buscar vigilante con menos de 3 dígitos rompía la búsqueda.

### 18 oct 2026 · Revista: Presentación personal y Capacitaciones (commit 92e9be8, desplegado 18 oct)
App 2.3 (APK + PWA v55). Migración `2026_10_18_220000`. Tests: `SupervisorUniformAndTrainingTest`. Detalle: `SUPERVISION-CAMPO.md`.

- **Presentación personal** (obligatoria en la revista, ligada al vigilante): catálogo de prendas en Ajustes; por prenda Bueno / Regular / Malo / No aplica, **sin valor por defecto**. Novedad automática si hay Regular o Malo, o manual; con novedad: observación obligatoria + 1 a 3 fotos. Entra al correo de la revista.
- **Capacitaciones** (opcional, una por revista, ligada al vigilante, nunca es novedad): Mensual/Personalizada, Virtual/En puesto, tema del catálogo (Ajustes; el supervisor puede crear desde la app, no editar ni borrar), motivo si es personalizada, descripción y cronómetro si es en puesto (módulo bloqueado mientras corre, sobrevive al cierre de la app, aviso a las 2 h), 1 a 3 fotos. Un vigilante no repite tema en el mismo mes: con señal se bloquea al elegir; sin señal se avisa y al sincronizar la capacitación repetida se descarta (la revista se guarda).
- **Resumen y PPTX**: indicadores y gráficos de capacitaciones (periodo por tipo/modalidad, por tema, cobertura mensual, personalizadas) y presentación personal con novedad (prendas, vigilantes); 3 diapositivas nuevas.

### 18 oct 2026 · Zonas con clientes, responsables de cliente y correos de novedades (commit 92e9be8, desplegado 18 oct)

- **Zonas** (Ajustes → Zonas): sin correo. Cada cliente tiene **una** zona principal (`clients.supervisor_zone_id`) y puede **compartirse** con otras (`supervisor_zone_shared_clients`). Modal **Asignar clientes a zonas** (buscador, filtros Todos / Sin zona / cada zona, tabla Cliente · Zona principal · Compartido con · Estado calculado, lotes). Botón **Clientes** por zona abre el mismo modal filtrado. Aviso «N clientes sin zona». Ficha del cliente: «Zona: Sur · compartido con Norte».
- **App supervisor**: solo ve clientes propios + compartidos de las zonas activas del turno; el servidor valida al guardar. Transición: si la empresa no tiene ninguna zona con clientes, ve todos. **Apoyos** con casilla «¿Con novedad?». Documentos sin cambios.
- **En vivo**: botón **Zonas** por supervisor en turno; zonas añadidas solo ese turno, con quién y cuándo (`supervisor_shift_zones`). Permiso: Ajustes en Gestionar.
- **Zona del registro**: cada revista y ficha guarda la zona principal del cliente; filtro Zona de Resumen y Fichas usa esa zona.
- **Responsable = admin de cliente interno**: correo corporativo (sin correo personal); varios clientes y varios responsables por cliente; «Permisos por cliente» con **Aplicar a todos** + clientes plegados (Igual a todos / Personalizado). **Portería solo lectura** para este rol.
- **Correos**: solo con novedad. Revista: un correo al cerrarla con todas sus novedades. Alarma y apoyo con novedad: un correo cada uno. Destino: correo corporativo de los responsables del cliente. En cola, con enlace firmado a la ficha. SMTP y worker del VPS: ver Abiertos.


### 17 oct 2026 · Mapas: pines en gota y agrupación por zoom (todos los módulos)
- Gota única para pines y grupos (número en la cabeza), colores e íconos de cada convención intactos. `map-pins.js` + `map-cluster.js` (`@googlemaps/markerclusterer`).
- Instalaciones: Mapa + «Instalaciones y servicios» arriba, Novedades + Revistas abajo; contenedores a 384 px y KPIs compactos (~65 px, −40 %); tarjeta del pin fija en la esquina del mapa.
- Aplicado en Instalaciones, Mi empresa, Supervisión, Observatorio, Plataforma y panel cliente. Detalle: `DISENO-UI-ZENTRA.md` §10.1.
- Tarjeta en el mapa de Plataforma (Ver empresa, Entrar como empresa) y del panel cliente. Botón **Compartir ubicación** (copiar, WhatsApp, Google Maps, menú del celular) en todas las tarjetas. Sin íconos de lugares de Google en los mapas.

### 17 oct 2026 · Rediseño de Instalaciones (empresa)
- Selector de cliente en el header (`x-ui.filter-picker`: lista al abrir, filtra al escribir) que filtra toda la vista y el refresco en vivo.
- KPIs con ícono y contexto (sin ubicación, vigilantes, revistas cumplidas/esperadas con barra, hora de la última novedad).
- Mapa al estilo de Supervisión (Satélite/Terreno, pines SVG con número de servicios, tarjeta al clic).
- «Servicios actuales» → **Instalaciones y servicios** (fila por instalación, puestos desplegables, buscador interno). Se quitó la tabla paginada y el buscador de abajo.
- El panel de cliente conserva su tablero (`sig-board`). Tests: `CompanyInstallationDirectoryTest`, `SigBoardAndPanicTest`.

### 17 oct 2026 · Revistas contratadas por puesto, filtros en modal y tema a la derecha
- Puesto: «Revistas contratadas por día» (`supervisor_posts.reviews_per_day`, 1–12, por defecto 1). Cumplimiento cuenta la cantidad en el día operativo, a cualquier hora; las de más no suman. Migración `2026_10_17_200000`.
- Resumen/PPTX: «N de M revistas esperadas» + explicación (antes «puesto-día»). Mi empresa: ayuda de Cobertura actualizada.
- Historial y Resumen usan el mismo panel **Filtros** de Fichas (chips + Limpiar todo).
- Ícono de tema al final del header en empresa, plataforma, cliente y portería.
- Tests: `PostComplianceTest`, `CompanySupervisionMapTest`.

### 16 oct 2026 · Desactivar líneas Accesos / Supervisión / Observatorio (temporal vs archivar)
- En Editar cliente, al desmarcar una línea el sistema exige elegir: **temporal** (libera cupo, datos intactos) o **archivar** (archivo operativo + retención `service_line_retention_days`, luego purga en `data:purge-retention`).
- Reactivar exige cupo; si estaba archivada, restaura puertas/puestos/folios marcados `archived_by_service_line`.
- Intake `/o/{slug}` exige `has_observatory`. Tablero Observatorio ignora folios con `archived_at`.
- Migración `2026_10_16_163000`. Tests: `ClientServiceLineDeactivateTest`.

### 15 oct 2026 · Comercial Super Admin (propuesta + cotización PDF + presentación PPTX)
Detalle: [`COMERCIAL-DOCUMENTOS.md`](COMERCIAL-DOCUMENTOS.md).

- Nav `/admin/comercial` (permiso `platform.companies.view`).
- Propuesta comercial: textos editables + planes Bronce/Plata/Oro/Platino del catálogo (FPDF).
- Cotización: plan personalizado (`planPicker`) + total comercial editable + nota; PDF con catálogo y total pactado.
- Presentación comercial: PowerPoint 16:9 · 11 diapositivas · paleta clara · tarjetas redondeadas con sombra · portada hero-dashboard · capturas panel empresa (Supervisión/Observatorio con scroll stitch) · cierre «Gracias» · sin planes/gráficas.
- Marcas Zentra · W Codex; presentador en texto (sin imagen de firma).
- Assets: `resources/images/commercial/deck/`. Tests: `CommercialDocumentsTest`.

### 15 oct 2026 · Turnos de portería (guarda real) + minuta

- Login guarda → exige iniciar turno (plantilla del catálogo empresa: Día/Noche/A/B/C).
- Hora servidor automática; nota opcional; `GuardShift.shift_template_id`.
- Abrir/cerrar escriben línea en minuta compartida de la puerta (`GuardLog` tipo `turno`).
- Sin cerrar turno no puede cerrar sesión. Operar portería (súper/empresa) no obliga turno.
- Tests: `GuardShiftFlowTest` + ajustes Portería/Booking/Authorization.

### 15 oct 2026 · Mi empresa: cobertura, personal y sin «conjuntos»

- Pulso (cobertura, personal, parafiscales, documentación, clientes, riesgo).
- Mapa de cobertura + cartera con barras de cupo por producto.
- Bloque Personal: empleados, donut/tendencia parafiscales, expediente base (HV/contratación/afiliaciones).
- Copy UI: «conjuntos» → cliente/instalación en Mi empresa, cartera operar, accesos, usuarios, portería.
- `BuildCompanyPersonnelPulseService` + live JSON `ops.personnel`.

### 15 oct 2026 · Panel empresas, Supervisión por puesto, cupos y Resumen (1, 1d, 1f, dashboard)

- `/admin/companies`: 3 tarjetas (Riesgo = suspendidas+vencidas; Total empresas; Clientes). Tabla con una línea por producto (metal, cantidad, unidad, hardware, valor), chip Personalizado, cupo usado/contratado por producto, total contratado.
- Supervisión se cobra/cuenta **por puesto** (`CommercialProduct::countsPosts`, `InstallationQuota::usedPosts`, `PostQuota::assertPostRoom` tope empresa). Texto en `/planes` y docs.
- Migración `2026_10_18_120000_sync_supervision_posts_and_overages`: sube cupos al uso real (incl. Accesos/Observatorio/Indexación desbordados) y mantiene el precio pactado.
- Resumen `/admin`: «conjuntos» → «clientes».
- Docs: `MODELO-COMERCIAL-PAQUETES.md`, `PAQUETES-ACCESOS-Y-SUPERVISION.md`.

### 15 oct 2026 · Ficha de empresa: cupos, ampliar producto, precio pactado, «Aplica» (1b, 1c, 1e)
Detalle en `MODELO-COMERCIAL-PAQUETES.md`.

- Cupos y ampliaciones: una barra por producto contratado (usado / contratado + metal); casillas de puestos por instalación, empleados por puesto y empleados totales.
- Ampliar producto del plan: solo lo contratado, salto de metal automático, precio sugerido editable, motivo obligatorio; queda como precio pactado y en Movimientos («Ampliación de cupo»). Migración `2026_10_18_100000` (`scheduled_amount*`).
- «Precio pactado» pasó de la tarjeta del plan al bloque de cupos.
- Cambiar plan y Ampliar: lista Aplicar ya · Aplicar el día… (calendario) · Aplicar al corte.

# Observatorio (tablero + API)

Intake de reportes escolares y seguimiento de eventos. No es portería ni PQRS de Supervisión.

**Última actualización:** 25 noviembre 2026

## Piezas

| Pieza | Qué es |
|--------|--------|
| **Reporte** | Denuncia. Canal (`source`) + quién (`reporter_role`). Puede llevar pin (lat/lng). |
| **Evento** | Incidente. Folio `EV-000123`. Varios reportes del mismo colegio y tipo se unen si el evento sigue abierto y el último reporte de ese tipo fue hace menos de 1 hora (`OBSERVATORY_MERGE_WINDOW_MINUTES`). Cerrado nunca recibe más reportes. El admin de esa instalación puede unir folios a mano o sacar un reporte a un folio nuevo. |

## Fuentes

En la ficha: `tipo · Canal · Rol · fecha` y abajo el nombre, o **Anónimo**.

| Quién | Dónde | Canal · Rol |
|--------|--------|-------------|
| Alumno, padre, vecino | Link `/o/{slug}` | Link público · Alumno / Padre / Vecino |
| Rector / apoyo | Panel `/client/observatory` (ya tienen usuario) | Panel · Rector / Apoyo |
| Supervisor de patrulla | PWA Supervisión → Observatorio | App de patrulla · Supervisor |
| Vigilante | Minuta, novedad, si la instalación es colegio y tiene puerta | App de patrulla · Vigilante |
| Software de Secretaría | `POST /api/observatory/reports` | API · Sistema externo |

Anónimo (todas las superficies): aviso «se oculta tu nombre y teléfono; solo se muestra la denuncia». No se guarda nombre, teléfono ni `reported_by_user_id`. Sí quedan canal y rol.

## Público `/o/{slug}`

Ejemplo: `/o/palmas-del-ingenio`. Solo instalaciones con perfil de sitio `observatory_community` (por defecto Colegio) y activas. Tres pasos, móvil, sin app:

1. Colegio (nombre o DANE)
2. Qué pasó + tipo (amenaza / riña / hurto / otro) + mapa satélite (pin en el colegio; se arrastra) + hasta 3 fotos (cámara y miniatura; opcional)
3. Quién eres (alumno / padre / vecino) + anónimo o identificado + «Recordarme en este teléfono»

Aviso de menores (Normoteca). Anónimo no guarda nombre ni teléfono. Alumno: el nombre de ese reporte no se guarda en el teléfono (Ley 1581). Padre/vecino: si marcan recordarme, el siguiente ingreso en **ese** celular salta a rellenar colegio/hecho. Otro celular empieza de nuevo.

Si no mueven el pin, se guarda el del colegio. Sin pin de colegio ni API, las coordenadas quedan vacías.

En el Tablero, **Compartir link** abre el modal con la URL `/o/{slug}` (Copiar / Cerrar). El listado hace scroll (`max-h` 90vh); con más de 6 clientes hay buscador. Rector y apoyo reportan desde **Nuevo reporte** (botón en el header del panel cliente, slot `$actions`). Empresa o admin del cliente ven el botón; al abrir, el sistema dice que con ese usuario no pueden reportar.

## App de patrulla

Turno abierto. Entrada **Observatorio** (aparte de la revista). Colegio, tipo, texto, **mapa con pin arrastrable**, **1 a 3 fotos** (cámara, miniaturas; la primera es obligatoria), anónimo. Colegios de la empresa en el paquete offline. `POST /api/supervision/observatory/reports` con `photos[]` y lat/lng.

Un reporte nuevo abre overlay palpitante + sonido en bucle hasta **Enterado** (solo quien tiene Observatorio). **Quien reporta no oye ni ve la alerta.** Poll `GET /company/ops/alerts.json` y `/client/ops/alerts.json`. El pánico por usuario lo oyen quienes tienen **Atención de pánicos** o **Supervisión**; Observatorio no alcanza, y viceversa. Quien tiene `ops.panic.attend` ve **Atender** (ficha en `/company/panics`); el primero se queda la ficha y el poll deja de devolver ese pánico: overlay y sonido se apagan en todos. **Enterado** silencia y recuerda el id en ese navegador; los demás siguen oyendo. El botón Pánico (panel y APK) dispara al toque, sin confirmar.

## Minuta

En el libro de minuta (`/access/guard_logs`), asunto **Novedad** y ubicación de un **colegio con puerta**: check «También al Observatorio» + tipo + anónimo. Sin puerta de colegio, no se muestra. No es el pánico.

## Estados

`nuevo` → `en_atencion` → `cerrado`. **Los gestiona el admin de instalaciones** de esa instalación (`client-installation-admin` con `installation_permission=admin`) y, desde el panel empresa, quien tenga **Gestionar** en Observatorio (admin de empresa; colaboradores según su matriz), en la ficha:

- **Agregar:** observación obligatoria. Desde Nuevo pasa a En atención. Si ya está en atención, el folio no cambia y la nota va a la bitácora.
- **Cerrar folio:** solo En atención; observación obligatoria. No se reabre.

Cancelar cierra el recuadro, no el folio. El apoyo ve, reporta y no cambia estado ni une. `client-admin` y la empresa con **Ver** solo leen. El botón **Nuevo reporte** se ve siempre en el panel cliente (y si la empresa opera el cliente); si el usuario no es rector/apoyo, al abrir dice que con ese usuario no puede reportar.

El mismo admin (o la empresa con Gestionar) puede **unir** otro folio del mismo colegio o **sacar** un reporte a un folio nuevo (el evento debe tener al menos dos reportes). No se une ni se saca de un evento cerrado.

## Quién ve

| Actor | Superficie |
|--------|------------|
| Comunidad | `/o/{slug}` |
| Admin instalaciones / apoyo | `/client/observatory/events` — solo sus instalaciones. Solo el administrador (no el apoyo) cambia estado, une folios o saca un reporte. Ambos pueden **Nuevo reporte**. |
| Admin del cliente | Mismo listado, todo el cliente. Ve **Nuevo reporte** pero no puede enviarlo. Sin cambiar estado ni unir. |
| Empresa | `/company/observatory/events` — clientes de la empresa. Con **Gestionar** (`observatory.events.update`): cambia estado, une, saca reportes y edita tipos. Con **Ver**: solo lectura. |
| Colaborador con Observatorio | Mismo panel empresa. Grant a nivel empresa: todos los clientes y el **detalle** del evento. Grant a cliente o instalación: ese alcance. |
| Supervisor | PWA Observatorio |
| Vigilante | Minuta → Observatorio si hay puerta de colegio |
| Software de Secretaría | API key (Integraciones API del súper admin). Solo los endpoints y clientes que se le dieron |

Permisos: `observatory.view`, `observatory.events.update`. Tras el alta: `php artisan db:seed --class=RoleAndPermissionSeeder`.

## Unir / sacar (v1)

En la ficha del evento (`/client/observatory/events/{id}` o `/company/observatory/events/{id}`), el admin de esa instalación o la empresa con Gestionar:

- **Unir aquí:** elige otro folio del mismo colegio. Los reportes pasan a este evento y el otro folio se elimina. No se une a un evento cerrado.
- **Sacar a folio nuevo:** en cada reporte, si el evento está abierto y tiene dos o más. Crea un `EV-` nuevo (`nuevo`) con ese reporte. No se saca de un evento cerrado.

`client-admin`, apoyo y empresa con Ver no ven esos botones.

## Tablero y Eventos

Pestañas **Tablero** | **Eventos** (cuelgan del header, no van dentro de la barra).

- **Encabezado** (slot `actions`, empresa y cliente): Tablero = `[Periodo] [Filtros (n)] [Compartir link] [Descargar PPTX]`; Eventos = `[Periodo] [Filtros (n)]`. Periodo = `period-picker` de Supervisión; sin `from/to` se usa 1 ene del año → hoy. Filtros (`observatoryFilters`, `partials/filters-panel`): buscador de cliente o instalación (`client_id` / `installation_id`; en cliente solo sus instalaciones), Estado, Tipo de evento (slug), Comuna y, en Eventos, «Texto o folio» (`q`: título, reporte, instalación, DANE, cliente, `EV-000024` o `24`). Chips con × y «Limpiar todo». Todo filtra tarjetas, mapa, gráficas, medidor, eventos, en vivo y PPTX (`ObservatoryFilters`). Grano automático: hasta 62 días por día, más por mes. Sin botón API. Fila 1: mapa + leyenda de tipos. **Todas** encuadra Cali y pinta las 22 comunas. Una comuna hace zoom a ese polígono. Calor = círculos (Google quitó HeatmapLayer). En la misma fila: mapa (mitad del ancho), convenciones y supervisores en turno (60% del resto, sin Ver ni Zonas) y tipos con nivel (40% del resto). La moto se ve siempre. El primer clic acerca el mapa a su recorrido; el segundo quita la línea y vuelve a mostrar todo el mapa. Debajo van las burbujas de reportes (revista, alarma, apoyo y observatorio; cada una solo con los datos que ese tipo trae; lo más nuevo arriba). El mismo listado está en Mi empresa. Después: **Riesgo por instalación** (ranking por R final, scroll interno). El semáforo es un cilindro horizontal de ECharts (mismo trazo que los cilindros acostados de Decowandy: cuerpo recto, tapa plana a la izquierda y disco solo en la punta). El color sale de la banda del motor. Si no hay R final, el tubo queda vacío. Fila siguiente, dos columnas al 50% del ancho del tablero: **Tendencia endógena** (Mes a mes o Día a día, línea) y **Días con más reportes** (cilindro vertical; la altura es la cantidad de ese día). Fila final: **Orígenes** (cilindros horizontales, en este orden: Alumno, Padre, Vecino, Usuario panel, Usuario API, Usuario app patrulla; se suma otro origen agregando una fila en `BuildObservatoryBoardService::originDefinitions`; en cero se ve el tubo vacío y la etiqueta) · **Fuentes** (dona) · **Atención de folios** (arco verde→rojo; % en el hueco; leyenda Cerrados / En trámite / Total; `load_rate` = `(nuevos + en atención × 0,4) / total`). Esos cinco gráficos son ECharts y se cargan solo en el tablero. En vivo: sin sondeo periódico. Reverb avisa al guardar un reporte, al cambiar un folio, al unir o al separar, y también con revista, alarma y apoyo. Ese aviso pide el tablero una vez y redibuja tendencia, días con más reportes, orígenes, riesgo por instalación y el semáforo. Si los números no cambiaron, los gráficos no se vuelven a crear. El mapa abre en Endógeno agrupado. El PPTX usa los mismos filtros; solo cifras. Tema claro: `.obs-kpi` / `.obs-card` usan `--ui-surface`; los gráficos leen `data-theme` al pintarse. Alpine: `panelSidebar` (layout) + `observatoryBoard` / `observatoryMap`. Si `app.js` no registra `panelSidebar`, el mapa y las gráficas quedan en blanco.
- **Eventos:** tabla folio / instalación / tipo / estado / abierto / Ver (+ cliente en empresa). **Ver** abre la ficha.

## Ficha

Grilla 2×2 (xl): mapa | datos del folio; novedades | bitácora. En móvil una columna. Fila 1 (mapa + datos) `h-[28rem]`; acciones de estado fijas al pie del card. Novedades y bitácora `h-80` con scroll interno. Unir folio queda debajo. Rector: Agregar / Cerrar folio. Bitácora = `observatory_event_status_logs` (estado, nota, quién, cuándo). Unir/sacar no escriben bitácora.

## API

Para el software de Secretaría (u otro sistema). Se conecta con una **API key** creada por el súper admin en la ficha de empresa → **Integraciones API** (`/admin/companies/{id}/integraciones`): la key es de un usuario de la empresa (existente o externo colaborador con Observatorio en Ver, en toda la empresa o en clientes elegidos) y solo abre los endpoints marcados (`observatory.events.read`, `observatory.board.read`, `observatory.sites.read`, `observatory.reports.write`; middleware `api.ability`). Encabezado `Authorization: Bearer <key>`. Sin el permiso: 403 `code=api_key_ability`; revocada o vencida: 401. El token personal (`POST /api/auth/login`) sigue funcionando. No censo ni portería.

| Método | Ruta | Quién |
|--------|------|--------|
| GET | `/api/observatory/events` | Secretaría, rector/apoyo (sus instalaciones), empresa |
| GET | `/api/observatory/events/{id}` | Igual |
| GET | `/api/observatory/board` | Igual |
| GET | `/api/observatory/sites` | Igual |
| POST | `/api/observatory/reports` | Canal API. Rector/apoyo o Sistema externo según el usuario. Empresa no |
| GET | `/api/observatory/openapi.json` | Público (contrato) |
| GET | `/docs/observatory` | Solo súper admin / soporte (Swagger) |

El POST usa las mismas reglas de unión (1 h, mismo colegio y tipo). Anónimo oculta nombre y teléfono.

Esa API no es el webhook de WhatsApp. La key sale de Integraciones API; el webhook entra solo y no crea folios.

## WhatsApp (buzón)

App **Observatorio SJ** (`114531311572242`), cuenta `1563910605572064`, número **+57 310 246 6733**. No usa el 315 ni el 555.

| | |
|--|--|
| Verificación | `GET /api/webhooks/meta` (`hub.mode=subscribe`, token, `hub.challenge`) |
| Mensajes | `POST /api/webhooks/meta` con `X-Hub-Signature-256` (`sha256=` + HMAC del cuerpo y `META_APP_SECRET`) |
| Política | `GET /privacidad` |

`.env`: `META_APP_SECRET`, `META_WEBHOOK_VERIFY_TOKEN`, `META_PHONE_NUMBER_ID`. En producción el id del 310 es `1390897910781326`. Otro id responde 200 y no se guarda. Sin secreto o con firma mala: 403.

`ReceiveMetaWebhookService` escribe en `meta_webhook_messages` (`message_id` único, texto, tipo, remitente, fecha). Un estado sin `messages` no crea fila. Si no guarda, el log `meta.webhook.skipped` deja el id y el campo (sale como error porque `LOG_LEVEL=error`); no incluye el texto ni el teléfono de quien escribe.

La cuenta tiene que estar suscrita a la app (`POST /{cuenta}/subscribed_apps`). El botón de prueba de Meta no usa el 310.

### Conversación guiada (21 nov 2026)

Cada mensaje nuevo del 310 dispara `ProcessMetaWebhookMessage` (cola) → `MetaObservatoryBot`. Estado en `meta_conversations` (una abierta por `wa_id`; vence a los `META_CONVERSATION_TTL_MINUTES`, 120 por defecto; «cancelar» la cierra).

Pasos: una pregunta («¿en qué colegio y qué pasó?»; el tipo se lee si viene en el texto, si no se pide) → hasta 2 fotos y 1 video de 30 s / 20 MB, o «Listo» → quién eres y anónimo en la misma lista. «Qué pasó» acepta una palabra. Si la misma persona repite sede y tipo dentro de la ventana de 1 h, no se crea otro reporte. Al final llama a `SubmitObservatoryReportService` con `source=whatsapp`. Responde el folio `EV-…`. Solo colegios con perfil Observatorio comunidad.

Si es anónimo, al cerrar se borran nombre y teléfono de `meta_conversations` y `meta_webhook_messages`.

`.env`: `META_OBSERVATORY_CLIENT_ID` (82 = Secretaría de Educación en producción), `META_ACCESS_TOKEN` (token permanente de usuario del sistema, solo en el VPS), `META_GRAPH_VERSION` (`v27.0`), `META_CONVERSATION_TTL_MINUTES`. Sin token o cliente, el bot no hace nada (log `meta.bot.unconfigured`). El cliente debe estar activo y con Observatorio.

Envío y descarga de fotos: `WhatsAppGateway` (`CloudApiWhatsAppGateway`). Solo responde dentro de las 24 h del último mensaje del usuario.

## Riesgos (pestaña)

La pestaña se llama **Riesgos**. Arriba, la empresa carga los delitos del mapa (nombre y color) y las cifras del año. La grilla se llama **Delitos por comuna y veredas**: una tabla de las 22 comunas y otra de las 93 veredas y 2 sectores rurales de Cali (Acuerdo 0636 de 2026, códigos `v01`–`v95`). Cada título tiene su buscador de columnas; el scroll horizontal sigue. Ocultar una columna no la borra al guardar. Eso no sale de los reportes. Abajo siguen los tipos del link y de WhatsApp, uno por cliente.

En el mapa el suiche es **Exógeno / Endógeno**. El periodo es el del encabezado del tablero. Las cifras de riesgo están por año: un periodo de un año pinta ese año y, si cruza dos, se suman.

- **Exógeno:** Agrupado (dona en el centro de la comuna; al alejar, una sola de Cali), Mapa de calor (el polígono completo, de azul a rojo) y Comuna (pin con la cantidad). El delito en cero no se pinta. La leyenda filtra el delito. Además se ven todas las instalaciones con coordenadas, aunque no tengan reportes, con el mismo pin del modo (dona, calor o círculo) y total 0.
- **Endógeno:** Agrupado (dona en la instalación), Mapa de calor (punto en la instalación) e Instalaciones (pin con la cantidad), con los reportes del periodo. Sin reportes queda el mismo pin del modo, con total 0. Las motos siguen igual. Una sede fuera de comuna usa las cifras de su vereda para el R comuna; comunas y veredas se normalizan por separado.

## Tipos (catálogo del cliente)

Los crean y editan el **admin del cliente** y la empresa con **Gestionar** (pestaña Tipos: buscador de cliente en el encabezado; crear, editar, activar/desactivar, eliminar). Con reportes no se borra (se desactiva); siempre queda uno activo. **Copiar estos tipos a otros clientes** crea en los destinos los que falten por nombre (sin tildes ni mayúsculas) y no toca los existentes (`ManageObservatoryReportTypesService`). Semilla: Amenaza N3, Riña N2, Hurto N2, Otro N1. El link `/o/{slug}`, el panel, la PWA y la minuta usan esos tipos. En el tablero empresa (Todos), leyenda, chips y gráficos agrupan por slug para no repetir el mismo tipo de cada cliente.

Puntaje del colegio = suma de niveles de sus reportes. Tablero: líneas por tipo (día/mes/año) y días pico.

## Mapa

Entran las instalaciones activas con coordenadas de clientes que tienen Observatorio, en Exógeno y en Endógeno, aunque no tengan reportes. Sin latitud y longitud no se pueden colocar. Gota = reporte. En el tablero de empresa el mapa lista todos los turnos abiertos, con el recorrido ya en los datos del turno. El clic pinta esa ruta y la encuadra; otro clic, o elegir otro supervisor, quita la línea anterior y encuadra motos e instalaciones. En el de cliente, solo si ese cliente tiene también Supervisión. No pinta pines de revista, alarma ni apoyo. Modos del suiche: Agrupado, Mapa de calor y Comuna o Instalaciones. El calor de riesgo pinta el polígono; el de reportes es un punto. Google quitó `HeatmapLayer` en Maps JS 3.65. La gota lleva margen en el SVG para que Maps no recorte la punta al acercar. Requiere `GOOGLE_MAPS_API_KEY`. El intake público `/o/{slug}` solo lista perfiles con Observatorio comunidad.

Capa y filtro: **22 comunas urbanas de Cali** (IDESC), ruta relativa `/geo/cali-comunas.geojson`. **Todas** (sin `?comuna=`) encuadra Cali y pinta las 22. Una comuna recorta y hace zoom a ese polígono. Fuera del perímetro: **Fuera de Cali**.

La **ficha de la instalación** usa la misma capa: al poner el pin, si cae en una comuna de Cali se guarda y se muestra la comuna IDESC (contorno en el mapa de al lado). Si el pin no cae en una comuna, el área queda en **Zona rural** y un selector lista las veredas y sectores rurales de Cali (`CaliVeredaCatalog`). La elegida se guarda con `area_kind` vereda y la etiqueta «nombre · corregimiento». Fuera de Cali no hay polígono.

## Módulo

En la ficha del cliente, checkbox **Observatorio**. Si no está chuleado, no aparece en el sidebar del cliente. Empresa siempre ve `/company/observatory`.

## Pliego (Anexo 7.5)

Texto literal: fuentes diversas + origen. Visita: Secretaría de Educación de Cali.

| Pedido | Estado |
|--------|--------|
| Maestro, mapa, pines, calor, intake, folio, bitácora, tablero, API | Hecho |
| Priorización configurable | Hecho: catálogo tipo + nivel + color |
| Filtros/capas territoriales | Hecho: comunas urbanas Cali (IDESC). No cubren el Valle |
| Salida de resultados (export) | Hecho: PPTX del tablero (`/company/observatory/tablero.pptx` y `/client/observatory/tablero.pptx`) |

## Siguiente

v1 del Anexo 7.5 cerrada. El WhatsApp guiado abre folios con texto y fotos. Faltan videos y la bandeja de revisión (ver `PENDIENTES.md`).

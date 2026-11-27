# Pendientes (Zentra)

Lista viva de lo definido y **no implementado**. Al implementar, mover a «Hecho» con fecha y commit.

## Abiertos

### 17. Dos cargas que el tope del pool no cubre
Definido 27 nov 2026. El pool ya está en 10 hijos. Siguen fuera de `5609367`.

- Si el hash de la ruta en calle ya coincide, no recargar el GPS (`EnsureSnappedSupervisorRouteService`).
- El tablero de instalaciones y Mi empresa no deben reconstruir el mes por un ping de supervisión.

### 9. Observatorio WhatsApp: entrega 2 (videos + bandeja de revisión)
Definido 21 nov 2026. La conversación guiada (texto + fotos) ya existe; ver `docs/OBSERVATORIO.md`. Para usarla en producción faltan: migrar en el VPS, `META_OBSERVATORY_CLIENT_ID=82` y `META_ACCESS_TOKEN` permanente (usuario del sistema con la cuenta de WhatsApp, permisos `whatsapp_business_messaging` y `whatsapp_business_management`) en el `.env` del VPS, `config:cache` y `queue:restart`.

- Videos: hecho en link y WhatsApp (mp4/webm, 30 s, 20 MB, se ve en la ficha). Sigue pendiente la bandeja de conversaciones abandonadas.
- Bandeja de revisión para conversaciones abandonadas o sin resolver (hoy vencen a las 2 h y se pierden).
- Confirmar con el usuario: política de menores y retención de fotos recibidas por WhatsApp.
- Messenger e Instagram quedan para después. No tocar el 315 ni el 555.

### 8. FCM_SERVER_KEY + Firebase en APK residentes (push)
Definido 9 nov 2026. Código listo (`SendResidentPushService`); en VPS `FCM_SERVER_KEY=` vacío a propósito.

- Crear/habilitar **Cloud Messaging API (heredada)** en Firebase `zentra-cf05a` → Server key en `.env` + `config:cache`.
- En `resident-app/android/app/`: añadir `google-services.json` del app Android Firebase (`cloud.wcodex.zentra.residentes`), `cap sync`, rebuild APK.
- Rehabilitar el script Push en `layouts/resident-app.blade.php` (hoy quitado: sin Firebase nativo `Push.register()` cerraba la APK tras login).
- Sin eso: web `/r/` y APK sin push.

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

### 27 nov 2026 · Pool PHP de Zentra
Solo `/etc/php/8.3/fpm/pool.d/zentra.wcodex.cloud.conf`: `pm.max_children = 10`, `request_terminate_timeout = 120s`, `php_admin_value[memory_limit] = 256M`. decowandy y default siguen en 250. Nginx, PHP, Reverb y la cola quedaron activos; el sitio respondió 200. El código que cabe en ese tope es `5609367`.

### 27 nov 2026 · Fichas de guardas y kilometraje
En Supervisión → Fichas, Guardas y Supervisores van a la izquierda de Periodo, con el botón del panel. Fichas y Kilometraje quedan debajo del header, a la derecha, solo en Supervisores. Kilometraje lista todos los turnos y Editar abre la corrección aunque no haya salto de 1.500 km. Diferencia desde está en el modal de Filtros. Commit `5609367`.

### 27 nov 2026 · Pines de supervisor y carga del VPS
El mapa entra en el último GPS. El ping lleva solo el punto y el pin lo recorre sin recargar `live.json`. La calle se pide solo del supervisor abierto. Observatorio cachea el mapa 20 s y recorta el cuerpo del reporte. La ficha PDF usa miniatura. El PPTX se arma en cola. Commit `5609367`.

### 27 nov 2026 · Zonas: menú «Compartir con…»
En Asignar clientes a zonas, la lista de «Compartir con…» y la de cada fila se abren sobre el modal, con alto máximo y scroll propio. Ya no las tapa la tabla ni el borde del modal. Commit `eae4e9b`.

### 27 nov 2026 · Brocha de color en el tablero
Fuentes, Orígenes y Tendencia tienen una brocha en el panel empresa, solo con gestión del Observatorio. Cada porción, barra o línea guarda su color por empresa. Sin elección, la paleta es diferenciada. Delitos y reportes por instalación usan el color del delito. Riesgo sigue en las cuatro bandas. El cliente ve los colores y no los cambia. Commit `db59d72`.

### 26 nov 2026 · PPTX igual al tablero
La descarga sigue el filtro y el periodo. Láminas: portada, cifras, mapa, atención con fuentes y orígenes, tendencia por mes o por día, delitos con reportes por instalación, riesgo, reportes en texto y cierre. Sin la lámina por delito ni los párrafos largos. Commit `db59d72`.

### 26 nov 2026 · Colores y diseño del tablero
Las tarjetas del tablero usan el verde y el borde de la ficha. Orígenes y Fuentes salen de los cuatro colores de Riesgos; si hay más, un tono vecino. Delitos y los tramos de la sede usan la banda del delito. Las barras se engruesan o se adelgazan según cuántas haya, y hacen scroll si no caben. Atención de folios sigue con los colores de estado. Commit `db59d72`.

### 26 nov 2026 · Barras del tablero
Orígenes, delitos y reportes por instalación usan cilindros de 22 px. La tarjeta dice el nombre, Total de reportes y Esta barra, y no se corta. Tendencia queda con Por día o Por mes en la misma fila. Delitos queda con «los más reportados». Riesgo por instalación pierde el subtítulo y gana buscador. Commit `db59d72`.

### 26 nov 2026 · Color del estado en la ficha
La píldora y la franja de la ficha, y la línea fina de los cuatro contenedores, usan el color del arco: Nuevo rojo, En trámite naranja, Cerrado verde. El mapa lleva ese borde y esquinas redondeadas. Commit `db59d72`.

### 26 nov 2026 · Ficha compacta
Los cuatro contenedores quedan más juntos y con el verde del sistema. La ficha de la derecha usa una grilla apretada y el color del estado. «Pasar reporte» es un texto; el clic abre un modal con instalación, folio y el porqué. Commit `58dae44`.

### 26 nov 2026 · Ficha del folio
El mapa de la ficha encuadra la comuna de esa sede y agrupa solo sus reportes. La ficha deja el riesgo repetido y muestra versión, con el motivo en la burbuja. Cada reporte lleva un `RP-` fijo. Pasar reporte mueve uno a un folio nuevo, a uno existente o a otra instalación, con justificación, y cierra el folio si se queda vacío. Se quitó Unir folio. En pantallas medianas y grandes las cuatro celdas caben; el scroll interno de ficha, novedades y bitácora se mantiene. Commit `58dae44`.

### 26 nov 2026 · Tarjeta, grupo y fila
Un clic deja solo la tarjeta de ese pin; la anterior se va aunque el mapa la pinte tarde. La tarjeta queda a 1 px de la cabeza del pin. Reporte e instalación van en la misma fila y miden igual. La de instalación abre con el nombre de la sede. La del reporte muestra la fuente y la observación. El grupo con un folio Nuevo palpita al alejar el zoom. La primera fila del tablero queda un 15 % más alta. Commit `a3c1947`.

### 26 nov 2026 · Pin, carga y motos
La tarjeta del pin se cierra con otro clic o con la X, y muestra comuna, puestos, guardas y compartir. El contorno del pin palpita en neón. El tablero ya no carga el recorrido completo: se pide al clic. El punto GPS mueve solo la moto. Commit `160aaf4`.

### 26 nov 2026 · Aviso del reporte
El reporte sale del modal central. En el mapa el pin Nuevo palpita y el zoom ocurre una vez; la tarjeta de la instalación sale en cada clic y la del reporte solo mientras sigue Nuevo. Fuera del mapa el aviso va abajo a la derecha. Notificaciones queda entre el nombre y Cerrar sesión. El pánico no cambia. Commit `2885830`.

### 26 nov 2026 · Avisos y mapa en vivo
Notificaciones, el aviso de la esquina y el pánico se piden al abrir y cuando el canal avisa. Se quitó la consulta de cada 4 segundos. Si el canal se cae, se pide cada 12 segundos. El reporte suena solo para quien gestiona, hasta cerrar, atender o tocar el pin. El mapa de supervisión deja la consulta de cada 5 segundos y se mueve cuando llega un punto, se abre o se cierra un turno. Commit `2885830`.

### 26 nov 2026 · Gestión que sube
La tarjetica suma lo trabajado. Cerrar un folio suma su parte completa y pasarlo a trámite suma la mitad. Diez folios nuevos marcan 0 %. El arco no cambia. Commit `53e995c`.

### 26 nov 2026 · Atención de folios
Un solo título, Atención de folios más el total. El arco es media dona con el tubo de Fuentes, puntas y cortes rectos, y solo pinta los estados que tienen folios. La leyenda es Cerrados, En trámite y Nuevos. La tarjetica de Gestión queda un centímetro sobre el borde del arco, con marco hundido y visor iluminado. Gestión y el porcentaje van en la misma línea y del mismo tamaño. La dona y el arco usan el mismo grosor de anillo y crecen centrados; las leyendas bajan al borde de la tarjeta. El folio nuevo pesa completo y el que está en trámite pesa la mitad. Commit `23a653f`.

### 26 nov 2026 · Gráficos del tablero
Segunda fila: Atención de folios, Fuentes y Orígenes, a tercios. Tercera: Tendencia endógena, Delitos endógenos por comuna (un cilindro por delito) e Instalaciones con más reportes (cilindro partido por delito, total visible, scroll interno y buscador). Riesgo por instalación baja a la fila de abajo, a todo el ancho. En el mapa, Eventos, Nuevos, En atención y Cerrados muestran el número. Commit `538057c`.

### 25 nov 2026 · Lista de supervisores en el Observatorio
Cada supervisor ocupa tres líneas: nombre con km y revistas, señal y zona, y el estado corto (Detenido, En ruta o Sin GPS). El clic sigue pintando el recorrido. En Supervisión la frase larga no cambia. Commit `3555881`.

### 25 nov 2026 · Burbujas del Observatorio
Cada burbuja es un párrafo: la revista dice qué hizo y si hubo novedad; la alarma, si fue prueba o atención y el resultado; el apoyo, el motivo; el reporte, quién y el texto corto. En ese contenedor solo entran clientes con Observatorio. Mi empresa sigue mostrando todos. Commit `de19fc3`.

### 25 nov 2026 · Encabezado del mapa y fila 60/20/20
Los cuatro números quedan como texto en el encabezado del mapa, junto a Mapa y Satélite. Sobre el mapa van el suiche y el botón Catálogo. Salen las tarjetas de KPI, la franja de etiquetas y los dos catálogos del lado. La fila es mapa 60 %, convenciones y supervisores 20 %, reportes 20 %. Commit `7bc7fdd`.

### 25 nov 2026 · Filtros del mapa por comuna
Exógeno ya no pinta las sedes. Agrupado es la dona de cada comuna, el calor es solo el polígono y Comuna es el círculo blanco con el total de esa comuna. En Endógeno el calor sigue en el punto de color, también cuando las sedes se agrupan. Commit `8b62470`.

### 25 nov 2026 · Gráficos del tablero y buscador de veredas
El tablero pinta tendencia, picos, orígenes, fuentes y el medidor con ECharts, cargado solo en esa página. Orígenes ya no espera un script de más abajo y en cero muestra el tubo vacío. Los días con más reportes siguen en vertical, ahora en cilindro. En Delitos por comuna y veredas hay un buscador junto a Comunas y otro junto a Veredas; el scroll horizontal sigue. Commit `d3ca201`.

### 25 nov 2026 · Instalaciones en el mapa y veredas
En Endógeno y en Exógeno el mapa muestra todas las instalaciones con coordenadas, aunque no tengan reportes. Sin reportes usan el pin del modo (dona, calor o círculo) con total 0. Al alejar, las sedes se agrupan y las motos quedan sueltas. En Exógeno el calor es el polígono y las sedes son un punto neutro. Si el pin no cae en una comuna de Cali, el formulario queda en Zona rural y guarda la vereda elegida. La grilla se llama Delitos por comuna y veredas: comunas y veredas van en tablas aparte. Catálogo: Acuerdo 0636 de 2026 (93 veredas y 2 sectores rurales). Commit `d3ca201`. El pin en cero: `1b11ae0`. Exógeno por comuna y el agrupado al alejar: `12a9c25`.

### 25 nov 2026 · Tablero del Observatorio
Semáforo de Riesgo por instalación en cilindro horizontal ECharts (estilo Decowandy: lados rectos, inicio plano, disco solo en la punta, color de la banda). Orígenes en cilindros horizontales: Alumno, Padre, Vecino, Usuario panel, Usuario API, Usuario app patrulla. Tendencia endógena y Días con más reportes comparten la fila al 50%. El tablero no se recalcula cada 5 s: Reverb avisa al cambiar un reporte y entonces se redibujan tendencia, picos, orígenes, riesgo por instalación y semáforo. El mapa abre en Endógeno agrupado. No había migración del bloque eliminado «Instalaciones por riesgo»; no se borró ninguna. Commit `f7e4b8f`. El tiempo real y el mapa en Endógeno agrupado: `50fb6f6`.

### 24 nov 2026 · Riesgos del observatorio en el mapa (local)
Pestaña Riesgos: delitos y cifras por comuna. El mapa usa el periodo del tablero. Riesgos: dona, calor sobre el polígono y pin por comuna. Reportes: dona, punto de calor y total por instalación. Hecho en local. Sin commit y sin VPS hasta que se confirme.

### 24 nov 2026 · Cierre del sistema suma el mapa al km final
Si cierra el sistema, el km final y el último km de la placa quedan en el inicio más los km del mapa. Sin GPS, el final queda igual al inicio. El cierre del supervisor sigue siendo el odómetro real. `AutoCloseExpiredSupervisorShiftsService`. Commit `33cf37a`.

### 24 nov 2026 · Kilometrajes por revisar
En Fichas, la tabla usa el periodo y los filtros de la pestaña. La ficha dice si la distancia es del inicio al cierre o del cierre anterior de la placa al inicio. Aceptar (o guardar una corrección) saca el turno de la tabla sin tocar el mapa ni la app de campo. Migración `2026_11_24_083500`. Commit `e539c96`. Detalle: [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md).

### 23 nov 2026 · Formulario de reporte (link y WhatsApp)
Link: al elegir el colegio pasa al resto sin «Continuar»; tipo, qué pasó, mapa, 2 fotos y video van juntos; identidad y envío aparecen al tener tipo y una palabra. Borrador en la pestaña (`sessionStorage`): un refresco recupera colegio, tipo, texto, pin y quién eres. El botón de envío pasa a «Guardando…» y queda deshabilitado. WhatsApp: una pregunta junta colegio y hecho; fotos y «quién eres / anónimo» se mantienen. Adjuntos: 2 fotos de 10 MB y 1 video de 30 s / 20 MB. Mismo colegio, tipo y persona dentro de 1 h no crea otro reporte. «Qué pasó» acepta una palabra. La app de supervisores no cambia. Migración local `2026_11_23_220000` aplicada. Sin commit. En el VPS, PHP del sitio y nginx ya estaban en 64 MB (no 4 ni 8).

### 23 nov 2026 · La ruta ámbar se quita como en Supervisión
La polilínea vive fuera de Alpine, como el arreglo `overlays` de Supervisión. Cada pintado hace `setMap(null)` y crea una línea nueva solo si hay supervisor.

### 23 nov 2026 · Clic del observatorio igual que Supervisión
El recorrido viaja en el turno. El clic pinta y encuadra esa ruta al instante. Otro clic, o elegir otro supervisor, quita la línea anterior y encuadra motos e instalaciones.

### 23 nov 2026 · Recorrido, señal y alto fijo en el Observatorio
La ruta del recorrido no estaba en el repo (404 al clic). Quedó publicada. El panel de convenciones + turnos tiene alto fijo (caben ~3 supervisores; el resto hace scroll). Las motos muestran En línea / Pantalla apagada / Sin señal y se deslizan con el GPS.

### 23 nov 2026 · Supervisores del observatorio visibles y motos en vivo
El listado de turnos quedaba vacío porque Alpine anidaba el tablero sobre el mapa. Quedó fuera de `observatoryBoard`, las motos se deslizan cada 5 s y el recorrido se pide al clic.

### 23 nov 2026 · Mapa del observatorio: recorrido al clic y una sola fila
El recorrido no queda pintado siempre. Clic en un supervisor en turno lo muestra y otro clic lo quita. Mapa, convenciones con turnos y tipos quedan en una fila: mitad, 60% del resto y 40% del resto.

### 23 nov 2026 · Tablero de reportes y mapa del observatorio
Las gotas del mapa dejan de cortar la punta al acercar. El mapa del observatorio muestra instalaciones con observatorio y, si el cliente también tiene supervisión, la moto y el recorrido del turno (sin pines de revista). Mi empresa y el tablero del observatorio listan revista, alarma, apoyo y observatorio en burbujas, cada una con los datos que ese tipo trae, y se refrescan al guardar. El observatorio incluye convenciones y supervisores en turno, sin Ver ni Zonas.

### 21 nov 2026 · Ícono del APK de Supervisión (2.11)
El launcher deja el robot de la plantilla y usa el logo enviado (Z, moto y cámara). VersionCode 21, misma firma release que el 2.10. Clave en `docs/SUPERVISION-CAMPO.md` y en `field-app/android/keystore/`.

### 21 nov 2026 · APK Supervisión 2.10 con firma release
Play Protect bloqueaba el APK porque iba firmado con la clave de prueba de Android. El 2.10 (versionCode 20) va firmado con `CN=W Codex` y sin el permiso de ubicación en segundo plano. La clave queda fuera del repo, en `field-app/android/keystore/`. Hay que desinstalar el APK anterior una vez.

### 21 nov 2026 · Documentos: header y pastillas por carpeta
Listado de empresa: título, buscador, Filtros y carga masiva en el header. Ocho pastillas (siete carpetas + Total) con trabajadores o documentos y peso; un clic filtra la tabla y otro reemplaza el filtro. Se parten en más filas cuando no caben.

### 21 nov 2026 · GPS de turno cada 5 s y pin que se desliza
El servicio nativo se quedaba vivo pero dejaba de anotar si el último fix pasaba de 60 s, y el mapa congelaba el pin. APK 2.9 (versionCode 19) pide ubicación cada 5 s, guarda solo un fix de menos de 45 s y no se apaga solo por 401, 422 o turno cerrado en la respuesta. Sin red la cola sigue en el teléfono. Pánico y Observatorio esperan un GPS nuevo; si no llega, el texto dice la hora de la última conocida. El mapa en vivo consulta cada 5 s y desliza el pin (salto mayor de 250 m se coloca de una vez). PWA `zentra-sup-v63`. Detalle: `SUPERVISION-CAMPO.md`.

### 19 nov 2026 · Buzón WhatsApp del Observatorio (commits `4d361ba`, `dce4e49`, `c087da2`)
- `GET/POST /api/webhooks/meta` guarda el texto del 310. Firma HMAC. Otro número responde 200 y no se guarda.
- La cuenta Observatorio SJ quedó suscrita a la app. El id real del 310 es `1390897910781326` (solo en `.env` del VPS, no en git).
- `GET /privacidad` es la URL que Meta pidió para publicar la app.
- No abre folios. No toca el 315 ni el 555.

### 19 nov 2026 · Política pública del WhatsApp del observatorio
- `GET /privacidad` para la URL que Meta exige al publicar la app Observatorio SJ.
- Cubre el número 310, el texto, la hora y el correo `soporte.admin@sjp.com.co`. No abre reportes ni toca el 315 ni el 555.

### 9 nov 2026 · Portería multi-auth + km Resumen/PPTX + periodo + Echo prod (commit `be40fc4`)
- Portería: lookup por cédula une invitados del cliente con visitante global; varias autorizaciones vigentes → selector de destino; `block_reason` seguro si `selected` es null.
- Visitante unique global: `ManageAuthorizationService::resolveVisitor` sin ClientScope + reintento UniqueConstraint.
- Supervisión: `ResolveSupervisorShiftTraveledKm` (odómetro absurdo / GPS basura no suman en Resumen/PPTX/tira); separadores de miles en tarjetas, tablas y ejes; etiquetas PPTX `#,##0`.
- Periodo: flechas ya no tapadas; selectores mes/año.
- Echo/Reverb: no conectar `wss://localhost` desde el dominio público; build de assets con `VITE_REVERB_*` de producción (`zentra.wcodex.cloud:443`).
- Docs: `PORTERIA.md`, `SUPERVISION-CAMPO.md`, `HOSTING-VPS.md`.

### 9 nov 2026 · App residentes (Accesos) + Usuarios app + Descargas
- Panel cliente: **Usuarios app** (alta en cascada, correo con credenciales, canal Android/iPhone web, cupo 4/unidad).
- Descargas empresa/cliente: APK residentes + enlace/QR compartir (`/r/`).
- App `/r/`: login, inicio (6 tarjetas), menú hamburguesa, autorizar persona/vehículo, correspondencia, reservas, bloqueos (consulta), mi unidad, perfil.
- API Sanctum `resident/*` + registro device; push FCM (stub hasta `FCM_SERVER_KEY`).
- Capacitor `resident-app/` → APK `public/downloads/zentra-residentes.apk` (producción `https://zentra.wcodex.cloud/r/`).
- Migrates `2026_11_09_100000`, `2026_11_09_120000`.
- Nota: layout `/r/` sin Push nativo (ver Abierto §8); scroll del shell corregido en WebView.

### 8 nov 2026 · Operar: menú único + modales
- Botón **Operar** (Cliente → Instalación → Portería). Sin selects sueltos en la ficha.
- Portería/instalación con 2+ opciones: modal centrado; 1 opción: entra directo.
- Sesión portería: instalación + puerta. Docs: `PORTERIA.md`.

### 8 nov 2026 · Instalaciones slim + feeds en Mi empresa
- `/company/installations`: sin KPIs ni paneles Novedades/Revistas; solo mapa + lista con buscador.
- Mi empresa → Operación del día: Novedades de servicio + Revistas del supervisor (live vía `feedsForCompany`).
- Docs: `CLIENTES-Y-ESTRUCTURA.md`, README.

### 8 nov 2026 · Tarjeta instalación (layout final) + hub Ajustes + perfiles
- Tarjeta full-width: mapa ~28% | detalle. Cabecera en una fila: Cliente · Perfil · Área · Administrador. Píldoras + Historial/Crear/Editar/Archivar/Ver ficha en una fila.
- Crear = dropdown Puerta/Puesto/Vigilante. Puertas horizontales (código auto). Puestos con scroll; sin lat/lng; motivo solo en cambios de servicio.
- Historial por instalación. `/company/ajustes` hub morph. `installation_profiles` reemplaza `kind`. Docs: `CLIENTES-Y-ESTRUCTURA.md`.

### 8 nov 2026 · Hub Ajustes (tarjetas → morph → catálogo) + @php frágil
- `/company/ajustes`: 3 tarjetas (Empresa · Clientes/sitio · App de campo) → modal morph con mini-cards → panel AJAX (lista + alta).
- Catálogos antiguos redirigen al hub; forms POST llevan `_settings_hub` y `RedirectSettingsHubForms` vuelve al mismo catálogo.
- Blade: `@php($x = f(...))` anidados pasan a bloque `@php`/`@endphp` (billing, admin, observatorio, acceso, empleados).
- Verificado en browser: hub, 17 paneles 200, crear/eliminar cargo, perfiles, zonas, facturación, form instalación con perfiles.

### 8 nov 2026 · Perfiles de sitio + Ajustes reagrupados
- Catálogo `installation_profiles` por empresa (Colegio de sistema fijo: DANE + Observatorio comunidad; resto CRUD/desactivar).
- `installations.kind` (enum) → `installation_profile_id`; Observatorio comunidad filtra por flag del perfil.
- Formulario instalación: sin código en alta, área bajo el mapa, perfil con hints; clasificación del cliente ≠ perfil del sitio.
- Ajustes en grupos: Empresa · Clientes/sitio · App de campo (según productos).
- Tests: `CompanyInstallationProfileTest` + directorio/Observatorio actualizados.

### 21 oct 2026 · Turnos del supervisor: relevo anticipado y turno cerrado por el sistema (commit 487a92a + c0e8af9, desplegado 21 oct)
Reporte de campo: «abro a las 17:30 y a las 18:00 me cierra» y «cierro sesión, entro con otra zona y me sale sin turno pero me deja pasar revista, luego no aparece». Causa común: `ResolveSupervisorShiftDeadlineService` calculaba el fin del turno en la mañana del mismo día (Noche abierto antes de las 18:00) o en el pasado (Día abierto después de las 18:00) → el cierre automático (cada 5 min) lo cerraba al instante. Casos en datos: Giors 12 oct (Noche 17:53), Esneyder 14 oct (Día 20:10), Jorge 14 oct (Día 18:18 y 23:17, 0 revistas).

- Fin programado = franja de la plantilla que contiene la hora de apertura, con relevo hasta 2 h antes del inicio. Fuera de franja: no se puede abrir («Ahora corresponde Noche (18:00 – 06:00)»); el cierre automático nunca pone fin antes del inicio.
- App: si el servidor ya no tiene el turno (ping, revista o al volver a entrar), lleva a «Abrir turno» con aviso y conserva la revista en curso, que se recupera al abrir el turno nuevo. El cierre guardado en el teléfono manda `shift_id` y no cierra un turno más nuevo.
- Tests: `SupervisorShiftScheduleTest`, `ResolveSupervisorShiftDeadlineTest`.
- Además (mismo día): arranque con «Cargando…» en vez del login, reabre la pantalla donde quedó y guarda en el teléfono lo escrito en cualquier módulo, Observatorio y cierre de turno. **PWA v61 + APK 2.7 (versionCode 17).**

### 21 oct 2026 · PPTX del Observatorio al nivel de Supervisión (commit 487a92a + c0e8af9, desplegado 21 oct)
`ExportObservatoryBoardService` (estilo de `ExportSupervisionExecutiveReportService`) + `BuildObservatoryExportDataService` + `RenderObservatoryHeatMapService`. Test: `ObservatoryCompanyManageTest::test_board_pptx_follows_scope_and_filters`.

- Portada con logos y periodo → Resumen (5 tarjetas, donas/barras, texto) → Mapa de calor (Static Maps oscuro + manchas por tipo + folios; sin clave dibuja sobre fondo liso) → una diapositiva por tipo (barras por día/mes, dona de estados, texto) → Gracias.
- Respeta alcance y filtros: empresa, cliente o instalación; fechas, estado, tipo (solo ese tipo), comuna y texto. Colaborador con clientes puntuales solo ve los suyos (tablero, en vivo y PPTX).

### 21 oct 2026 · Integraciones API en la ficha de empresa (súper admin) (commit 487a92a + c0e8af9, desplegado 21 oct)
Ruta `/admin/companies/{id}/integraciones`. Migración `2026_10_21_200000` (tabla `api_integration_keys` + `personal_access_tokens.is_integration`). Tests: `ApiIntegrationKeysTest`. Detalle: `OBSERVATORIO.md` (API).

- API key = token Sanctum de **un usuario** de la empresa (existente o externo nuevo: colaborador con Observatorio en Ver, en toda la empresa o en clientes elegidos, clave aleatoria).
- Permisos por módulo (Observatorio, Portería): Sin acceso / Lectura / Lectura y escritura / Personalizado por endpoint. Cada ruta de `routes/api.php` declara `api.ability:<clave>` (`ApiAbilities`); la app de campo y cerrar sesión son `api.ability:human` (una key no entra).
- Listar, regenerar (la anterior deja de servir) y revocar. La key se muestra una vez. Ayuda «?» tipo manual en la pestaña.
- El login personal y la app de campo ya no borran las API keys del usuario.
- «Entrar como empresa» deja de ser pestaña: botón verde **Entrar** con ícono de puerta junto a «← Empresas».
- Dos manuales descargables en PDF (súper admin y técnico externo): **Manual de API Key** y **Manual de Integración** (+ Swagger y respuestas 200/201/401/403/422). Ruta `admin.companies.integrations.manual` (`api-key` | `integracion`); contenido en `views/modules/admin/companies/manuals/`.
- `/docs/observatory` rediseñada: pasos Autorizar → endpoint → Ejecutar, etiquetas de Swagger en español, más contraste, el `curl` muestra `TU_API_KEY` y el contrato OpenAPI habla de API key (no del login personal).

### 21 oct 2026 · Observatorio: filtros en el encabezado, empresa gestiona y tema claro (commit 487a92a + c0e8af9, desplegado 21 oct)
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

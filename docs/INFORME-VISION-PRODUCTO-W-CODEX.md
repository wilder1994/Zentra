# Informe de visión de producto — Zentra (W Codex Solution)

**Producto:** Zentra  
**Dueña:** W Codex Solution  
**Estado:** decisión de producto (sin cambio de código en este corte)  
**Fecha:** 2026-09-13  
**Objeto:** bitácora de cómo se vende Zentra, qué se toma de SJ-SIG, cómo avanza la geometría y cómo se cubre el factor *Herramienta de Seguridad Educativa* (Anexo 7.5).

Este documento es bitácora. No se implementa nada aquí: primero se registra la decisión, después se abre fase de código.

Documentos que este informe no sustituye: [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md), [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md), [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md), [`PAQUETES-ACCESOS-Y-SUPERVISION.md`](PAQUETES-ACCESOS-Y-SUPERVISION.md). SJ-SIG sigue documentado en su propio repo (`SJ-SIG/docs/informe-definicion.md`).

---

## 1. Dueña y marcas

| Campo | Valor |
|--------|--------|
| Empresa de software | **W Codex Solution** |
| Producto comercial | **Zentra** (SaaS B2B de accesos, vigilancia y supervisión de campo) |
| Cantera / prototipo contractual | **SJ-SIG** (Laravel 13, factor 4.2.4, repo aparte) |
| App de campo hoy | PWA `Zentra_Supervision` / `field-app/` + **APK corte 2** (GPS con pantalla apagada) |

Zentra se construye para **venderse** a empresas de seguridad y, con el mismo esqueleto, a entidades (Secretaría, Alcaldía, colegios). Una licitación concreta (SJ, Educación de Cali, etc.) es un **cliente** o un **módulo**, no una marca nueva ni un segundo sistema.

SJ-SIG nació para un pliego. El avance útil (mapa, unidades, expediente, tablero de entidad) se **porta a Zentra**. No se fusionan los repos (Laravel 11 vs 13; tenancy distinto). No se despliega SJ-SIG como producto paralelo.

---

## 2. Principios

1. **Un producto, muchos tipos de cliente.** PH, bodega, contrato de vigilancia, Secretaría con colegios: misma geometría, fichas distintas.
2. **La gente de vigilancia es de la empresa.** Nunca del PH ni del colegio. Se **asigna** a un puesto.
3. **El puesto cuelga de una instalación** y tiene **modalidad** (8 / 12 / 24 h) y cupo (unidades por cargo).
4. **La instalación es el sitio físico.** Ahí viven mapa, código, administradores opcionales y la estructura (apto o salón).
5. **Accesos ≠ Supervisión ≠ Observatorio.** Puertas, puestos de revista y denuncias ciudadanas no comparten tablas operativas.
6. **Trabajar en Zentra.** SJ-SIG es referencia; no se mantiene dos árboles de instalaciones.
7. **La PWA no basta para tracking de fondo.** Eso ya lo cubre el **APK corte 2** (foreground service). La PWA sigue siendo respaldo.

---

## 3. Geometría unificada (la que se va a completar)

Hoy Zentra ya tiene empresa → clientes → instalaciones, con tres hijos: puertas (`locations`, Accesos), puestos (`supervisor_posts`, compartidos) y **censo** (`structures.installation_id`). Falta el cupo (unidades por cargo) y admins de sede.

**Modelo objetivo:**

```
W Codex / empresa de seguridad (tenant Zentra)
  └── Empleados (maestro de la empresa)
        └── asignación → Puesto

Cliente (PH, bodega, Secretaría, contrato…)
  └── Instalación (sede, colegio, predio)
        ├── Administradores opcionales (rector, auxiliar, admin del sitio)
        ├── Accesos (puertas)                    ← mundo Accesos
        ├── Puestos (modalidad + unidades)       ← mundo Supervisión
        │     └── empleados asignados
        └── Estructura (nodos)                   ← hecho 2026-09-11
              └── Personas del sitio
                    PH: torre → apto → residente
                    Colegio: salón 6A → estudiantes
```

Tres reglas fijas:

| Regla | Detalle |
|--------|---------|
| Vigilante | Ficha en **Empleados** de la empresa. El Excel de empleados no elige cliente ni puesto (sigue [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md)). La asignación es un **segundo paso**. |
| Puesto | Siempre de una instalación. Siempre con modalidad 8/12/24 h y unidades por cargo (de SJ-SIG: `GuardRole` + `post_staffings`; en Zentra enriquecer `supervisor_posts`). |
| Estructura | Cuelga de la **instalación**, no del cliente suelto (`structures.installation_id`). Un PH de una sola sede no cambia de cara (instalación = el mismo cliente). Un colegio o un cliente con dos sedes sí lo necesita. **Hecho 2026-09-11.** |

Accesos y Supervisión siguen siendo dos mundos: un acceso no es un puesto. Las **instalaciones son compartidas**.

---

## 4. Qué se toma de SJ-SIG (cantera, no fusión)

SJ-SIG no se pega al código de Zentra. Se portan flujos y reglas, servicio por servicio.

| Pieza SJ-SIG | Qué aporta | Dónde cae en Zentra |
|--------------|------------|------------------------|
| Places + pin de cliente/instalación | Dirección real, mapa, pines PNG | Ficha de cliente e **instalación** (hoy la instalación es poco más que un nombre) |
| Código automático de sede | `{sigla}-01` | Alta de instalación |
| Puesto: modalidad + unidades por cargo | Cupo contratado, no “quién está hoy” | `supervisor_posts` (+ staffing) |
| Bitácora de cambio | Motivo según instalación / puesto / cupo | Edición de instalación/puestos |
| Excel personal + preview/diff | Ritual ya similar al maestro WM | Maestro WM **ampliado** con columnas SJ-SIG opcionales (ver §5) |
| Indexador HV / contratación / cursos / afiliaciones | Expediente contractual | Módulo vendible **Expediente / SIG** (paquete, como Accesos/Supervisión) |
| Tablero de entidad (mapa + KPIs) | Lo que ve el supervisor del cliente | Rol de entidad sobre el cliente/contrato |
| `Tenant::displayName()`, burbujas de mapa | UX | Mapa empresa / tablero |

**No portar como sistema aparte:** tenancy 1:1 de SJ-SIG, seed de licitación, vhost 8086. SJ como cliente de Zentra cubre el Anexo 7.4 cuando el módulo Expediente + tablero de entidad estén activos.

Laravel 11 (Zentra) vs 13 (SJ-SIG): no mezclar bases ni `composer.json`.

---

## 5. Empleados: ficha de empresa, ritual WM

| | Zentra (hoy) | SJ-SIG (cantera) |
|---|----------------|------------------|
| Quién es | Colaborador de **la empresa** | Vigilante del **contrato** |
| Pantalla | Listado + ficha en 4 bloques + foto (Personal SJ-SIG, estilos Zentra) | `/personal` |
| Excel | `Maestro Colaboradores WM` (A–Z) + columnas SJ-SIG opcionales | `ficha_empleados SJ-SIG` |
| Campos | Identidad Zentra (nombres partidos, DIVIPOLA, catálogos) + contacto, vinculación, seguridad social | Lo mismo en un `full_name` y cargo libre |
| Puesto | Fuera del Excel. Asignar/reasignar: ficha del cliente (Accesos/Supervisión) | Fuera del Excel |
| Expediente PDF | Sidebar **Documentos** (indexador empresa). Cliente: flag + solo puestos del cliente | Documentos / indexador |
| Usuario | Se crea en **Usuarios** (`nombre.apellido.####`) | Login de entidad, no del vigilante |

**Hecho (2026-09-10):** la ficha de Empleados de la empresa ya es la de SJ-SIG Personal. El maestro WM **se queda**; las columnas extras son opcionales. **Hecho (2026-09-10):** indexador de carpetas en sidebar **Documentos** (empresa, todos los empleados). El cliente lo ve solo si Accesos + `show_personnel_folders`, y solo de vigilantes asignados a un puesto suyo.

La **asignación empleado → puesto** ya está en la ficha del cliente (mismo puesto en Accesos y Supervisión). El Excel de empleados no elige puesto. Cupo por cargo y filtro de empleados por cliente siguen aparte.

---

## 6. Instalación enriquecida y administradores de sede

Hoy el alta de instalación en Zentra es nombre + “sede = cliente” + mapa + puestos (modalidad y vigilantes) + puertas. Falta código de sede, cupo por cargo y admin de sede.

**Objetivo de la ficha de instalación:**

- Nombre, dirección (Places), ciudad, pin en mapa
- Código automático
- Flag sede-cliente (`is_client_site`)
- Tipo/kind opcional: `conjunto` · `bodega` · `colegio` · … (para no pedir código DANE en una bodega)
- **Administradores opcionales** (N personas, cargo del catálogo: rector, auxiliar, administrador del sitio, coordinador…)

El `client-admin` sigue viendo **todo el cliente**. El admin de instalación solo **esa sede**. Imprescindible si el cliente es una Secretaría con cientos de colegios: el rector de Santa Librada no puede ver los 400 planteles.

Sirve igual al PH: admin del cliente ≠ conserje de una torre.

El contacto del directorio (rector, teléfono, código oficial, comuna) es la **misma** ficha de instalación + su admin, no una tabla suelta de “colegios”.

---

## 7. Colegios y licitaciones de Educación

No es un fork de Accesos. Es **el mismo árbol** con tipo de cliente/estructura `Colegio` y un módulo **Observatorio** encima.

**Tenancy de Educación (Cali u otra ciudad):**

```
Cliente: Secretaría de Educación
  └── Instalación: INEM Jorge Isaacs
        · código oficial, comuna, contacto
        · admin: rector (+ auxiliares)
        ├── Puesto: Portería principal · 24 h · 2 unidades
        │     └── empleados de la empresa de vigilancia
        └── Estructura
              └── Salón 6A → estudiantes (tipo de persona del censo)
              └── Salón 6B → estudiantes
```

- **Un cliente = la Secretaría. Muchas instalaciones = colegios.**  
  Si cada colegio fuera un cliente, el mapa de la ciudad, el ranking y el Secretario rompen el aislamiento.
- Estudiantes = `structure_members` con tipo `estudiante` (grado, jornada, acudiente). No una tabla “alumnos” el día 1.
- El salón no es un acceso. La puerta del colegio sí (`locations`).
- El buscador “INEM Jorge Isaacs” busca **instalaciones**.

### 7.1 Factor pliego — Herramienta de Seguridad Educativa (máx. 4 puntos)

Pliego de vigilancia / contratación pública. El puntaje se otorga a quien **suscriba el Anexo 7.5** (diligenciado y firmado por el representante legal) comprometiendo que, para prestar el servicio, pondrá a disposición del Departamento Administrativo de Contratación Pública un **módulo integrado de seguridad educativa**. La visita técnica verifica **funcionamiento y operación**, no solo el papel.

Eso es **Zentra + Observatorio** sobre el cliente Secretaría. No es otra marca ni un segundo hosting.

| # | Factor del Anexo 7.5 | Peso | Qué pide (desglose) |
|---|----------------------|------|---------------------|
| 1 | Gestión de seguridad de instituciones y contexto | 0,55 | Registro maestro y contexto institucional (0,30); consulta y búsqueda asociada (0,25) |
| 2 | Componente geográfico | 0,95 | Mapa de instituciones (0,25); eventos o riesgos georreferenciados (0,25); filtros/capas territoriales (0,25); identificación de concentraciones (0,20) |
| 3 | Gestión multifuente | 0,85 | Ingreso de fuentes autorizadas diversas (0,30); relación o normalización (0,30); trazabilidad del origen o fuente (0,25) |
| 4 | Reportes de comunidad educativa | 0,55 | Recepción con controles de privacidad (0,25); flujo y estado de atención (0,20); asociación a institución o evento (0,10) |
| 5 | Gestión de eventos | 0,75 | Ciclo de vida, estado y responsable (0,25); relación de reportes de distintas fuentes sobre la misma situación (0,25); histórico y evidencia (0,25) |
| 6 | Analítica y priorización | 0,90 | Tendencias, recurrencias o concentraciones (0,30); priorización configurable de riesgo o exposición (0,30); tablero, filtros y salida de resultados (0,30) |
| 7 | Interoperabilidad avanzada | 0,45 | Gestión de eventos/riesgos por API o formatos estructurados (0,25); documentación y control de la interfaz (0,20) |
| | **Total** | **4,00** | |

El Anexo en prosa pide lo mismo sin puntajes: maestro + búsqueda; mapa + eventos geo + capas + concentraciones; fuentes + normalización + origen; reportes comunitarios con privacidad, flujo y vínculo; eventos con dueño, cruce de fuentes e histórico; analítica configurable y tablero; API o equivalente documentado.

**Lectura literal (12 sep 2026, texto del pliego / Anexo 7.5):** Multifuente = fuentes autorizadas diversas + unirlas + ver el origen. Visita prevista: Secretaría de Educación de Cali. Capas territoriales = comunas urbanas de Cali (IDESC / [datos.cali.gov.co](https://datos.cali.gov.co/dataset/servicio-wms-comunas-de-cali)); no cubren todo el Valle.

### 7.2 Qué hay hoy en Zentra (brecha)

Zentra es maduro en **accesos, censo y supervisión de campo**. El Observatorio ya tiene intake, fuentes, agrupar 1 h, unir/sacar, mapa/calor, Tablero + Eventos, ficha con bitácora y API documentada. El lenguaje de producto sigue siendo cliente / instalación / portería, no «institución educativa».

| Factor | Cobertura hoy | Reutilizable | Brecha para la visita |
|--------|---------------|--------------|------------------------|
| 1 Maestro + búsqueda | ~95 % | Directorio con tipo, DANE de sede **escrito a mano** (si colegio; 8–12 dígitos, único), nombre repetible, personal N (admin/apoyo + cargo), área, mapa, búsqueda | Catálogo MEN / typeahead al escribir; DANE de *establecimiento* compartido entre sucursales |
| 2 Geográfico | **Sí** | Mapa Observatorio (cualquier sede + pin; calor en círculos; **comunas IDESC**: Todas = Cali + 22, o zoom a una). Google Maps 3.65 sin HeatmapLayer | PostGIS no es requisito del pliego |
| 3 Multifuente | **Sí** | Comunidad, panel, PWA, minuta y API; origen (canal + rol) en la ficha | — |
| 4 Comunidad | **Sí** | Intake `/o/{slug}`, anónimo, aviso menores, flujo de estados, vínculo a sede/folio | — |
| 5 Eventos | **Sí** | Folio; agrupa 1 h; unir/sacar; bitácora; rector **Agregar** / **Cerrar folio** | — |
| 6 Analítica | **Sí** | Tablero + tipos del cliente (nivel/color) + puntaje + líneas + picos + calor sede/riesgo + **PPTX** | — |
| 7 Interoperabilidad | ~90 % | `/api/observatory/*` Sanctum + OpenAPI `/docs/observatory` | Tokens de integración dedicados (hoy login del usuario) |

**No cumple el Anexo, por sí solo:** pánico de portería, minuta, `locations`, clustering visual de pines, ni el tablero de la empresa de seguridad.

### 7.3 Definición de producto (antes de código)

Capa **Observatorio**. Lee instalaciones/colegios. **No** vive en minuta ni en `locations` (principio 5).

| Pieza | Decisión |
|--------|----------|
| Institución | `Installation` con `kind = colegio` + contexto (código oficial, comuna, rector/contacto). Misma ficha del §6; no tabla suelta de colegios |
| Reporte | Intake (web anónima o identificada) con **fuente**, privacidad y estado de atención |
| Evento | Agrupa reportes de varias fuentes sobre **una** situación: ciclo, responsable, histórico, evidencia |
| Mapa | Pines de colegios + eventos/riesgos georreferenciados; filtros/capas territoriales; concentraciones (calor o ranking) |
| Tablero | Tendencias, recurrencia, ranking, prioridad configurable, filtros y export |
| API | JSON de eventos/riesgos + documentación (OpenAPI) y control de la interfaz |

**Fuentes que pide el pliego:** varias y autorizadas, con origen visible. En Zentra: comunidad educativa, panel (rector/apoyo), supervisión de campo, portería, API (canal Integración).

**Privacidad:** el aviso de menores de Normoteca (`minors_data_policy`) aplica al censo y, cuando haya intake comunitario, al tratamiento de datos de NNA. El reporte anónimo oculta nombre y teléfono; no reutiliza el pánico de Accesos.

**Quién ve qué**

| Actor | Superficie |
|--------|------------|
| Comunidad (padre, docente, entorno) | Web móvil 3 pasos. **No** instala APK |
| Rector / admin de sede | Solo su instalación |
| Secretaría (client-admin / entidad) | Mapa ciudad, tablero, ranking, API de lectura |
| Empresa de vigilancia | Sigue en Accesos / Supervisión / PWA. Alimenta fuentes; no es el panel del Observatorio |

### 7.4 Seis tareas de demostración (lenguaje ciudadano)

Las mismas del Anexo, en el orden en que se enseñan en visita. Encajan 1:1 con los factores 1–6; el factor 7 es la API de esa capa.

| # | Tarea (lenguaje ciudadano) | Factor | Dónde |
|---|----------------------------|--------|--------|
| 1 | Directorio de colegios (código, comuna, rector, contacto) | 1 | Instalación + admins |
| 2 | Mapa de la ciudad: pines por tipo de alerta + mapa de calor | 2 | Observatorio (nuevo) |
| 3 | Varias fuentes (padre, rector, patrulla, portería, API) en la misma base | 3 y 4 | Observatorio |
| 4 | Botón de pánico anónimo (ocultar nombre y teléfono) | 4 | Observatorio; **no** pánico de portería |
| 5 | Enganchar reportes repetidos en un incidente | 5 | Observatorio |
| 6 | Tablero, gráficas, ranking colegio más peligroso → más seguro | 6 | Observatorio |
| — | API / formatos estructurados + documentación | 7 | Observatorio (lectura/escritura controlada) |

### 7.5 Stack de esa licitación

El texto tipo “PostgreSQL + PostGIS + Python/Node + React/Angular” es receta, no obligación de logo.

| Momento | Decisión |
|---------|----------|
| Demo en semanas | Laravel (oficio de W Codex) + mapa + polígonos de comunas. Calor por recuento o capa Maps |
| Producto / puntaje geo | PostgreSQL + PostGIS (límites, distancias, calor de verdad). El backend puede seguir en Laravel |
| Portal ciudadano | Web móvil de 3 pasos. El padre **no** instala APK para denunciar |
| Panel Secretaría | Mapa + tablas + gráficos, privado |
| Interoperabilidad | OpenAPI del Observatorio; mismos formatos en la visita (crear evento, listar, filtrar) |

PostGIS **no cabe** en hosting compartido MySQL (plan Ilimitado típico). Va en VPS. **No** es requisito del Anexo 7.5: las capas de visita son GeoJSON de IDESC sobre Google Maps.

**Pliego Observatorio (Anexo 7.5):** v1 cerrada (tablero + PPTX + comunas IDESC + prioridad). MEN / typeahead no está en el Anexo.

---

## 8. App de campo (APK)

Decisión de valor para W Codex: las licitaciones de **vigilancia** y el mapa en vivo piden tracking aunque el celular esté en segundo plano o la pantalla apagada.

| Escenario | Superficie |
|-----------|------------|
| Revista, fotos, cola offline, mapa con la app abierta | PWA y APK ([`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md)) |
| Punto en el mapa con pantalla off | **APK corte 2** (Capacitor + `ShiftTrackingService`; notificación “Turno de supervisión activo”). El mapa distingue En línea / Pantalla apagada / Sin señal. |
| Portal de denuncias escolares | Web móvil, no APK |
| iOS | Después; el campo en Colombia es mayoritariamente Android |

La PWA **congela el JavaScript** con la pantalla apagada. Tailscale no lo arregla. Eso ya está anotado en supervisión de campo.

Matices de producto:

- En Android moderno el GPS de fondo **no puede ser invisible** (notificación persistente).
- 15 s fijos con pantalla off gasta batería; en campo suele ir 30–60 s, o más frecuente en movimiento.
- Play Console: ~USD 25 una vez + verificación + (cuentas nuevas) 12 testers × 14 días. La tienda pública no es obligatoria el día 1: Descargas + APK o Play privado para empresas cliente.
- Una sola APK para todas las empresas; el login identifica la empresa (igual que la PWA).

No reescribir la revista en React Native. Envolver lo que ya captura.

---

## 9. Hosting y dominio

**Proveedor elegido:** Hostinger, contrato **48 meses** (prioridad: no renegociar y dejar URL pública).

El plazo largo sirve. El **plan** tiene que aguantar lo que se va a meter:

| Carga | ¿Plan compartido Ilimitado (~50 GB, MySQL)? |
|-------|-----------------------------------------------|
| Landing W Codex + Zentra + PWA | Arranque sí |
| Fotos de revista + PDF + GPS | Se llena; hay que rotar o disco aparte |
| Observatorio con PostGIS | **No** |

**Decisión de capacidad:** los 4 años en **Cloud** o **VPS** Hostinger, no en el Ilimitado compartido si van Zentra + fotos + (luego) geo escolar. El Ilimitado se renueva ~4× más caro y no instala PostGIS.

Mismo servidor: Zentra sí. Observatorio: misma máquina solo si es VPS y **base aparte**.

**Dominio comercial (padre = W Codex, no un producto):**

| Host | Uso |
|------|-----|
| `www.wcodex.co` (o `.com.co` / `.solutions`) | Estudio / empresa |
| `zentra.wcodex.co` | SaaS |
| `campo.wcodex.co` | PWA y, después, la APK contra la misma API |

Si Zentra se vende a terceros con marca propia, más adelante `zentra.co` y W Codex queda como “hecho por”. Evitar `zentra_supervision.…`, `sj-sig.zentra.…` y guiones innecesarios.

El pliego de SJ puede seguir diciendo **SJ-SIG** en el Anexo; por detrás es Zentra con el módulo Expediente. No uses `sjsig.` como casa del producto.

---

## 10. Cómo se cubre la licitación de vigilancia (factor 4.2.4)

No se entrega un segundo hosting “SJ-SIG producción”.

SJ (o la empresa que gane) es una **empresa en Zentra**. Se activan Accesos/Supervisión según el contrato y el paquete **Expediente / SIG** (HV, cursos, parafiscales, tablero que ve el usuario de la entidad). El supervisor de la Alcaldía es usuario de **entidad** de ese cliente, no entra al Command Center ni a la PWA de campo.

Aislamiento: un supervisor de la entidad A no existe en el universo B (igual que hoy `tenant`/`client` en Zentra).

---

## 11. Orden de trabajo (cuando haya fase de código)

No se abre código en este corte. El orden acordado:

1. Puesto: modalidad + unidades por cargo (de SJ-SIG, sobre `supervisor_posts`).
2. Asignación empleado de empresa → puesto.
3. Instalación: Places, mapa, código, kind.
4. Administradores opcionales por instalación.
5. ~~Censo colgando de la instalación (salón / apto).~~ **Hecho 2026-09-11.** Personas asignadas al nodo; acceso de persona (`structure_app_users`). Vigilante de portería solo si hay puertas. Supervisor firma revista en minuta con código de 6 dígitos. Alta de nodo: `code` interno automático; padre por árbol («Crear dentro de» / **+**).
6. Paquete Expediente / SIG (indexador + tablero de entidad) — cubre pliego de vigilancia.
7. APK de campo (GPS de fondo) — diferenciador comercial.
8. Observatorio escolar (Anexo 7.5). v1 **hecho** (tablero + PPTX). Prioridad = catálogo del cliente. Comunas = IDESC Cali. Detalle: [`OBSERVATORIO.md`](OBSERVATORIO.md).

---

## 12. Fuera de alcance de este informe (no hacer)

- Fusionar repos Zentra + SJ-SIG.
- Sustituir el Excel WM por el de SJ-SIG.
- Meter denuncias o calor en minuta / `locations`.
- Tratar el pánico de portería como pánico anónimo escolar.
- Hacer cada colegio un `clients` aparte.
- Publicar Play Store antes de tener el *foreground service* real.
- Asumir que Hostinger Ilimitado compartido es el sitio del observatorio PostGIS.

---

## 13. Historial

| Fecha | Cambio |
|--------|--------|
| 2026-09-10 | Alta del informe. W Codex dueña; Zentra producto; SJ-SIG cantera. Geometría instalación + puesto con modalidad + admins de sede + censo bajo instalación. APK para GPS de fondo. Hostinger 48 meses en Cloud/VPS. Observatorio escolar como capa, no como Accesos. |
| 2026-09-10 | Empleados de empresa: ficha SJ-SIG Personal (4 bloques + foto). Excel WM ampliado, no sustituido. Sin indexador ni asignación a puesto. |
| 2026-09-10 | Instalaciones con georreferencia. Casilla «La instalación es el mismo cliente» copia nombre + pin. Un catálogo para Accesos y Supervisión. Cartera vacía habla de clientes, no de conjuntos. |
| 2026-09-10 | Puesto compartido Accesos/Supervisión: modalidad 8/12/24 h + vigilantes. Puertas solo en Accesos (`locations`). |
| 2026-09-10 | Ficha del cliente: una tarjeta de sitio (instalaciones + puestos) y otra de puertas (solo Accesos). Sin segundo árbol de Supervisión. |
| 2026-09-10 | Sitio sin bloque de revistas (van a `/company/supervision`). Varios vigilantes por puesto; un empleado = un puesto; Reasignar en la ficha del empleado. |
| 2026-09-10 | Documentos de personal (indexador SJ-SIG) en empresa. Cliente: flag Accesos + solo empleados de sus puestos, solo lectura. Distinto de Normoteca. |
| 2026-09-11 | Censo bajo instalación (`structures.installation_id`). Panel cliente: elige instalación → nodos → personas → acceso. Usuarios del cliente = `client-admin`. Vigilante portería solo con puertas. Supervisor firma revista en minuta. Término de producto: **cliente**, no conjunto. |
| 2026-09-11 | Alta de nodo sin código visible: `code` interno (slug, `-2` si choca, prefijo del padre). Panel ancho: árbol + **Crear dentro de** indentado; **+** en el nodo. |
| 2026-09-11 | Administradores del cliente interno (empleado, 1+ clientes) o externo (ficha propia, 1 cliente). Línea **Admin instalaciones**: varias sedes del mismo cliente; Ajustes solo ver; no crea usuarios. |
| 2026-09-11 | `/company/users`: pestañas Activos/Desactivados en el header; foto circular; Cliente + Instalaciones en una fila con filtro. |
| 2026-09-11 | Ficha cliente: tarjeta **Gestión de módulos**. Sidebar fijo (Resumen, Instalaciones, Personas, Usuarios, Accesos, Ajustes) y opcional (Vehículos, Mascotas, Autorizaciones, Puertas). |
| 2026-09-11 | Alta de usuarios solo súper admin y admin empresa. Personas: tipo de documento (TI/RC) + fecha de nacimiento. Menores: Ley 1581 art. 7; portería solo nombre; sin export. |
| 2026-09-11 | Protección de menores en Normoteca (`minors_data_policy`): clickwrap de contrato; aviso al crear cliente y admin cliente/instalaciones; Personas lee el texto vigente. |
| 2026-09-11 | Anexo 7.5 *Herramienta de Seguridad Educativa* (4 puntos, 7 factores): brecha vs Zentra, definición del Observatorio (institución / reporte / evento / mapa / tablero / API). Sin código. |
| 2026-09-11 | Factor 1 (directorio): módulo `/company/installations` (tabla, búsqueda, ficha con código, comuna, rector = admin de instalaciones, mapa). |
| 2026-09-11 | Instalaciones unificadas: alta en módulo y en ficha cliente; admin de sede = rol + cargo; ficha empresa con puestos; sidebar cliente **Instalaciones** (estructura dentro de la ficha). |
| 2026-09-11 | Ficha de sede: mapa a la izquierda, datos a la derecha. Árbol: sin vehículos/mascotas si el módulo está apagado. Miniaturas del indexador: worker pdf.js como blob (MIME `.mjs` en CloudPanel). |
| 2026-09-11 | Indexador: columna izquierda `minmax(24rem, 32rem)`; miniaturas ~220px (`scale` 0.48). |
| 2026-09-11 | Área de la sede desde el mapa: comuna / localidad / vereda / corregimiento; si no aplica no se guarda. |
| 2026-09-11 | Factor 1: `kind` + DANE de sede (único, solo colegio, texto 8–12 dígitos; sin catálogo MEN); nombre repetible; personal N con permiso admin/apoyo; `rector_user_id` = contacto del directorio. |
| 2026-09-11 | Ficha de sede: dirección bajo el nombre; bloques Administrador y Apoyo (`cargo · nombre`). |
| 2026-09-11 | Observatorio v1: reporte → evento. Público `/o/{slug}` (colegios, 3 pasos, foto opcional, anónimo). Estados `nuevo` → `en_atencion` → `cerrado`; los cierra el admin de instalaciones. Empresa y `client-admin` ven, no cambian estado. |
| 2026-09-11 | Observatorio: link público copiable en empresa y cliente. Permisos `observatory.view` / `observatory.events.update`. Siguiente: mapa/calor. |
| 2026-09-11 | Observatorio mapa/calor v1: pines de colegios por estado y capa de calor de eventos abiertos. |
| 2026-09-11 | Observatorio tablero v1: KPIs por estado, ranking de colegios, filtro fecha; vista reordenada (filtro → cifras → mapa/ranking → eventos). |
| 2026-09-11 | Observatorio: agrupa reportes (mismo colegio + tipo, ventana 1 h) y pin en el intake; calor por ubicación del reporte. |
| 2026-09-12 | Observatorio: el admin de la sede une folios del mismo colegio o saca un reporte a un folio nuevo. Empresa, `client-admin` y apoyo no. |
| 2026-09-12 | Observatorio fuentes: comunidad (alumno/padre/vecino + recordar en el teléfono), panel (rector/apoyo), PWA patrulla (supervisor), minuta novedad si hay puerta de colegio (vigilante). Anónimo oculta nombre y teléfono. |
| 2026-09-12 | Observatorio tablero (tendencia, tipos, canales, medidor de cierre) y API Sanctum + OpenAPI `/docs/observatory`. Secretaría escribe como Integración; empresa solo lee. |
| 2026-09-12 | Observatorio: pestañas Tablero / Eventos; ficha con bitácora (Agregar / Cerrar folio, nota obligatoria); Compartir link; Nuevo reporte visible y bloqueado si no es rector/apoyo. |
| 2026-09-12 | Shell: pestañas colgando del header; Nuevo reporte en `$actions`; Cerrar sesión en el pie del sidebar (todos los paneles); flecha para ocultar/mostrar el menú. |
| 2026-09-12 | Tablero Observatorio: mapa + leyenda; colegios + tendencia ancha; tipo/canal más grandes; controles Pines/Calor y Mapa/Satélite fuera del lienzo de Google. |
| 2026-09-12 | Anexo 7.5 leído en literal. Visita Cali. Falta para el pliego (ya cubierto después): capas comunas IDESC, prioridad configurable, export. |
| 2026-09-13 | Observatorio: tipos del cliente (nivel+color), puntaje, líneas, picos, calor sede/riesgo, filtro empresa por cliente, módulo opt-in. Falta comunas y PPTX. |
| 2026-09-13 | Observatorio: capa y filtro por comuna urbana de Cali (IDESC). Queda export PPTX. |
| 2026-09-12 | Ficha de instalación: el pin en Cali asigna y pinta la comuna IDESC (no solo Places). |
| 2026-09-12 | Observatorio UX: buscador de comuna en el mapa (sin chips 01–22), zoom al polígono aunque no haya sedes, fechas en modal, cliente más angosto. |
| 2026-09-12 | Observatorio: pines de cualquier tipo de sede; sin sedes = mapa Colombia + 22 comunas; con sedes = zoom a pines y solo comunas ocupadas; comuna vacía se pinta. Área IDESC de la ficha bloqueada. |
| 2026-09-12 | Observatorio: Google Maps 3.65 quitó HeatmapLayer; el calor es círculo propio. La capa IDESC se carga siempre (ruta relativa); Todas = Cali + 22; una comuna hace zoom a ese polígono. |
| 2026-09-12 | Observatorio: export PPTX del tablero (mismos filtros; cifras, ranking, tendencia, picos y canal). |
| 2026-09-12 | App de campo: APK debug (Capacitor) en Descargas; API fija a zentra.wcodex.cloud. GPS con pantalla off = corte 2. |
| 2026-09-14 | Observatorio: `/o/{slug}` y panel rector con cámara y hasta 3 miniaturas (opcional). Tablero empresa agrupa tipos por slug. |
| 2026-09-14 | Parafiscales: layout PILA por filas CC+cédula (hoja de cotizantes), no por la Identificación del aportante. |
| 2026-09-14 | Parafiscales: recorte = copia del xlsx original (fondo/logo) + filas del cotizante; una carga sustituye la anterior. |
| 2026-09-14 | Súper admin: cambio de plan Accesos+Supervisión ya / fecha / al corte. Cartera de clientes con scroll. |
| 2026-09-14 | Tablero SIG en resumen cliente e instalaciones; pánico por usuario (empresa/APK) y overlay Observatorio sin alertar a quien reporta. |
| 2026-09-14 | APK Supervisión v1.3 (pánico en campo; Descargas). |
| 2026-09-14 | APK v1.4: permisos de GPS/notificaciones antes del servicio en primer plano (no cierra al reentrar con turno abierto). |
| 2026-09-14 | Atención de pánicos: permiso `ops.panic.attend`, ficha abierto/cerrado, descarga tipo supervisor, alarma en bucle. |
| 2026-09-14 | Fix: `panelSidebar` + `opsLiveAlerts` juntos en `app.js`; sin el sidebar Alpine el tablero Observatorio queda en blanco. |
| 2026-09-15 | Mapa En vivo: filtro de traza GPS (precisión, velocidad de moto, picos aislados) para que la polilínea no se vea como sismógrafo. |
| 2026-09-16 | Supervisión: En vivo sin filtros ni trazas hasta clic; Historial solo turnos cerrados (una ruta en calle); ficha de turno (Ver) se guarda al cierre (`sheet_snapshot`). |
| 2026-09-16 | APK/PWA v1.6: selfie de inicio en el círculo de perfil. PWA `zentra-sup-v47`. |
| 2026-09-17 | Portería: consola vigilante (ingreso unificado, censo, pánico `operational_alerts`). Puerta solo al login del vigilante; Operar portería no. Empleado = empresa; persona = instalación. |
| 2026-09-15 | Matriz de permisos (admin y colaborador): tablero, instalaciones, pánicos, SIG. Overlay pánico/observatorio independientes, palpitante y sonido en bucle. |
| 2026-09-15 | Admin cliente e instalaciones: misma matriz (SIG, Observatorio, Censo) por cliente o sede. |
| 2026-09-15 | Ficha de folio Observatorio: grilla mapa + datos / novedades + bitácora, con scroll en los paneles inferiores. |
| 2026-09-15 | Tablero Observatorio: medidor de carga (verde→rojo). Nuevo empuja a rojo; atender baja; cerrar baja más. Arco sin recorte; leyenda bajo la aguja. |
| 2026-09-15 | Pánico: envío al toque (panel/APK). Enterado silencia a un usuario; Atender apaga overlay y sonido en todos. APK v1.5. |
| 2026-09-15 | Modalidades de servicio del puesto: catálogo en Ajustes (1–24 h; semilla 8/12/24). |

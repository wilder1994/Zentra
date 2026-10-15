# Portería (`/access`)

**Última actualización:** 17 septiembre 2026

Consola de **turno del vigilante**. Login rol `guardia` → `/access`. El admin de empresa entra con **Operar portería**. El supervisor de vigilancia **no** usa este panel (solo APK/PWA).

## Empleados vs personas

| | **Empleado** | **Persona** |
|--|--------------|-------------|
| Quién | Colaborador de la **empresa de seguridad** | Gente de la **instalación** (censo) |
| Tabla | ficha `employees` | `structure_members` en un **nodo** |
| Dónde | `/company/employees` | `/client/members` (y consulta en portería) |
| Ejemplos | vigilante, supervisor, admin interno | residente, alumno, ocupante del predio |
| En portería | Opera el turno (usuario + puerta) | Entra, sale o se busca por nodo |

El vigilante es **empleado** asignado a un **puesto**. No es una persona del censo. Un **visitante** no es empleado ni persona del nodo.

Detalle de fichas de empresa: [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md). Censo: [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

## Puerta de operación

Tabla `locations` (no confundir con **puesto**). Solo el **vigilante** (`guardia`) al entrar a `/access`. **Operar portería** desde el expediente no pide puerta ni turno.

1. **Una puerta activa:** el sistema la liga al vigilante. No pide elegir.
2. **Varias puertas:** el vigilante elige al abrir turno. Queda en `guard_shifts.location_id` y en sesión.
3. Sin puertas activas el vigilante no opera.

El pánico del vigilante usa **ese usuario + esa puerta**. El admin de empresa en portería dispara pánico del cliente (sin elegir puerta).

## Pánico

Un solo canal: el de admin cliente, admin instalaciones y supervisor.

- Clic → `POST /access/ops/panic` (sin modal ni minuta).
- Overlay + sonido en la empresa (`/company/ops/alerts.json`). **Enterado** / **Atender** (`/company/panics`).
- Quien pulsa **no** oye su propio pánico.
- GPS si el navegador lo da.

Se eliminó el pánico viejo (`POST /access/guard_logs/panic`, modal de minuta `is_panic`). Las minutas no disparan pánico.

## Menú

Resumen · Ingreso y salida · Personas · Vehículos · Mascotas · Correspondencia · Autorizaciones · Reservas · Minutas · Lista de bloqueo · Instalaciones · Visitantes · Turnos.

## Ingreso y salida

`/access/logs` tiene **dos pestañas**.

1. **Movimiento.** Una búsqueda (placa, documento o nombre). La tarjeta muestra foto de la **ficha** si existe. **Ingresa** si está afuera, **Sale** si está adentro, **Cancelar**. Censo entra/sale directo. Visitante al ingresar: último nodo/autorizador (editable: nodo + miembro, o destino en texto). Si no hay ficha: **¿Registrar?** (foto opcional solo ahí; se guarda en `structure_members` / `visitors` / `vehicles`, no en cada movimiento).
2. **Registros.** Movimientos del día o rango, con filtros personas/vehículos, vigilante y buscador. Columnas: entrada, salida, quién, tipo, foto, destino, autoriza, vigilante, estado.

Destino: `destination_structure_id` / `destination_text`. Autorizador del predio: `authorized_member_id`. El vigilante queda en `host_id`. Puerta: turno/sesión o primera activa (`operatingOrFirst`). Bloqueados no entran.

Personas / vehículos / mascotas / instalaciones leen el censo (no `employees`). Visitantes: pestaña personas y vehículos de visita. Autorizaciones: `visitor_pre_authorizations`. Reservas: zonas comunes; pestañas Hoy (incluye pendientes, sin «Dar uso»), Próximas, Historial y Calendario. En Calendario el vigilante ve días libres u ocupados y pulsa «Solicitar reserva»: queda por aprobar (`origin = porteria`, «vía portería») hasta que el admin de la instalación o del cliente la apruebe, modifique o rechace. No se puede solicitar un horario ya confirmado.

Pánico: ver arriba. Tests: `PorteriaDoorAndPanicTest`, `PorteriaConsoleTest`.

## Minuta (libro)

`/access/guard_logs` es el libro de minuta de la puerta: encabezado «Minuta de servicio» con cliente, instalación, puerta, fecha y vigilante; renglones Fecha · Hora · Asunto · Anotación. Navega por día (sin días futuros), cambia de puerta si hay varias e imprime solo el libro.

- **Nueva anotación:** línea al final del libro (solo hoy). Asuntos: General, Novedad, Incidente, Cambio de turno. Hora y turno (diurno 06–18) los pone el servidor; el vigilante que escribe firma la línea.
- **Revista del supervisor:** modal; el supervisor digita su código de 6 dígitos (o uno del catálogo de portería). Solo la revista exige código.
- **Correcciones:** nada se borra (no hay ruta de borrar ni editar). «Corregir» agrega una línea `correccion` enlazada (`corrects_guard_log_id`); la original muestra «Corregida a las HH:MM».
- **GPS:** se captura si el navegador lo da; no bloquea ni valida distancia.
- Novedad en puerta de colegio: casilla «También al Observatorio».

Tests: `PorteriaRevistaTest`.

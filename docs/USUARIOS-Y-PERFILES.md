# Usuarios, perfiles y ubicación

Gestión de usuarios web (`users`) por panel, perfil de empresa con geolocalización y datos de clientes.

**Última actualización:** 28 septiembre 2026

La **ficha de empleado** (listado, 4 bloques SJ-SIG, foto, Excel WM + extras) vive en el sidebar **Empleados**. Es gente de la **empresa**, no del censo del predio. El Excel **no** crea usuario: solo la ficha de empleado. Reimportar el mismo documento **actualiza** la ficha (no duplica). Cargos, tipos y catálogos de Supervisión de campo: **Ajustes**. Ver [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md), [`PORTERIA.md`](PORTERIA.md) y [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md). Este documento cubre **usuarios** (`users`): login y roles. Login y formularios: mensajes en español al centro de la pantalla ([`DISENO-UI-ZENTRA.md`](DISENO-UI-ZENTRA.md)). **Una sesión** por usuario en el panel web: si hay sesión abierta en **otro equipo**, pregunta una vez «Tienes una sesión abierta. ¿Cerrar la otra y seguir aquí?» (Sí no vuelve a pedir la contraseña; vale 2 min). En el mismo equipo (cookie `zentra_device`) no vuelve a preguntar. La app de campo (APK/PWA) va aparte: ver **Cambio de dispositivo** en [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md).

Sidebar empresa: **Mi empresa** (dashboard) · Facturación · Clientes · **Instalaciones** · **Observatorio** · Supervisión · **Descargas** · **Empleados** · **Documentos** · Usuarios · **Mis datos** (este perfil) · **Ajustes** (Cargos | Tipos | Estructuras | Zonas | Turnos | Modalidades | Preoperacional | Documentos | Libros | Tipos de arma | Marcas | Riesgos | Alarmas | Apoyos | Presentación personal | Temas de capacitación). Chatbot de ayuda y PQRS: pendiente, [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md). Observatorio: [`OBSERVATORIO.md`](OBSERVATORIO.md).

---

## Modelo de acceso (Corte 1)

Tres administradores; el **admin empresa** también usa la matriz Nada / Ver / Gestionar (mismo catálogo que el colaborador). El rol `company-admin` ya no trae los módulos de empresa: salen de `user_module_grants`. Alta nueva o seeder: todos en Gestionar. Portería/censo del cliente siguen en el rol.

| Quién | Alcance |
|--------|---------|
| Admin empresa (`company-admin`) | Matriz de módulos de empresa + portería/censo del rol |
| Admin cliente (`client-admin`) | Ese cliente. Matriz por módulo (SIG, Observatorio, nodos, personas, vehículos, mascotas, autorizaciones, usuarios, app, empleados, documentos). **No crea usuarios empresa.** |
| Admin instalaciones (`client-installation-admin`) | Instalaciones asignadas. Misma matriz recortada a esas instalaciones. Apoyo: no borra nodos ni edita ficha. **No crea usuarios empresa.** |

Oficios (no son un collage):

| Oficio | Acceso |
|--------|--------|
| **Supervisor** | Solo app de campo. Login usuario + clave. Extra: código 6 dígitos para revista. |
| **Vigilante** | Solo portería del cliente asignado (con puertas). Reasignar a otro cliente con puertas: mismas credenciales. |

**Admin cliente** e **admin instalaciones**: matriz Nada / Ver / Gestionar por módulo de cliente o instalación (no solo SIG / Observatorio / Censo). Portería no se recorta en esa matriz. Empleados y documentos empiezan en Nada: si se conceden, el listado es el de puestos de ese cliente o de esa instalación. Tipos de persona: botón en **Personas**, no en Ajustes.

---

## Alta de usuario en empresa (regla única)

Panel `/company/users`. **Crear y editar usan el mismo formulario** (`modules/company/users/partials/form.blade.php`). No se da acceso desde la ficha de Empleados.

**Listado:** pestañas **Activos | Desactivados** en el header (`x-slot:headerTabs`, igual que Ajustes). Sin título ni texto en el cuerpo. Misma fila: Buscar + **+ Nuevo usuario**.

**Formulario:**

1. Foto circular centrada (`x-client.member-photo-picker`, campo `avatar`). Muestra avatar o icono de cámara; clic para elegir.
2. **Rol**. Si es administrador del cliente: origen interno/externo. Admin instalaciones es siempre externo. Al crear admin cliente o instalaciones se muestra el aviso vigente de protección de datos de menores (Normoteca).
3. Primera fila de ficha: **Nombre** y **Cédula**. Interno: ambos buscan empleados activos **sin** usuario. Externo: se escriben a mano (más cargo y correo).
4. **Usuario de acceso** (todos los roles de empresa: admin empresa, colaborador, admin del cliente, supervisor, vigilante): primer nombre + primer apellido paterno, ASCII, minúsculas, más **4 dígitos aleatorios** que cambian en cada generación (`ana.perez.4821`). No es la cédula. Único en `users.username`. Ese es el **login**.
5. **Cargo / función**: select del catálogo `company_job_titles` (interno) o texto libre (externo). Al elegir empleado se precarga el cargo de la ficha.
6. **Correo corporativo** (solo administrador del cliente **interno**, `users.corporate_email`): ahí le llegan las novedades de Supervisión de sus clientes. **No** es login. El email personal de la ficha ya no se muestra en el formulario. Envío de usuario/clave por correo: pendiente.
7. **Cliente** e **Instalaciones** (si el rol lo pide) en la misma fila: filtro al escribir y lista con checkbox. Instalaciones se cargan al elegir cliente (`GET /company/users/installations`).
8. Generar clave, activo. Los campos `type="password"` (login, alta, cambio, código de supervisor) tienen icono de ojo para verla (`x-ui.password-wrap`).
9. Clave aleatoria. Tras crear, modal para **copiar usuario y contraseña** (no se vuelven a mostrar). Primera entrada: cambia **usuario y contraseña** (`/password/primera` o PWA). Supervisor: además `supervisor_code` de 6 dígitos (revista Accesos, no login).
10. La zona de Supervisión **no** se pega al usuario ni se muestra en la tabla: se elige al abrir turno. Los clientes van en la zona (Ajustes → Zonas).
10b. **Responsable de clientes = administrador del cliente interno.** Varios clientes por responsable y varios responsables por cliente. **Permisos por cliente**: bloque **Aplicar a todos** (Nada / Ver / Gestionar por módulo + botón «Aplicar a todos los clientes») y la lista de clientes plegada con etiqueta **Igual a todos** / **Personalizado**; al cambiar el bloque se actualizan los que siguen igual. **Portería en solo lectura** (`porteria.readonly`): ve todo, no registra ni cambia nada (403 / aviso «solo lectura»; el botón de pánico sí funciona). Recibe por correo las novedades de sus clientes (ver [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md)).
11. **No se elimina** la cuenta. **Desactivar** (pestaña Activos) pone `is_active = false`: sale del listado activo, no puede entrar al panel ni a la PWA, el historial queda. Pestaña **Desactivados** → Reactivar. No se puede desactivar a uno mismo.
12. **Empresa con acceso suspendido** (ficha plataforma, bloque **Acceso al sistema** → Suspender / Archivar): el **admin empresa** entra al panel en solo lectura con el aviso **Servicio suspendido**. El resto (portería, PWA/APK, API) no entra: *Este usuario no tiene acceso al sistema. Comuníquese con el administrador.* No confundir con **Cancelar membresía**. Detalle: [`PLATAFORMA-ADMIN.md`](PLATAFORMA-ADMIN.md).

Cuentas antiguas pueden seguir entrando con `users.email` si lo tienen. Plataforma (`/admin/users`) no usa este flujo.

**Quién crea usuarios**

| Quién | Dónde |
|--------|--------|
| Súper administrador | `/admin/users` (`platform.users.manage`) |
| Administrador empresa | `/company/users` (`company.users.assign`). Puede crear `company-admin`. |
| Colaborador con Usuarios → Gestionar | `/company/users` (`company.users.assign` vía grant). **No** puede crear ni editar `company-admin`. |
| Admin del cliente / admin instalaciones | **No crean.** `/client/users` solo lista y edita externos de ese cliente. |

---

## Administradores del cliente (interno / externo)

No todos los que administran un cliente son empleados de la empresa.

Al crear un **Administrador del cliente** o **Admin instalaciones** se pregunta el origen:

| Origen | Quién | Clientes | Alta |
|--------|-------|----------|------|
| **Interno** | Empleado de la empresa | Uno o varios | Panel empresa: ficha de empleado, igual que el resto de roles internos |
| **Externo** | Persona del cliente (no empleado) | Un solo cliente | Nombre, cédula, cargo, correo. Login `nombre.apellido.####` |

Dos líneas de externo:

| Rol UI | Spatie | Alcance |
|--------|--------|---------|
| **Administrador del cliente** | `client-admin` | Todo el panel de ese cliente |
| **Admin instalaciones** | `client-installation-admin` | Varias instalaciones **del mismo cliente**. En la ficha de instalación: bloque **Administrador** o **Apoyo** (`cargo · nombre`). El cargo (rector, auxiliar…) es `job_title`. Permiso `installation_permission`: **admin** (opera y puede borrar nodos) o **apoyo** (mismas instalaciones, no borra nodos). No se crea un tercer rol. **Ajustes**: solo ver. **No** crea usuarios. `client-admin` externo no lleva instalación (p. ej. Secretario). |

El panel `/client/users` **lista** administradores externos de ese cliente. **No crea usuarios**: el alta queda en `/company/users` o `/admin/users`. Los internos se asignan en `/company/users`.

Asignación de instalaciones: `client_user_installation_assignments`. El censo (estructura, personas, vehículos, mascotas, accesos de persona) se filtra a esas instalaciones.

---

## Glosario operativo (nombres canónicos)

Usar **siempre** estos nombres en UI y documentación de producto. Los slugs Spatie se mantienen por compatibilidad.

| Nombre de producto | Rol Spatie (`users`) | Pertenece a | Resumen |
|--------------------|----------------------|-------------|---------|
| **Vigilante** | `guardia` | Empresa | Opera **portería** de una instalación **con puertas**. Debe estar asignado a un puesto de esa instalación. Sin puertas no hay usuario de portería. |
| **Supervisor de vigilancia** | `supervisor` | Empresa | **Solo app de campo** (PWA/APK). Login: `users.username` + clave. **Sin** panel empresa ni portería. Firma revista en la **minuta** con código **6 dígitos** (`supervisor_code`). API: `/api/supervision/login`. |
| **Administrador del cliente** | `client-admin` | Cliente | Interno (empleado, 1+ clientes) o externo (1 cliente). Panel del cliente; **no crea usuarios**. |
| **Admin instalaciones** | `client-installation-admin` | Cliente | Siempre externo. Varias instalaciones del mismo cliente. Admin o apoyo (`installation_permission`). Sin crear usuarios; Ajustes solo lectura. |
| **Administrador empresa** | `company-admin` | Empresa | Cartera, usuarios operativos, perfil. |
| **Colaborador** | `colaborador` | Empresa | Matriz por módulo (`user_module_grants`). |
| **Admin empresa** | `company-admin` | Empresa | Misma matriz; el rol solo deja portería/censo. |
| **Súper administrador** | `super-admin` | Plataforma | Panel `/admin`. |

**Prohibido en producto:** llamar “guarda/guardia” al vigilante; llamar “supervisor” al admin del cliente; inventar un segundo tipo de supervisor (p. ej. “supervisor portería” vs “supervisor empresa”). Hay **un solo** supervisor: el de vigilancia. El término de producto es **cliente**, no conjunto.

### Relación empresa ↔ cliente (cobros)

Zentra **no** factura ni muestra deuda del cliente hacia la empresa de seguridad. Ese cobro es externo (contrato de vigilancia).

En el cliente se registran datos comerciales (`party_type`, documento, contactos, representante, ciudad) y **`service_started_at`**. El **tipo de estructura** se fija en el alta. Instalaciones, accesos y puestos **no** van en el Excel; instalaciones en `/company/installations` o ficha del cliente; puestos en la ficha de la instalación. Ver [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

---

## Reglas: Vigilante

1. Asignado a **un único** cliente a la vez (`client_user_assignments` + `primary_client_id`).
2. El empleado debe estar en un **puesto de una instalación de ese cliente**.
3. Esa instalación debe tener **puertas**. Sin puertas no se crea (ni se necesita) el usuario de portería.
4. Se puede **reasignar** a otro cliente **con puertas**. Conserva el **mismo usuario y contraseña**.
5. Se puede editar la **ficha de empleado** (nombre, cargo/función, foto) sin crear otro usuario (ej. portería ↔ ronda).
6. El sistema debe poder responder siempre: *¿a qué cliente e instalación está asignado este vigilante?*

Slug técnico: `guardia`. Label UI: **Vigilante**.

### Operar desde el expediente = ver como ese rol

Al operar un cliente desde el expediente (súper admin o admin de empresa), portería (`access.*`) y panel cliente (`client.*`) usan **los permisos del rol operado**, no los de quien opera (`OperateAsRole`):

| Botón | Permisos que se aplican |
|-------|-------------------------|
| Operar portería | Rol `guardia` (Vigilante) |
| Operar cliente | Rol `client-admin` + módulos por defecto del cliente |
| Operar instalación | Rol `client-installation-admin` + módulos por defecto de la instalación |

Menú y URLs (estricto): lo que el rol no puede, da 403. El panel empresa y «Volver al expediente» no cambian.

Vigilante en portería: no ve Instalaciones, no crea/borra puntos de acceso, torres, apartamentos ni zonas comunes, no ve reportes ni auditoría, y **puede bloquear pero no quitar bloqueos** (`access.blocklist.remove`, solo administradores). Sí atiende Reservas del día (check-in por QR) y abre/cierra su turno.

---

## Reglas: Supervisor de vigilancia

1. Pertenece a la **empresa** (`security_company_id`). **No** requiere asignación fija a un cliente ni a una zona.
2. Alta de acceso: **Usuarios** → Nuevo → nombre y cédula del empleado (misma fila) → se genera **usuario de acceso** (`nombre.apellido.####`) y clave. Rol supervisor. La ficha de empleado (Ficha/Editar) no crea usuarios.
3. Al crear el usuario también se genera un **`supervisor_code`**: numérico, **6 dígitos**, **permanente** hasta regeneración deliberada. Sirve para **pasar revista en la minuta de portería**. No es el usuario de la PWA.
4. El código de 6 dígitos es único **por empresa**.
5. **Revista Supervisión:** en la app de campo; no se vuelve a firmar en puesto. Zona se elige al abrir turno (Norte hoy, Sur mañana, o las dos el mismo día); solo ve los clientes de esa zona (propios y compartidos) y la central puede habilitarle otra zona en vivo. Ver [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md).
6. No entra al panel web: `/home` y el resto redirigen a «usa la app». No confundir con admin del cliente ni con el vigilante de turno.

Slug técnico: `supervisor`. Label UI: **Supervisor de vigilancia**.

---

## Alcance por panel

| Panel | Rutas | Quién puede gestionar |
|-------|-------|------------------------|
| **Plataforma** | `/admin/users` | Todos los `users` y roles |
| **Empresa** | `/company/users` | Usuarios de `security_company_id` + usuarios asignados a clientes de esa empresa |
| **Cliente** | `/client/users` | Lista administradores **externos** de ese cliente. **No crea**. Alta: empresa o plataforma. |

**No mezclar** con `structure_app_users` (Accesos: personas del censo, `usuario@login_suffix`) — gestionados en `/client/app-users`.

### Roles asignables

| Panel | Roles |
|-------|--------|
| Plataforma | `super-admin`, `company-admin`, `client-admin`, `client-installation-admin`, `guardia`, `supervisor`, `colaborador`, `resident`, `anfitrion`, `admin-accesos` |
| Empresa (admin empresa) | `company-admin`, `client-admin`, `client-installation-admin`, `guardia`, `supervisor`, `colaborador` |
| Empresa (colaborador) | Igual **sin** `company-admin` (`AssignableRoles::forCompanyActor`) |
| Cliente | `client-admin`, `client-installation-admin` (solo externos) |

Roles que requieren asignación a cliente (`client_ids`): `client-admin`, `client-installation-admin`, `guardia`, `resident`, `anfitrion`.

- `guardia` (Vigilante): **exactamente un** cliente, y solo si hay puesto + puertas en una instalación.
- `client-admin` interno: **uno o varios** clientes.
- `client-admin` externo y `client-installation-admin`: **exactamente un** cliente.
- `supervisor`: **sin** `client_ids` (alcance empresa).
- `colaborador`: clientes e instalaciones salen de los grants (empresa ancha, cliente o instalación). Grant empresa **Clientes** = listado y expediente de todos los clientes de la empresa.

---

## Colaborador (`user_module_grants`)

Tabla: `user_module_grants` (`scope` company/client/installation, `module`, `level` view/manage). Sync: `SyncCollaboratorAccessService`. Catálogo: `GrantableModules`.

**Empresa:** Clientes, Instalaciones, Observatorio, Supervisión, Empleados, Documentos, Usuarios, Ajustes.

**Cliente / instalación:** Observatorio, Censo.

Reglas:

- Clientes Ver/Gestionar a nivel empresa abre `/company/clients` (listado y ficha). Gestionar también crea/edita. Sin esa rama el menú se veía y el listado respondía 403.
- Observatorio o Supervisión a nivel empresa abre **todos** los clientes e instalaciones de la empresa (detalle de evento incluido).
- Grant a un cliente abre las instalaciones de ese cliente; grant a una instalación solo esa.
- Empleados: `company.employees.view` / `manage` (`EmployeePolicy`). Documentos: `company.documents.view` / `manage` (indexar, carga masiva y **descargar** = manage; Ver no descarga).
- Usuarios → Gestionar no incluye promover a admin empresa.

---

## Campos relevantes

### `users`

| Campo | Uso |
|-------|-----|
| `username` | Login: `nombre.apellido.####` (4 dígitos aleatorios) para **todos** los usuarios creados en empresa |
| `email` | Opcional. No es el login de las altas nuevas. Cuentas viejas pueden seguir entrando con él. El correo de la persona es `employees.email` (email personal). |
| `job_title` | Cargo / función del empleado (portería, ronda, etc.) |
| `avatar_path` | Foto de perfil (storage `public`) |
| `supervisor_code` | Código revista Accesos 6 dígitos (solo rol supervisor; no es login PWA) |
| `primary_client_id` | Cliente actual del vigilante (y otros roles con asignación) |
| `admin_origin` | `internal` / `external` en administradores del cliente |
| `document_number` | Cédula del administrador externo |
| `must_change_password` | Primera entrada: redirige a `password.first` hasta cambiar **usuario** (`nombre.apellido.####` distinto) y **clave** |

### `clients`

| Campo | Uso |
|-------|-----|
| `service_started_at` | Fecha de inicio de servicio (única fecha comercial operativa del cliente en Zentra) |

---

## Permisos (`config/access.php`)

| Permiso | Uso |
|---------|-----|
| `platform.users.view` / `platform.users.manage` | Listado y CRUD global |
| `company.users.view` / `company.users.assign` | Ver / crear-editar usuarios en panel empresa |
| `company.employees.view` / `company.employees.manage` | Listar / mutar fichas de empleados |
| `company.documents.view` / `company.documents.manage` | Ver carpetas y preview / indexar, subir y **descargar** |
| `company.billing.manage` | Facturación (matriz) |
| `company.profile.manage` | **Mis datos** (perfil legal/geo) |
| `company.downloads.view` | Descargas PWA/APK |
| `company.installations.view` / `manage` | Listado/ficha / alta-edición de instalaciones |
| `ops.panic.attend` | Atender pánicos: módulo, ficha y botón Atender. Independiente de Supervisión y de Observatorio |
| `company.devices.approve` | Cambio de dispositivo: alerta y Aceptar/Rechazar cuando un supervisor entra desde otro celular (vence en 10 min). El admin de empresa siempre puede |
| `company.geo.approve` | Georreferenciaciones: alerta abajo a la derecha y Aprobar/Rechazar la ubicación que envía un supervisor para una instalación. Módulo «Aprobar georreferenciaciones» en la matriz. El admin de empresa siempre puede |
| `ops.sig.view` | Resumen / tablero SIG (panel cliente) |
| `company.settings.view` / `company.settings.manage` | Ajustes (cargos, tipos, catálogos) |
| `client.users.manage` | Ver/editar administradores del cliente. **No** crear. |
| `client.settings.manage` | Crear/editar tipos de persona. El admin de instalaciones no lo tiene (Ajustes solo ver) |
| `observatory.view` | Ver eventos del Observatorio (empresa: sus clientes; cliente: su alcance). Misma llave para la API `/api/observatory/*` |
| `observatory.events.update` | Cambiar estado, unir folios del mismo colegio o sacar un reporte a folio nuevo. Solo admin de instalaciones de esa instalación (`installation_permission=admin`), no el apoyo |

El rol `supervisor` **no** tiene permisos `access.*` (solo app). Tras cambios en permisos:

```bash
php artisan db:seed --class=RoleAndPermissionSeeder
```

---

## Rutas

### Plataforma

| Ruta | Función |
|------|---------|
| `GET /admin/users` | Listado global |
| `GET/POST /admin/users/create` | Crear usuario |
| `GET/PUT /admin/users/{user}/edit` | Editar usuario |
| `GET /admin/companies/{company}/profile` | Perfil empresa (legal + geo) |
| `PUT /admin/companies/{company}/profile` | Guardar perfil |

### Empresa

| Ruta | Función |
|------|---------|
| `GET /company/users` | Listado (`status=active` \| `inactive`; pestañas en header) |
| `GET/POST /company/users/create` | Crear usuario empresa / colaborador / vigilante / supervisor / admin cliente o instalaciones |
| `GET/PUT /company/users/{user}/edit` | Editar (foto, cargo, reasignación, código supervisor) |
| `GET /company/users/employee-search` | Typeahead de empleados sin usuario |
| `POST /company/users/credentials-preview` | Genera `username` + clave |
| `GET /company/users/installations` | Instalaciones del cliente (admin instalaciones) |
| `POST /company/users/{user}/deactivate` | Desactiva el acceso; conserva historial |
| `POST /company/users/{user}/reactivate` | Reactiva el acceso |
| `GET /company/settings` | **Mis datos**: perfil, ubicación, logo (arrastrar / pegar / recortar) e encabezado de fichas |
| `PUT /company/settings` | Guardar perfil, logo y texto de encabezado |
| `GET/POST /company/clients` | Cartera de clientes (`service_started_at`) |
| `GET /supervision/app` | Aviso web del supervisor: usar la app de campo |

### Cliente

| Ruta | Función |
|------|---------|
| `GET /client/users` | Administradores del cliente (listado) |
| `GET/POST /client/users/create` | Crear: solo empresa o plataforma (`company.users.assign` / `platform.users.manage`) |
| `GET/PUT /client/users/{user}/edit` | Editar |
| `GET /client/app-users` | Accesos (personas del censo) |

---

## Ubicación y dirección

### Modelo

| Entidad | Campos |
|---------|--------|
| `security_companies` | `address`, `city`, `department`, `latitude`, `longitude` |
| `clients` | `address`, `city`, `department`, `latitude`, `longitude`, `service_started_at` |
| `commercial_signup_intents` | `address`, `city`, `department`, `latitude`, `longitude` (paso datos) |

Migración geo y operativos: absorbidas en creates baseline (`create_security_companies`, `create_clients`, `create_users` + FKs).

### UI compartida

Componente Blade: `x-ui.geo-address-fields` (dirección, ciudad, departamento + lat/long; botón mapa).

JS: `resources/js/geo-address-picker.js` (Places Autocomplete + Geocoding; requiere APIs en la clave Google Maps).

Icono dirección: `resources/images/ui/map-pin.png`. Icono GPS supervisor (mapa Supervisión): `resources/images/ui/supervisor-moto.png`. Servir ambos en `public/images/ui/` (`public/images` ignorada por git).

Usado en: signup paso 1, **Mis datos** (`/company/settings`), perfil/alta admin empresa, alta/edición de clientes.

### Reglas de negocio empresa

- `tax_id` **inmutable** tras `hasCompletedAcceptance()` (clickwrap en expediente).
- Servicio: `UpdateCompanyProfileService` · DTO: `GeoAddressData` · reglas: `GeoAddressRules`.
- Alta admin: `CreateCompanyService` · `StoreCompanyRequest` · rutas `admin.companies.create/store`.
- Tras crear la empresa a mano, **paso obligatorio**: misma ficha de empleado + acceso `company-admin` (`CreateCompanyFirstAdminService` · `admin.companies.first-admin.*`). Tipo y cargo se crean en el modal de la ficha (no se siembran solos). Sin ese admin, el detalle redirige al paso 2. Usuario `nombre.apellido.####` y clave se muestran una vez.

---

## Arquitectura

| Pieza | Ubicación |
|-------|-----------|
| Alcance queries | `UserScopeResolver` |
| Autorización | `UserPolicy`, `EmployeePolicy`, `SecurityCompanyPolicy` |
| CRUD usuarios | `ManageScopedUserService` |
| Grants colaborador | `ParseAccessGrants`, `SyncCollaboratorAccessService`, `GrantableModules` |
| Roles / labels | `AssignableRoles` (`forCompanyActor` recorta `company-admin` al colaborador) |
| Listado paginado | `UserRepository::paginateScoped()` |

Vistas empresa (crear = editar): `modules/company/users/partials/form.blade.php`. Foto: `x-client.member-photo-picker` (`name=avatar`). Cliente: `modules/client/users/partials/form.blade.php` (solo externos). Plataforma: `modules/shared/managed-user-form.blade.php`. Perfil: `modules/shared/company-profile-form.blade.php`. Asignación de instalaciones: `ClientUserInstallationAssignment`.

---

## Portería (minuta, turno y pánico)

Detalle: [`PORTERIA.md`](PORTERIA.md).

- Firma de **revista / minuta** por código en `/access` (tipo Revista + `users.supervisor_code` de 6 dígitos, o catálogo `supervision_codes`). Válido aunque el cliente también tenga Supervisión de campo.
- Turno abierto del vigilante: `guard_shifts` + puerta (`/access/turnos`). Una puerta se asigna sola; varias las elige el vigilante al abrir. Quien opera desde el expediente no pasa por esa pantalla.
- Pánico: `POST /access/ops/panic` (mismo overlay que cliente/supervisor). Ya no hay pánico en minuta.
- Turno del **supervisor** (Supervisión): `supervisor_shifts` + PWA. Distinto del turno de portería.

---

## Tests

```bash
php artisan test --filter=ScopedUserManagementTest
php artisan test --filter=CompanyUserFromEmployeeTest
php artisan test --filter=PasswordUpdateTest
php artisan test --filter=AuthenticationTest
php artisan test --filter=CollaboratorAccessTest
php artisan test --filter=SupervisorShiftApiTest
php artisan test --filter=PorteriaRevistaTest
php artisan test --filter=PorteriaDoorAndPanicTest
php artisan test --filter=PorteriaConsoleTest
php artisan test --filter=StructureModuleTest
```

---

Ver también: [`EMPLEADOS-Y-CARGOS.md`](EMPLEADOS-Y-CARGOS.md) · [`LANDING-Y-CONTRATACION.md`](LANDING-Y-CONTRATACION.md) · [`PLATAFORMA-ADMIN.md`](PLATAFORMA-ADMIN.md) · [`MODULO-DOCUMENTOS.md`](MODULO-DOCUMENTOS.md)

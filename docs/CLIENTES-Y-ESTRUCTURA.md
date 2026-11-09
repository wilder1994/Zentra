# Clientes, instalaciones, Accesos y Supervisión

**Última actualización:** 8 noviembre 2026

Fuente de verdad del **cliente comercial** y de los dos árboles operativos. El censo (nodos `structures`) cuelga de la **instalación**, no del cliente suelto.

Zentra **no** cobra al cliente final por vigilancia; solo registra `service_started_at`.

---

## Glosario

| Término | Significado | Dónde |
|---------|-------------|--------|
| **Cliente** | Ficha comercial (`clients`). PH, oficinas, bodegas, etc. | Alta / Excel de **clientes** / pestaña Cliente |
| **Ciudad** | Ubicación del cliente (`clients.city` + `department`). **No es un nodo del árbol.** Lo que en el Excel viejo de empleados decía «Sector» era ciudad. | Ficha y Excel de clientes |
| **Instalación** | Sitio físico del cliente, **siempre con georreferencia**. Perfil de sitio, código interno (auto), DANE si el perfil lo exige, **área**, **Administrador** y **Apoyo**. El nombre puede repetirse. Puede ser **el mismo cliente** (copia nombre + pin de la ficha). URL pública del Observatorio: `/o/{slug}` (perfiles con Observatorio comunidad). | Módulo `/company/installations` · `/client/installations` · ficha del cliente · [`OBSERVATORIO.md`](OBSERVATORIO.md) |
| **Perfil de sitio** | Catálogo por empresa (`installation_profiles`): reglas del sitio (DANE, Observatorio comunidad). **Colegio** es de sistema (fijo). Distinto del tipo de estructura del cliente. | Ajustes → Clientes/sitio → Perfiles de sitio |
| **Puerta** | Punto de portería (peatonal, vehicular, principal). Tabla `locations` (`type = access_point`). Solo Accesos. Código **automático** (`P01`…). **No** es un puesto. | Tarjeta de instalación (fila Puertas) |
| **Puesto** | Puesto de vigilancia (`supervisor_posts`): modalidad en horas (catálogo Ajustes → Modalidades), días de servicio, **revistas contratadas por día** (por defecto 1; ver [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md) § Cumplimiento) y vigilantes asignados. GPS de revista = pin de la instalación (sin lat/lng en el alta del puesto). **Nunca** un `location`. | Tarjeta de instalación (Puestos y vigilantes) |
| **Tipo de estructura** | Catálogo **por empresa** (`structure_types.security_company_id`), fijo en el alta (`clients.structure_type_id`). Clasificación del cliente (censo); **no** es el perfil del sitio. | Ajustes → Clientes/sitio → Estructuras / ficha cliente |
| **Nodo / subnodo** | Censo (`structures`, `parent_id` + `installation_id`). Torre, salón, apto. Distinto de puesto/acceso. | Ficha de instalación (`/client/installations/{id}`) |
| **Persona (censo)** | `structure_members` en un nodo. Tipo de documento + fecha de nacimiento. Menores: dato reservado salvo 4 admins; portería solo nombre. **No es empleado de la empresa.** | Panel cliente · portería Personas |
| **Empleado** | Colaborador de la empresa de seguridad (`employees`). El vigilante opera portería; no es una persona del nodo. | `/company/employees` |
| **Acceso de persona** | `structure_app_users` de esa persona. Login `usuario@login_suffix` para app o panel. | `/client/app-users` |

La ficha corta por **objeto**, no por línea comercial. Instalación, puertas y puestos se editan en **una** tarjeta (puertas solo con Accesos). Operar portería / operar cliente también exigen Accesos. Supervisión de campo se opera en la app y en `/company/supervision`.

Al **Ver** el cliente, el header es **Cliente | Resumen | Instalaciones** (sin «+ Cliente»; el alta de cliente solo en cartera). Resumen (si `has_access`) son los KPIs de portería. **Instalaciones** (`?vista=sitio`) usa la **tarjeta de instalación** unificada (mapa ~28% + detalle). `?vista=puertas` / `accesos` / `supervision` redirigen a sitio. Un puesto es un servicio. Motivo del cambio solo si se altera modalidad/días/estado; **agregar o quitar vigilantes no exige motivo**. Las revistas no van en novedades de servicio. El pánico es `operational_alerts` → **Atención de pánicos**. Ver [`PORTERIA.md`](PORTERIA.md).

---

## Alta del cliente (formulario y Excel)

El paquete cuenta **instalaciones activas**. Sin cupo libre no se crea cliente ni instalación, ni se reactiva una archivada.

Crear cliente = **solo la ficha**, igual que el formulario de `/company/clients/create`:

tipo de cliente, nombre comercial, razón social, tipo y número de documento, contactos, representante, tipo de estructura, dirección, **ciudad**, departamento, líneas Accesos / Supervisión, inicio de servicio.

**La carga masiva de clientes no crea instalaciones, ni puestos, ni accesos.** Tampoco nodos de censo.

Instalaciones las crea **solo la empresa**, en `/company/installations` (con cliente) o en la ficha del cliente. Puestos (modalidad + empleados) en la ficha de la instalación o en la tarjeta del cliente. Accesos (puertas) siguen en la ficha del cliente.

| Excel | Qué crea | Qué no crea |
|-------|----------|-------------|
| Clientes | Filas `clients` (mismos campos del formulario) | Instalaciones, puestos, accesos, nodos, personas |
| Empleados | Ficha de colaborador (alta o **actualización** si el documento ya existe; cargo incluido) | Cliente, instalación, puesto, acceso, usuario. **Sin** columnas razón social / instalaciones / sector / puesto |

---

## Directorio de instalaciones

`/company/installations` (rediseño 17 oct 2026): header con selector **Cliente** (lista al abrir, filtra al escribir sin tildes, ↑↓ Enter; `x-ui.filter-picker`) y **+ Instalación** (preselecciona el cliente filtrado). El cliente elegido (`?client_id=`) filtra toda la pantalla y el refresco en vivo (`GET /company/sig/live.json?client_id=`). KPIs compactos (~65 px, ícono a la izquierda, número y detalle en una línea): Instalaciones (+ sin ubicación), Servicios hoy (+ vigilantes), Revistas hoy (cumplidas/esperadas, barra con semáforo) y Novedades hoy (+ hora de la última). Contenedores de 384 px, en dos filas: **Mapa** | **Instalaciones y servicios**; **Novedades de servicio** | **Revistas del supervisor**. Mapa con gotas agrupadas por zoom (ver `DISENO-UI-ZENTRA.md` §10.1) y tarjeta fija en la esquina con **Ver instalación** y **Compartir**. **Instalaciones y servicios** (una fila por instalación con servicios y vigilantes; clic despliega sus puestos con modalidad, revistas/día y vigilantes; buscador interno por nombre, código o DANE; botón ubicar en el mapa). Ya no hay tabla paginada ni buscador abajo (`installationsBoard`). **Crear** siempre con cliente. La ficha `/company/installations/{id}` y `?vista=sitio` comparten la **misma tarjeta**: mapa estático (~28%) | detalle a todo el ancho. Cabecera en **una fila**: Cliente (nombre + dirección debajo) · Perfil de sitio · Área · Administrador. Fila de acciones: píldoras (Puerta / Puesto / Vigilantes / Activa) a la izquierda; **Historial · Crear · Editar · Archivar · Ver ficha** a la derecha. **Crear** es dropdown (Puerta / Puesto / Vigilante). Puertas en fila horizontal (Editar / Archivar). Puestos y vigilantes con scroll. Historial = novedades `service_change` de esa instalación. El **nombre puede repetirse**. `code` interno se genera solo; **no** es DANE. El perfil (`installation_profiles`) reemplaza el enum `kind`: DANE obligatorio solo si el perfil lo exige (p. ej. Colegio de sistema). Área: comuna IDESC en Cali (no editable); fuera, el usuario la escribe. Alta también desde la ficha del cliente.

Los asignados (`client_user_installation_assignments`) se parten en dos bloques: **Administrador** (`installation_permission=admin`) y **Apoyo** (`support`). Formato `cargo · nombre`. Si un bloque está vacío, se muestra — . `rector_user_id` es solo el **contacto del directorio**, no el único admin. El permiso es el mismo en todas las instalaciones de esa persona. Apoyo opera el censo y no borra nodos. El panel cliente tiene el mismo directorio (sin crear) y el árbol de nodos en la ficha.

## Árbol del sitio (tarjeta de instalación)

Si `has_access` o `has_supervision`. Abre `/company/clients/{id}?vista=sitio` (misma UI que `/company/installations/{id}`):

1. Crea **instalaciones** (+ Instalación; casilla «La instalación es el mismo cliente» o nombre + mapa). Toda instalación lleva georreferencia.
2. En cada tarjeta, **Crear** → Puerta / Puesto / Vigilante.
3. **Puestos** (`supervisor_posts`: modalidad, días, festivos, revistas/día, vigilantes). Sin lat/lng en el form. Una instalación admite varios puestos; un empleado solo un puesto (reasignar desde su ficha).

Las revistas de campo viven en `/company/supervision`. Historial de la tarjeta = `OperationalAlert` tipo novedad de servicio de esa instalación (incluye altas/cambios de puertas y puestos).

```
Cliente
  └── Instalación (puede ser el propio cliente)
        ├── Puerta (locations, código auto)     ← solo Accesos
        └── Puesto (modalidad + días + vigilantes)  ← supervisor_posts
```

El censo (torres, salones, aptos, personas) se define en la ficha del panel cliente (`/client/installations/{id}`).

La app de campo (`GET /api/supervision/posts`) lista **estos puestos**, nunca `locations`. Sin puestos no se guarda revista.

Solo Supervisión: sitio sin puertas ni Operar. Solo Accesos: sitio + puertas + Operar.

---

## Instalación = el cliente

Casilla **«La instalación es el mismo cliente»** (`is_client_site`):

- **Chuleada:** el nombre y la ubicación (dirección, ciudad, depto, lat/lng) se copian de la ficha del cliente. Si el cliente no tiene pin, no se crea.
- **Sin chulear:** se escribe el nombre y se fija el pin con el mismo mapa de la ficha (depto, ciudad, dirección). Sin coordenadas no se guarda.

Toda instalación queda con georreferencia. No se usa «sector» ni ciudad como nivel intermedio del árbol.

Instalaciones, puertas y puestos se gestionan en **una** tarjeta por instalación (puertas solo si Accesos).

### Ajustes empresa (`/company/ajustes`)

Hub con 3 bloques (Empresa · Clientes/sitio · App de campo). Cada catálogo abre en modal (lista + alta). Los índices viejos redirigen al hub; los POST llevan `_settings_hub` y vuelven al mismo catálogo.

---

## Tablas

Create en la migración original de cada dominio; **sin ALTER sueltos**.

| Acción | Tabla | Rol |
|--------|--------|-----|
| **Creada** | `installation_profiles` | Catálogo por empresa: nombre, `requires_dane`, `observatory_community`, `is_system` (Colegio fijo) |
| **Creada** | `installations` | Sitio físico. `client_id`, `installation_profile_id`, nombre, flag mismo-cliente, activo, geo, `dane_code` si el perfil lo exige |
| **Creada** | `supervisor_posts` | Puesto compartido. `client_id`, `installation_id`, nombre, `modality`, `service_days`, `serves_holidays`, `reviews_per_day`, activo |
| **Creada** | `supervisor_post_employee` | Vigilantes asignados al puesto |
| **Ajustada** | `locations` | Puerta. `installation_id` obligatorio; `code` auto (`P01`…). No es el puesto |
| **Ajustada** | `structures` | Censo por instalación (`installation_id`) |
| **Ajustada** | `supervisor_shift_reviews` | `supervisor_post_id` (ya no `location_id`) |

No se clonan tablas de Patrulla (`review_posts`, etc.). Flota de Supervisión sigue en `supervisor_fleet_vehicles`, no en `vehicles` de Accesos.

**Puente minuta:** el supervisor de la empresa (usuario + código de 6 dígitos) firma revista en la minuta de portería (`guard_logs` tipo `revista`). La PWA de campo sigue para la ronda de Supervisión. No se reutiliza `locations` como puesto.

---

## Censo (nodos) — Accesos / panel cliente

Árbol de **personas y unidades** por instalación. No es el de portería ni el de Supervisión.

1. Empresa: tipos de estructura en Ajustes → Estructuras.
2. Empresa: alta de cliente (ficha) e **instalaciones**.
3. Panel cliente (`/client/installations`): directorio → ficha de la instalación → árbol + **Nuevo nodo**. El tipo se **hereda** del cliente (nota bajo el nombre; no hay campo Tipo ni Código).
4. **Crear dentro de:** primera opción = esta instalación (raíz); el resto indentado en orden de árbol. El **+** de cada nodo lo deja como padre y enfoca el nombre.
5. **Tipos de persona** en Personas (botón + modal). Catálogo **por cliente**.
6. Personas en **un** nodo de esa instalación (elige instalación → nodo).
7. Acceso de persona (`/client/app-users`) para app o panel.

```
Cliente (tipo fijo, ej. Propiedad horizontal)
  └── Instalación (propia del cliente, colegio…)
        ├── Puestos / puertas
        └── Nodos (bloque Instalaciones en la ficha)
              Torre A
                Apto 101 → Persona → acceso de persona
              Salón A    → Persona → acceso de persona
```

También válido: nodo hoja directo (casa o salón sin torre) → persona en ese nodo.

Tabla `structures`: `installation_id` (FK). `code` interno, **automático e inmutable**: slug del nombre, único por instalación; si choca, sufijo `-2`; si el padre ya tiene código, se antepone (`torre-a-apto-101`). Unique `(installation_id, code)`. El POST `code` se ignora. Migración `2026_09_11_120000_add_installation_id_to_structures`.

El layout del panel usa `ClientLayout` con `wide` (sin `max-w-7xl`) para que el árbol no quede estrecho.

Personas, vehículos y mascotas filtran por **instalación** y luego **nodo** (indentado). Columna **Nodo**, no unidad. El código de la tabla de personas es el de **acceso** (`access_code` / QR), no el interno del nodo.

Listado de personas: una sola barra (buscar, instalación, filtrar + **Exportar** / **Nueva persona**). Alta y edición: foto circular centrada con icono de cámara y preview.

Tipos de persona: tabla `member_types` (`client_id`, nombre, slug, activo). `structure_members.member_type_id`. Sin tipos activos no se registra persona. No se borra un tipo en uso. Migración `2026_09_11_140000_create_member_types_and_fk`.

---

## Campos comerciales de `clients`

| Campo | Uso |
|-------|-----|
| `party_type` | `legal_entity` / `natural_person` |
| `legal_name`, `name` | Razón social / nombre comercial |
| `document_type`, `tax_id` | Tipo (catálogo) + número |
| `email`, `phone` | Contacto |
| `representative_name`, `representative_email` | Representante (exigido si jurídica) |
| `structure_type_id` | Tipo de estructura fijado |
| `address`, `city`, `department` | Ubicación (ciudad ≠ instalación) |
| `has_access`, `has_supervision`, `has_observatory` | Líneas de servicio (cupo). Al desactivar en Editar: **temporal** (libera cupo, datos intactos) o **archivar** (archivo operativo + retención `service_line_retention_days`, purga en `data:purge-retention`). Reactivar exige cupo. Distinto de «Gestión de módulos» (solo qué ve el panel). |
| `show_personnel_folders` | Si la empresa tiene **Indexación** y el cliente Accesos: comparte carpetas del personal (solo empleados en puestos de ese cliente). No es el producto Indexación; es el flag de compartir. |
| `panel_modules` | JSON opcional: `vehicles`, `pets`, `authorizations`, `doors`. Null = todos on. `doors` exige `locations` activas. |
| `slug`, `login_suffix` | Internos; auto |

Migraciones de ficha:  
`2026_08_16_180000_add_commercial_fields_to_clients_table` ·  
`2026_08_16_190000_add_structure_type_id_to_clients_table` ·  
`2026_08_16_170000_create_identity_document_types_table` ·  
`2026_09_03_144000_add_security_company_id_to_structure_types` (catálogo de tipos por empresa) ·  
`2026_09_11_180000_add_panel_modules_to_clients` ·  
`2026_09_11_200000_add_identity_and_birth_to_structure_members` (tipo de documento + fecha de nacimiento + TI/RC)

---

## Copy de UI

Listado `/company/clients` vacío: **«Aún no tienes clientes creados en la cartera»** + **Crear cliente** (no «conjunto»).

Pestañas de ficha empresa (`/company/clients/{id}`): **Cliente** | **Resumen** (si `has_access`) | **Instalaciones**.

- Cliente: ficha + Operar portería / Operar cliente / Operar instalación, Editar, Gestión de módulos. Sin «+ Cliente» aquí.
- Instalaciones (`?vista=sitio`): tarjeta unificada por instalación (mapa, perfil, puertas, puestos, Crear, Historial). Sin revistas aquí.
- Operar cliente + flag: sidebar **Documentos** (`/client/documents`), solo empleados con puesto en ese cliente.

Sidebar del panel cliente:

- **Fijos:** Resumen, Instalaciones, Personas, Usuarios, Accesos, Ajustes.
- **Opcionales** (check en Gestión de módulos): Vehículos, Mascotas, Autorizaciones, **Puertas** (antes Consola portería). Puertas solo se puede activar si ya hay `locations` activas. Nav oculto y ruta 403 si está apagado. Portería de empresa (`Operar portería` / vigilante) no usa este corte.

El panel `/client/installations` es el directorio de instalaciones (tabla + ficha). En la ficha el mapa va a la izquierda y los datos a la derecha. Los nodos van en el bloque **Instalaciones**; las pastillas de vehículos/mascotas solo si el módulo está activo. `/client/structures` redirige ahí. Censo por instalación. Personas / vehículos / mascotas: mismo filtro instalación → nodo. Personas: tipo de documento (catálogo + TI/RC) y fecha de nacimiento; si es menor de 18, aviso Ley 1581 art. 7 / Decreto 1377 / Ley 1098, autorización del representante, sin export ni acceso de persona; en portería solo el nombre. `/client/users` lista administradores **externos** de ese cliente; **no crea** (alta en empresa o plataforma). `/client/app-users` es **Accesos** (personas del censo). `/client/settings/member-types` es **Ajustes** (tipos de persona; admin instalaciones solo ve). Banner al operar: **panel del cliente**.

---

## Personas del censo (menores)

`structure_members` pide **tipo de documento** (`identity_document_types`: RC, TI, CC, CE, NIT, PA) y **fecha de nacimiento**. Edad &lt; 18 = menor (Ley 1098 art. 3).

- Texto vigente: Normoteca (`minors_data_policy`). El súper admin lo versiona. Sale en el clickwrap de contratar, como aviso al crear cliente o admin cliente/instalaciones, y en Personas si el registro es menor. Hay que marcar autorización del representante (`minor_treatment_accepted_at`).
- Ficha completa: súper admin, admin empresa, admin cliente, admin instalaciones.
- Portería (`/access/*`): solo el **nombre**.
- No van al Excel de asamblea ni tienen acceso de persona (`structure_app_users`).

---

## Rutas empresa (árbol)

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/company/installations` | Tablero de instalaciones: filtro por cliente (`?client_id=`), KPIs, mapa, novedades, revistas e instalaciones con sus servicios |
| GET/PUT | `/company/installations/{id}` | Ficha (misma tarjeta que sitio) / editar (perfil, geo, DANE, área, admin/apoyo, puestos) |
| GET | `/company/ajustes` | Hub Ajustes (Empresa · Clientes/sitio · App de campo); paneles AJAX |
| GET | `/company/ajustes/panel/{key}` | Panel JSON de un catálogo (lista + alta) |
| GET | `/client/installations` | Directorio del cliente (admin cliente: todas; admin de instalación: las suyas) |
| GET | `/client/installations/{id}` | Ficha + estructura (nodos). Sin alta de instalación ni puestos |
| POST/PUT/DELETE | `/company/clients/{id}/installations` | Alta rápida en el árbol del cliente |
| POST/PUT/DELETE | `/company/clients/{id}/locations` | Puertas de una instalación (solo Accesos) |
| POST/PUT/DELETE | `/company/clients/{id}/posts` | Puestos compartidos (Accesos y Supervisión) |
| PUT | `/company/clients/{id}/modules` | Gestión de módulos del panel cliente |

Permiso: `company.clients.manage`. Portería (`/access/locations`) también crea accesos, exigiendo `installation_id` del cliente activo.

---

## Seeds

| Seed | Incluye |
|------|---------|
| `DatabaseSeeder` (mínimo) | Tipos de documento; **no** clientes ni instalaciones |
| `PilotDemoSeeder` | Empresa, clientes (ficha + líneas), censo demo. Palmas: instalación propia del cliente + 4 puertas + 2 puestos. Torres: instalación propia del cliente + 1 puerta + 1 puesto (solo Accesos) |

---

## Tests

```bash
php artisan test --filter=StructureModuleTest
php artisan test --filter=ClientMemberTypeTest
php artisan test --filter=ClientCensusDirectoryTest
php artisan test --filter=ClientMemberMinorProtectionTest
php artisan test --filter=CompanyClientExpedienteTest
php artisan test --filter=ScopedUserManagementTest
php artisan test --filter=PorteriaRevistaTest
```


# Empleados, cargos y tipos (empresa)

**Última actualización:** 21 septiembre 2026

Maestro de colaboradores de la **empresa** de seguridad. **No** son las **personas** del censo de una instalación (`structure_members`). Esa gente vive en **Personas** del panel cliente / portería. Ver [`PORTERIA.md`](PORTERIA.md) y [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

Distinto de **usuarios** (`/company/users`): la ficha de empleado es el colaborador; el usuario es el login. Ver [`USUARIOS-Y-PERFILES.md`](USUARIOS-Y-PERFILES.md).

La pantalla copia Personal de SJ-SIG (listado, ficha, foto, bloques HR/SS) con estilos Zentra. Se mantienen nombres partidos, tipo de documento de catálogo, DIVIPOLA y cargos/tipos por empresa. Fuente Excel: `Maestro Colaboradores WM.xlsx` **ampliado** con columnas SJ-SIG opcionales. **No** van las cuatro de asignación a cliente (razón social, instalaciones, sector, puesto).

---

## Dónde vive

Sidebar **Empleados** (maestro) y **Documentos** (indexador de carpetas del personal). **Ajustes** → pestañas **Cargos** | **Tipos** | **Estructuras** | **Zonas** | **Turnos** | **Preoperacional** | **Documentos** | **Libros** | **Tipos de arma** | **Marcas** | **Riesgos** | **Alarmas** | **Apoyos**. Las de Supervisión de campo: [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md). El **Documentos** del sidebar no es la Normoteca de plataforma (`docs/MODULO-DOCUMENTOS.md`).

| Pieza | Dónde |
|-------|--------|
| Dashboard | Sidebar **Mi empresa** (`/company/dashboard`) |
| Perfil legal | Sidebar **Mis datos** (`/company/settings`), sin pestañas |
| Listado / alta / ficha | Sidebar **Empleados** (`/company/employees`) |
| Carpetas / indexador | Sidebar **Documentos** (`/company/documents`) |
| Cargos | Ajustes → `/company/job-titles` |
| Tipos de colaborador | Ajustes → `/company/collaborator-types` |
| Tipos de estructura | Ajustes → `/company/structure-types` |
| Zonas / turnos / preoperacional (Supervisión) | Ajustes → `/company/supervision-zones`, `…-shifts`, `…-preop` |
| Formato Excel | `GET /company/employees/template` |
| Carga masiva | modal en el listado → preview → aceptar |
| Foto | Círculo en alta/edición; en la ficha se cambia al elegir archivo (`POST /company/employees/{id}/photo`) |

Permisos: Empleados `company.employees.view` / `manage`; Documentos `company.documents.view` / `manage` (subir e indexar = manage). El admin empresa los tiene todos. Un colaborador solo si el grant lo dice. El login se da en **Usuarios** (`company.users.assign`): mismo formulario crear/editar; todos los roles con empleado; usuario `nombre.apellido.####`. El email personal es el de la ficha; el From de avisos es el correo de la **zona** de Supervisión.

El primer administrador de una empresa nueva usa **la misma ficha** (`admin.companies.first-admin.*`).

---

## Listado

Tabla: identificación, nombre, estado (Activo / Retiro), cargo, ingreso, teléfono, EPS, enlace **Ficha**. Búsqueda por nombre, documento, correo o teléfono. Filtro activos / archivados / todos. Tamaño de página 10 / 25 / 50 / 100. Acciones: **Formato**, **Carga masiva**, **Nuevo empleado**.

Filtro por cliente: pendiente. Un puesto admite varios empleados; un empleado = un puesto. El alta en el sitio solo toma gente libre; **Reasignar** está en la ficha.

---

## Ficha (pantalla)

Cuatro bloques, como SJ-SIG Personal:

1. **Identidad** — documento (catálogo), expedición DIVIPOLA, nombres partidos, sexo, nacimiento DIVIPOLA, nacionalidad, sangre, escolaridad, estado civil, hijos, discapacidad, foto JPG/PNG/WebP (máx. 2 MB, disco `local`).
2. **Contacto y residencia** — teléfono, correo, residencia, dirección, emergencia.
3. **Vinculación laboral** — tipo de colaborador y cargo (catálogos), vinculación, cotizante, tipo de contrato, mismo centro de costo, ingreso, vencimiento, retiro.
4. **Seguridad social** — EPS, pensión (AFP), caja, ARL y nivel de riesgo.

Tipo o cargo vacíos: modal AJAX para crear el primero sin perder el formulario. Nacimiento y expedición: departamento → municipio (`resources/data/colombia-divipola.json`). Bogotá D.C. es departamento propio.

**Reasignar** (si está activo): modal cliente → instalación → puesto. El Excel no elige puesto.

**No en esta ficha:** carpeta documental (HV, cursos, PDFs).

---

## Cargos y tipos

Catálogos **por empresa**, no seeder de plataforma. El formulario usa select. Si el Excel trae un cargo o un tipo que no existe, el import **lo crea** al aceptar (aviso en el preview). No se elimina un cargo o tipo con empleados.

---

## Columnas del Excel (A–Z + extras)

Rojo = obligatorio en el archivo. Gris = opcional en el archivo.

| Col | Encabezado | Zentra |
|-----|------------|----------|
| A–B | Tipo y nro. documento | Obligatorio. Tipo del catálogo (código o nombre). Si el número ya existe, se actualiza la ficha. |
| C–D | Ap. Paterno / Materno | **Al menos uno.** Los dos es mejor, no obligatorio. |
| E | Nombres | Obligatorio |
| F | Sexo | Hombre / Mujer |
| G | Edad | **No se carga** (se calcula) |
| H | Tipo Colaborador | Obligatorio. Se crea en el catálogo Tipos si falta. |
| I | Cargo | Obligatorio. Se crea en el catálogo si falta. |
| J | Mismo CC origen? | SI / NO, opcional |
| K | Fecha Nacimiento | Obligatoria |
| L–O | Nacimiento / emergencia | Opcional |
| P | Nacionalidad | Obligatoria |
| Q | Discapacidad | SI / NO, opcional |
| R | Email Ficha | Gris en archivo, **obligatorio** en sistema. Único por empresa. El mismo correo de **esa** ficha (mismo documento) se acepta. El de **otro** empleado es error. |
| S–U | Expedición documento | Opcional |
| V | G.Sanguíneo | O+, O-, A+, A-, B+, B-, AB+, AB- o **Pendiente** (si no se conoce). |

Tras V (gris, opcionales): teléfono, residencia, dirección, escolaridad, estado civil, hijos, vinculación, cotizante, tipo de contrato, ingreso, vencimiento, retiro, EPS/AFP (código y nombre), caja, ARL y nivel de riesgo. Un archivo WM solo A–Z sigue valiendo.

**No van en este Excel:** razón social, instalaciones, sector, puesto. Eso es del **cliente** y se arma a mano en la ficha (Instalaciones y puestos / Puertas). «Sector» era ciudad; la ciudad del cliente está en el Excel de clientes. Ver [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

No se archiva desde el Excel. Documento que ya existe: **aviso** (se actualiza la ficha, incluido el cargo). Correo de **otro** empleado: error. No se crea usuario desde el Excel. La foto no viaja en el Excel.

---

## Carga masiva

1. **Formato:** xlsx con hoja `Empleados` (vacía, mismos encabezados y colores) + hoja `Instrucciones` (no se importa).
2. **Carga masiva:** arrastrar, elegir archivo o pegar tabla (con encabezados).
3. **Revisar datos:** KPIs válidas / avisos / errores. Nada se guarda.
4. **Aceptar** solo si hay 0 errores. Aviso no bloquea. Luego vuelve al listado. Mensaje: *N fichas aplicadas* (altas y actualizaciones).

El import lee la hoja `Empleados`, o `WM`, o la primera hoja.

| Preview | Al aceptar |
|---------|------------|
| Documento nuevo | Crea la ficha |
| Documento ya en la empresa | **Aviso.** Actualiza la ficha (nombres, cargo, tipo, correo, extras SJ-SIG, etc.). Si el cargo cambia, el aviso lo dice. No duplica. |
| Correo de otro empleado | **Error.** No se acepta el lote |
| Mismo documento dos veces en el archivo | **Error** (duplicado interno) |
| Cargo o tipo que no existe | **Aviso.** Se crea en el catálogo al aceptar |

---

## Archivar

No está en el Excel. En la ficha: `is_active = false` + `ceased_at`. Si tenía usuario, se desactiva el acceso. El listado muestra **Retiro**. `left_on` es dato de vinculación; no sustituye archivar.

---

## Carpetas del personal

`/company/documents`: 7 carpetas (Historia laboral, Contratación, Certificados, Cursos, Afiliaciones, **Parafiscales**, Otros). Carga por lote PDF + indexador (FPDI + pdf.js), salvo Parafiscales. Distinto de la Normoteca (`/admin/documents`). Permiso ver: `company.documents.view`; indexar/subir: `company.documents.manage` (o `company.settings.manage`).

**Parafiscales:** no van en Normoteca. En el listado, **Carga masiva planilla**: Excel PILA (hoja por `CC`+cédula). Overlay con % al leer y al guardar. El recorte es una copia del xlsx original (tema, fondo, logo) con solo las filas de ese colaborador; BJ15 = su total. **Ver** sirve el PDF generado con LibreOffice (igual a la descarga); si no hay `soffice`, tabla HTML. **Descargar** (xlsx o PDF) solo con Gestionar (`company.documents.manage`); Ver no baja archivos. Una carga nueva **reemplaza** la planilla previa de ese colaborador. No se guarda la planilla completa.

**Peso en disco (medido 15 oct 2026, SJ, 1.060 empleados = 1,6 GB):** cada recorte pesa ~1,14 MB porque copia la imagen de fondo del xlsx original (`xl/media/image1.png`, 1,1 MB) en cada empleado. Sin esa imagen el recorte quedaría en ~50 KB (hoja + estilos + logos pequeños). Los PDF de afiliaciones y certificados suman aparte (~1,3 MB por archivo, solo quien los tenga). Ojo: el recorte conserva `sharedStrings.xml` del libro original, con textos de otros cotizantes aunque no se vean.

Miniaturas del lote: el worker de pdf.js es un `.mjs`. CloudPanel lo sirve como `application/octet-stream` y Chrome lo bloquea. El indexador lo vuelve a servir como blob `application/javascript`. El PDF se pide de una vez (`disableRange`), no por rangos. **Ampliar** sigue siendo el iframe nativo. El formulario (izq.) usa `minmax(24rem, 32rem)`; las miniaturas van a ~220px (`scale` 0.48).

Tablas: `employee_documents`, `employee_document_batches`. Disco: `storage/app/companies/{id}/employees/{id}/{carpeta}/`. Borrar PDF solo durante 12 h.

El cliente **solo** ve esas carpetas si tiene Accesos y `show_personnel_folders`. En Operar cliente: `/client/documents`, solo empleados con puesto en ese cliente. **Ver** = preview; **Gestionar** = descargar. Reasignar puesto cambia la visibilidad; los PDF siguen en la empresa.

## Pendiente (otro corte)

- Filtrar empleados por cliente (listado).

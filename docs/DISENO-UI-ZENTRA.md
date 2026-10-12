# Diseño UI — Zentra

Referencia oficial del sistema visual. **Panel empresa** (`/company`, acento indigo) y **panel plataforma** (`/admin`, acento violet).

**Vistas canónicas:**
- Empresa: `resources/views/modules/company/dashboard.blade.php`
- Plataforma: `resources/views/modules/admin/dashboard.blade.php`

**Layouts:** `layouts/company.blade.php` · `layouts/admin.blade.php` · `layouts/client.blade.php` · `layouts/access.blade.php`

Portería (`/access`): menú vigilante (Resumen, Ingreso y salida, censo, pánico `ops-live-alerts`). Puerta/turno solo el vigilante; Operar portería entra al Resumen. [`PORTERIA.md`](PORTERIA.md).

**Sidebar cliente:** Resumen · Instalaciones · Observatorio · Personas · Usuarios · Accesos · Ajustes (fijos). Opcionales en ficha empresa (**Gestión de módulos**): Vehículos · Mascotas · Autorizaciones · Puertas. Zonas comunes y Documentos siguen por permiso / flag.

**Shell común:** wrapper `h-screen flex overflow-hidden` + Alpine `panelSidebar`. `resources/js/app.js` registra `panelSidebar` y `opsLiveAlerts`; si falta el primero, Observatorio no dibuja mapa ni Chart.js.

**Sidebar empresa:** el menú se recorta por catálogo (`$canMod` + permiso). Accesos abre Mi empresa, Facturación, Clientes, Instalaciones, Pánicos, Empleados, Usuarios, Mis datos, Ajustes. Supervisión suma campo y Descargas (sin Mi empresa). Indexación suma Documentos. Observatorio solo si hay Accesos y el flag. Defaults en `config/catalog.php`; editables en `/admin/pricing`. **Lista completa posible:** Mi empresa · Facturación · Clientes · Instalaciones · Observatorio · Supervisión · Atención de pánicos · Descargas · Empleados · Documentos · Usuarios · Mis datos · Ajustes. Mapa de Mi empresa y Supervisión: pin índigo = cliente, cian = instalación (leyenda en dos columnas). Observatorio: filtro (cliente angosto, fechas con ícono) + KPIs; mapa con buscador de comuna IDESC (Todas = Cali + 22); pines de cualquier instalación; calor con círculos (sin HeatmapLayer); botón **PPTX** en el tablero; pestaña Eventos. Ficha de folio: grilla 2×2 (mapa | datos a `h-[28rem]`; novedades | bitácora `h-80` con scroll). Ficha de instalación: mapa + pin; en Cali pinta la comuna IDESC. Ajustes usa `headerTabs` Cargos | Tipos | Estructuras | Zonas | Turnos | Modalidades | Preoperacional | Documentos | Libros | Tipos de arma | Marcas | Riesgos | Alarmas | Apoyos. Supervisión usa `headerTabs` En vivo | Historial | Resumen | Fichas. Usuarios usa `headerTabs` Activos | Desactivados; Buscar y **+ Nuevo usuario** en la misma fila; formulario con foto circular (`avatar`) y Cliente + Instalaciones en una fila (filtro + checkbox). Panel cliente `/client/users`: mismos tabs; solo admins **externos** de ese cliente (nunca personal de la empresa). En vivo e Historial: mapa alto + lista. En vivo: sin filtros de header; motos sin trazas hasta clic en la fila; Ver = ficha de turno en curso. Historial: Desde/Hasta/Supervisor; una ruta en calle al clic; Ver = ficha guardada al cierre. Pines agrupados (~50 m): lista vertical; la moto no entra en esa lista. Hover moto: tooltip nombre + En línea / Pantalla apagada / Sin señal. Clic: OverlayView compacto (no InfoWindow nativo). Leyenda en la columna. Historial cerrado: callejero (Roads, `GOOGLE_MAPS_SERVER_API_KEY`). Cierre automático al fin de plantilla + 30 min (sin replay). Pendiente: compositor PPTX y chatbot ayuda/PQRS ([`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md)). Empleados y Mis datos no tienen pestañas.

---

## 1. Componentes Blade (`x-ui.*`)

| Componente | Ruta | Uso |
|------------|------|-----|
| Botón | `resources/views/components/ui/button.blade.php` | `<x-ui.button>` |
| Label | `resources/views/components/ui/label.blade.php` | `<x-ui.label>` |
| Input | `resources/views/components/ui/input.blade.php` | `<x-ui.input>` |
| Error campo | `resources/views/components/ui/field-error.blade.php` | `<x-ui.field-error :messages="$errors->get('campo')" />` |
| Avisos | `resources/views/components/ui/flash-toasts.blade.php` | `<x-ui.flash-toasts />` en cada layout |

**Regla:** no inventar clases de botón/input en vistas nuevas del panel empresa; usar estos componentes.

**Idioma y avisos (español):** `APP_LOCALE=es`. Textos en `lang/es/` (`validation`, `auth`, `passwords`) y `lang/es.json`. Los formularios llevan `novalidate` desde `resources/js/form-feedback.js`: el navegador no muestra *Please fill out this field*. El primer error, flash `success`/`warning`/`error` o el HTML5 en español sale **al centro ~2,8 s** y enfoca el campo. API JSON: `Revise los datos…` / `No tienes permiso…` (no *The given data was invalid*). App de campo: overlay `#feedback`, misma regla. Tests: `tests/Feature/Locale/SpanishMessagesTest.php`.

### Ejemplo formulario

```blade
<div>
    <x-ui.label for="name">Nombre del conjunto</x-ui.label>
    <x-ui.input id="name" type="text" name="name" :value="old('name')" required />
    <x-ui.field-error :messages="$errors->get('name')" />
</div>

<x-ui.button type="submit" size="md">Guardar</x-ui.button>
```

### Ejemplo acciones header

```blade
<x-ui.button variant="secondary" :href="route('company.porteria.enter')">Portería</x-ui.button>
<x-ui.button :href="route('company.clients.create')">+ Conjunto</x-ui.button>
```

---

## 2. Tipografía — jerarquía (L1 → L6)

Fuente: **Figtree** (`font-sans`). Base operativa: **14px** (`text-sm`).

| Nivel | Tailwind | Peso | Color | Dónde |
|-------|----------|------|-------|-------|
| **L1** | `text-base` | `font-semibold` | `text-white` | Título de página en header nav |
| **L2** | `text-xs` | normal | `text-slate-500` | Subtítulo contexto (nombre empresa) |
| **L3** | `text-sm` | `font-semibold` | `text-white` | Título de sección (Conjuntos, Cuenta) |
| **L4** | `text-sm` | `font-medium` / normal | `text-slate-200` / `text-slate-400` | Cuerpo, celdas tabla |
| **L5** | `text-xs` | `font-medium` (thead) | `text-slate-500` | Meta, captions, encabezados tabla |
| **L6** | `text-xs` | `font-medium` | `text-slate-400` | Labels de formulario (`x-ui.label`) |
| **Métrica** | `text-base` | `font-semibold` | `text-white` + `tabular-nums` | Importes destacados (ej. próximo cargo) |
| **Mono** | `text-xs` | — | `font-mono text-indigo-300/90` | Login APP, códigos |

### Subniveles en tabla (L4)

- Celda primaria: `font-medium text-slate-200`
- Subtítulo (slug): `text-xs text-slate-600`

### Prohibido en panel empresa

- `text-2xl`, `text-3xl` en contenido operativo
- `uppercase tracking-widest` en botones
- Gradientes en **datos / tablas / formularios** (sí en el chrome del tema claro)

---

## 3. Botones (`x-ui.button`)

| Prop | Valores | Default |
|------|---------|---------|
| `variant` | `primary`, `secondary`, `success`, `platform` | `primary` |
| `size` | `sm`, `md` | `sm` |
| `href` | URL opcional (renderiza `<a>`) | — |
| `type` | `button`, `submit`, `reset` | `button` |

### Tamaños

| Size | Altura | Clases |
|------|--------|--------|
| **sm** | 36px (`h-9`) | `h-9 px-4 text-sm font-medium` |
| **md** | 40px (`h-10`) | `h-10 px-5 text-sm font-semibold` |

### Temas (oscuro / claro)

`data-theme` en `<html>` (`dark` por defecto). Toggle en headers (`partials.theme-toggle`). Preferencia: `localStorage.zentra-theme`. Tokens en `resources/css/theme.css`.

| Token | Uso |
|-------|-----|
| `--ui-accent` | Gestión, sidebar activo, botones primary |
| `--ui-danger` / `--ui-warning` / `--ui-caution` / `--ui-success` | Solo alertas (grave / media / leve / ok) |
| `--ui-surface` + `--ui-border` | Tablas, cards, inputs |

Claro: mate verdoso, fondo en degradado suave. Datos, KPIs y cards (incl. `.company-cc-card`) sobre `--ui-surface`. Chart.js toma ticks/grid de `ZentraTheme.chartPalette()`.

Chips: `.ui-chip` / `.ui-chip-link` (+ `success|caution|warning|danger`). En claro, badges `*-900/30` + `*-300` se remapean a esos tokens para no verse opacos.

### Variantes (`x-ui.button`)

| Variant | Clase | Uso |
|---------|-------|-----|
| **primary** / **platform** | `ui-btn-primary` | CTA y gestión |
| **secondary** | `ui-btn-secondary` | Acción secundaria |
| **success** | `ui-btn-success` | Confirmación positiva |

**Forma:** `rounded-lg` · `inline-flex items-center justify-center`

### Links de acción en tabla

| Acción | Clases |
|--------|--------|
| Ver / Gestionar | `ui-chip-link` |
| Estado / ciclo | `ui-chip` + tono (`ui-chip-caution`, etc.) |

---

## 4. Formularios

### Label (`x-ui.label`)

```
block text-xs font-medium text-slate-400 mb-1
```

### Input (`x-ui.input`)

| Prop | Valores | Default |
|------|---------|---------|
| `accent` | `indigo`, `platform` | `indigo` |

```
w-full h-9 px-3 text-sm rounded-lg
border border-slate-700 bg-slate-950 text-white
placeholder:text-slate-600
focus:ring-1
```

- Empresa: `focus:border-indigo-500 focus:ring-indigo-500/30`
- Plataforma: `accent="platform"` → `focus:border-violet-500 focus:ring-violet-500/30`
- `type="password"` (también `x-text-input`): icono de ojo a la derecha (`x-ui.password-wrap`, Alpine `show`). El campo nace oculto; el botón cambia a texto y el `aria-label` a *Mostrar* / *Ocultar contraseña*. No usar `<input type="password">` suelto.

### Error (`x-ui.field-error`)

```
mt-1 text-xs text-red-400
```

### Contenedor formulario

```
space-y-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4
```

### Input compuesto (prefijo `usuario@`)

Misma altura `h-9`, borde compartido, focus en contenedor:

```
flex h-9 rounded-lg border border-slate-700 bg-slate-950
focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500/30
```

**Vistas de referencia:** `resources/views/modules/company/clients/create.blade.php`, `edit.blade.php`

---

## 5. Tablas

### Contenedor

```
rounded-lg border border-slate-800 overflow-hidden bg-slate-900/80
```

### Tabla

```
min-w-full text-sm
```

### Header (`thead`)

```
bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500
th: px-4 py-2.5 text-left font-medium
```

### Body

```
tbody: divide-y divide-slate-800
tr: hover:bg-slate-800/30
td: px-4 py-3
```

### Empty state

```
px-4 py-10 text-center text-sm text-slate-500
```

---

## 6. Superficies y espaciado

| Elemento | Clases |
|----------|--------|
| Página (contenido) | `space-y-4` |
| Grid 8+4 | `grid grid-cols-1 lg:grid-cols-12 gap-4` |
| Franja contexto / licencia | `rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-3` |
| Panel con secciones | `rounded-lg border border-slate-800 bg-slate-900/80 divide-y divide-slate-800` |
| Sección panel | `px-4 py-3` |
| Main layout | `max-w-7xl px-4 sm:px-6 lg:px-8 py-5` |
| Header nav | `py-3` |

### Barra de progreso (cupo)

```
Contenedor: h-1.5 rounded-full bg-slate-800
Relleno normal: bg-indigo-500
Relleno alerta (≥90%): bg-amber-500
```

---

## 7. Badges y estado

| Estado | Clases |
|--------|--------|
| Activo | `rounded-full bg-emerald-900/30 px-2 py-0.5 text-xs text-emerald-300` |
| Inactivo | `rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-500` |
| Promo badge | `rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-medium text-emerald-300` |
| Chip plan | `rounded-md border border-indigo-800/50 bg-indigo-900/30 px-2 py-0.5 text-xs text-indigo-200` |

### Estado suscripción (franja licencia)

- Activa: `text-emerald-400`
- Renovación próxima: `text-amber-400`
- Vencida: `text-red-400`

---

## 8. Zonas comerciales (panel Cuenta)

Misma estructura `px-4 py-3` — solo cambia color semántico:

| Zona | Fondo | Acento texto | CTA |
|------|-------|--------------|-----|
| Próximo cargo | neutro | blanco / slate-500 | — |
| Licencia anual | `bg-emerald-950/25` | `text-emerald-200` / `text-emerald-400` | `variant="success"` |
| Ampliar cupo | `bg-indigo-950/20` | `text-indigo-200` / `text-indigo-300` | `primary` |
| Incluido en plan | neutro | `text-indigo-300/80` + chips indigo | — |

---

## 9. Layout empresa — shell

**Archivo:** `resources/views/layouts/company.blade.php`  
**Composer:** `app/View/Composers/CompanyLayoutComposer.php` (inyecta `companyContext` para cupo en header)

### Header nav

- **Izquierda:** L1 título (`$title`) + L2 empresa (`$companyContext['company_name']`)
- **Derecha:** Portería (secondary) + Conjunto (primary, si cupo disponible)
- **Sin** Cerrar sesión en header

### Sidebar

- Ancho: `w-64`
- Alto: viewport completo (`lg:h-full` dentro de `h-screen`); **no** crece con el `main`
- Nav item activo: `bg-indigo-600 text-white`
- Nav item idle: `text-slate-300 hover:bg-slate-800`
- Pie: nombre usuario + **Cerrar sesión** (`partials/sidebar-user`), fijo abajo (`shrink-0`)
- Nav con scroll interno si el menú es largo; flecha a media altura para plegar el aside

### Flash messages

```
success: rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 text-sm
warning: rounded-lg bg-amber-900/40 border border-amber-700 text-amber-200 px-4 py-3 text-sm
```

---

## 10. Acentos por panel

| Panel | Acento primary | Estado migración |
|-------|----------------|------------------|
| Empresa (`/company`) | `indigo` | ✅ Referencia actual |
| Plataforma (`/admin`) | `violet` | ✅ Dashboard, empresas, pricing |
| Conjunto (`/client`) | `teal` | Pendiente |
| Portería (`/access`) | `indigo` | Consola vigilante (ingreso/censo/pánico) |

Al migrar client/access: duplicar patrones de este doc cambiando solo el color acento en botones, focus de inputs y nav activo.

---

## 11. Checklist al migrar una vista

1. Usar `<x-company-layout title="...">` (título = L1 en header).
2. No duplicar botones Portería/Conjunto en contenido (van en layout).
3. Secciones con L3 (`text-sm font-semibold`).
4. Tablas según sección 5.
5. Formularios con `x-ui.label`, `x-ui.input`, `x-ui.field-error`, submit `size="md"`.
6. Sin `text-3xl`, sin gradientes, sin `rounded-2xl` en datos.
7. Comparar visualmente con `/company/dashboard`.

---

## 12. Vistas empresa — estado migración

| Vista | Estado |
|-------|--------|
| `company/dashboard` | ✅ Referencia completa |
| `company/clients/create` | ✅ Formulario UI |
| `company/clients/edit` | ✅ Formulario UI |
| `company/clients/index` | ✅ Cartera + modo `?modo=operar` |
| `company/clients/show` | Pendiente |
| `company/clients/select` | ❌ Eliminada — unificada en `index?modo=operar` + `company.porteria.enter` |

---

## 13. Panel plataforma (`/admin`) — violet

**Layout:** `resources/views/layouts/admin.blade.php`  
**Documentación funcional:** [`docs/PLATAFORMA-ADMIN.md`](PLATAFORMA-ADMIN.md)

### Shell

| Elemento | Detalle |
|----------|---------|
| Wrapper | `h-screen flex overflow-hidden` — body `overflow-hidden` |
| Aside | `lg:h-full lg:flex-col shrink-0` — altura viewport, sin stretch del contenido |
| Zonas aside | Marca `shrink-0` · nav `flex-1` · pie usuario `shrink-0` (fijo abajo) |
| Columna derecha | `min-h-0 overflow-y-auto` — único scroll de página |
| Contenedor main | `max-w-[1600px]`, `flex flex-col min-h-0` para paneles con scroll interno |
| Sidebar nav activo | `bg-violet-600 text-white` |
| Header acciones | `x-ui.button variant="platform"` (Empresas) y `secondary` (Precios) |
| Links tabla | `text-xs text-violet-400 hover:text-violet-300` |

### Vistas migradas

| Vista | Estado |
|-------|--------|
| `admin/dashboard` | ✅ Mapa, KPIs, cartera (6 segmentos), paquetes, TOP 5, tendencia MRR |
| `admin/downloads` | ✅ Descargar APK + web PWA de respaldo |
| `admin/companies/index` | ✅ KPIs riesgo + totales; tabla cartera + badges |
| `admin/companies/show` | ✅ Paquete + formulario `x-ui.*` |
| `admin/pricing/edit` | ✅ Unitarios + matriz calculada |

### Select nativo (sin componente aún)

```
w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white
focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30
```

---

*Última actualización: 13 septiembre 2026 — avisos centrados en español (`flash-toasts` + `form-feedback.js`).*

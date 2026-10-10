# Modelo comercial — Paquetes empresa (Zentra)

Documentación de la implementación de pricing B2B para empresas de seguridad (julio 2026).

---

**Catálogo 29 sep 2026:** metales Bronce (1–5) · Plata (6–15) · Oro (16–40) · Platino (41–100). Unidad = 100%. Pack = unidad × cupo × (1 − %). Extra = unidades al 100%. Empleados 3k/6k/9k/12k. Indexación add-on más barata si ya hay Accesos/Supervisión. Observatorio: un precio según metal de Accesos. Módulos por producto en `/admin/pricing`. `config/catalog.php` + `pricing_settings.catalog`.

## Resumen ejecutivo

| Quién compra | Qué compra | Qué limita |
|--------------|------------|------------|
| Empresa de seguridad | Metales + productos (Accesos, Supervisión, Indexación, Observatorio) | Instalaciones activas (`max_clients`) y empleados (3k–12k) |

Unidad comercial = **instalación activa**. Personas/nodos/puertas de la sede no se venden por cupo.

---

## Metales y fórmula

| Plan | Rango / pack | Empleados |
|------|--------------|-----------|
| Bronce | 1–5 / pack 5 | 3.000 |
| Plata | 6–15 / pack 15 | 6.000 |
| Oro | 16–40 / pack 40 | 9.000 |
| Platino | 41–100 / pack 100 | 12.000 |

```
precio_pack = unidad × cupo_pack × (1 − % descuento del metal)
N sedes     = mayor pack que quepa + extras × unidad (100%)
```

Ciclo anual: ×12 × (1 − `tenancy.pricing.annual_discount`, ~17%).

## Productos y menús

Defaults en `config/catalog.php`. Editables en `/admin/pricing` (un modal, 4 bloques).

| Producto | Menú por defecto |
|----------|------------------|
| Accesos | Mi empresa, Facturación, Clientes, Instalaciones, Pánicos, Empleados, Usuarios, Mis datos, Ajustes |
| Supervisión | Facturación, Clientes, Instalaciones, Supervisión, Pánicos, Descargas, Empleados, Usuarios, Mis datos, Ajustes |
| Indexación | Facturación, Empleados, Documentos, Usuarios, Mis datos, Ajustes |
| Observatorio | Observatorio (solo si hay Accesos) |

Indexación **sola** = unidad lista (más cara) + cupo del metal. **Add-on** (ya hay Accesos o Supervisión) = `indexing_addon`, solo carpetas. Observatorio: un precio por metal de Accesos (Platino más barato).

El panel recorta el sidebar con `CompanyEntitlements` + `$canMod`. Alta de empleado respeta el tope.

## Dónde se ve

- `/admin/pricing` — 4 tablas + Editar catálogo  
- `/` y `/planes` — agrupa qué lleva cada metal  
- Ficha empresa — etiqueta Bronce/Plata… + extras  

Flags en `security_companies`: `has_indexing`, `has_observatory` (existentes quedan en true). JSON `pricing_settings.catalog`.

Motor: `App\Support\Catalog\CatalogPricer`. Signup público aún usa SKU legacy (`pack_5/10/50/100_manual`). `PriceCalculator` (volumen 1–500) queda para ese checkout.

## Rutas

| Método | Ruta | Permiso |
|--------|------|---------|
| GET/PUT | `/admin/pricing` | `platform.companies.view` / `manage` |
| GET | `/planes` | público |

## Tests (BD `zentra_test`)

```bash
php artisan test --filter=CatalogPricerTest
php artisan test --filter=PriceCalculatorTest
```

## Deploy

```bash
php artisan migrate --force
npm run build
php artisan view:cache && php artisan route:cache
```

Sin `migrate:fresh`. Ver [`HOSTING-VPS.md`](HOSTING-VPS.md).

## Changelog

| Fecha | Cambio |
|-------|--------|
| 2026-09-29 | Metales, 4 productos, módulos por plan, indexación add-on, observatorio por metal de Accesos |
| 2026-07-20 | Modelo inicial cupo + unitarios |

# Paquetes Accesos, Supervisión, Indexación y Observatorio

Catálogo vigente (13 oct 2026). Precios, fórmula, cupos y carrito: [`MODELO-COMERCIAL-PAQUETES.md`](MODELO-COMERCIAL-PAQUETES.md).

## Planes (mismos 4 en los 4 productos)

| Plan | Instalaciones | Puestos por instalación | Empleados por puesto | Empleados totales |
|------|---------------|-------------------------|----------------------|-------------------|
| Bronce | 1–5 | 2 | 3 | 1.000 |
| Plata | 6–15 | 5 | 5 | 2.000 |
| Oro | 16–40 | 7 | 7 | 3.000 |
| Platino | 41+ | 10 | 10 | 5.000 |

Cada producto se cobra **por instalación** y toma el plan de su propia cantidad. Los límites de la empresa salen del plan más alto entre sus productos. Combinar productos da 30% de descuento a cada uno después del de mayor valor.

## Productos

**Accesos** — cantidad `max_clients`. Menú: Mi empresa, Facturación, Clientes, Instalaciones, Pánicos, Empleados, Usuarios, Mis datos, Ajustes. **No** Descargas.

**Supervisión** — cantidad `max_supervision_clients`. Menú: Facturación, Clientes, Instalaciones, Supervisión, Pánicos, Descargas, Empleados, Usuarios, Mis datos, Ajustes. **No** Mi empresa. GPS y app de campo: [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md).

**Indexación** — cantidad `indexing_seats`, precio propio (ya no es complemento).

**Observatorio** — cantidad `observatory_seats`, precio propio. Se vende solo. Menú: Mi empresa, Facturación, Clientes, Instalaciones, Observatorio, Usuarios, Mis datos, Ajustes. Por cliente: `has_observatory`.

Módulos por producto se marcan en `/admin/pricing` → Editar planes.

## Cupo de instalaciones

Crear cliente o instalación pide 1 cupo libre de cada producto que tenga el cliente (Accesos, Supervisión, Observatorio). Archivar libera; restaurar vuelve a pedir cupo. El súper admin amplía cupos en la ficha empresa y la empresa puede **Solicitar ampliación** en Facturación. [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

## Checkout

`/planes` (tarjetas por plan) → `/planes/carrito` → `/contratar` con las cantidades de los 4 productos. La ficha empresa y Facturación cambian el plan ya, en una fecha o al corte.

## App de campo

PWA `field-app/` · API `/api/supervision/*` · **Descargas** (APK + QR). No Play Store.

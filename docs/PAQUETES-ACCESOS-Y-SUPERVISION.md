# Paquetes Accesos, Supervisión, Indexación y Observatorio

Catálogo vigente (13 oct 2026). Precios, fórmula, cupos y carrito: [`MODELO-COMERCIAL-PAQUETES.md`](MODELO-COMERCIAL-PAQUETES.md).

## Planes (mismos 4 en los 4 productos)

| Plan | Instalaciones | Indexación (empleados) | Puestos por instalación | Empleados por puesto | Empleados totales |
|------|---------------|------------------------|-------------------------|----------------------|-------------------|
| Bronce | 1–5 | 1–1.000 | 2 | 3 | 1.000 |
| Plata | 6–15 | 1.001–2.000 | 5 | 5 | 2.000 |
| Oro | 16–40 | 2.001–3.000 | 7 | 7 | 3.000 |
| Platino | 41+ | 3.001+ | 10 | 10 | 5.000 |

Accesos y Observatorio se cobran **por instalación**; Supervisión **por puesto** de servicio; Indexación **por empleado**. Cada producto toma el plan de su propia cantidad. Los límites de la empresa salen del plan más alto entre sus productos. Combinar productos da 30% de descuento a cada uno después del de mayor valor.

## Productos

**Accesos** — cantidad `max_clients`. Menú: Mi empresa, Facturación, Clientes, Instalaciones, Pánicos, Empleados, Usuarios, Mis datos, Ajustes. **No** Descargas.

**Supervisión** — cantidad `max_supervision_clients` = **puestos** de servicio (pagas por cada puesto que supervisas). N puestos permiten hasta N instalaciones con Supervisión (mín. 1 puesto por instalación) y el tope de puestos por instalación del metal. Menú: Facturación, Clientes, Instalaciones, Supervisión, Pánicos, Descargas, Empleados, Usuarios, Mis datos, Ajustes. **No** Mi empresa. GPS y app de campo: [`SUPERVISION-CAMPO.md`](SUPERVISION-CAMPO.md).

**Indexación** — cantidad `indexing_seats` = **empleados**, precio por empleado ($1.000 menos el descuento del plan). No cuenta para el cupo de instalaciones; el tope de empleados de la empresa nunca queda por debajo de los empleados contratados.

**Observatorio** — cantidad `observatory_seats`, precio propio. Se vende solo. Menú: Mi empresa, Facturación, Clientes, Instalaciones, Observatorio, Usuarios, Mis datos, Ajustes. Por cliente: `has_observatory`.

Módulos por producto se marcan en `/admin/pricing` → Editar planes.

## Cupo de instalaciones y puestos

Crear cliente o instalación pide 1 cupo libre de Accesos/Observatorio si el cliente los tiene. Supervisión: crear instalación con Supervisión exige cupo de puestos libres (≥1); crear el puesto N+1 en la empresa se bloquea si no hay cupo. La barra de cupos de Supervisión muestra puestos usados / contratados. Archivar libera; restaurar vuelve a pedir cupo. El súper admin amplía cupos en la ficha empresa y la empresa puede **Solicitar ampliación** en Facturación. [`CLIENTES-Y-ESTRUCTURA.md`](CLIENTES-Y-ESTRUCTURA.md).

## Checkout

`/planes` (tarjetas por plan) → `/planes/carrito` → `/contratar` con las cantidades de los 4 productos. La ficha empresa y Facturación cambian el plan ya, en una fecha o al corte.

## App de campo

PWA `field-app/` · API `/api/supervision/*` · **Descargas** (APK + QR). No Play Store.

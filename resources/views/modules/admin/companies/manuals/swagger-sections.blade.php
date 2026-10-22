{{-- Secciones extra del «Manual de Integración» (Swagger y respuestas). --}}
<h2>8. La documentación (Swagger)</h2>
<p><strong>Swagger</strong> es una página que lista todos los endpoints del Observatorio: qué datos pide cada uno, qué responde y qué errores puede dar. Sirve para conocer la API y probarla con una key real <strong>sin escribir código</strong>.</p>
<ul>
    <li>Página: <code>{{ $docsUrl }}</code> (la abre soporte de Zentra).</li>
    <li>Contrato para el técnico: <code>{{ $specUrl }}</code>. Se importa en Postman, Insomnia o cualquier Swagger y muestra lo mismo.</li>
</ul>

<h2>9. Autorizar con la key</h2>
<ol>
    <li>Abre la documentación y pulsa el botón verde <strong>Autorizar</strong>.</li>
    <li>Pega la key <strong>sin</strong> la palabra «Bearer» y pulsa <strong>Autorizar</strong>. Debe decir «Autorizado».</li>
    <li>Cierra la ventana. Los candados de los endpoints quedan cerrados: ya van con la key.</li>
    <li>Al terminar, vuelve a <strong>Autorizar → Quitar key</strong> para que el navegador no guarde la key.</li>
</ol>

<h2>10. Probar los endpoints uno por uno</h2>
<ol>
    <li>Abre un endpoint, por ejemplo <code>GET /api/observatory/events</code> (Listar eventos).</li>
    <li>Llena los filtros que necesites (todos son opcionales): estado, fechas, colegio, búsqueda, página.</li>
    <li>Pulsa <strong>Ejecutar</strong>.</li>
    <li>Swagger muestra el comando <code>curl</code> enviado (la key aparece como <code>TU_API_KEY</code>), la dirección consultada y la respuesta del servidor.</li>
</ol>
<table>
    <tr><th>Endpoint</th><th>Para qué</th><th>Permiso de la key</th></tr>
    <tr><td><code>GET /api/observatory/events</code></td><td>Listar eventos (folios)</td><td>Eventos: listar y ver</td></tr>
    <tr><td><code>GET /api/observatory/events/{id}</code></td><td>Ver un folio con sus reportes</td><td>Eventos: listar y ver</td></tr>
    <tr><td><code>GET /api/observatory/board</code></td><td>Cifras del tablero y riesgos</td><td>Tablero (cifras)</td></tr>
    <tr><td><code>GET /api/observatory/sites</code></td><td>Colegios e instalaciones del alcance</td><td>Colegios e instalaciones</td></tr>
    <tr><td><code>POST /api/observatory/reports</code></td><td>Crear un reporte</td><td>Crear reportes</td></tr>
</table>
<p class="note">Las pruebas son reales: «Crear reporte» crea un reporte de verdad. Para explorar usa una key de solo lectura.</p>

<h2>11. Las respuestas son datos (JSON), no una página</h2>
<p>La API no devuelve pantallas ni páginas web: devuelve <strong>datos en formato JSON</strong> para que el otro sistema los guarde o los muestre a su manera. Ejemplo de un evento:</p>
<pre>{
  "data": [
    {
      "folio": "EV-000024",
      "status": "en_atencion",
      "status_label": "En atención",
      "title": "Hurto",
      "opened_at": "2026-10-06T10:10:22-05:00",
      "reports_count": 2,
      "installation": { "name": "IE Santa Librada", "dane_code": "176001000201" },
      "client": { "name": "Palmas del Ingenio" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}</pre>

<h2>12. Respuestas posibles</h2>
<table>
    <tr><th>Código</th><th>Qué significa</th><th>Qué hacer</th></tr>
    <tr><td><code>200</code></td><td>Todo bien: llegaron los datos.</td><td>Nada.</td></tr>
    <tr><td><code>201</code></td><td>Se creó el reporte (o se unió a un folio abierto del mismo colegio y tipo).</td><td>Guardar el folio que devuelve.</td></tr>
    <tr><td><code>401</code></td><td>La key es inválida, fue revocada o regenerada, o venció.</td><td>Pedir una key nueva a soporte.</td></tr>
    <tr><td><code>403</code></td><td>La key no tiene ese permiso (<code>code: api_key_ability</code>) o el dato está fuera de su alcance.</td><td>Revisar el permiso con soporte o usar un colegio del alcance.</td></tr>
    <tr><td><code>422</code></td><td>Faltan datos o están mal (p. ej. texto muy corto, tipo inexistente).</td><td>Leer <code>errors</code> en la respuesta y corregir.</td></tr>
</table>

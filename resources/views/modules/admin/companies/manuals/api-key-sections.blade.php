{{-- Secciones del «Manual de API Key». HTML simple: se usa en el modal y en el PDF. --}}
<h2>1. Qué es una API key</h2>
<p>Una <strong>API key</strong> es una clave larga que permite a otro sistema (por ejemplo, el de la Secretaría de Educación o el software de un cliente) leer o enviar datos a Zentra <strong>sin usar la contraseña de una persona</strong>. Funciona como una llave con permisos: solo abre las puertas que se le dieron.</p>
<p>La entrega soporte de Zentra (súper administrador) desde la ficha de la empresa, pestaña <strong>Integraciones API</strong>.</p>

<h2>2. Cómo se crea</h2>
<ol>
    <li>En la pestaña Integraciones API, pulsa <strong>+ Nueva API key</strong>.</li>
    <li>Escribe un <strong>nombre</strong> que diga para quién es (p. ej. «Secretaría de Educación — Observatorio»).</li>
    <li>Elige el <strong>usuario</strong> de la key:
        <ul>
            <li><strong>Usuario existente</strong> de la empresa: la key ve lo mismo que ese usuario.</li>
            <li><strong>Usuario externo nuevo</strong>: se crea solo para la integración, con el Observatorio en «Ver», en toda la empresa o solo en los clientes que marques. No entra al panel; solo usa la API.</li>
        </ul>
    </li>
    <li>Marca los <strong>permisos por módulo</strong> (ver punto 3).</li>
    <li>Si quieres, pon una fecha de <strong>vencimiento</strong> (es opcional, ver punto 4).</li>
    <li>Pulsa <strong>Crear key</strong>. La key se muestra <strong>una sola vez</strong>: cópiala y entrégala por un medio seguro. Zentra no la vuelve a mostrar.</li>
</ol>

<h2>3. Cómo funcionan los permisos</h2>
<p>Cada key tiene un <strong>doble filtro</strong>:</p>
<ol>
    <li><strong>Endpoints permitidos</strong>: por módulo eliges <em>Sin acceso</em>, <em>Lectura</em> (solo consultar), <em>Lectura y escritura</em> (también crear) o <em>Personalizado</em> (marcas endpoint por endpoint).</li>
    <li><strong>Datos del usuario</strong>: aunque tenga el permiso, la key solo ve los <strong>clientes e instalaciones de su usuario</strong>. Nunca ve datos de otros clientes.</li>
</ol>
<p class="note">Ejemplo: una key con Observatorio en «Lectura» para un usuario externo del cliente Palmas puede listar eventos de Palmas, pero no crear reportes ni ver eventos de otros clientes.</p>

<h2>4. Vencimiento (opcional)</h2>
<ul>
    <li>Al crear la key tú eliges si le pones fecha («Vence») o no.</li>
    <li><strong>Sin fecha</strong>: la key no caduca. Sigue activa hasta que se <strong>revoque</strong> o se <strong>regenere</strong>.</li>
    <li><strong>Con fecha</strong>: funciona hasta ese día. Cuando la fecha pasa, deja de funcionar, aparece como «Vencida» y el otro sistema recibe error <code>401</code>. Para seguir hay que crear una key nueva.</li>
</ul>
<p class="note">Consejo: pon fecha cuando el acceso sea temporal (un contrato, una prueba) y avísale al técnico cuándo vence.</p>

<h2>5. Regenerar y revocar</h2>
<ul>
    <li><strong>Regenerar</strong>: entrega una key nueva con los mismos permisos y la misma fecha. La anterior deja de servir al instante. Úsalo si la key se perdió o se filtró. Una key vencida no se regenera: se crea una nueva.</li>
    <li><strong>Revocar</strong>: apaga la key de inmediato y no se puede reactivar. Úsalo cuando termine la integración.</li>
    <li>Si el usuario de la key se desactiva, la key también deja de funcionar.</li>
</ul>

<h2>6. Qué muestra la tabla</h2>
<table>
    <tr><th>Columna</th><th>Qué significa</th></tr>
    <tr><td>Key</td><td>Nombre y los últimos 4 caracteres, para reconocerla sin mostrarla.</td></tr>
    <tr><td>Usuario</td><td>Persona o usuario externo dueño de la key, con su rol y correo.</td></tr>
    <tr><td>Permisos</td><td>Nivel por módulo: Lectura, Lectura y escritura o Personalizado (con la lista de endpoints).</td></tr>
    <tr><td>Estado</td><td>Activa · Vencida (pasó la fecha) · Usuario inactivo · Revocada (con fecha y quién la revocó).</td></tr>
    <tr><td>Último uso</td><td>Cuándo la usó el otro sistema por última vez. «Nunca» si aún no se ha usado.</td></tr>
    <tr><td>Fechas</td><td>Creada, regenerada y vencimiento («Sin vencimiento» si no tiene).</td></tr>
</table>

<h2>7. Uso básico con curl</h2>
<p>En cada petición el otro sistema envía la key en el encabezado <code>Authorization: Bearer</code> y pide JSON. Para probar que la key funciona:</p>
<pre>curl {{ $baseUrl }}/api/auth/me \
  -H "Authorization: Bearer TU_API_KEY" \
  -H "Accept: application/json"</pre>
<p>Si responde con los datos del usuario, la key funciona. <code>401</code> = key inválida, revocada o vencida. <code>403</code> = la key no tiene ese permiso o el dato está fuera de su alcance.</p>

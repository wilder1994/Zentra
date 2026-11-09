<x-mail::message>
# Hola, {{ $memberName }}

Te crearon acceso a la app de **{{ $clientName }}**.

**Usuario:** {{ $login }}  
**Contraseña:** {{ $password }}  
**Canal:** {{ $channelLabel }}

@if ($isIosWeb)
En iPhone entra por el navegador (Safari) con este enlace:

{{ $webUrl }}

Guarda este correo en un lugar seguro. Puedes cambiar la contraseña más adelante desde la app web.
@else
Instala la app Android desde **Descargas** del panel de tu conjunto (o pídele el APK a la administración). Luego inicia sesión con el usuario y la contraseña de arriba.

No compartas estas credenciales.
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# {{ $heading }}

<x-mail::table>
| | |
|:--|:--|
@foreach ($facts as $fact)
| **{{ $fact['label'] }}** | {{ $fact['value'] }} |
@endforeach
</x-mail::table>

@if ($generalNote)
**Novedad reportada en la revista**

{{ $generalNote }}

@endif
@foreach ($sections as $section)
**{{ $section['title'] }}**

@foreach ($section['rows'] as $row)
- {{ $row }}
@endforeach

@endforeach
@if ($sheetUrl)
<x-mail::button :url="$sheetUrl">
Ver ficha completa
</x-mail::button>
@endif

Recibes este correo porque eres responsable de este cliente en {{ $companyName }}. Solo se envían los registros con novedad.
</x-mail::message>

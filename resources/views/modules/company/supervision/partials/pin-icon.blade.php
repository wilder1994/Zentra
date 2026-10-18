@php
    // Gota 32×42 (punta en 16,41). Misma forma que resources/js/map-pins.js.
    $class = isset($class) ? ' class="'.$class.'"' : '';
    $path = 'M16 41C16 41 29 27.6 29 16A13 13 0 0 0 3 16C3 27.6 16 41 16 41Z';
    $drop = fn (string $fill, string $glyph, string $stroke = '#0b1220') => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42"'.$class.'>'
        .'<path d="'.$path.'" fill="'.$fill.'" stroke="'.$stroke.'" stroke-width="1.8" stroke-linejoin="round"/>'
        .'<g transform="translate(16 16) scale(.86) translate(-16 -16)">'.$glyph.'</g></svg>';
@endphp
@switch($kind)
@case('start')
{!! $drop('#16a34a', '<rect x="10" y="7.5" width="2.2" height="17" rx="1" fill="#ffffff"/><path d="M12.2 8h11l-3 4.2 3 4.2h-11z" fill="#ffffff"/>') !!}
@break
@case('flag')
{!! $drop('#334155', '<rect x="9" y="7.5" width="2.2" height="17" rx="1" fill="#f8fafc"/><rect x="11.2" y="8" width="12" height="9" fill="#ffffff"/><path d="M11.2 8h3v3h-3zM17.2 8h3v3h-3zM14.2 11h3v3h-3zM20.2 11h3v3h-3zM11.2 14h3v3h-3zM17.2 14h3v3h-3z" fill="#0b1220"/><rect x="11.2" y="8" width="12" height="9" fill="none" stroke="#0b1220" stroke-width=".6"/>', '#f8fafc') !!}
@break
@case('review')
{!! $drop('#16a34a', '<rect x="10" y="8.5" width="12" height="15.5" rx="2" fill="#ffffff"/><rect x="12.8" y="6.8" width="6.4" height="3.6" rx="1.2" fill="#0b1220"/><path d="M12.6 16.6l2.5 2.5 4.4-5" fill="none" stroke="#16a34a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>') !!}
@break
@case('novelty')
{!! $drop('#ea580c', '<rect x="10" y="8.5" width="12" height="15.5" rx="2" fill="#ffffff"/><rect x="12.8" y="6.8" width="6.4" height="3.6" rx="1.2" fill="#0b1220"/><rect x="14.8" y="12.4" width="2.4" height="6.4" rx="1.2" fill="#ea580c"/><circle cx="16" cy="21.2" r="1.4" fill="#ea580c"/>') !!}
@break
@case('alarm')
{!! $drop('#dc2626', '<path d="M11 21.5v-4.5a5 5 0 0 1 10 0v4.5z" fill="#ffffff"/><rect x="9.2" y="21.5" width="13.6" height="2.8" rx="1" fill="#ffffff"/><path d="M16 7.2v2.4M9.4 9.6l1.6 1.6M22.6 9.6L21 11.2" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>') !!}
@break
@case('stop')
{!! $drop('#0b1220', '<polygon points="11.4,5.4 20.6,5.4 26.6,11.4 26.6,20.6 20.6,26.6 11.4,26.6 5.4,20.6 5.4,11.4" fill="#991b1b" stroke="#ffffff" stroke-width="1.4"/><text x="16" y="19" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="7.6" font-weight="800" fill="#ffffff">STOP</text>', '#991b1b') !!}
@break
@case('coverage')
{!! $drop($color ?? '__COLOR__', ($ring ?? '__RING__').'<text x="16" y="20.4" text-anchor="middle" font-family="system-ui,sans-serif" font-size="'.($fontSize ?? '__FONT__').'" font-weight="700" fill="#ffffff">'.e($text ?? '__TEXT__').'</text>') !!}
@break
@case('support')
{!! $drop('#2563eb', '<path d="M16 7.5l7 2.6v5.2c0 4.4-3 7.4-7 8.6-4-1.2-7-4.2-7-8.6v-5.2z" fill="#ffffff"/>') !!}
@break
@case('client')
{!! $drop('#6366f1', '<circle cx="16" cy="12.6" r="3.4" fill="#ffffff"/><path d="M9.6 22.4c.6-3.6 3.2-5.6 6.4-5.6s5.8 2 6.4 5.6z" fill="#ffffff"/>') !!}
@break
@case('installation')
{!! $drop('#0891b2', '<path d="M10 22v-8.6l6-3.9 6 3.9V22z" fill="#ffffff"/><path d="M14.3 22v-4.2h3.4V22z" fill="#0891b2"/>') !!}
@break
@endswitch

@php($class = isset($class) ? ' class="'.$class.'"' : '')
@switch($kind)
@case('start')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#16a34a" stroke="#0b1220" stroke-width="2"/><rect x="10" y="7.5" width="2.2" height="17" rx="1" fill="#ffffff"/><path d="M12.2 8h11l-3 4.2 3 4.2h-11z" fill="#ffffff"/></svg>
@break
@case('flag')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#334155" stroke="#f8fafc" stroke-width="1.6"/><rect x="9" y="7.5" width="2.2" height="17" rx="1" fill="#f8fafc"/><rect x="11.2" y="8" width="12" height="9" fill="#ffffff"/><path d="M11.2 8h3v3h-3zM17.2 8h3v3h-3zM14.2 11h3v3h-3zM20.2 11h3v3h-3zM11.2 14h3v3h-3zM17.2 14h3v3h-3z" fill="#0b1220"/><rect x="11.2" y="8" width="12" height="9" fill="none" stroke="#0b1220" stroke-width=".6"/></svg>
@break
@case('review')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#16a34a" stroke="#0b1220" stroke-width="2"/><rect x="10" y="8.5" width="12" height="15.5" rx="2" fill="#ffffff"/><rect x="12.8" y="6.8" width="6.4" height="3.6" rx="1.2" fill="#0b1220"/><path d="M12.6 16.6l2.5 2.5 4.4-5" fill="none" stroke="#16a34a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
@break
@case('novelty')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#ea580c" stroke="#0b1220" stroke-width="2"/><rect x="10" y="8.5" width="12" height="15.5" rx="2" fill="#ffffff"/><rect x="12.8" y="6.8" width="6.4" height="3.6" rx="1.2" fill="#0b1220"/><rect x="14.8" y="12.4" width="2.4" height="6.4" rx="1.2" fill="#ea580c"/><circle cx="16" cy="21.2" r="1.4" fill="#ea580c"/></svg>
@break
@case('alarm')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#dc2626" stroke="#0b1220" stroke-width="2"/><path d="M11 21.5v-4.5a5 5 0 0 1 10 0v4.5z" fill="#ffffff"/><rect x="9.2" y="21.5" width="13.6" height="2.8" rx="1" fill="#ffffff"/><path d="M16 7.2v2.4M9.4 9.6l1.6 1.6M22.6 9.6L21 11.2" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/></svg>
@break
@case('stop')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><polygon points="10.4,2 21.6,2 30,10.4 30,21.6 21.6,30 10.4,30 2,21.6 2,10.4" fill="#0b1220"/><polygon points="11,3.5 21,3.5 28.5,11 28.5,21 21,28.5 11,28.5 3.5,21 3.5,11" fill="#991b1b" stroke="#ffffff" stroke-width="1.4"/><text x="16" y="19.1" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="8.2" font-weight="800" fill="#ffffff">STOP</text></svg>
@break
@case('support')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"{!! $class !!}><circle cx="16" cy="16" r="14" fill="#2563eb" stroke="#0b1220" stroke-width="2"/><path d="M16 7.5l7 2.6v5.2c0 4.4-3 7.4-7 8.6-4-1.2-7-4.2-7-8.6v-5.2z" fill="#ffffff"/></svg>
@break
@endswitch

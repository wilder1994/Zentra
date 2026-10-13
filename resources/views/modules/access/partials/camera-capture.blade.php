{{--
  Cámara en dos pasos: Abrir cámara → acomodar → Tomar foto.
  name: nombre del input hidden; required: marca visual; label/hint: textos.
--}}
@php
    $name = $name ?? 'person_photo_data';
    $label = $label ?? 'Foto';
    $required = (bool) ($required ?? false);
    $hint = $hint ?? ($required ? 'Obligatoria en el primer registro' : 'Opcional si ya hay foto o es pre-registro');
    $initial = $initial ?? '';
@endphp
<div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3 space-y-2"
     x-data="accessCam(@js($initial))"
     x-cloak>
    <div class="flex items-center justify-between gap-2">
        <div>
            <p class="text-sm font-medium text-slate-200">{{ $label }}@if($required) <span class="text-amber-400">*</span>@endif</p>
            <p class="text-xs text-slate-500">{{ $hint }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" x-show="!open && !dataUrl" @click="start()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold">Abrir cámara</button>
            <button type="button" x-show="!open && dataUrl" @click="start()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 text-white">Actualizar foto</button>
            <button type="button" x-show="dataUrl && !open" @click="clear()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">Quitar</button>
        </div>
    </div>

    <div x-show="open" class="space-y-2">
        <video x-ref="live" autoplay playsinline muted class="w-full max-h-56 rounded-lg bg-black object-cover"></video>
        <div class="flex gap-2">
            <button type="button" @click="capture()" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Tomar foto</button>
            <button type="button" @click="stop()" class="px-3 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Cancelar</button>
        </div>
    </div>

    <img x-show="dataUrl && !open" :src="dataUrl" alt="Foto capturada" class="h-24 w-24 rounded-lg object-cover border border-slate-700">
    <input type="hidden" name="{{ $name }}" :value="dataUrl">
</div>
@once
@push('scripts')
<script>
function accessCam(initial) {
    return {
        open: false,
        stream: null,
        dataUrl: initial || '',
        async start() {
            this.open = true;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                await this.$nextTick();
                this.$refs.live.srcObject = this.stream;
            } catch (e) {
                alert('No se pudo abrir la cámara. Revisa el permiso del navegador.');
                this.open = false;
            }
        },
        stop() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            if (this.$refs.live) this.$refs.live.srcObject = null;
            this.open = false;
        },
        capture() {
            const v = this.$refs.live;
            if (!v || !v.videoWidth) return;
            const c = document.createElement('canvas');
            c.width = v.videoWidth;
            c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.dataUrl = c.toDataURL('image/jpeg', 0.8);
            this.stop();
        },
        clear() {
            this.dataUrl = '';
        }
    };
}
</script>
@endpush
@endonce

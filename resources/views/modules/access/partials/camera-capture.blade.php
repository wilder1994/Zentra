{{--
  Cámara en modal cuadrado: Abrir cámara → acomodar → Tomar foto → Usar foto.
  name: nombre del input hidden; required: marca visual; label/hint: textos.
--}}
@php
    $name = $name ?? 'person_photo_data';
    $label = $label ?? 'Foto';
    $required = (bool) ($required ?? false);
    $hint = $hint ?? ($required ? 'Obligatoria en el primer registro' : 'Opcional');
    $initial = $initial ?? '';
    $facing = $facing ?? 'user';
@endphp
<div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3" x-data="accessCam(@js($initial), @js($facing))">
    <div class="flex items-center gap-3">
        <template x-if="dataUrl">
            <img :src="dataUrl" alt="Foto capturada" class="h-16 w-16 rounded-lg object-cover border border-slate-700">
        </template>
        <template x-if="!dataUrl">
            <div class="h-16 w-16 rounded-lg border border-dashed border-slate-700 bg-slate-900 flex items-center justify-center text-slate-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.8 6.2 8 4.5h8l1.2 1.7H20a1 1 0 0 1 1 1V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7.2a1 1 0 0 1 1-1h2.8Z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
            </div>
        </template>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-200">{{ $label }}@if($required) <span class="text-amber-400">*</span>@endif</p>
            <p class="text-xs text-slate-500">{{ $hint }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" @click="openModal()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold"
                    x-text="dataUrl ? 'Cambiar foto' : 'Abrir cámara'"></button>
            <button type="button" x-show="dataUrl" @click="dataUrl = ''"
                    class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300">Quitar</button>
        </div>
    </div>

    <input type="hidden" name="{{ $name }}" :value="dataUrl">

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4"
             @click.self="closeModal()"
             @keydown.escape.window="open && closeModal()">
            <div class="w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-white">{{ $label }}</p>
                    <button type="button" @click="closeModal()" class="text-slate-400 hover:text-white text-xl leading-none" aria-label="Cerrar">&times;</button>
                </div>

                <div class="relative mx-auto aspect-square w-full overflow-hidden rounded-xl bg-black">
                    <video x-ref="live" x-show="!preview" autoplay playsinline muted class="h-full w-full object-cover"></video>
                    <img x-show="preview" :src="preview" alt="Vista previa" class="h-full w-full object-cover">
                    <p x-show="error" x-text="error" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-red-300"></p>
                </div>

                <div class="flex justify-center gap-2">
                    <template x-if="!preview">
                        <button type="button" @click="capture()" :disabled="!!error"
                                class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold disabled:opacity-40">Tomar foto</button>
                    </template>
                    <template x-if="preview">
                        <div class="flex gap-2">
                            <button type="button" @click="retake()" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Repetir</button>
                            <button type="button" @click="accept()" class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Usar foto</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@once
@push('scripts')
<script>
function accessCam(initial, facing) {
    return {
        facing: facing || 'user',
        open: false,
        stream: null,
        preview: '',
        error: '',
        dataUrl: initial || '',
        async openModal() {
            this.open = true;
            this.preview = '';
            await this.startStream();
        },
        async startStream() {
            this.error = '';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: this.facing, width: { ideal: 720 }, height: { ideal: 720 } }, audio: false });
                await this.$nextTick();
                this.$refs.live.srcObject = this.stream;
            } catch (e) {
                this.error = 'No se pudo abrir la cámara. Revisa el permiso del navegador.';
            }
        },
        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            if (this.$refs.live) this.$refs.live.srcObject = null;
        },
        capture() {
            const v = this.$refs.live;
            if (!v || !v.videoWidth) return;
            const side = Math.min(v.videoWidth, v.videoHeight);
            const sx = (v.videoWidth - side) / 2;
            const sy = (v.videoHeight - side) / 2;
            const c = document.createElement('canvas');
            c.width = 600;
            c.height = 600;
            c.getContext('2d').drawImage(v, sx, sy, side, side, 0, 0, 600, 600);
            this.preview = c.toDataURL('image/jpeg', 0.85);
            this.stopStream();
        },
        async retake() {
            this.preview = '';
            await this.startStream();
        },
        accept() {
            this.dataUrl = this.preview;
            this.closeModal();
        },
        closeModal() {
            this.stopStream();
            this.preview = '';
            this.open = false;
        },
    };
}
</script>
@endpush
@endonce

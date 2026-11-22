{{--
    Zoomable image viewer: mouse wheel / +- buttons to zoom, drag to pan, double-click to toggle zoom,
    rotate 90°, reset, full screen (Esc to leave), open the original in a new tab.

    @param string $src
    @param string $alt
    @param string|null $heightClass  e.g. 'h-80' (default) or 'h-[70vh]'
--}}
@php($heightClass ??= 'h-80')

<div
    x-data="{
        scale: 1,
        rotation: 0,
        x: 0,
        y: 0,
        dragging: false,
        startX: 0,
        startY: 0,
        min: 1,
        max: 6,
        fullscreen: false,
        // Browsers without the Fullscreen API (e.g. iPhone Safari) get a fixed overlay instead.
        overlay: false,
        toggleFullscreen() {
            if (this.fullscreen) {
                document.fullscreenElement ? document.exitFullscreen() : this.leaveOverlay();

                return;
            }

            if (this.$el.requestFullscreen) {
                this.$el.requestFullscreen().catch(() => this.enterOverlay());
            } else {
                this.enterOverlay();
            }
        },
        enterOverlay() {
            this.overlay = true;
            this.fullscreen = true;
            document.body.style.overflow = 'hidden';
        },
        leaveOverlay() {
            this.overlay = false;
            this.fullscreen = false;
            document.body.style.overflow = '';
            this.reset();
        },
        syncFullscreen() {
            if (this.overlay) return;
            this.fullscreen = document.fullscreenElement === this.$el;
            if (! this.fullscreen) this.reset();
        },
        zoom(by) {
            this.scale = Math.min(this.max, Math.max(this.min, +(this.scale * by).toFixed(2)));
            if (this.scale === 1) { this.x = 0; this.y = 0; }
        },
        wheel(event) {
            this.zoom(event.deltaY < 0 ? 1.15 : 1 / 1.15);
        },
        toggle() {
            this.scale > 1 ? this.reset() : (this.scale = 2.5);
        },
        rotate() {
            this.rotation = (this.rotation + 90) % 360;
        },
        reset() {
            this.scale = 1; this.rotation = 0; this.x = 0; this.y = 0;
        },
        start(event) {
            if (this.scale === 1) return;
            this.dragging = true;
            this.startX = event.clientX - this.x;
            this.startY = event.clientY - this.y;
        },
        move(event) {
            if (! this.dragging) return;
            this.x = event.clientX - this.startX;
            this.y = event.clientY - this.startY;
        },
    }"
    @fullscreenchange.document="syncFullscreen()"
    @keydown.escape.window="overlay && leaveOverlay()"
    class="group relative overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10 {{ $heightClass }}"
    :style="fullscreen
        ? `height: 100vh; width: 100vw; border-radius: 0; background: #030712;${overlay ? ' position: fixed; inset: 0; z-index: 9999;' : ''}`
        : ''"
>
    <div
        class="flex h-full w-full touch-none select-none items-center justify-center"
        :class="scale > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
        @wheel.prevent="wheel($event)"
        @dblclick="toggle()"
        @pointerdown="start($event)"
        @pointermove.window="move($event)"
        @pointerup.window="dragging = false"
    >
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            draggable="false"
            loading="lazy"
            class="max-h-full max-w-full object-contain"
            :class="dragging ? '' : 'transition-transform duration-150'"
            :style="`transform: translate(${x}px, ${y}px) scale(${scale}) rotate(${rotation}deg)`"
        />
    </div>

    <div class="absolute bottom-2 right-2 flex items-center gap-0.5 rounded-lg bg-gray-950/70 p-0.5 text-white shadow-sm backdrop-blur">
        <button type="button" @click="zoom(1 / 1.25)" class="rounded-md p-1.5 hover:bg-white/15" title="Zoom out">
            <x-heroicon-m-minus class="h-4 w-4" />
        </button>
        <span class="w-11 text-center text-xs tabular-nums" x-text="`${Math.round(scale * 100)}%`"></span>
        <button type="button" @click="zoom(1.25)" class="rounded-md p-1.5 hover:bg-white/15" title="Zoom in">
            <x-heroicon-m-plus class="h-4 w-4" />
        </button>
        <span class="mx-0.5 h-4 w-px bg-white/25"></span>
        <button type="button" @click="rotate()" class="rounded-md p-1.5 hover:bg-white/15" title="Rotate">
            <x-heroicon-m-arrow-path class="h-4 w-4" />
        </button>
        <button type="button" @click="reset()" class="rounded-md p-1.5 hover:bg-white/15" title="Reset zoom and rotation">
            <x-heroicon-m-viewfinder-circle class="h-4 w-4" />
        </button>
        <button type="button" @click="toggleFullscreen()" class="rounded-md p-1.5 hover:bg-white/15" :title="fullscreen ? 'Exit full screen (Esc)' : 'Full screen'">
            <x-heroicon-m-arrows-pointing-out x-show="! fullscreen" class="h-4 w-4" />
            <x-heroicon-m-arrows-pointing-in x-show="fullscreen" x-cloak class="h-4 w-4" />
        </button>
        <a href="{{ $src }}" target="_blank" rel="noopener" class="rounded-md p-1.5 hover:bg-white/15" title="Open full size in a new tab">
            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" />
        </a>
    </div>

    <p class="pointer-events-none absolute left-2 top-2 rounded-md bg-gray-950/60 px-2 py-1 text-[11px] text-white opacity-0 transition group-hover:opacity-100">
        <span x-text="fullscreen ? 'Scroll to zoom · drag to move · Esc to exit' : 'Scroll to zoom · drag to move · double-click to toggle'"></span>
    </p>
</div>

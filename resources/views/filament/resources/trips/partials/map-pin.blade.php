{{--
    Map pin used for the trip map markers (rendered to a string and handed to the JS), the legend and the journey list.
    $icon: 'person' | 'package' | 'flag'; $color: pin fill; $size: width in px (height keeps the 32:42 pin ratio).
--}}
@php($size = $size ?? 32)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42" width="{{ $size }}" height="{{ round($size * 42 / 32) }}" style="display:block;filter:drop-shadow(0 1px 2px rgba(0,0,0,.4))" aria-hidden="true">
    <path d="M16 1C7.7 1 1 7.6 1 15.8 1 26.9 16 41 16 41s15-14.1 15-25.2C31 7.6 24.3 1 16 1z" fill="{{ $color }}" stroke="#fff" stroke-width="2" />
    @switch($icon)
        @case('person')
            <circle cx="16" cy="11.5" r="3.6" fill="#fff" />
            <path d="M9.5 22.5c0-3.7 2.9-6.3 6.5-6.3s6.5 2.6 6.5 6.3z" fill="#fff" />
            @break
        @case('package')
            <path d="M9.5 11.5 16 8.3l6.5 3.2v7.8L16 22.5l-6.5-3.2z" fill="#fff" />
            <path d="M9.5 11.5 16 14.7l6.5-3.2M16 14.7v7.8" fill="none" stroke="{{ $color }}" stroke-width="1.3" stroke-linejoin="round" />
            @break
        @case('flag')
            <rect x="10.5" y="7" width="1.8" height="16" rx=".9" fill="#fff" />
            <path d="M12.3 7.6h9.4l-2.4 3.6 2.4 3.6h-9.4z" fill="#fff" />
            @break
    @endswitch
</svg>

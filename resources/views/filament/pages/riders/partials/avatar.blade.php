@php
    $initials = $user->initials() !== '' ? $user->initials() : '?';
    $palette = ['bg-amber-500', 'bg-emerald-500', 'bg-sky-500', 'bg-violet-500', 'bg-rose-500', 'bg-teal-500'];
    $color = $palette[$user->id % count($palette)];
    $dimension = ($size ?? 'md') === 'lg' ? 'h-16 w-16 text-xl' : 'h-10 w-10 text-sm';
@endphp

@if($user->avatar_url)
    <img
        src="{{ $user->avatar_url }}"
        alt="{{ $user->name !== '' ? $user->name : 'Rider avatar' }}"
        class="{{ $dimension }} shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
    />
@else
    <div class="{{ $dimension }} {{ $color }} shrink-0 rounded-full flex items-center justify-center font-semibold text-white ring-2 ring-white dark:ring-gray-800">
        {{ $initials }}
    </div>
@endif

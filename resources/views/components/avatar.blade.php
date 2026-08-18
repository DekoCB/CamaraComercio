@props(['user', 'size' => 32])
@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => 'avatar avatar-photo']) }}
         style="width: {{ $size }}px; height: {{ $size }}px;">
@else
    <span {{ $attributes->merge(['class' => 'avatar']) }} style="width: {{ $size }}px; height: {{ $size }}px;">{{ $user->initials() }}</span>
@endif

{{--
    Placeholder institutional mark — no official Cámara de Comercio logo
    exists in this repository (checked in docs/DESIGN_SYSTEM.md §3).
    Replace this component's contents with the real logo when available;
    every place the brand appears (sidebar, login) reuses this one file.
--}}
@props(['size' => 36])
<div class="brand-mark" style="width: {{ $size }}px; height: {{ $size }}px;">
    {{ icon('building-2', 'icon', (int) round($size * 0.55)) }}
</div>

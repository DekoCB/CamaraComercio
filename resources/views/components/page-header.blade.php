@props(['title', 'subtitle' => null])
<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="d-flex gap-2 flex-wrap">{{ $actions }}</div>
    @endisset
</div>

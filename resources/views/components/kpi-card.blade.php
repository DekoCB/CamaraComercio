@props([
    'label',
    'value',
    'icon',
    'variant' => 'blue',
    'trend' => null,
    'trendContext' => 'vs. mes anterior',
    'critical' => false,
    'footnote' => null,
    'href' => null,
    'active' => false,
    'hint' => null,
])
@php
    // With an href the card doubles as a quick filter: it links to the
    // listing filtered by what it counts, and highlights when applied.
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" title="{{ $hint ?? 'Filtrar la tabla' }}" @endif
    class="kpi-card {{ $critical ? 'is-critical' : '' }} {{ $href ? 'is-link' : '' }} {{ $active ? 'is-active' : '' }}">
    <div class="kpi-card-top">
        <span class="kpi-label">{{ $label }}</span>
        <span class="kpi-icon icon-{{ $variant }}">{{ icon($icon, 'icon', 18) }}</span>
    </div>
    <div class="kpi-value" data-label="{{ $label }}">{{ $value }}</div>
    @if ($trend)
        <span class="kpi-trend {{ $trend['direction'] === 'up' ? 'is-up' : 'is-down' }}">
            {{ icon($trend['direction'] === 'up' ? 'arrow-up-right' : 'arrow-down-right', 'icon', 14) }}
            {{ number_format($trend['percent'], 1) }}%
            <span class="kpi-trend-context">{{ $trendContext }}</span>
        </span>
    @elseif ($footnote)
        <span class="kpi-trend-context" style="font-size: var(--text-xs); color: var(--color-text-tertiary);">{{ $footnote }}</span>
    @endif
    @if ($active)
        <span class="kpi-active-mark">{{ icon('check', 'icon', 12) }} Filtro aplicado</span>
    @endif
</{{ $tag }}>

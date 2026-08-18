@props([
    'label',
    'value',
    'icon',
    'variant' => 'blue',
    'trend' => null,
    'trendContext' => 'vs. mes anterior',
    'critical' => false,
    'footnote' => null,
])
<div class="kpi-card {{ $critical ? 'is-critical' : '' }}">
    <div class="kpi-card-top">
        <span class="kpi-label">{{ $label }}</span>
        <span class="kpi-icon icon-{{ $variant }}">{{ icon($icon, 'icon', 18) }}</span>
    </div>
    <div class="kpi-value">{{ $value }}</div>
    @if ($trend)
        <span class="kpi-trend {{ $trend['direction'] === 'up' ? 'is-up' : 'is-down' }}">
            {{ icon($trend['direction'] === 'up' ? 'arrow-up-right' : 'arrow-down-right', 'icon', 14) }}
            {{ number_format($trend['percent'], 1) }}%
            <span class="kpi-trend-context">{{ $trendContext }}</span>
        </span>
    @elseif ($footnote)
        <span class="kpi-trend-context" style="font-size: var(--text-xs); color: var(--color-text-tertiary);">{{ $footnote }}</span>
    @endif
</div>

@php
    $classes = [
        \App\Models\Associate::STATUS_ACTIVO => 'badge-success',
        \App\Models\Associate::STATUS_SUSPENDIDO => 'badge-warning',
        \App\Models\Associate::STATUS_DESAFILIADO => 'badge-neutral',
    ];
@endphp
<span class="badge {{ $classes[$status] ?? 'badge-neutral' }}">{{ ucfirst(strtolower($status ?? '')) }}</span>

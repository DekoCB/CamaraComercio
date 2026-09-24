@extends('layouts.app')

@section('title', 'Calendario de alquileres')

@php
    use App\Models\Rental;
    use Carbon\CarbonImmutable;

    $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $statusDot = [
        Rental::STATUS_COTIZADA => 'is-cotizada',
        Rental::STATUS_CONFIRMADA => 'is-confirmada',
        Rental::STATUS_FACTURADA => 'is-facturada',
    ];

    // Same Monday-first month grid as associates.birthdays.
    $gridStart = $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
    $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
    $weeks = [];
    for ($cursor = $gridStart; $cursor->lessThanOrEqualTo($gridEnd); $cursor = $cursor->addWeek()) {
        $weeks[] = collect(range(0, 6))->map(fn (int $i) => $cursor->addDays($i));
    }
@endphp

@section('content')
    <x-page-header title="Calendario de alquileres" subtitle="Reservas de espacios por día. Solo se muestran las que siguen activas.">
        <x-slot:actions>
            <a href="{{ route('rentals.index') }}" class="btn btn-secondary btn-sm">{{ icon('list-filter', 'icon', 16) }} Ver como lista</a>
            @can('rentals.manage')
                <a href="{{ route('rentals.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nueva cotización de alquiler">{{ icon('plus', 'icon', 16) }} Nueva cotización</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-surface calendar-card">
                <div class="calendar-header">
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('rentals.calendar', ['month' => $prevMonth]) }}" class="btn btn-ghost btn-icon" title="Mes anterior" aria-label="Mes anterior">{{ icon('chevron-left', 'icon', 16) }}</a>
                        <h3 class="calendar-title">{{ ucfirst($months[$month->month]) }} {{ $month->year }}</h3>
                        <a href="{{ route('rentals.calendar', ['month' => $nextMonth]) }}" class="btn btn-ghost btn-icon" title="Mes siguiente" aria-label="Mes siguiente">{{ icon('chevron-right', 'icon', 16) }}</a>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="calendar-legend">
                            <span class="calendar-legend-dot is-cotizada"></span> Cotizada
                            <span class="calendar-legend-dot is-confirmada"></span> Confirmada
                            <span class="calendar-legend-dot is-facturada"></span> Facturada
                        </span>
                        @if (! $month->isSameMonth($today))
                            <a href="{{ route('rentals.calendar') }}" class="btn btn-secondary btn-sm">Hoy</a>
                        @endif
                    </div>
                </div>

                <div class="calendar-grid" role="grid" aria-label="Calendario de {{ $months[$month->month] }} {{ $month->year }}">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $name)
                        <div class="calendar-weekday" role="columnheader">{{ $name }}</div>
                    @endforeach

                    @foreach ($weeks as $week)
                        @foreach ($week as $day)
                            @php
                                $dayRentals = $monthRentals->get($day->toDateString(), collect());
                                $inMonth = $day->isSameMonth($month);
                            @endphp
                            <div class="calendar-day {{ $inMonth ? '' : 'is-outside' }} {{ $day->isToday() ? 'is-today' : '' }} {{ $dayRentals->isNotEmpty() ? 'has-events' : '' }}" role="gridcell">
                                <div class="calendar-day-number">{{ $day->day }}</div>
                                @if ($inMonth)
                                    @foreach ($dayRentals as $rental)
                                        <a href="{{ route('rentals.show', $rental) }}"
                                           class="calendar-event {{ $statusDot[$rental->status] ?? '' }}"
                                           title="{{ $rental->space->name }} — {{ $rental->associate->name }} ({{ $rental->statusLabel() }}), {{ $rental->starts_at->format('H:i') }}–{{ $rental->ends_at->format('H:i') }}">
                                            <span class="calendar-event-name">{{ $rental->space->name }}</span>
                                            <span class="calendar-event-kind">{{ $rental->starts_at->format('H:i') }} · {{ $rental->associate->name }}</span>
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-surface">
                <div class="d-flex align-items-center gap-2 mb-2">
                    {{ icon('calendar', 'icon', 18) }}
                    <h3 class="form-section-title" style="padding: 0; margin: 0;">Hoy</h3>
                </div>
                @if ($todayRentals->isEmpty())
                    <p class="cell-muted" style="margin: 0; font-size: 0.875rem;">No hay alquileres reservados para hoy.</p>
                @else
                    <ul class="birthday-list">
                        @foreach ($todayRentals as $rental)
                            <li>
                                <a href="{{ route('rentals.show', $rental) }}" class="link-plain">
                                    <strong>{{ $rental->space->name }}</strong> — {{ $rental->associate->name }}
                                </a>
                                <div class="cell-muted" style="font-size: var(--text-xs);">
                                    {{ $rental->starts_at->format('H:i') }}–{{ $rental->ends_at->format('H:i') }} · <x-status-badge :status="$rental->status" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

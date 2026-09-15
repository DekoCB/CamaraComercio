@extends('layouts.app')

@section('title', 'Cumpleaños de socios')

@php
    use App\Services\BirthdayService;
    use Carbon\CarbonImmutable;

    $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $dayLabel = fn ($d) => $weekdays[$d->dayOfWeek].' '.$d->day.' de '.$months[$d->month];
    $kindClass = [
        BirthdayService::KIND_LEGAL_REP => 'badge-info',
        BirthdayService::KIND_CCH_REP => 'badge-neutral',
        BirthdayService::KIND_ANNIVERSARY => 'badge-success',
    ];
    $kindShort = [
        BirthdayService::KIND_LEGAL_REP => 'Rep. legal',
        BirthdayService::KIND_CCH_REP => 'Rep. CCH',
        BirthdayService::KIND_ANNIVERSARY => 'Aniversario',
    ];
    $yearsLabel = fn (array $e) => $e['kind'] === BirthdayService::KIND_ANNIVERSARY
        ? $e['years'].' años de la empresa'
        : 'cumple '.$e['years'].' años';

    // Month grid, Monday-first: pad with the tail of the previous month
    // and the head of the next so every row has seven cells.
    $gridStart = $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
    $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
    $weeks = [];
    for ($cursor = $gridStart; $cursor->lessThanOrEqualTo($gridEnd); $cursor = $cursor->addWeek()) {
        $weeks[] = collect(range(0, 6))->map(fn (int $i) => $cursor->addDays($i));
    }
@endphp

@section('content')
    <x-page-header title="Gestión de asociados" subtitle="Cumpleaños de los representantes y aniversarios de las empresas asociadas. Cada día el sistema publica en las notificaciones los que corresponden." />

    @include('associates._tabs', ['active' => 'cumpleanos'])

    <div class="row g-3">
        {{-- Calendario mensual --}}
        <div class="col-lg-8">
            <div class="card-surface calendar-card">
                <div class="calendar-header">
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('associates.birthdays', ['month' => $prevMonth]) }}" class="btn btn-ghost btn-icon" title="Mes anterior" aria-label="Mes anterior">{{ icon('chevron-left', 'icon', 16) }}</a>
                        <h3 class="calendar-title">{{ ucfirst($months[$month->month]) }} {{ $month->year }}</h3>
                        <a href="{{ route('associates.birthdays', ['month' => $nextMonth]) }}" class="btn btn-ghost btn-icon" title="Mes siguiente" aria-label="Mes siguiente">{{ icon('chevron-right', 'icon', 16) }}</a>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="calendar-legend">
                            <span class="calendar-legend-dot is-legal"></span> Rep. legal
                            <span class="calendar-legend-dot is-cch"></span> Rep. CCH
                            <span class="calendar-legend-dot is-anniversary"></span> Aniversario
                        </span>
                        @if (! $month->isSameMonth($today))
                            <a href="{{ route('associates.birthdays') }}" class="btn btn-secondary btn-sm">Hoy</a>
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
                                $events = $monthEvents->get($day->toDateString(), collect());
                                $inMonth = $day->isSameMonth($month);
                            @endphp
                            <div class="calendar-day {{ $inMonth ? '' : 'is-outside' }} {{ $day->isToday() ? 'is-today' : '' }} {{ $events->isNotEmpty() ? 'has-events' : '' }}" role="gridcell">
                                <div class="calendar-day-number">{{ $day->day }}</div>
                                @if ($inMonth)
                                    @foreach ($events as $event)
                                        <a href="{{ route('associates.show', $event['associate']) }}"
                                           class="calendar-event is-{{ str_replace('_rep', '', $event['kind']) }}"
                                           title="{{ $event['person'] }} — {{ $event['kind_label'] }}, {{ $yearsLabel($event) }}{{ $event['kind'] !== BirthdayService::KIND_ANNIVERSARY ? ' ('.$event['associate']->name.')' : '' }}">
                                            <span class="calendar-event-name">{{ $event['person'] }}</span>
                                            <span class="calendar-event-kind">{{ $kindShort[$event['kind']] }} · {{ $event['years'] }} años</span>
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Hoy + próximos --}}
        <div class="col-lg-4">
            <div class="card-surface mb-3 birthday-today {{ $todayEvents->isEmpty() ? '' : 'has-events' }}">
                <div class="d-flex align-items-center gap-2 mb-2">
                    {{ icon('cake', 'icon', 18) }}
                    <h3 class="form-section-title" style="padding: 0; margin: 0;">Hoy, {{ $dayLabel($today) }}</h3>
                </div>
                @if ($todayEvents->isEmpty())
                    <p class="cell-muted" style="margin: 0; font-size: 0.875rem;">No hay cumpleaños ni aniversarios hoy.</p>
                @else
                    <ul class="birthday-list">
                        @foreach ($todayEvents as $event)
                            @include('associates._birthday_item', ['event' => $event, 'kindClass' => $kindClass, 'yearsLabel' => $yearsLabel])
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card-surface">
                <h3 class="form-section-title" style="padding: 0;">Próximos 30 días</h3>
                @if ($upcoming->isEmpty())
                    <p class="cell-muted" style="margin: 0; font-size: 0.875rem;">Ningún asociado tiene cumpleaños o aniversario en los próximos 30 días.</p>
                @else
                    @foreach ($upcoming as $date => $events)
                        @php $d = CarbonImmutable::parse($date); @endphp
                        <div class="birthday-day">
                            <div class="birthday-day-label">
                                {{ ucfirst($dayLabel($d)) }}
                                @if ($d->isTomorrow())
                                    <span class="badge badge-warning">Mañana</span>
                                @else
                                    <span class="cell-muted">en {{ $today->diffInDays($d) }} días</span>
                                @endif
                            </div>
                            <ul class="birthday-list">
                                @foreach ($events as $event)
                                    @include('associates._birthday_item', ['event' => $event, 'kindClass' => $kindClass, 'yearsLabel' => $yearsLabel])
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
@endsection

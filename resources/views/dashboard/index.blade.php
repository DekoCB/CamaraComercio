@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Asociados registrados</div>
                <div class="kpi-value">{{ $totalAssociates }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Facturado del período</div>
                <div class="kpi-value">{{ format_money($billedThisPeriod) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Cobrado del mes</div>
                <div class="kpi-value">{{ format_money($collectedThisMonth) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Deuda pendiente</div>
                <div class="kpi-value">{{ format_money($pendingBalance) }}</div>
                @if ($overdueCount > 0)
                    <div class="small text-danger">{{ $overdueCount }} factura(s) vencida(s)</div>
                @endif
            </div>
        </div>
    </div>

    <div class="table-card">
        <h2 class="h6 mb-3">Accesos rápidos</h2>
        <div class="d-flex flex-wrap gap-2">
            @module('associates')
                @can('associates.manage')
                    <a href="{{ route('associates.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-plus"></i> Registrar asociado
                    </a>
                @endcan
                <a href="{{ route('associates.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people"></i> Ver asociados
                </a>
            @endmodule
            @module('billing')
                @can('billing.generate')
                    <a href="{{ route('invoices.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-receipt"></i> Generar facturación
                    </a>
                @endcan
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-receipt"></i> Ver facturas
                </a>
            @endmodule
            @module('payments')
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-cash-coin"></i> Ver pagos
                </a>
            @endmodule
        </div>
    </div>
@endsection

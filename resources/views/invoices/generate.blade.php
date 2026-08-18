@extends('layouts.app')

@section('title', 'Generar facturación del mes')

@section('content')
    <x-page-header title="Generar facturación del mes"
        subtitle="Se creará una factura para cada asociado activo que aún no tenga una en el período indicado." />

    <div class="card-surface" style="max-width: 640px">
        @include('invoices._generate')
    </div>
@endsection

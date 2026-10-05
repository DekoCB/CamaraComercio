@extends('layouts.app')

@section('title', 'Registrar salida')

@section('content')
    <x-page-header title="Registrar salida" :subtitle="$session->plate">
        <x-slot:actions>
            <a href="{{ route('parking.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('parking._checkout-form', ['session' => $session])
    </div>
@endsection

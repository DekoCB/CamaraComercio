@extends('layouts.app')

@section('title', 'Registrar pago')

@section('content')
    <x-page-header title="Registrar pago">
        <x-slot:actions>
            <a href="{{ route('payments.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('payments._form')
    </div>
@endsection

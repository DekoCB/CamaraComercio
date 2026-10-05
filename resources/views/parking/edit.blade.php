@extends('layouts.app')

@section('title', 'Editar registro de estacionamiento')

@section('content')
    <x-page-header title="Editar registro" :subtitle="$session->plate">
        <x-slot:actions>
            <a href="{{ route('parking.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 680px">
        @include('parking._form', ['session' => $session])
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Nuevo trámite — Placas')

@section('content')
    <x-page-header title="Nuevo trámite de placa">
        <x-slot:actions>
            <a href="{{ route('plates.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 760px">
        @include('plates._form')
    </div>
@endsection

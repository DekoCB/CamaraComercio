@extends('layouts.app')

@section('title', 'Editar alquiler — '.$rental->space->name)

@section('content')
    <x-page-header title="Editar alquiler" :subtitle="$rental->space->name.' · '.$rental->associate->name">
        <x-slot:actions>
            <a href="{{ route('rentals.show', $rental) }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 720px">
        @include('rentals._form')
    </div>
@endsection

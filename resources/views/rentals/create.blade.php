@extends('layouts.app')

@section('title', 'Nueva cotización de alquiler')

@section('content')
    <x-page-header title="Nueva cotización de alquiler">
        <x-slot:actions>
            <a href="{{ route('rentals.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 720px">
        @include('rentals._form')
    </div>
@endsection

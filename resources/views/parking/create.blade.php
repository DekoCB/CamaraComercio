@extends('layouts.app')

@section('title', 'Registrar entrada')

@section('content')
    <x-page-header title="Registrar entrada">
        <x-slot:actions>
            <a href="{{ route('parking.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 680px">
        @include('parking._form')
    </div>
@endsection

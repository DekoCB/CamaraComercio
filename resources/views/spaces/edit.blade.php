@extends('layouts.app')

@section('title', 'Editar espacio — '.$space->name)

@section('content')
    <x-page-header :title="'Editar espacio — '.$space->name">
        <x-slot:actions>
            <a href="{{ route('spaces.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('spaces._form')
    </div>
@endsection

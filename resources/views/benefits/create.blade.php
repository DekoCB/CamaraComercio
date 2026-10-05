@extends('layouts.app')

@section('title', 'Nuevo beneficio')

@section('content')
    <x-page-header title="Nuevo beneficio">
        <x-slot:actions>
            <a href="{{ route('benefits.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('benefits._form')
    </div>
@endsection

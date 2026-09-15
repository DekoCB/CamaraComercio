@extends('layouts.app')

@section('title', $associate ? 'Editar asociado' : 'Registrar asociado')

@section('content')
    <x-page-header :title="$associate ? 'Editar asociado' : 'Registrar asociado'"
                   :subtitle="$associate ? $associate->name : 'Completa la ficha del nuevo asociado. Solo la razón social es obligatoria; el resto puede completarse después.'">
        <x-slot:actions>
            <a href="{{ $associate ? route('associates.show', $associate) : route('associates.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface">
        @include('associates._form')
    </div>
@endsection

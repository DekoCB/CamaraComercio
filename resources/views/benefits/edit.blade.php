@extends('layouts.app')

@section('title', 'Editar beneficio')

@section('content')
    <x-page-header title="Editar beneficio" :subtitle="$benefit->name">
        <x-slot:actions>
            <a href="{{ route('benefits.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('benefits._form', ['benefit' => $benefit])
    </div>
@endsection

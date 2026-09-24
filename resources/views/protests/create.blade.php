@extends('layouts.app')

@section('title', 'Nuevo registro — Protestos y Moras')

@section('content')
    <x-page-header title="Nuevo registro de protesto o mora">
        <x-slot:actions>
            <a href="{{ route('protests.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 760px">
        @include('protests._form')
    </div>
@endsection

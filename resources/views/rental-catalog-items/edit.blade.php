@extends('layouts.app')

@section('title', 'Editar ítem')

@section('content')
    <x-page-header title="Editar ítem" :subtitle="$item->name">
        <x-slot:actions>
            <a href="{{ route('rental-catalog-items.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        @include('rental-catalog-items._form', ['item' => $item])
    </div>
@endsection

@extends('layouts.app')

@section('title', $associate ? 'Editar asociado' : 'Registrar asociado')

@section('content')
    <x-page-header :title="$associate ? 'Editar asociado' : 'Registrar asociado'" />

    <div class="card-surface" style="max-width: 560px">
        @include('associates._form')
    </div>
@endsection

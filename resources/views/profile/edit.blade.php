@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <x-page-header title="Mi perfil" subtitle="Actualiza tu foto, nombre, correo y contraseña." />

    <div class="card-surface" style="max-width: 560px">
        @include('profile._form')
    </div>
@endsection

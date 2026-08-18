@extends('layouts.app')

@section('title', $user ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    <x-page-header :title="$user ? 'Editar usuario' : 'Nuevo usuario'" />

    <div class="card-surface" style="max-width: 560px">
        @include('admin.users._form')
    </div>
@endsection

@extends('layouts.app')

@section('title', $role ? 'Editar rol' : 'Nuevo rol')

@section('content')
    <x-page-header :title="$role ? 'Editar rol' : 'Nuevo rol'" />

    <div class="card-surface" style="max-width: 560px">
        @include('admin.roles._form')
    </div>
@endsection

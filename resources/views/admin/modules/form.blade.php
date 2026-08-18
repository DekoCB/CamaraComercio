@extends('layouts.app')

@section('title', 'Nuevo módulo')

@section('content')
    <x-page-header title="Nuevo módulo" />

    <div class="card-surface" style="max-width: 560px">
        @include('admin.modules._form')
    </div>
@endsection

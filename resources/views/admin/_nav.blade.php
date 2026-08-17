@php $current = request()->route()->getName(); @endphp
<div class="btn-group btn-group-sm" role="group">
    <a href="{{ route('admin.users.index') }}" class="btn {{ str_starts_with($current, 'admin.users') ? 'btn-primary' : 'btn-outline-primary' }}">Usuarios</a>
    <a href="{{ route('admin.roles.index') }}" class="btn {{ str_starts_with($current, 'admin.roles') ? 'btn-primary' : 'btn-outline-primary' }}">Roles</a>
    <a href="{{ route('admin.modules.index') }}" class="btn {{ str_starts_with($current, 'admin.modules') ? 'btn-primary' : 'btn-outline-primary' }}">Módulos</a>
</div>

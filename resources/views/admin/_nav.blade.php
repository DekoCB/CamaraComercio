@php $current = request()->route()->getName(); @endphp
<div class="tabs">
    <a href="{{ route('admin.users.index') }}" class="tab-link {{ str_starts_with($current, 'admin.users') ? 'is-active' : '' }}">
        {{ icon('users', 'icon', 15) }} Usuarios
    </a>
    <a href="{{ route('admin.roles.index') }}" class="tab-link {{ str_starts_with($current, 'admin.roles') ? 'is-active' : '' }}">
        {{ icon('shield', 'icon', 15) }} Roles
    </a>
    <a href="{{ route('admin.modules.index') }}" class="tab-link {{ str_starts_with($current, 'admin.modules') ? 'is-active' : '' }}">
        {{ icon('grid-3x3', 'icon', 15) }} Módulos
    </a>
</div>

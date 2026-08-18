<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('role')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = [
            'user' => null,
            'roles' => Role::orderBy('name')->get(),
            'action' => route('admin.users.store'),
            'method' => 'POST',
        ];

        return $request->ajax() ? view('admin.users._form', $data) : view('admin.users.form', $data);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        $user = User::create($data);

        AuditLog::record('user.create', 'user', (string) $user->id);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Request $request, User $user): View
    {
        $data = [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'action' => route('admin.users.update', $user),
            'method' => 'PUT',
        ];

        return $request->ajax() ? view('admin.users._form', $data) : view('admin.users.form', $data);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $user->update($data);

        AuditLog::record('user.update', 'user', (string) $user->id);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado correctamente.');
    }
}

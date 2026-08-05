<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\SetUserActiveAction;
use App\Actions\UpdateUserAction;
use App\Enums\UserRole;
use App\Http\Requests\Usuarios\StoreUserRequest;
use App\Http\Requests\Usuarios\UpdateUserRequest;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.usuarios.index', [
            'usuarios' => User::query()
                ->with('roles')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.usuarios.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $action->execute(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: UserRole::from($request->validated('role')),
        );

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.usuarios.edit', [
            'usuario' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        Gate::authorize('update', $user);

        $action->execute(
            user: $user,
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: UserRole::from($request->validated('role')),
            password: $request->validated('password'),
        );

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuario actualizado.');
    }

    public function toggleActive(Request $request, User $user, SetUserActiveAction $action): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        try {
            $action->execute($user, active: ! $user->is_active, actor: $request->user());
        } catch (DomainException) {
            return redirect()
                ->route('usuarios.index')
                ->withErrors(['active' => 'El admin no puede desactivarse a sí mismo.']);
        }

        return redirect()
            ->route('usuarios.index')
            ->with('status', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.');
    }
}

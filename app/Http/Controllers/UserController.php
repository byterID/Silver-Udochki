<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // Сотрудники — все, кроме ролей User и Guest
    public function staff(Request $request)
    {
        $users = $this->filteredUsers($request, staff: true)->paginate(20)->withQueryString();
        $roles = Role::all();

        return view('users.staff', compact('users', 'roles'));
    }

    // Покупатели — все с ролью User
    public function customers(Request $request)
    {
        $users = $this->filteredUsers($request, staff: false)->paginate(20)->withQueryString();
        $roles = Role::all();

        return view('users.customers', compact('users', 'roles'));
    }

    private function filteredUsers(Request $request, bool $staff)
    {
        $query = User::query();

        if ($staff) {
            $query->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['user', 'guest']));
        } else {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'user'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'ilike', '%'.$request->input('name').'%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'ilike', '%'.$request->input('email').'%');
        }
        if ($staff && $request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', fn ($q) => $q->where('name', 'ilike', '%'.$role.'%'));
        }

        $sort = in_array($request->input('sort'), ['name', 'email', 'created_at'])
            ? $request->input('sort')
            : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        return $query;
    }

    // Создание нового сотрудника (из модалки)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return back()->with('status', 'Пользователь «'.$user->name.'» создан');
    }

    // Редактирование имени, email и роли (из модалки)
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'role'  => 'required|exists:roles,name',
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('status', 'Данные пользователя обновлены');
    }

    // Удаление с подтверждением через повторный ввод email
    public function destroy(Request $request, User $user)
    {
        $request->validate([
            'email_confirmation' => 'required|string',
        ]);

        if ($request->input('email_confirmation') !== $user->email) {
            return back()->withErrors([
                'email_confirmation' => 'Введённый email не совпадает с email пользователя.',
            ]);
        }

        // Защита: не даём удалить самого себя
        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'email_confirmation' => 'Нельзя удалить собственную учётную запись.',
            ]);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('status', 'Пользователь «'.$name.'» удалён');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // 1. достать всех пользователей из базы
        $users = User::all();
        // 2. достать все роли (для выпадающих списков)
        $roles = Role::all();
        // 3. отдать их в шаблон
        return view('users.index', compact('users', 'roles'));
    }

    public function updateRole(Request $request, User $user)
    {
        // $user придёт автоматически из URL (route model binding)
        // 1. проверить, что пришло валидное имя роли
        $data = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);
        // 2. заменить все роли пользователя на выбранную
        $user->syncRoles([$data['role']]);
        // 3. вернуться назад с сообщением
        return back()->with('status', 'Роль обновлена');
    }
}

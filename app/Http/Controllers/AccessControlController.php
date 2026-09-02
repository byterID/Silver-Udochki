<?php

namespace App\Http\Controllers;

use App\Models\ActionGroup;
use App\Models\Permission;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions:id,name')->orderBy('id')->get();

        // группируем действия: сначала группы по порядку, потом «без группы»
        $groups = ActionGroup::with('permissions:id,name,action_group_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'name'        => $g->name,
                'permissions' => $g->permissions->map->only(['id', 'name'])->values(),
            ]);

        $ungrouped = Permission::whereNull('action_group_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map->only(['id', 'name'])
            ->values();

        if ($ungrouped->isNotEmpty()) {
            $groups->push([
                'name'        => 'Без группы',
                'permissions' => $ungrouped,
            ]);
        }
        $permissionsCount = Permission::count();

        return view('control-panel.access-control', compact('roles', 'groups', 'permissionsCount'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions'   => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $selected = collect($data['permissions'] ?? [])->map(fn ($id) => (int) $id);

        // Защита: у супер-роли нельзя снимать заблокированные права
        if ($role->name === config('access.super_role')) {
            $lockedIds = Permission::whereIn('name', config('access.locked_permissions'))
                ->pluck('id');

            // принудительно возвращаем заблокированные права в набор
            $missing = $lockedIds->diff($selected);

            if ($missing->isNotEmpty()) {
                $selected = $selected->merge($lockedIds)->unique();

                $role->syncPermissions($selected->all());

                return back()->withErrors([
                    'permissions' => 'Нельзя снять критичные права («'
                        . implode('», «', config('access.locked_permissions'))
                        . '») у роли «' . $role->name . '». Они были сохранены.',
                ]);
            }
        }

        $role->syncPermissions($selected->all());

        return back()->with('status', 'Доступ для группы «'.$role->name.'» обновлён');
    }

}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\ControlPanel\UpdateRoleAccessRequest;
use App\Models\ActionGroup;
use App\Models\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->orderBy('id')->get();

        $groups = ActionGroup::with('permissions:id,name,title,action_group_id')
            ->orderBy('name')
            ->get()
            ->map(fn (ActionGroup $g) => [
                'name' => $g->name,
                'permissions' => $g->permissions
                    ->map->only(['id', 'name', 'title'])
                    ->values(),
            ]);

        $ungrouped = Permission::whereNull('action_group_id')
            ->orderBy('name')
            ->get(['id', 'name', 'title'])
            ->map->only(['id', 'name', 'title'])
            ->values();

        if ($ungrouped->isNotEmpty()) {
            $groups->push(['name' => 'Без группы', 'permissions' => $ungrouped]);
        }

        return view('control-panel.access-control', [
            'roles' => $roles,
            'groups' => $groups,
            'permissionsCount' => Permission::count(),
        ]);
    }

    public function update(UpdateRoleAccessRequest $request, Role $role): RedirectResponse
    {
        $roleLevel = RoleName::tryFrom($role->name)?->level() ?? 0;

        $this->authorize('manageRoleAccess', [\App\Models\User::class, $roleLevel]);

        $actor = $request->user();
        $superRole = (string) config('access.super_role');
        $locked = collect(config('access.locked_permissions', []));
        $exclusive = collect(config('access.exclusive_permissions', []));

        $selected = Permission::whereIn('id', $request->validated()['permissions'] ?? [])
            ->pluck('name');

        $warnings = [];

        if ($role->name === $superRole) {
            // Защита «в минус»: критичные права нельзя снять с админа.
            $missing = $locked->diff($selected);

            if ($missing->isNotEmpty()) {
                $selected = $selected->merge($missing);
                $warnings[] = 'Критичные права («'.$missing->implode('», «')
                    .'») нельзя снять с роли «'.$role->name.'» — они сохранены.';
            }
        } else {
            // Защита «в плюс»: эксклюзивные права нельзя выдать никому другому.
            $forbidden = $selected->intersect($exclusive);

            if ($forbidden->isNotEmpty()) {
                $selected = $selected->diff($forbidden);
                $warnings[] = 'Права («'.$forbidden->implode('», «')
                    .'») доступны только роли «'.$superRole.'» — они не выданы.';
            }
        }

        // Нельзя выдать право, которого нет у тебя самого.
        if (! $actor->isSuperAdmin()) {
            $own = $actor->getAllPermissions()->pluck('name');
            $escalation = $selected->diff($own);

            if ($escalation->isNotEmpty()) {
                $selected = $selected->diff($escalation);
                $warnings[] = 'Нельзя выдавать права, которых нет у вас: «'
                    .$escalation->implode('», «').'».';
            }
        }

        $role->syncPermissions($selected->unique()->values()->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $warnings === []
            ? back()->with('status', "Доступ для группы «{$role->name}» обновлён")
            : back()
                ->with('status', "Доступ для группы «{$role->name}» обновлён с ограничениями")
                ->withErrors(['permissions' => implode(' ', $warnings)]);
    }
}

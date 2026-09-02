<?php

namespace App\Http\Controllers;

use App\Models\ActionGroup;
use App\Models\Permission;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::with('actionGroup')->orderBy('id')->get();
        $groups      = ActionGroup::orderBy('name')->get();

        return view('control-panel.permissions', compact('permissions', 'groups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:permissions,name',
            'title'           => 'required|string|max:255',
            'action_group_id' => 'nullable|exists:action_groups,id',
        ]);

        Permission::create([
            'name'            => $data['name'],
            'title'           => $data['title'],
            'action_group_id' => $data['action_group_id'] ?? null,
            'guard_name'      => 'web',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Действие «'.$data['title'].'» создано');
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:permissions,name,'.$permission->id,
            'title'           => 'required|string|max:255',
            'action_group_id' => 'nullable|exists:action_groups,id',
        ]);

        $permission->update([
            'name'            => $data['name'],
            'title'           => $data['title'],
            'action_group_id' => $data['action_group_id'] ?? null,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Действие обновлено');
    }
}

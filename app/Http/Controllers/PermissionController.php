<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ControlPanel\UpdatePermissionRequest;
use App\Models\ActionGroup;
use App\Models\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(): View
    {
        return view('control-panel.permissions', [
            'permissions' => Permission::with('actionGroup')->orderBy('id')->get(),
            'groups' => ActionGroup::orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Действие обновлено');
    }
}

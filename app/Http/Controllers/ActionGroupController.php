<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ControlPanel\StoreActionGroupRequest;
use App\Http\Requests\ControlPanel\UpdateActionGroupRequest;
use App\Models\ActionGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ActionGroupController extends Controller
{
    public function index(): View
    {
        return view('control-panel.action-groups', [
            'groups' => ActionGroup::withCount('permissions')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreActionGroupRequest $request): RedirectResponse
    {
        $group = ActionGroup::create($request->validated());

        return back()->with('status', "Группа «{$group->name}» создана");
    }

    public function update(UpdateActionGroupRequest $request, ActionGroup $actionGroup): RedirectResponse
    {
        $actionGroup->update($request->validated());

        return back()->with('status', 'Название группы обновлено');
    }
}

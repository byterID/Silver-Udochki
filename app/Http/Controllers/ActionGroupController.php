<?php

namespace App\Http\Controllers;

use App\Models\ActionGroup;
use Illuminate\Http\Request;

class ActionGroupController extends Controller
{
    public function index()
    {
        $groups = ActionGroup::withCount('permissions')->orderBy('id')->get();

        return view('control-panel.action-groups', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:action_groups,name',
        ]);

        ActionGroup::create($data);

        return back()->with('status', 'Группа «'.$data['name'].'» создана');
    }

    public function update(Request $request, ActionGroup $actionGroup)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:action_groups,name,'.$actionGroup->id,
        ]);

        $actionGroup->update($data);

        return back()->with('status', 'Название группы обновлено');
    }
}

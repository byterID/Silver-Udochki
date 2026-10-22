<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\ControlPanel\DeleteUserRequest;
use App\Http\Requests\ControlPanel\StoreUserRequest;
use App\Http\Requests\ControlPanel\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private const SORTABLE = ['name', 'email', 'created_at'];

    public function __construct(private readonly UserService $users) {}

    public function staff(Request $request): View
    {
        return $this->listing($request, 'users.staff', RoleName::staffValues());
    }

    public function customers(Request $request): View
    {
        return $this->listing($request, 'users.customers', RoleName::customerValues());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return back()->with('status', "Пользователь «{$user->name}» создан");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated(), $request->user());

        return back()->with('status', "Пользователь «{$user->name}» обновлён");
    }

    public function destroy(DeleteUserRequest $request, User $user): RedirectResponse
    {
        $name = $user->name;

        $this->users->delete($user, $request->user());

        return back()->with('status', "Пользователь «{$name}» удалён");
    }

    /** @param list<string> $roleNames */
    private function listing(Request $request, string $view, array $roleNames): View
    {
        $this->authorize('viewAny', User::class);

        $level = $request->user()->highestRoleLevel();

        return view($view, [
            'users' => $this->filtered($request, $roleNames)->paginate(20)->withQueryString(),
            'roles' => array_values(array_filter(RoleName::cases(), fn (RoleName $role) => $role->isAssignableBy($level))),
        ]);
    }

    /**
     * @param  list<string>  $roleNames
     * @return Builder<User>
     */
    private function filtered(Request $request, array $roleNames): Builder
    {
        $query = User::query()
            ->with('roles')
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $roleNames));

        foreach (['name', 'email'] as $field) {
            $term = SearchTerm::from($request->query($field));
            if ($term !== '') {
                $query->whereLike($field, '%'.$term.'%');
            }
        }

        $role = $request->query('role');
        if (is_string($role) && in_array($role, $roleNames, true)) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
        }

        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';

        return $query->orderBy($sort, $request->query('direction') === 'asc' ? 'asc' : 'desc');
    }
}

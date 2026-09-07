<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\ControlPanel\DeleteUserRequest;
use App\Http\Requests\ControlPanel\StoreUserRequest;
use App\Http\Requests\ControlPanel\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
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
        $this->authorize('viewAny', User::class);

        return view('users.staff', [
            'users' => $this->filtered($request, RoleName::staffValues())
                ->paginate(20)
                ->withQueryString(),
            'roles' => $this->assignableRoles($request),
        ]);
    }

    public function customers(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.customers', [
            'users' => $this->filtered($request, RoleName::customerValues())
                ->paginate(20)
                ->withQueryString(),
            'roles' => $this->assignableRoles($request),
        ]);
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

    /**
     * @return Builder<User>
     */
    private function filtered(Request $request, array $roleNames): Builder
    {
        $query = User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $roleNames));

        if ($request->filled('name')) {
            $query->where('name', 'ilike', '%'.$this->escapeLike($request->string('name')->value()).'%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'ilike', '%'.$this->escapeLike($request->string('email')->value()).'%');
        }

        if ($request->filled('role')) {
            $role = $request->string('role')->value();

            if (in_array($role, $roleNames, true)) {
                $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
            }
        }

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    /**
     * Роли, которые текущий пользователь вправе назначать.
     * Нужно, чтобы в выпадающем списке не было заведомо запрещённых вариантов.
     *
     * @return array<int, RoleName>
     */
    private function assignableRoles(Request $request): array
    {
        $level = $request->user()->highestRoleLevel();
        $strict = (bool) config('access.strict_hierarchy', true);

        return array_values(array_filter(
            RoleName::cases(),
            fn (RoleName $role) => $strict
                ? $role->level() < $level
                : $role->level() <= $level
        ));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}

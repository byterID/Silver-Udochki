@extends('control-panel.layout')

@section('panel-content')
    <h1>Управление пользователями</h1>

    @if (session('status'))
        <p style="color: green;">{{ session('status') }}</p>
    @endif
    <table border="1" cellpadding="8">
        <thead>
        <tr>
            <th>Имя</th>
            <th>Email</th>
            <th>Текущая роль</th>
            <th>Сменить роль</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->getRoleNames()->first() ?? 'нет роли' }}</td>
                <td>
                    <form method="POST" action="/users/{{ $user->id }}/role">
                        @csrf
                        @method('PUT')
                        <select name="role">
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}"
                                    @selected($user->hasRole($role->name))>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit">Сохранить</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection

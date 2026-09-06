<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'app:make-admin {email : Email существующего пользователя}';

    protected $description = 'Выдать пользователю роль администратора';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower($this->argument('email')))->first();

        if ($user === null) {
            $this->error('Пользователь не найден.');

            return self::FAILURE;
        }

        if (! $this->confirm("Выдать роль администратора пользователю {$user->email}?")) {
            return self::SUCCESS;
        }

        $user->syncRoles([RoleName::Admin->value]);

        $this->info("Готово: {$user->email} теперь администратор.");

        return self::SUCCESS;
    }
}

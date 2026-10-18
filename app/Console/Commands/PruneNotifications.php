<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Удаляет уведомления старше срока хранения';

    public function handle(): int
    {
        $threshold = now()->subDays(Config::integer('tasks.notifications_ttl_days'));

        // Одним DELETE: строки маленькие, событий и наблюдателей на них нет
        $deleted = DatabaseNotification::query()->where('created_at', '<', $threshold)->delete();

        if ($deleted > 0) {
            Log::info('Удалены старые уведомления', ['count' => $deleted]);
        }

        $this->info("Удалено уведомлений: {$deleted}");

        return self::SUCCESS;
    }
}

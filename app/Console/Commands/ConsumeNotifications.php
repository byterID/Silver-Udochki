<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tasks\TaskCompletedPublisher;
use App\Services\Tasks\TaskNotifier;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;

final class ConsumeNotifications extends Command
{
    protected $signature = 'tasks:notify';

    protected $description = 'Читает tasks.completed и отправляет пользователям уведомления';

    public function handle(TaskNotifier $notifier): int
    {
        $consumer = Kafka::consumer([TaskCompletedPublisher::TOPIC])
            ->withConsumerGroupId('notifier')
            ->withManualCommit()
            ->withHandler(function (ConsumerMessage $message, MessageConsumer $consumer) use ($notifier): void {
                $notifier->handle($message);
                $consumer->commit($message);
            })
            ->build();

        $this->info('Нотификатор запущен, жду события...');

        $consumer->consume();

        $this->info('Нотификатор завершился корректно');

        return self::SUCCESS;
    }
}

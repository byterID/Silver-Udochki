<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tasks\TaskDispatcher;
use App\Services\Tasks\TaskProcessor;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;

final class ConsumeTasks extends Command
{
    protected $signature = 'tasks:consume';

    protected $description = 'Читает tasks.requested из Kafka и выполняет задачи';

    public function handle(TaskProcessor $processor): int
    {
        $consumer = Kafka::consumer([TaskDispatcher::TOPIC])
            ->withConsumerGroupId('task-workers')
            ->withManualCommit()
            ->withHandler(function (ConsumerMessage $message, MessageConsumer $consumer) use ($processor): void {
                $processor->handle($message);
                $consumer->commit($message);
            })
            ->build();

        $this->info('Воркер запущен, жду сообщения...');

        $consumer->consume(); // бесконечный цикл до SIGTERM

        $this->info('Получен сигнал остановки, воркер завершился корректно');

        return self::SUCCESS;
    }
}

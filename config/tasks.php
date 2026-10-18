<?php

declare(strict_types=1);

return [
    // Сколько раз воркер может взяться за одну задачу
    'max_attempts' => 3,

    // Через сколько минут задача в processing считается зависшей.
    // Должно быть заметно больше самой долгой задачи (у нас максимум 60 сек).
    'stale_after_minutes' => (int) env('TASKS_STALE_AFTER_MINUTES', 10),

    // Сколько секунд даём TaskDispatcher, чтобы опубликовать задачу самому
    'publish_grace_seconds' => 60,

    'pending_timeout_minutes' => (int) env('TASKS_PENDING_TIMEOUT_MINUTES', 60),

    'result_ttl_minutes' => (int) env('TASKS_RESULT_TTL_MINUTES', 5),

    // Уведомления о задачах старше этого удаляет notifications:prune (и прочитанные, и нет)
    'notifications_ttl_days' => (int) env('TASKS_NOTIFICATIONS_TTL_DAYS', 7),
];

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

    'result_ttl_minutes' => (int) env('TASKS_RESULT_TTL_MINUTES', 5),
];

# 📚 Silver Udochki — документация

Подробное описание проекта. Краткий обзор, быстрый старт и схемы — в [README основной ветки](https://github.com/byterID/Silver-Udochki#readme),
термины — в [CONTEXT.md](https://github.com/byterID/Silver-Udochki/blob/main/CONTEXT.md).

## 🛒 Функциональность

| Документ | О чём |
|---|---|
| [Каталог](features/catalog.md) | структура, загрузка данных, дампы |
| [Цены и акции](features/pricing.md) | виды акций, формула расчёта, ограничения |
| [Склад, кошелёк, заказы](features/shop.md) | остатки, баланс, оформление заказа, возвраты |
| [Утиная зорька](features/game.md) | правила мини-игры и проверка результата |
| [Роли и права](features/access.md) | иерархия ролей, защита прав, панель управления |
| [Фоновые задачи](features/background-tasks.md) | типы задач, статусы, уведомления, команды |

## 🏗 Архитектура и процессы

| Документ | О чём |
|---|---|
| [Архитектура](engineering/architecture.md) | сервисы, образы, тома, принципы кода |
| [Kafka](engineering/kafka.md) | топики, гарантии доставки, отладка |
| [Мониторинг](engineering/monitoring.md) | экспортеры и их настройка |
| [CI/CD](engineering/ci-cd.md) | проверки и шаги деплоя |
| [Регламент работы с Git](engineering/workflow.md) | коммиты, ветки, pull request |

---

> Ветка `orphan/docs` изолирована: у неё нет общей истории с `main` и `develop`, в код она не мёржится.

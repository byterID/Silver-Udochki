# 📊 Мониторинг

Prometheus и Grafana работают на отдельной машине и снимают метрики с сервера по Tailscale.
На сервере приложения запущены только экспортеры из `docker-compose.monitoring.yml`.
Алерты настроены, уведомления приходят в Telegram-бот. Схемы и скриншоты — в [README основной ветки](https://github.com/byterID/Silver-Udochki#как-устроен-мониторинг).

## Экспортеры

| Экспортер | Порт | Что показывает |
|---|---|---|
| node-exporter | 9100 | CPU, память, диск, сеть хоста |
| cAdvisor | 8081 | ресурсы каждого контейнера |
| postgres-exporter | 9187 | соединения, транзакции, размер БД |
| nginx-exporter | 9113 | запросы и соединения nginx |

Порты публикуются только на Tailscale-адрес из `MONITORING_BIND_IP`. node-exporter работает в сети и PID-пространстве хоста.
nginx-exporter читает `stub_status` nginx на внутреннем порту `8080`, снаружи он недоступен.

## Включение

Переменные в `.env` на сервере:

| Переменная | Значение |
|---|---|
| `MONITORING_BIND_IP` | Tailscale-адрес сервера (`tailscale ip -4`) |
| `MONITORING_DB_PASSWORD` | пароль пользователя БД `monitoring` (`openssl rand -hex 24`) |
| `COMPOSE_FILE` | `docker-compose.yml:docker-compose.monitoring.yml` |

Без `COMPOSE_FILE` Compose не увидит файл экспортеров, и `up -d --remove-orphans` удалит их как лишние контейнеры:
деплой проверяет эту переменную и останавливается, если её нет.

Весь стек потребляет около 1–1,3 ГБ памяти, поэтому на сервере с 2 ГБ RAM включён swap.

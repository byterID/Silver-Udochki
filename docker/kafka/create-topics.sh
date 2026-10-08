#!/bin/sh
# Создаёт топики Kafka. Безопасно запускать при каждом деплое:
# --if-not-exists пропускает уже существующие топики.
set -eu

KAFKA="docker compose exec -T kafka /opt/kafka/bin"
# -T отключает псевдотерминал: в CI и по SSH его нет, без флага exec падает.

# Kafka стартует несколько секунд после старта контейнера: ждём, пока ответит.
i=0
until $KAFKA/kafka-topics.sh --bootstrap-server localhost:9092 --list >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -ge 30 ] && { echo "Kafka не поднялась за 60 секунд" >&2; exit 1; }
    sleep 2
done

create() {
    $KAFKA/kafka-topics.sh --bootstrap-server localhost:9092 \
        --create --if-not-exists --topic "$1" --partitions "$2" --replication-factor 1
}

create tasks.requested     3
create tasks.completed     1
create tasks.requested.dlq 1

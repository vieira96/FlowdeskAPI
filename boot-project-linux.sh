#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker não foi encontrado. Instale o Docker Engine e o Docker Compose antes de continuar."
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "O plugin Docker Compose não foi encontrado."
    exit 1
fi

docker compose down

# Faz os arquivos gerados nos contêineres pertencerem ao usuário local.
export LOCAL_UID="$(id -u)"
export LOCAL_GID="$(id -g)"

echo "Construindo a imagem PHP..."
docker compose build app queue reverb

echo "Iniciando MySQL e Redis..."
docker compose up -d --wait mysql redis

echo "Garantindo os bancos de desenvolvimento e de testes..."
docker compose exec -T mysql mysql -uroot -proot -e "
    CREATE DATABASE IF NOT EXISTS flowdesk;
    CREATE DATABASE IF NOT EXISTS flowdesk_testing;
    CREATE USER IF NOT EXISTS 'flowdesk'@'%' IDENTIFIED BY 'flowdesk';
    ALTER USER 'flowdesk'@'%' IDENTIFIED BY 'flowdesk';
    GRANT ALL PRIVILEGES ON flowdesk.* TO 'flowdesk'@'%';
    GRANT ALL PRIVILEGES ON flowdesk_testing.* TO 'flowdesk'@'%';
    FLUSH PRIVILEGES;
"

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

echo "Instalando as dependências PHP..."
docker compose run --rm --name flowdesk-app-bootstrap app composer install --no-interaction --prefer-dist

echo "Configurando a aplicação..."
if ! grep -q '^APP_KEY=base64:' .env; then
    docker compose run --rm --name flowdesk-app-bootstrap app php artisan key:generate --force
fi
docker compose run --rm --name flowdesk-app-bootstrap app php artisan migrate --force

echo "Iniciando a aplicação, fila e WebSocket..."
docker compose up -d app queue reverb

echo
echo "Projeto pronto em http://localhost:8000"

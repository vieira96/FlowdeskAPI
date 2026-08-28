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

run_bootstrap() {
    docker rm -f flowdesk-app-bootstrap >/dev/null 2>&1 || true
    docker compose run --rm --name flowdesk-app-bootstrap app "$@"
}

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
run_bootstrap composer install --no-interaction --prefer-dist

echo "Configurando a aplicação..."
if ! grep -q '^APP_KEY=base64:' .env; then
    run_bootstrap php artisan key:generate --force
fi
run_bootstrap php artisan migrate --force

ai_model="$(grep '^AI_OLLAMA_MODEL=' .env | cut -d= -f2- || true)"
ai_model="${ai_model:-qwen3:4b}"

echo "Iniciando o serviço de IA e baixando o modelo ${ai_model}..."
docker compose up -d ollama
docker compose exec -T ollama ollama pull "$ai_model"

echo "Iniciando a aplicação, fila, WebSocket e IA..."
docker compose up -d app queue reverb ollama

echo
echo "Projeto pronto em http://localhost:8000"

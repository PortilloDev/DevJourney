#!/bin/bash
set -e

echo "🔄 Pulling latest changes..."
git pull

echo "🔨 Building app image..."
# --pull es obligatorio: sin él, docker reutiliza la imagen base cacheada
# en la máquina y un parche de seguridad de PHP nunca llega al contenedor.
docker compose build --pull app

echo "🛑 Stopping containers and cleaning networks..."
docker compose down

echo "🚀 Starting all services..."
docker compose up -d

echo "🧹 Clearing Laravel caches..."
docker compose exec app php artisan optimize:clear

echo "🔎 Versión de PHP en ejecución:"
docker compose exec -T app php -v | head -n 1

echo "✅ Deploy completado"

#!/bin/bash
set -e

echo "🔄 Pulling latest changes..."
git pull

echo "🔨 Building app image..."
docker compose build app

echo "🛑 Stopping containers and cleaning networks..."
docker compose down

echo "🚀 Starting all services..."
docker compose up -d

echo "🧹 Clearing Laravel caches..."
docker compose exec app php artisan optimize:clear

echo "✅ Deploy completado"

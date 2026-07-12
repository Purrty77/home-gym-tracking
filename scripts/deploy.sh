#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
sudo systemctl reload apache2
echo "Déploiement terminé."


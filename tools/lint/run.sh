#!/usr/bin/env bash
# tools/lint/run.sh v0.1.0
# Назначение: запуск линтеров локально/в CI. Возвращает код ошибки при нарушениях.
# FIX: добавлен вызов BladePurityGuard.

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
php "$ROOT_DIR/tools/lint/BladePurityGuard.php"

#!/usr/bin/env bash
# backup.sh — v0.2.0
# Назначение: создать архив-бэкап текущего проекта (эта папка) в каталоге backup на два уровня
# выше, с версией формата R.M.P. R и M задаются константами ниже, инкрементируется только P.
# Также реализована ротация: хранится не более RETAIN последних патчей для текущих R/M.
# FIX: добавлены константы RELEASE/MAJOR, формат имени vR.M.P.tar.gz, ротация по патчу, сохранение
# в каталог backup, корректный поиск текущего патча по маске vR.M.*.tar.gz.

set -Eeuo pipefail

### === Константы релиза (меняешь вручную при необходимости) ===
RELEASE=0        # R — номер релиза
MAJOR=3          # M — мажорная версия
RETAIN=10        # сколько последних патчей P хранить (ротация)
PATCH_PAD=3      # ширина нулями для P (напр. 001, 002, ...)

### === Расположение каталогов ===
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"      # .../node1/forum
PROJECT_DIR="$SCRIPT_DIR"                                       # архивируем эту папку
# Папка для бэкапов: на два уровня выше, в подкаталог backup (как у тебя в дереве)
BACKUP_DIR="$(realpath "$SCRIPT_DIR/../../backup")"

mkdir -p "$BACKUP_DIR"

### === Вспомогательные функции ===
die() { echo "Error: $*" >&2; exit 1; }

# Возвратит текущий max P (число) для заданных R/M, если такие архивы есть; иначе 0
current_max_patch() {
  local r="$1" m="$2"
  local max=0
  shopt -s nullglob
  # маска: vR.M.*.tar.gz (пример: v0.3.041.tar.gz)
  local files=("$BACKUP_DIR"/v"${r}.${m}."*.tar.gz)
  shopt -u nullglob
  for f in "${files[@]}"; do
    local bn="${f##*/}"              # v0.3.041.tar.gz
    local core="${bn%.tar.gz}"       # v0.3.041
    # извлечь P после второй точки
    local p="${core#v${r}.${m}.}"    # 041
    # только цифры
    if [[ "$p" =~ ^[0-9]+$ ]]; then
      ((10#$p > max)) && max=$((10#$p))
    fi
  done
  echo "$max"
}

# Удаляет лишние патчи, оставляя последние RETAIN для текущих R/M
rotate_patches() {
  local r="$1" m="$2" keep="$3"
  shopt -s nullglob
  local files=("$BACKUP_DIR"/v"${r}.${m}."*.tar.gz)
  shopt -u nullglob
  # если файлов меньше либо равно keep — ничего не делаем
  local count="${#files[@]}"
  (( count <= keep )) && return 0

  # Отсортируем по патчу (численно)
  # Сформируем пары "patch filepath", отсортируем по patch и удалим старые.
  mapfile -t sorted < <(
    for f in "${files[@]}"; do
      bn="${f##*/}"
      core="${bn%.tar.gz}"
      p="${core#v${r}.${m}.}"
      [[ "$p" =~ ^[0-9]+$ ]] || continue
      printf "%d\t%s\n" "$((10#$p))" "$f"
    done | sort -n
  )

  # Сколько удалить
  local to_delete=$((count - keep))
  for ((i=0; i<to_delete; i++)); do
    line="${sorted[$i]}"
    [[ -n "$line" ]] || continue
    fpath="${line#*$'\t'}"
    [[ -f "$fpath" ]] && rm -f -- "$fpath"
  done
}

### === Определяем следующий патч P ===
CUR_MAX_P="$(current_max_patch "$RELEASE" "$MAJOR")"
NEXT_P=$((CUR_MAX_P + 1))
printf -v NEXT_P_PAD "%0*d" "$PATCH_PAD" "$NEXT_P"

ARCHIVE_NAME="v${RELEASE}.${MAJOR}.${NEXT_P_PAD}.tar.gz"
ARCHIVE_PATH="$BACKUP_DIR/$ARCHIVE_NAME"

### === Создание архива ===
# Упаковываем родительскую папку + имя текущей директории, чтобы в архиве был верхним
# уровнем сам каталог проекта (forum), а не абсолютные пути.
PARENT_DIR="$(dirname "$PROJECT_DIR")"
BASE_NAME="$(basename "$PROJECT_DIR")"

# Исключения можно добавить через --exclude, при необходимости:
#   --exclude 'vendor' --exclude 'node_modules' --exclude 'storage/cache'
tar -C "$PARENT_DIR" -czf "$ARCHIVE_PATH" "$BASE_NAME"

### === Ротация ===
rotate_patches "$RELEASE" "$MAJOR" "$RETAIN"

echo "done! backup saved as backup/$ARCHIVE_NAME (R=$RELEASE, M=$MAJOR, P=$NEXT_P_PAD)"

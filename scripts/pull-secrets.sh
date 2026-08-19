#!/usr/bin/env bash
# Обновление .env из Infisical перед сборкой.
#
# Источник правды для секретов — Infisical (infisical.kazpayment.kz, проект
# kulager), а .env стал ПРОИЗВОДНЫМ файлом: он генерируется здесь, а правится
# только через интерфейс. Правка руками теряется при следующем деплое.
#
# Отдельная машинная учётка, не та, что у kaspi-billing: утечка ключей одного
# проекта не должна открывать второй.
#
# compose.yaml подставляет значения через ${...}, то есть файл нужен ДО запуска
# `docker compose up`. Инъекция в окружение процесса (`infisical run --`) сюда
# не годится.
set -euo pipefail

PROJ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Полный путь к бинарю, а не просто `infisical`: при запуске не из
# интерактивной оболочки (cron, systemd) каталога ~/.local/bin в PATH не будет.
BIN="${INFISICAL_BIN:-$HOME/.local/bin/infisical}"
CONF="${INFISICAL_CONF:-$HOME/.config/infisical/kulager.env}"
ENVIRONMENT="${INFISICAL_ENV:-prod}"

# Нижняя граница числа переменных. Их 4; порог 3, чтобы удаление одной не
# требовало правки скрипта, но оборванный ответ был пойман. Смысл проверки:
# оборванный запрос возвращает синтаксически ПРАВИЛЬНЫЙ, но короткий файл, и без
# порога compose подставит пустые значения молча.
MIN_KEYS="${INFISICAL_MIN_KEYS:-3}"

# Без этих двух compose.yaml откажется стартовать (`${...:?}`), а KULAGER_DB_USER
# и KULAGER_DEBUG имеют умолчания и обязательными не являются.
REQUIRED=(KULAGER_DB_PASS KULAGER_DB_ROOT_PASS)

warn() { echo "!! $*" >&2; }

if [ ! -x "$BIN" ]; then warn "нет $BIN — деплою на текущем .env"; exit 0; fi
if [ ! -f "$CONF" ]; then warn "нет $CONF с ключами машинной учётки — деплою на текущем .env"; exit 0; fi

set -a; . "$CONF"; set +a
export INFISICAL_TELEMETRY_ENABLED=false

umask 077
tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT

if ! token=$("$BIN" login --method=universal-auth \
      --client-id="$INFISICAL_CLIENT_ID" --client-secret="$INFISICAL_CLIENT_SECRET" \
      --domain="$INFISICAL_API_URL" --silent --plain 2>/dev/null); then
  warn "Infisical не пустил по машинной учётке — деплою на текущем .env"; exit 0
fi

# --expand=false обязателен: по умолчанию CLI разбирает $-подстановки внутри
# значений, и пароль, содержащий $, приедет искажённым.
if ! INFISICAL_TOKEN="$token" "$BIN" export \
      --env="$ENVIRONMENT" --projectId="$INFISICAL_PROJECT_ID" \
      --domain="$INFISICAL_API_URL" --format=dotenv --expand=false --silent > "$tmp" 2>/dev/null; then
  warn "выгрузка секретов не удалась — деплою на текущем .env"; exit 0
fi

count=$(grep -cE '^[A-Za-z_][A-Za-z_0-9]*=' "$tmp" || true)
if [ "$count" -lt "$MIN_KEYS" ]; then
  warn "из Infisical пришло $count переменных при пороге $MIN_KEYS — деплою на текущем .env"; exit 0
fi

for k in "${REQUIRED[@]}"; do
  if ! grep -qE "^${k}=" "$tmp"; then
    warn "в выгрузке нет обязательной переменной $k — деплою на текущем .env"; exit 0
  fi
done

# Предыдущая версия сохраняется всегда: мгновенный откат без обращения к
# интерфейсу — `cp .env.bak .env` и пересборка.
if [ -f "$PROJ/.env" ]; then
  cp "$PROJ/.env" "$PROJ/.env.bak"
  chmod 600 "$PROJ/.env.bak"
fi

cp "$tmp" "$PROJ/.env"
chmod 600 "$PROJ/.env"
echo "==> .env обновлён из Infisical: $count переменных, окружение $ENVIRONMENT"

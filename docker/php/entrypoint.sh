#!/bin/sh
# ==============================================================================
# PokéLink — container bootstrap
#
# Runs before every service command. The sequence is:
#
#   1. ensure .env exists
#   2. ensure APP_KEY is set
#   3. ensure the framework's writable directories exist
#   4. wait for MySQL                      (app role only)
#   5. migrate, and seed on first install  (app role only)
#   6. link storage                        (app role only)
#   7. optimize                            (app role only)
#   8. signal readiness and exec the command
#
# CONTAINER_ROLE selects the path. `app` runs everything; `queue` and `reverb`
# skip steps 4-7 because Compose already gates them on `app` reporting healthy,
# which means the schema exists before they start. Exactly one service migrates.
# ==============================================================================

set -e

APP_DIR="/var/www/html"
CONTAINER_ROLE="${CONTAINER_ROLE:-app}"

# Persisted on the pokelink_storage named volume: survives a restart, and is
# destroyed by `docker compose down -v`, which is what makes that command
# reproduce a clean, freshly seeded environment.
INSTALL_MARKER="${APP_DIR}/storage/app/.pokelink-installed"

# Lives in the container filesystem, outside every volume, so it never survives
# a container recreation. This is what the Compose healthcheck tests.
READY_MARKER="/tmp/pokelink-ready"

DB_WAIT_ATTEMPTS=30
DB_WAIT_INTERVAL=2

cd "${APP_DIR}"

log() {
    echo "[pokelink][${CONTAINER_ROLE}] $*"
}

fail() {
    echo "[pokelink][${CONTAINER_ROLE}] $*" >&2
    exit 1
}

# ------------------------------------------------------------------------------
# 0. Drop compiled caches
#
# bootstrap/cache lives on the bind mount, so it outlives the container and even
# `docker compose down -v`. A stale config.php would be read as the current
# configuration before .env is even in place — which, among other things, makes
# `key:generate` look for the previous APP_KEY in a file that no longer has one.
# Deleting the files beats `optimize:clear`, which would boot the framework
# against the very cache being replaced. Step 7 regenerates all of it.
# ------------------------------------------------------------------------------
rm -f "${APP_DIR}"/bootstrap/cache/config.php \
      "${APP_DIR}"/bootstrap/cache/routes-*.php \
      "${APP_DIR}"/bootstrap/cache/events.php \
      "${APP_DIR}"/bootstrap/cache/services.php \
      "${APP_DIR}"/bootstrap/cache/packages.php 2>/dev/null || true

# ------------------------------------------------------------------------------
# 1. Environment file
# ------------------------------------------------------------------------------
if [ ! -f "${APP_DIR}/.env" ]; then
    log "arquivo .env ausente — copiando de .env.example"
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env" \
        || fail "não foi possível criar o .env — verifique as permissões do diretório do projeto"
fi

# ------------------------------------------------------------------------------
# 2. Application key
# ------------------------------------------------------------------------------
if ! grep -qE '^APP_KEY=.+' "${APP_DIR}/.env"; then
    log "APP_KEY vazia — gerando"

    # key:generate exits 0 even when it fails to write, so the file is what gets
    # checked, not the exit code.
    php artisan key:generate --force --no-interaction || true

    grep -qE '^APP_KEY=.+' "${APP_DIR}/.env" \
        || fail "não foi possível gerar a APP_KEY — verifique se o .env é gravável"
fi

# ------------------------------------------------------------------------------
# 3. Writable directories
#
# storage/ is a named volume that starts empty on a clean boot, so these are
# recreated here rather than relied upon from the image.
# ------------------------------------------------------------------------------
mkdir -p \
    "${APP_DIR}/storage/framework/cache/data" \
    "${APP_DIR}/storage/framework/sessions" \
    "${APP_DIR}/storage/framework/views" \
    "${APP_DIR}/storage/logs" \
    "${APP_DIR}/storage/app/public" \
    "${APP_DIR}/bootstrap/cache" \
    || fail "não foi possível criar os diretórios de storage"

for dir in "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"; do
    [ -w "${dir}" ] || fail "o diretório ${dir} não é gravável pelo usuário do container ($(id -u):$(id -g))"
done

# Roles other than `app` stop here: the schema is already in place.
if [ "${CONTAINER_ROLE}" != "app" ]; then
    log "iniciando: $*"
    exec "$@"
fi

# ------------------------------------------------------------------------------
# 4. Wait for MySQL
#
# The credentials are read out of .env rather than from the container
# environment on purpose. Exporting DB_* into the container would shadow
# phpunit.xml — PHPUnit will not override a real environment variable — and the
# test suite would silently run against the live MySQL database instead of the
# in-memory SQLite one. .env stays the single source of truth.
# ------------------------------------------------------------------------------
env_value() {
    sed -n "s/^${1}=//p" "${APP_DIR}/.env" \
        | head -n 1 \
        | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

db_ready() {
    php -r '
        [, $host, $port, $name, $user, $pass] = $argv + [null, "mysql", "3306", "pokelink", "pokelink", ""];

        try {
            new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $name),
                $user,
                $pass,
                [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' "$(env_value DB_HOST)" "$(env_value DB_PORT)" "$(env_value DB_DATABASE)" \
      "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" 2>/dev/null
}

attempt=1
while [ "${attempt}" -le "${DB_WAIT_ATTEMPTS}" ]; do
    if db_ready; then
        log "banco de dados disponível (tentativa ${attempt})"
        break
    fi

    log "aguardando banco de dados... (${attempt}/${DB_WAIT_ATTEMPTS})"

    if [ "${attempt}" -eq "${DB_WAIT_ATTEMPTS}" ]; then
        fail "banco de dados não respondeu em $((DB_WAIT_ATTEMPTS * DB_WAIT_INTERVAL))s — verifique os logs do serviço mysql"
    fi

    attempt=$((attempt + 1))
    sleep "${DB_WAIT_INTERVAL}"
done

# ------------------------------------------------------------------------------
# 5. Migrate, and seed on first install
#
# The marker is written only after both succeed, so a failed first boot retries
# from a clean state on the next `docker compose up` instead of leaving a
# half-installed database that never seeds.
# ------------------------------------------------------------------------------
if [ -f "${INSTALL_MARKER}" ]; then
    log "instalação existente detectada — aplicando migrations pendentes"
    php artisan migrate --force --no-interaction \
        || fail "falha ao aplicar as migrations — veja o nome da migration acima"
else
    log "primeira inicialização — executando migrations e seeders"
    php artisan migrate --force --no-interaction \
        || fail "falha ao executar as migrations — o banco não foi populado; veja o nome da migration acima"

    php artisan db:seed --force --no-interaction \
        || fail "falha ao executar os seeders"

    date -u +"%Y-%m-%dT%H:%M:%SZ" > "${INSTALL_MARKER}"
    log "instalação concluída"
fi

# ------------------------------------------------------------------------------
# 6. Public storage symlink
# ------------------------------------------------------------------------------
if [ ! -e "${APP_DIR}/public/storage" ]; then
    php artisan storage:link --no-interaction || log "aviso: storage:link falhou (seguindo mesmo assim)"
fi

# ------------------------------------------------------------------------------
# 7. Optimize
# ------------------------------------------------------------------------------
php artisan optimize --no-interaction || fail "falha ao otimizar a aplicação"

# ------------------------------------------------------------------------------
# 8. Signal readiness and hand over to the service command
# ------------------------------------------------------------------------------
touch "${READY_MARKER}"
log "pronto — http://localhost:$(env_value APP_PORT)"
log "iniciando: $*"

exec "$@"

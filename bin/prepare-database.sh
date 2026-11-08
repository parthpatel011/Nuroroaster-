#!/usr/bin/env bash
# Creates the local PostgreSQL roles and databases for NurseRoster.
# The passwords below are disposable local/CI fixture passwords. Do not reuse them anywhere else.
set -euo pipefail

APP_ROLE="${APP_ROLE:-nurseroster_app}"
APP_PASSWORD="${APP_PASSWORD:-nurseroster_app}"
OWNER_ROLE="${OWNER_ROLE:-nurseroster_owner}"
OWNER_PASSWORD="${OWNER_PASSWORD:-nurseroster_owner}"
PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-5432}"
SUPERUSER="${PGUSER:-postgres}"

for identifier in "$APP_ROLE" "$OWNER_ROLE" "$SUPERUSER"; do
  if [[ ! "$identifier" =~ ^[a-z_][a-z0-9_]*$ ]]; then
    echo "Refusing unsafe database identifier: $identifier" >&2
    exit 1
  fi
done

for password in "$APP_PASSWORD" "$OWNER_PASSWORD"; do
  if [[ ! "$password" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "This setup script only accepts simple fixture passwords." >&2
    exit 1
  fi
done

if [[ -n "${PGPASSWORD:-}" ]]; then
  psql_super() {
    psql -h "$PGHOST" -p "$PGPORT" -U "$SUPERUSER" -v ON_ERROR_STOP=1 "$@"
  }
else
  psql_super() {
    sudo -u postgres psql -v ON_ERROR_STOP=1 "$@"
  }
fi

psql_super <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '${OWNER_ROLE}') THEN
        CREATE ROLE ${OWNER_ROLE} LOGIN PASSWORD '${OWNER_PASSWORD}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    ELSE
        ALTER ROLE ${OWNER_ROLE} WITH LOGIN PASSWORD '${OWNER_PASSWORD}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '${APP_ROLE}') THEN
        CREATE ROLE ${APP_ROLE} LOGIN PASSWORD '${APP_PASSWORD}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    ELSE
        ALTER ROLE ${APP_ROLE} WITH LOGIN PASSWORD '${APP_PASSWORD}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    END IF;
END
\$\$;
SQL

for database in nurseroster nurseroster_test; do
  exists="$(psql_super -tAc "SELECT 1 FROM pg_database WHERE datname = '${database}'" | tr -d '[:space:]')"
  if [[ "$exists" != "1" ]]; then
    psql_super -c "CREATE DATABASE ${database} OWNER ${OWNER_ROLE}"
  fi

  psql_super -d "$database" <<SQL
CREATE EXTENSION IF NOT EXISTS btree_gist;
GRANT CONNECT ON DATABASE ${database} TO ${APP_ROLE};
GRANT USAGE ON SCHEMA public TO ${APP_ROLE};
GRANT CREATE, USAGE ON SCHEMA public TO ${OWNER_ROLE};
ALTER SCHEMA public OWNER TO ${OWNER_ROLE};
SQL
done

echo "PostgreSQL roles and databases are ready."
echo "Runtime role: ${APP_ROLE} (NOBYPASSRLS). Migration role: ${OWNER_ROLE} (NOBYPASSRLS)."

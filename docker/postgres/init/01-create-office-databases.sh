#!/usr/bin/env bash
set -euo pipefail

create_database_and_user() {
  local database="$1"
  local username="$2"
  local password="$3"

  if [ -z "$database" ] || [ -z "$username" ]; then
    return
  fi

  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-EOSQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${username}') THEN
    CREATE ROLE "${username}" LOGIN PASSWORD '${password}';
  ELSE
    ALTER ROLE "${username}" WITH LOGIN PASSWORD '${password}';
  END IF;
END
\$\$;

SELECT 'CREATE DATABASE "${database}" OWNER "${username}"'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${database}')\gexec

GRANT ALL PRIVILEGES ON DATABASE "${database}" TO "${username}";
EOSQL
}

create_database_and_user "${CREDITSOFT_PG_DATABASE:-creditsoft}" "${CREDITSOFT_PG_USER:-creditsoft}" "${CREDITSOFT_PG_PASSWORD:-creditsoft}"
create_database_and_user "${CRM_PG_DATABASE:-${TWENTY_PG_DATABASE:-crm}}" "${CRM_PG_USER:-${TWENTY_PG_USER:-crm}}" "${CRM_PG_PASSWORD:-${TWENTY_PG_PASSWORD:-crm}}"

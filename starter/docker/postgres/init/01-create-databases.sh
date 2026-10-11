#!/bin/sh
# Runs once, on the first start of an empty volume (docker compose down -v to run it again).
# One database per module in $MODULE_DATABASES, owned by $POSTGRES_USER, which migrates them.
# The application reads and writes them as distributable_app, which can't create or alter tables.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE ROLE distributable_app WITH LOGIN PASSWORD '${DB_APP_PASSWORD:-distributable_app}';
EOSQL

for db in ${MODULE_DATABASES:-iam analytics}; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
        CREATE DATABASE ${db} OWNER ${POSTGRES_USER};
        GRANT CONNECT ON DATABASE ${db} TO distributable_app;
EOSQL

    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "${db}" <<-EOSQL
        GRANT USAGE ON SCHEMA public TO distributable_app;
        ALTER DEFAULT PRIVILEGES FOR USER ${POSTGRES_USER} IN SCHEMA public
            GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO distributable_app;
        ALTER DEFAULT PRIVILEGES FOR USER ${POSTGRES_USER} IN SCHEMA public
            GRANT USAGE, SELECT ON SEQUENCES TO distributable_app;
EOSQL
done

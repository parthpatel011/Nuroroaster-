-- Disposable local fixture passwords. Do not reuse them outside this development database.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'nurseroster_owner') THEN
        CREATE ROLE nurseroster_owner LOGIN PASSWORD 'nurseroster_owner' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'nurseroster_app') THEN
        CREATE ROLE nurseroster_app LOGIN PASSWORD 'nurseroster_app' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    END IF;
END
$$;

SELECT 'CREATE DATABASE nurseroster OWNER nurseroster_owner'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'nurseroster')\gexec

SELECT 'CREATE DATABASE nurseroster_test OWNER nurseroster_owner'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'nurseroster_test')\gexec

\connect nurseroster
CREATE EXTENSION IF NOT EXISTS btree_gist;
GRANT CONNECT ON DATABASE nurseroster TO nurseroster_app;
GRANT USAGE ON SCHEMA public TO nurseroster_app;
GRANT CREATE, USAGE ON SCHEMA public TO nurseroster_owner;
ALTER SCHEMA public OWNER TO nurseroster_owner;

\connect nurseroster_test
CREATE EXTENSION IF NOT EXISTS btree_gist;
GRANT CONNECT ON DATABASE nurseroster_test TO nurseroster_app;
GRANT USAGE ON SCHEMA public TO nurseroster_app;
GRANT CREATE, USAGE ON SCHEMA public TO nurseroster_owner;
ALTER SCHEMA public OWNER TO nurseroster_owner;

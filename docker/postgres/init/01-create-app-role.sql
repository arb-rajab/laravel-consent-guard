-- Simulates what a real host application's own infrastructure already
-- provides before installing this package: a Postgres role the running
-- application connects as, distinct from whatever role owns the schema.
-- This package's own migration/command never create this role — only a
-- host app (or, here, local/CI test setup) is expected to.
DO
$do$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'consent_guard_app') THEN
        CREATE ROLE consent_guard_app LOGIN PASSWORD 'consent_guard_app_password';
    END IF;
END
$do$;

GRANT CONNECT ON DATABASE consent_guard_test TO consent_guard_app;
GRANT USAGE ON SCHEMA public TO consent_guard_app;

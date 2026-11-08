<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = config('database.connections.pgsql.username');

        if (! is_string($role) || preg_match('/^[a-z_][a-z0-9_]*$/', $role) !== 1) {
            throw new RuntimeException('The app database role name is missing or unsafe.');
        }

        DB::unprepared(<<<SQL
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'btree_gist') THEN
                    RAISE EXCEPTION 'btree_gist is required. Run bin/prepare-database.sh before migrating.';
                END IF;
            END
            \$\$;

            ALTER DEFAULT PRIVILEGES IN SCHEMA public
                GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role};
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
                GRANT USAGE, SELECT ON SEQUENCES TO {$role};

            CREATE TABLE tenants (
                id uuid PRIMARY KEY,
                name text NOT NULL,
                timezone text NOT NULL,
                status text NOT NULL DEFAULT 'active',
                settings jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT tenants_status_check CHECK (status IN ('active', 'suspended', 'offboarding')),
                CONSTRAINT tenants_name_check CHECK (char_length(name) BETWEEN 1 AND 160),
                CONSTRAINT tenants_timezone_check CHECK (char_length(timezone) BETWEEN 1 AND 64)
            );

            CREATE TABLE memberships (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL,
                user_id uuid NOT NULL REFERENCES users (id),
                role text NOT NULL,
                status text NOT NULL DEFAULT 'active',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT memberships_role_check CHECK (role IN ('hospital_admin', 'supervisor', 'staff')),
                CONSTRAINT memberships_status_check CHECK (status IN ('active', 'inactive')),
                CONSTRAINT memberships_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT memberships_tenant_user_unique UNIQUE (tenant_id, user_id),
                CONSTRAINT memberships_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            );
            CREATE INDEX memberships_tenant_role_idx ON memberships (tenant_id, role);
            CREATE INDEX memberships_user_idx ON memberships (user_id);

            CREATE TABLE employees (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                employee_code text NOT NULL,
                full_name text NOT NULL,
                status text NOT NULL DEFAULT 'active',
                inactive_from date NULL,
                user_id uuid NULL REFERENCES users (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT employees_status_check CHECK (status IN ('active', 'inactive')),
                CONSTRAINT employees_code_check CHECK (employee_code ~ '^[A-Za-z0-9_-]{1,32}\$'),
                CONSTRAINT employees_name_check CHECK (char_length(full_name) BETWEEN 1 AND 120),
                CONSTRAINT employees_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT employees_tenant_code_unique UNIQUE (tenant_id, employee_code)
            );
            CREATE UNIQUE INDEX employees_user_unique ON employees (user_id) WHERE user_id IS NOT NULL;
            CREATE INDEX employees_tenant_status_name_idx ON employees (tenant_id, status, full_name);

            CREATE TABLE wards (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                code text NOT NULL,
                name text NOT NULL,
                status text NOT NULL DEFAULT 'active',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT wards_status_check CHECK (status IN ('active', 'inactive')),
                CONSTRAINT wards_code_check CHECK (code ~ '^[A-Za-z0-9_-]{1,32}\$'),
                CONSTRAINT wards_name_check CHECK (char_length(name) BETWEEN 1 AND 120),
                CONSTRAINT wards_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT wards_tenant_code_unique UNIQUE (tenant_id, code)
            );
            CREATE INDEX wards_tenant_status_idx ON wards (tenant_id, status);

            CREATE TABLE shift_definitions (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                code text NOT NULL,
                name text NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                break_minutes integer NOT NULL DEFAULT 0,
                kind text NOT NULL DEFAULT 'duty',
                status text NOT NULL DEFAULT 'active',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT shift_definitions_status_check CHECK (status IN ('active', 'inactive')),
                CONSTRAINT shift_definitions_kind_check CHECK (kind IN ('duty')),
                CONSTRAINT shift_definitions_break_check CHECK (break_minutes >= 0 AND break_minutes <= 240),
                CONSTRAINT shift_definitions_code_check CHECK (code ~ '^[A-Za-z0-9_-]{1,32}\$'),
                CONSTRAINT shift_definitions_name_check CHECK (char_length(name) BETWEEN 1 AND 120),
                CONSTRAINT shift_definitions_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT shift_definitions_tenant_code_unique UNIQUE (tenant_id, code)
            );
            CREATE INDEX shift_definitions_tenant_status_idx ON shift_definitions (tenant_id, status);

            CREATE TABLE rosters (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                ward_id uuid NOT NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                status text NOT NULL DEFAULT 'draft',
                lock_version integer NOT NULL DEFAULT 0,
                created_by uuid NOT NULL REFERENCES users (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT rosters_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'published', 'superseded')),
                CONSTRAINT rosters_period_check CHECK (period_end >= period_start AND (period_end - period_start) <= 62),
                CONSTRAINT rosters_lock_check CHECK (lock_version >= 0),
                CONSTRAINT rosters_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT rosters_ward_fk FOREIGN KEY (tenant_id, ward_id) REFERENCES wards (tenant_id, id)
            );
            CREATE INDEX rosters_tenant_ward_period_idx ON rosters (tenant_id, ward_id, period_start);
            CREATE INDEX rosters_tenant_status_idx ON rosters (tenant_id, status, period_start);

            ALTER TABLE rosters ADD CONSTRAINT rosters_no_period_overlap
                EXCLUDE USING gist (
                    tenant_id WITH =,
                    ward_id WITH =,
                    daterange(period_start, period_end, '[]') WITH &&
                ) WHERE (status <> 'superseded');

            CREATE TABLE duty_assignments (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL,
                roster_id uuid NOT NULL,
                employee_id uuid NOT NULL,
                shift_definition_id uuid NOT NULL,
                duty_date date NOT NULL,
                starts_at timestamptz NOT NULL,
                ends_at timestamptz NOT NULL,
                during tstzrange GENERATED ALWAYS AS (tstzrange(starts_at, ends_at, '[)')) STORED,
                status text NOT NULL DEFAULT 'scheduled',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT duty_assignments_status_check CHECK (status IN ('scheduled', 'cancelled')),
                CONSTRAINT duty_assignments_window_check CHECK (ends_at > starts_at),
                CONSTRAINT duty_assignments_duration_check CHECK (ends_at - starts_at <= interval '24 hours'),
                CONSTRAINT duty_assignments_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT duty_assignments_roster_fk FOREIGN KEY (tenant_id, roster_id) REFERENCES rosters (tenant_id, id),
                CONSTRAINT duty_assignments_employee_fk FOREIGN KEY (tenant_id, employee_id) REFERENCES employees (tenant_id, id),
                CONSTRAINT duty_assignments_shift_fk FOREIGN KEY (tenant_id, shift_definition_id) REFERENCES shift_definitions (tenant_id, id)
            );
            CREATE INDEX duty_assignments_roster_idx ON duty_assignments (tenant_id, roster_id);
            CREATE INDEX duty_assignments_employee_start_idx ON duty_assignments (tenant_id, employee_id, starts_at);
            CREATE INDEX duty_assignments_date_idx ON duty_assignments (tenant_id, duty_date);

            ALTER TABLE duty_assignments ADD CONSTRAINT duty_assignments_no_overlap
                EXCLUDE USING gist (
                    tenant_id WITH =,
                    employee_id WITH =,
                    during WITH &&
                ) WHERE (status = 'scheduled');

            CREATE TABLE roster_versions (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL,
                roster_id uuid NOT NULL,
                version_no integer NOT NULL,
                snapshot jsonb NOT NULL,
                snapshot_sha256 char(64) NOT NULL,
                published_by uuid NOT NULL REFERENCES users (id),
                published_at timestamptz NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT roster_versions_version_check CHECK (version_no >= 1),
                CONSTRAINT roster_versions_hash_check CHECK (snapshot_sha256 ~ '^[0-9a-f]{64}\$'),
                CONSTRAINT roster_versions_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT roster_versions_roster_version_unique UNIQUE (roster_id, version_no),
                CONSTRAINT roster_versions_roster_fk FOREIGN KEY (tenant_id, roster_id) REFERENCES rosters (tenant_id, id)
            );
            CREATE INDEX roster_versions_tenant_roster_idx ON roster_versions (tenant_id, roster_id, version_no);
            COMMENT ON TABLE roster_versions IS 'Append-only publish snapshots. This slice has no writer and no publish API.';

            CREATE TABLE rule_sets (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                version integer NOT NULL,
                status text NOT NULL,
                effective_from date NULL,
                created_by uuid NOT NULL REFERENCES users (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT rule_sets_status_check CHECK (status IN ('draft', 'signed_off', 'retired')),
                CONSTRAINT rule_sets_version_check CHECK (version >= 1),
                CONSTRAINT rule_sets_signed_effective_check CHECK (status <> 'signed_off' OR effective_from IS NOT NULL),
                CONSTRAINT rule_sets_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT rule_sets_tenant_version_unique UNIQUE (tenant_id, version)
            );

            CREATE TABLE rule_set_rules (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL,
                rule_set_id uuid NOT NULL,
                rule_type text NOT NULL,
                params jsonb NOT NULL DEFAULT '{}'::jsonb,
                severity text NOT NULL,
                source_kind text NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT rule_set_rules_severity_check CHECK (severity IN ('off', 'warning', 'error', 'block')),
                CONSTRAINT rule_set_rules_source_check CHECK (source_kind IN ('pack', 'hospital_policy')),
                CONSTRAINT rule_set_rules_integrity_not_configurable CHECK (rule_type NOT IN ('BR-01', 'BR-03')),
                CONSTRAINT rule_set_rules_block_allowlist CHECK (
                    severity <> 'block' OR rule_type IN ('BR-02', 'BR-04', 'BR-05')
                ),
                CONSTRAINT rule_set_rules_type_check CHECK (char_length(rule_type) BETWEEN 1 AND 40),
                CONSTRAINT rule_set_rules_tenant_id_unique UNIQUE (tenant_id, id),
                CONSTRAINT rule_set_rules_set_fk FOREIGN KEY (tenant_id, rule_set_id) REFERENCES rule_sets (tenant_id, id)
            );
            CREATE INDEX rule_set_rules_set_idx ON rule_set_rules (tenant_id, rule_set_id, rule_type);
            COMMENT ON TABLE rule_set_rules IS 'Configurable rule pack rows. Default severity is warning. BR-01 and BR-03 cannot be stored here.';

            CREATE TABLE idempotency_keys (
                id uuid PRIMARY KEY,
                tenant_id uuid NOT NULL REFERENCES tenants (id),
                scope text NOT NULL,
                idempotency_key text NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT idempotency_keys_scope_check CHECK (char_length(scope) BETWEEN 1 AND 80),
                CONSTRAINT idempotency_keys_key_check CHECK (char_length(idempotency_key) BETWEEN 1 AND 200),
                CONSTRAINT idempotency_keys_unique UNIQUE (tenant_id, scope, idempotency_key)
            );
            CREATE INDEX idempotency_keys_tenant_idx ON idempotency_keys (tenant_id, scope);

            CREATE OR REPLACE FUNCTION enforce_duty_assignment_integrity()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = public
            AS \$fn\$
            DECLARE
                emp_status text;
                emp_inactive date;
                shift_status text;
                roster_status text;
                roster_start date;
                roster_end date;
                tenant_tz text;
                local_duty date;
            BEGIN
                SELECT status, inactive_from
                    INTO emp_status, emp_inactive
                FROM employees
                WHERE id = NEW.employee_id AND tenant_id = NEW.tenant_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'ASSIGNMENT_TARGET_NOT_FOUND' USING ERRCODE = 'P0001';
                END IF;

                SELECT status INTO shift_status
                FROM shift_definitions
                WHERE id = NEW.shift_definition_id AND tenant_id = NEW.tenant_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'ASSIGNMENT_TARGET_NOT_FOUND' USING ERRCODE = 'P0001';
                END IF;

                SELECT status, period_start, period_end
                    INTO roster_status, roster_start, roster_end
                FROM rosters
                WHERE id = NEW.roster_id AND tenant_id = NEW.tenant_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'ASSIGNMENT_TARGET_NOT_FOUND' USING ERRCODE = 'P0001';
                END IF;

                SELECT timezone INTO tenant_tz FROM tenants WHERE id = NEW.tenant_id;
                local_duty := (NEW.starts_at AT TIME ZONE tenant_tz)::date;

                IF local_duty IS DISTINCT FROM NEW.duty_date THEN
                    RAISE EXCEPTION 'DUTY_DATE_MISMATCH' USING ERRCODE = 'P0001';
                END IF;

                IF NEW.status = 'scheduled' THEN
                    IF roster_status <> 'draft' THEN
                        RAISE EXCEPTION 'ROSTER_NOT_DRAFT' USING ERRCODE = 'P0001';
                    END IF;

                    IF NEW.duty_date < roster_start OR NEW.duty_date > roster_end THEN
                        RAISE EXCEPTION 'DUTY_OUTSIDE_PERIOD' USING ERRCODE = 'P0001';
                    END IF;

                    IF shift_status <> 'active' THEN
                        RAISE EXCEPTION 'SHIFT_INACTIVE' USING ERRCODE = 'P0001';
                    END IF;

                    IF emp_status = 'inactive' OR (emp_inactive IS NOT NULL AND NEW.duty_date >= emp_inactive) THEN
                        RAISE EXCEPTION 'STAFF_INACTIVE' USING ERRCODE = 'P0001';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$fn\$;

            DROP TRIGGER IF EXISTS duty_assignments_integrity ON duty_assignments;
            CREATE TRIGGER duty_assignments_integrity
                BEFORE INSERT OR UPDATE ON duty_assignments
                FOR EACH ROW
                EXECUTE FUNCTION enforce_duty_assignment_integrity();

            ALTER TABLE tenants ENABLE ROW LEVEL SECURITY;
            ALTER TABLE tenants FORCE ROW LEVEL SECURITY;
            ALTER TABLE memberships ENABLE ROW LEVEL SECURITY;
            ALTER TABLE memberships FORCE ROW LEVEL SECURITY;
            ALTER TABLE employees ENABLE ROW LEVEL SECURITY;
            ALTER TABLE employees FORCE ROW LEVEL SECURITY;
            ALTER TABLE wards ENABLE ROW LEVEL SECURITY;
            ALTER TABLE wards FORCE ROW LEVEL SECURITY;
            ALTER TABLE shift_definitions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE shift_definitions FORCE ROW LEVEL SECURITY;
            ALTER TABLE rosters ENABLE ROW LEVEL SECURITY;
            ALTER TABLE rosters FORCE ROW LEVEL SECURITY;
            ALTER TABLE duty_assignments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE duty_assignments FORCE ROW LEVEL SECURITY;
            ALTER TABLE roster_versions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE roster_versions FORCE ROW LEVEL SECURITY;
            ALTER TABLE rule_sets ENABLE ROW LEVEL SECURITY;
            ALTER TABLE rule_sets FORCE ROW LEVEL SECURITY;
            ALTER TABLE rule_set_rules ENABLE ROW LEVEL SECURITY;
            ALTER TABLE rule_set_rules FORCE ROW LEVEL SECURITY;
            ALTER TABLE idempotency_keys ENABLE ROW LEVEL SECURITY;
            ALTER TABLE idempotency_keys FORCE ROW LEVEL SECURITY;

            CREATE POLICY tenants_isolation ON tenants
                USING (
                    id = nullif(current_setting('app.tenant_id', true), '')::uuid
                    OR EXISTS (
                        SELECT 1 FROM memberships AS member_row
                        WHERE member_row.tenant_id = tenants.id
                          AND member_row.user_id = nullif(current_setting('app.user_id', true), '')::uuid
                    )
                )
                WITH CHECK (id = nullif(current_setting('app.tenant_id', true), '')::uuid);

            CREATE POLICY memberships_isolation ON memberships
                USING (
                    user_id = nullif(current_setting('app.user_id', true), '')::uuid
                    OR tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid
                )
                WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

            CREATE POLICY employees_isolation ON employees
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY wards_isolation ON wards
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY shift_definitions_isolation ON shift_definitions
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY rosters_isolation ON rosters
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY duty_assignments_isolation ON duty_assignments
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY roster_versions_isolation ON roster_versions
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY rule_sets_isolation ON rule_sets
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY rule_set_rules_isolation ON rule_set_rules
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);
            CREATE POLICY idempotency_keys_isolation ON idempotency_keys
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid);

            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$role};
            GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$role};
            REVOKE UPDATE, DELETE ON TABLE roster_versions FROM {$role};
            GRANT EXECUTE ON FUNCTION enforce_duty_assignment_integrity() TO {$role};
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS idempotency_keys;
            DROP TABLE IF EXISTS rule_set_rules;
            DROP TABLE IF EXISTS rule_sets;
            DROP TABLE IF EXISTS roster_versions;
            DROP TABLE IF EXISTS duty_assignments;
            DROP TABLE IF EXISTS rosters;
            DROP TABLE IF EXISTS shift_definitions;
            DROP TABLE IF EXISTS wards;
            DROP TABLE IF EXISTS employees;
            DROP TABLE IF EXISTS memberships;
            DROP TABLE IF EXISTS tenants;
            DROP FUNCTION IF EXISTS enforce_duty_assignment_integrity();
        SQL);
    }
};

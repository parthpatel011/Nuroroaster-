# Architecture and operations

This note describes the first vertical slice. It is not a production-readiness claim, and nothing here has been load-tested.

## What runs

One Laravel 13 application serves a React TypeScript interface and a JSON API from the same origin. PostgreSQL 16 is the only required service. There is no Redis, queue worker, object storage, or separate frontend host in this slice.

```text
Browser (React, Vite build)
        │  same-origin session cookie
        ▼
Laravel HTTP  ── tenant context ── PostgreSQL
        │
        └── database queue / cache / sessions (tables only; no worker is required yet)
```

PHP modules live under `app/Domain`:

| Module | Responsibility |
| --- | --- |
| Tenancy | Hospital, membership, request-scoped tenant context, row-level security setting |
| Identity | User account and login |
| Organisation | Wards |
| Staff | Staff directory |
| Shifts | Shift definitions and local-time window math |
| Rosters | Draft rosters, assignments, future snapshot type |
| Rules | Pure rule contract. Integrity stays in the database |
| Shared | Idempotent job middleware and private export disk |

Controllers are thin: a form request validates input, a policy authorizes the current membership, and an action or query performs the work. Domain code does not read `tenant_id` from the request body.

## Tenant isolation

Every hospital-owned table has `tenant_id`, a unique `(tenant_id, id)`, and composite foreign keys so a row cannot point at another hospital's parent. PostgreSQL row-level security is enabled and forced. Operational policies compare `tenant_id` to `current_setting('app.tenant_id')` and fail if that setting is missing or not a UUID.

The web and queue runtime connects as `nurseroster_app`, which is not a superuser and does not have `BYPASSRLS`. Migrations connect as `nurseroster_owner`, which also does not bypass row-level security. Tests assert both facts, assert the app role does not own the tables, and assert that `SET row_security = off` makes a tenant query fail instead of returning rows. That is the PostgreSQL behavior for a non-owner: the setting does not reveal other hospitals.

The tenant id is taken from the signed-in user's membership stored in the session. It is reset at the end of the request and in a `finally` block around each tenant-aware job. A missing reset is still fail-closed because an empty setting cannot be cast to a UUID.

Honest limit: anyone who can open a SQL session as `nurseroster_app` can `set_config('app.tenant_id', ...)` and then read that hospital. Row-level security stops a forgotten `WHERE`, not a stolen database password. Keep the database on a private network and use a different password from the local fixture.

## Time

Instants are `timestamptz` and the database session is UTC. Each hospital has an IANA `timezone`. Shift definitions store local wall-clock times. A duty date is the local date of the start. If the end clock time is at or before the start, the end is the next local day. Overlap uses a half-open `tstzrange`, so a shift that ends at 23:00 does not overlap one that starts at 23:00. The exclusion constraint covers every roster for that staff member, including overnight duties.

## Auth and requests

The UI and API share the Laravel session cookie (`SameSite=Lax`, `HttpOnly`). Mutating routes sit on the `web` middleware group, so cross-site requests need the CSRF token or a browser `Sec-Fetch-Site: same-origin` header. Login is limited to 5 attempts per minute per email and IP. Passwords are hashed. Login failures return a generic problem response and the login action does not log the password. List endpoints cap `per_page` at 50. The roster grid eager-loads the ward, assignments, and shift definitions, and lazy loading is disabled so an N+1 throws.

Laravel's HTTP test client skips CSRF mismatch checks while `APP_ENV=testing`. The route test checks that login is on the `web` stack. A manual request without a token or `Sec-Fetch-Site` header is the runtime check; see the README for the result of that check in this environment.

## Scaling path

The application process stores no tenant or session state. Sessions, cache, and the queue use PostgreSQL, so more than one PHP-FPM or `php artisan serve` process can share them without sticky sessions. That is the intended first scale-out. It is not evidence that the app has been run that way.

When those tables become hot, move cache and queues to Redis and keep PostgreSQL as the system of record. Do not introduce extra deployable services before that pain exists. Queue jobs that touch hospital data must use `ApplyTenantContext`, and jobs that must not run twice must use `ClaimIdempotency`. The idempotency insert uses a savepoint so a duplicate key does not abort the surrounding transaction.

Future PDF and PNG files go through `PrivateExportStore` on the `exports` disk. That disk is local and private, and Laravel will not serve it. A second app server cannot see files written on the first. Switch the disk to S3-compatible storage before running more than one app server, and do not put roster files on a public URL.

`roster_versions` is append-only for the app role (`INSERT` and `SELECT` only). Nothing writes a snapshot yet. `rule_set_rules` rejects `BR-01` and `BR-03` and only allows `block` for a short allow-list. Packs are warning-first by convention. The overlap exclusion and the inactive-staff trigger are the hard rules.

## Known limitations

- No publish, revision, export, share, attendance, leave, or messaging workflow exists. The UI says so.
- Login is email and password for seeded accounts. Employee-code and phone login are not built.
- A person with two memberships is signed into the oldest active one. There is no hospital switcher.
- Supervisors are not limited to a subset of wards.
- The database queue table can carry a tenant id in the job payload. No HTTP endpoint enqueues jobs. When one does, the dispatcher must copy the tenant id from `TenantContext`, never from the client.
- Tests use the array session and cache drivers. The application defaults are the database drivers.
- This checkout has not been deployed, backed up, or measured under concurrent users.

## Conflicts in the planning documents

The attached plans disagree. This slice follows the later instruction when they conflict.

| Topic | Older plan | Decision used here |
| --- | --- | --- |
| WhatsApp | Some P0 text sends individual messages | Manual share of a PDF/PNG is the intended product behavior, and it is not built yet |
| Row-level security | One open decision says to evaluate it later | Enabled now, with a non-bypass runtime role |
| Rule severity | "Warning" and "error" are both used | Only overlap and inactive staff block. Other rules are not evaluated yet, and the table defaults that future work toward warnings |
| Business-rule numbers | The master plan and the tech note number rules differently | `BR-01` overlap and `BR-03` inactive staff, as in the tech note |
| Language | Some market text mentions Gujarati or Hindi | English UI |
| Leave balances and replacement finder | Some P0/P1 text includes them | Post-pilot, not in this slice |
| Login | Employee code or phone | Email and password for this slice |
| Timeline | A 10-week full MVP | This repository is the first slice only |

Prices, hosting regions, WhatsApp fees, and legal dates in the plans were not re-checked and are not used by the software.

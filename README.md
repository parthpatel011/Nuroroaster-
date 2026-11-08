# NurseRoster

NurseRoster is a multi-tenant duty roster for a small hospital. This repository is the first vertical slice: a hospital can sign in, keep staff, wards, and shift definitions, and build a draft roster. The draft is stored in PostgreSQL. Inactive staff and overlapping duties, including overnight shifts, are rejected by the database.

Publishing, PDF or PNG export, and WhatsApp sharing are not implemented. The screen says that. There is no patient data and the seed data is synthetic.

## Versions used while building this slice

| Tool | Version |
| --- | --- |
| PHP | 8.3.6 |
| Laravel | 13.40.0 |
| PostgreSQL | 16.15 |
| Node.js | 22.23.4 |
| npm | 10.9.9 |
| Vite | 8.3.6 |
| React | 19.2.x (lockfile) |
| TypeScript | 5.9.x (lockfile) |

CI uses PHP 8.3, PostgreSQL 16, and Node 22. Composer and npm lockfiles pin the libraries.

## Local setup

These commands were run on Ubuntu 24.04. PostgreSQL must be running locally, or use the Compose file below. No paid account is required.

```bash
sudo apt-get update
sudo apt-get install -y php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-pgsql php8.3-intl unzip postgresql postgresql-contrib
# Install Composer from https://getcomposer.org if `composer` is not on your PATH.

composer install
npm ci
bash bin/prepare-database.sh
cp .env.example .env
php artisan key:generate
```

Edit `.env` and replace both `replace-me` database passwords with the local fixture passwords created by the script: `nurseroster_app` and `nurseroster_owner`. Those values exist only for this disposable development database. They are also hard-coded in `phpunit.xml` so CI does not need a `.env` file. Do not reuse them for a shared or production database.

```bash
php artisan migrate --database=pgsql_owner --force
php artisan db:seed --force
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000`. For frontend hot reload in a second terminal, run `npm run dev` and keep `php artisan serve` running.

### Docker database instead of a host PostgreSQL

If Docker is installed and port 5432 is free:

```bash
docker compose up -d
PGPASSWORD=postgres bash bin/prepare-database.sh
```

The Compose init script creates the same roles on a fresh volume. Running the shell script afterwards is safe; it updates the roles if they already exist.

### Synthetic demo accounts

Password for every account: `local-demo-only-password`

| Email | Hospital | Role |
| --- | --- | --- |
| `admin.north@example.com` | Demo Hospital North | Hospital admin |
| `supervisor.north@example.com` | Demo Hospital North | Supervisor |
| `staff.north@example.com` | Demo Hospital North | Staff, read only |
| `admin.south@example.com` | Demo Hospital South | Hospital admin |

Staff names look like `Nurse Demo 01`. They are not real people.

## Tests and checks

```bash
composer lint          # Pint
php artisan test       # PHPUnit on PostgreSQL, including row-level security
npm run lint           # ESLint
npm run typecheck      # tsc --noEmit
npm test               # Vitest
npm run build          # Vite production build
```

`php artisan test` creates the schema with `nurseroster_owner` and runs queries as `nurseroster_app`. SQLite is not used. The tests cover cross-hospital denial, role authorization, inactive staff, same-nurse overlap, overnight duties, and roster persistence.

GitHub Actions runs the same PHP and Node checks. PHPUnit's HTTP client does not enforce CSRF tokens. Outside tests, a login POST without a token or `Sec-Fetch-Site: same-origin` returns 419. Set `SESSION_SECURE_COOKIE=true` when the app is served over HTTPS.

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for tenancy, time, the scaling path, and the planning conflicts. Short version: one Laravel process, one PostgreSQL database, session cookies, and row-level security. More PHP processes can share the database. Do not add Redis or object storage until a real bottleneck shows up. Do not split this into services.

## What is deferred

- Publish, immutable snapshot writer, revision, and diff
- PDF, PNG, and Excel export, and the manual WhatsApp share button
- WhatsApp Business API, SMS, email, and any paid messaging
- Leave requests, leave balances, and the replacement finder
- Attendance, care slots, and coverage or hours rules
- Employee-code or phone login, MFA, and a hospital switcher
- Department and branch management, staff import, and audit UI
- Production hosting, backups, and load testing

The database already has `roster_versions` (insert-only for the app role) and `rule_set_rules` (warning-first, and `BR-01` / `BR-03` cannot be configured). Those tables have no product UI.

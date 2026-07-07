# MINITOR

An open-source, self-hosted alternative to [Laravel Nightwatch](https://nightwatch.laravel.com).

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Nightwatch instruments your Laravel app and streams telemetry to Laravel's
hosted dashboard. MINITOR reuses that same official instrumentation package
([`laravel/nightwatch`](https://github.com/laravel/nightwatch), unmodified)
but replaces the closed-source hosted pieces with two open, self-hostable
components:

- **This repo** — the MINITOR dashboard you self-host once: an ingest API,
  Postgres storage, and an Inertia + Vue UI covering every Nightwatch record
  type, per project — requests, queries, exceptions (grouped into issues,
  plus a raw feed), queued jobs and their processing attempts, artisan
  commands, scheduled tasks, cache events, outgoing HTTP requests, logs, and
  users seen.
- **[minitor-agent](https://github.com/enioladev1/minitor-agent)** — a
  Composer package installed on each Laravel app you want to monitor. It
  stands in for Nightwatch's closed-source local agent, speaking the same
  wire protocol, and forwards telemetry here instead of to Laravel's
  hosted service.

## Features

- **Requests** — throughput, status codes, and duration per route, with a
  per-request timeline.
- **Queries** — slow queries grouped by normalized SQL, per connection.
- **Exceptions** — occurrences grouped into issues (fingerprinted by
  class/file/line), with status tracking and comments.
- **Queued jobs** — per-queue throughput, duration, and failures, down to
  individual processing attempts.
- **Commands & scheduled tasks** — artisan runs and their exit codes/duration.
- **Cache** — hit/miss rates per key.
- **Outgoing requests** — HTTP calls your app makes, grouped by host.
- **Logs** and **users seen** — a raw log feed and which users hit which
  requests.
- Everything above is scoped **per project**, so one self-hosted MINITOR
  instance can monitor any number of separate Laravel apps.

## Quick start

1. Stand up the dashboard:

   ```bash
   git clone https://github.com/enioladev1/minitor.git
   cd minitor
   cp .env.docker.example .env   # fill in APP_KEY (instructions inside the file)
   docker compose up -d
   ```

2. Visit `http://localhost:8000`, register the operator account, and
   create your first project. Open that project's Settings dialog and copy
   its install snippet — both values in it (`NIGHTWATCH_TOKEN` and
   `MINITOR_PROJECT_TOKEN`) are generated for you; there's nothing to
   invent or type yourself.

3. On the Laravel app you want to monitor:

   ```bash
   composer require minitor/agent
   ```

   Paste the snippet you copied into its `.env`:

   ```
   NIGHTWATCH_TOKEN=<generated for this project>
   MINITOR_SERVER_URL=https://your-minitor-host
   MINITOR_PROJECT_TOKEN=<generated for this project>
   ```

   Then run the agent (keep it alive with Supervisor/systemd/your process
   manager of choice):

   ```bash
   php artisan minitor:agent
   ```

4. Watch that project's dashboard fill up with real traffic. Repeat step 3
   for each additional app — each gets its own key and fully separate data.

Full agent install/config details:
[github.com/enioladev1/minitor-agent](https://github.com/enioladev1/minitor-agent).

## Run without Docker

Requirements: PHP 8.3+, Composer, Node 20+, PostgreSQL 14+.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# set DB_* in .env to point at your Postgres instance
php artisan migrate
php artisan serve
```

## How data gets here

1. A monitored Laravel app runs the official `laravel/nightwatch` package
   (installed automatically by `minitor/agent`).
2. `minitor/agent` runs alongside that app, decodes Nightwatch's telemetry
   locally, and batches it upstream to this server's `POST /api/ingest`,
   authenticated with the project's key.
3. `App\Services\Ingest\RecordIngestor` persists each record into
   project-scoped tables — one per Nightwatch record type (`requests`,
   `queries`, `exceptions` grouped into `issues`, `queued_jobs` and
   `job_attempts`, `commands`, `scheduled_tasks`, `cache_events`,
   `outgoing_requests`, `logs`, `monitored_users`).
4. The dashboard (Inertia + Vue) reads from those tables per project.

Each project has two generated credentials, both shown together in its
Settings dialog:

- **`token`** (`MINITOR_PROJECT_TOKEN`) — authenticates that monitored
  app's agent to *this* server over the network, when it calls
  `POST /api/ingest`.
- **`local_secret`** (`NIGHTWATCH_TOKEN`) — a local-only handshake secret
  between `laravel/nightwatch` and `minitor/agent` on the *monitored
  app's own server*; it never reaches this dashboard over the network.
  It's named `NIGHTWATCH_TOKEN` because that's the exact env var the
  real, unmodified `laravel/nightwatch` package reads — we can't rename
  it without forking that package. Regenerating a project rotates both.

## Why this exists

Nightwatch's instrumentation package is MIT-licensed and open — it's the
local agent that receives from it, and the hosted dashboard, that aren't.
MINITOR keeps the official instrumentation as-is and replaces only the
closed pieces, so teams that want to self-host can, without giving up
Laravel's own telemetry code.

## Data retention

MVP does not yet prune old data automatically — plan for periodic cleanup
of the `requests` / `queries` / `exceptions` tables if running for a long
time at real traffic volume.

## License

MIT

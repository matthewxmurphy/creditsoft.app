# CreditSoft Docker Intranet Node

CreditSoft can run as a repeatable Docker intranet node. The goal is not to hide the local-first design; it is to make each office server easier to install, backup, move, and join into a node pool.

## What Runs

The default compose stack runs:

- `intranet`: Laravel/Vue app on port `8001`.
- `queue`: Laravel database queue worker.
- `scheduler`: Laravel scheduler worker.

Optional profiles:

- `office`: runs the full office stack in one Compose profile.
- `router`: runs the Node loopback router against the intranet container.
- `postgres`: runs CreditSoft against PostgreSQL instead of SQLite.
- `crm`: runs the white-label CRM sidecar stack with server, worker, shared Postgres server, and Redis.

## Full Office Stack

For the productized install path, prefer one office profile instead of hand-starting every lane:

```bash
docker compose --env-file .env.docker --profile office up -d
```

When running this directly from source, set the PostgreSQL `DB_*` values shown below before starting the profile. The generated installer does that automatically.

That still runs multiple containers under one Compose project. That is intentional: the database, Redis, queue, scheduler, router, intranet app, and CRM sidecar each keep their own lifecycle while the installer gives the office one obvious command. A literal single container would make database recovery and sidecar updates harder.

The generated installer exposes the same path:

```bash
bash install.sh --office
```

```powershell
.\install.ps1 -Office
```

## First Run

```bash
cp .env.docker.example .env.docker
docker compose --env-file .env.docker build intranet
docker compose --env-file .env.docker up -d intranet queue scheduler
```

For a real office install, set a stable `APP_KEY` in `.env.docker` before moving data between machines. If it is left empty, the container creates a persistent local key inside the `creditsoft_storage` volume so restarts keep working, but that key is not ideal for production moves.

Then open:

```text
http://127.0.0.1:8001
```

## Persistent Data

The compose stack keeps these as Docker volumes:

- `creditsoft_storage`: uploads, browser captures, backups, installer state, and private app files.
- `creditsoft_cache`: Laravel bootstrap cache.
- `office_pg_data`: PostgreSQL data for the CreditSoft intranet and CRM sidecar databases.

Do not delete those volumes unless you intend to reset the office node.

## PostgreSQL Mode

PostgreSQL is the supported intranet database mode. Run CreditSoft with the `postgres` profile and keep the DB values in `.env.docker` pointed at `office-db`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=office-db
DB_PORT=5432
DB_DATABASE=creditsoft
DB_USERNAME=creditsoft
DB_PASSWORD=<same value as CREDITSOFT_PG_PASSWORD>
OFFICE_PG_PUBLIC_PORT=5432
```

Then start the stack with:

```bash
docker compose --env-file .env.docker --profile postgres up -d office-db intranet queue scheduler
```

The generated intranet node installer can do this automatically with `bash install.sh --postgres`, `.\install.ps1 -Postgres`, or the full-stack `--office` / `-Office` mode.

Important: this means one shared database server, not one shared database. CreditSoft and the CRM sidecar keep separate databases and users unless we deliberately build a sync layer between them.

## Router Profile

The router profile exposes a local front door:

```bash
docker compose --env-file .env.docker --profile router up -d local-router
```

That gives the workstation/container host:

```text
http://127.0.0.1:8877/dashboard?source=intranet-client
```

The router proxies to the `intranet` service over Docker networking. On employee machines, the packaged router would point at the fastest healthy office node instead.

## CRM Sidecar Profile

The CRM should stay a white-label sidecar first. It has its own runtime and storage expectations, so the first CreditSoft integration should be:

- Run the CRM sidecar separately or with the optional profile.
- Set `CREDITSOFT_CRM_ENABLED=true`.
- Set `CREDITSOFT_CRM_BASE_URL`.
- Keep `CRM_IMAGE=creditsoft/crm-sidecar:local` and `CRM_BASE_IMAGE=update.creditsoft.app/creditsoft/crm-sidecar:latest` so the office builds a small local white-label layer over the CreditSoft-controlled CRM base image.
- Add API key/webhook sync only after the sidecar is reachable.

To start the optional sidecar:

```bash
docker compose --env-file .env.docker --profile crm up -d crm crm-worker
```

The included profile follows the upstream CRM container shape under the hood: app server, worker, Postgres 16, Redis, and persistent local storage. The public installer pulls the CreditSoft image alias from the update lane.

## Node Pool Direction

Multiple Dockerized intranet nodes can become a pool:

- Client router probes all candidate `/api/v1` endpoints.
- Fastest healthy app response wins.
- Database backups mirror through the cluster backup lane.
- Future work should add sync lag, write leadership, and failover safety before treating a backup node as writable.

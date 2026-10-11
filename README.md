# E-Commerce Code Challenge

An e-commerce application built with Laravel 13, Inertia, Vue 3, MySQL 8.4 and Redis. Guests browse and search the catalog, customers buy with a simulated payment gateway, and admins manage products and import them in bulk from CSV.

The sample CSV provided with the challenge was downloaded on **October 11, 2026**. It ships unchanged as [`database/data/sample-products.csv`](database/data/sample-products.csv) and is imported automatically the first time the app starts.

- [Quick start (Docker)](#quick-start-docker)
- [Demo accounts](#demo-accounts)
- [Seed data: how the seeders work](#seed-data-how-the-seeders-work)
- [Everyday commands](#everyday-commands)
- [Development mode with hot reload](#development-mode-with-hot-reload)
- [Running without Docker](#running-without-docker)
- [Running the tests](#running-the-tests)
- [Ports and configuration](#ports-and-configuration)
- [Troubleshooting](#troubleshooting)

## Quick start (Docker)

The only requirement is [Docker](https://docs.docker.com/get-docker/) with Compose v2 (Docker Desktop includes both). You don't need PHP, Composer, Node or MySQL on your machine, and you don't need to create a `.env` file.

```bash
git clone git@github.com:germanrochar/e-commerce-code-challenge.git
cd e-commerce-code-challenge
make setup
```

Without `make` (for example on Windows), run the same thing directly:

```bash
docker compose up -d --build --wait
```

The first build takes a few minutes. When the command returns, open **http://localhost:8080**.

On the first start, the containers:

1. Build a production image with PHP 8.4 behind nginx and PHP-FPM, with the frontend already compiled.
2. Start MySQL and Redis and wait until both are healthy.
3. Generate an application key and share it between all application containers.
4. Run the database migrations.
5. Seed the two demo accounts and import the sample CSV ([details below](#seed-data-how-the-seeders-work)).
6. Start a queue worker for regular jobs, a separate worker for CSV imports, and the scheduler.

Stopping and starting again keeps all data. Use `make fresh` to start over from an empty database.

## Demo accounts

| Role     | Email                  | Password   | What you can do                                                          |
| -------- | ---------------------- | ---------- | ------------------------------------------------------------------------ |
| Admin    | `admin@example.com`    | `password` | Everything a customer can, plus product CRUD and CSV imports at `/admin` |
| Customer | `customer@example.com` | `password` | Browse, add to cart, check out, see order history                        |

Guests can browse, search and fill a cart without an account. They're asked to log in or register when they check out.

## Seed data: how the seeders work

Yes, the project has seeders, and they run automatically. `DatabaseSeeder` calls two seeders in order.

**`DemoUsersSeeder`** creates the admin and customer accounts above.

- It looks each account up by email. Missing accounts are created as verified users with the password `password`.
- Existing accounts only get their role reset. Their name and password are left alone, so running the seeder again never overwrites anything you changed while testing.

**`SampleCatalogSeeder`** imports `database/data/sample-products.csv` through the same importer the admin CSV upload uses, so the seed data goes through the same validation and duplicate checks.

- It only runs when the `products` table is empty, deleted products included. Restarting the app never re-imports the file or brings back products you deleted.
- Of the file's 97 data rows, 85 products are created. The other 12 are 7 invalid rows, 3 duplicate SKUs and 2 empty rows.
- The import is credited to the admin account. Log in as admin and open **Admin → Imports** to see the import, each rejected row with its reason, and a download of the rejected rows.

**When the seeders run:**

| Situation                                | What happens                                                                            |
| ---------------------------------------- | --------------------------------------------------------------------------------------- |
| First `make setup` / `docker compose up` | Both seeders run: the accounts are created and the sample CSV is imported.              |
| Every later start of the `app` container | The seeders run again; the accounts are only re-checked and the catalog is left as is.  |
| `make fresh`                             | All Docker volumes are deleted, so the next start seeds everything from scratch.        |
| `make dev-fresh` (development stack)     | Runs `php artisan migrate:fresh --seed` without rebuilding containers.                  |
| Manually                                 | `docker compose exec app php artisan db:seed`, or `php artisan db:seed` without Docker. |

Model factories (`database/factories`) are only used by the test suite. The seeders don't use them because Faker is a development dependency and isn't installed in the production image.

To load more data, generate a large CSV and import it:

```bash
make generate-csv ROWS=100000            # writes ./products-100000.csv
make import FILE=products-100000.csv     # imports it from the command line with a progress bar
```

You can also upload any CSV at **Admin → Imports**. Uploads are processed in the background on the `imports` queue, and the page shows live progress.

## Everyday commands

Run `make` (or `make help`) to list every target. Each one is a thin wrapper around `docker compose`.

| Command                                         | What it does                                                        |
| ----------------------------------------------- | ------------------------------------------------------------------- |
| `make setup`                                    | Build and start everything, then print the URL and demo credentials |
| `make up` / `make down`                         | Start or stop the stack; data is kept                               |
| `make restart`                                  | Restart the app, both queue workers and the scheduler               |
| `make logs`                                     | Follow the logs of every container                                  |
| `make shell`                                    | Open a shell inside the app container                               |
| `make fresh`                                    | Delete all data and start again with fresh seed data                |
| `make test`                                     | Run the PHPUnit suite against MySQL in a container                  |
| `make lint`                                     | Run Pint and Larastan in a container                                |
| `make import FILE=path.csv`                     | Import a CSV from your machine                                      |
| `make generate-csv ROWS=100000`                 | Generate a large CSV for testing imports                            |
| `make loadtest SCRIPT=catalog`                  | Run a k6 load test against the running stack                        |
| `make dev` / `make dev-down` / `make dev-fresh` | Development stack, see below                                        |

## Development mode with hot reload

```bash
make dev
```

This runs the same services as `make setup`, with a few differences:

- Your working copy is mounted into the containers, so PHP changes apply on the next request.
- A Vite container provides hot module reload for the Vue code.
- [Mailpit](https://mailpit.axllent.org/) catches outgoing email at http://localhost:8025.
- Composer and npm dependencies are installed inside the containers on the first start.

Long-running workers keep the code they started with, so run `make restart` after changing jobs or other queued code.

## Running without Docker

You'll need:

- **PHP 8.4** with the `pdo_mysql`, `intl`, `bcmath` and `redis` extensions
- **Composer 2**
- **Node 22**
- **MySQL 8.4** and **Redis 7**

The simplest option is to keep MySQL and Redis in Docker and run everything else natively. `.env.example` already points to the ports Docker forwards (MySQL on `33060`, Redis on `63790`):

```bash
docker compose up -d mysql redis    # only the databases
composer setup                       # .env, app key, migrations + seeders, npm ci, frontend build
composer dev                         # web server, both queues, scheduler, logs and Vite
```

Open the URL that `composer dev` prints (http://localhost:8000 by default).

- `composer setup` copies `.env.example` to `.env` if it doesn't exist, generates the key, runs `migrate --seed`, links storage, installs npm packages and builds the frontend.
- `composer dev` runs `php artisan dev`. It starts the web server, a queue listener for both the `default` and `imports` queues (so CSV uploads get processed), the scheduler, a log viewer and Vite.

**No `redis` PHP extension?** Either install it with `pecl install redis`, or switch the Redis-backed drivers to the database in `.env`:

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

**Your own MySQL instead of Docker?** Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`, create the database, and run `php artisan migrate --seed`.

## Running the tests

The tests run against MySQL rather than SQLite, because the app relies on MySQL behaviour: full-text search, row locks and conditional stock updates.

```bash
make test
```

This builds the development image and runs `php artisan test` against a separate `testing` database. That database is created automatically when the MySQL container first starts, so your demo data is never touched.

To run the tests natively, start MySQL with `docker compose up -d mysql` and run `php artisan test`. `phpunit.xml` already points at the `testing` database on port `33060`.

Static analysis and formatting checks:

```bash
make lint                                    # Pint + Larastan (level 7) in Docker
vendor/bin/pint --test && vendor/bin/phpstan analyse
npm run check && npm run types:check         # frontend lint, format and TypeScript
```

GitHub Actions runs these checks on every push to every branch. It also builds the Docker stack from scratch and checks that the seeded catalog is served.

## Ports and configuration

| Service                           | Default address                                                         | Override with        |
| --------------------------------- | ----------------------------------------------------------------------- | -------------------- |
| Store (nginx + PHP-FPM)           | http://localhost:8080                                                   | `APP_PORT`           |
| MySQL                             | `127.0.0.1:33060` (user `ecommerce` / `secret`, root password `secret`) | `FORWARD_DB_PORT`    |
| Redis                             | `127.0.0.1:63790`                                                       | `FORWARD_REDIS_PORT` |
| Vite dev server (`make dev` only) | http://localhost:5173                                                   | `VITE_PORT`          |
| Mailpit (`make dev` only)         | http://localhost:8025                                                   | `MAILPIT_PORT`       |

The Docker stack's settings live in `docker-compose.yml`, not in `.env`, so a local `.env` used for native development doesn't change how the containers behave. Options you're likely to change are prefixed with `DOCKER_`:

| Variable                    | Default | Purpose                           |
| --------------------------- | ------- | --------------------------------- |
| `DOCKER_PAYMENT_LATENCY_MS` | `300`   | Simulated payment gateway latency |
| `DOCKER_FPM_MAX_CHILDREN`   | `16`    | PHP-FPM worker processes          |
| `DOCKER_OPCACHE_MEMORY_MB`  | `256`   | OPcache memory                    |
| `DOCKER_DB_READ_HOST`       | empty   | Optional MySQL read replica host  |

## Troubleshooting

- **Port 8080 (or 33060 or 63790) is already in use.** Choose another port when starting, for example `APP_PORT=8088 make setup`, or `APP_PORT=8088 docker compose up -d --build --wait`. The app URL follows the new port.
- **The first start seems stuck.** Run `make logs`. The app container prints a banner with the URL and credentials once migrations and seeding are done. On a cold machine, the image build and the first MySQL initialization can take a few minutes.
- **I want a clean slate.** Run `make fresh`. It deletes the database, Redis and uploaded files, then seeds again.
- **A CSV upload stays in "Queued".** The `queue-imports` container processes imports; check that it's running with `docker compose ps`. Without Docker, make sure `composer dev`, or `php artisan queue:work --queue=default,imports`, is running.

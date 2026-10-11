# E-Commerce Code Challenge

An e-commerce application built with Laravel 13, Inertia, Vue 3, MySQL 8.4 and Redis. Guests browse and search the catalog, customers buy with a simulated payment gateway, and admins manage products and import them in bulk from CSV.

**The application lives at http://localhost:8080.**

The sample CSV provided with the challenge was downloaded on **October 11, 2026**. It ships unchanged as [`database/data/sample-products.csv`](database/data/sample-products.csv) and is imported automatically the first time the app starts.

Design notes, decisions and how the project was built are in [docs/approach.md](docs/approach.md).

- [Quick start (Docker)](#quick-start-docker)
- [Demo accounts](#demo-accounts)
- [Test cards](#test-cards)
- [Everyday commands](#everyday-commands)
- [Development mode with hot reload](#development-mode-with-hot-reload)
- [Running the tests](#running-the-tests)
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
5. Create the two demo accounts and import the sample CSV.
6. Start a queue worker for regular jobs, a separate worker for CSV imports, and the scheduler.

Stopping and starting again keeps all data. Use `make fresh` to start over from an empty database.

## Demo accounts

| Role     | Email                  | Password   | What you can do                                                          |
| -------- | ---------------------- | ---------- | ------------------------------------------------------------------------ |
| Admin    | `admin@example.com`    | `password` | Everything a customer can, plus product CRUD and CSV imports at `/admin` |
| Customer | `customer@example.com` | `password` | Browse, add to cart, check out, see order history                        |

Guests can browse, search and fill a cart without an account. They're asked to log in or register when they check out.

## Test cards

Payments are simulated. No real card is charged. Use any future expiry (`12/34` works) and any 3-digit CVC (`123` works).

| Number                | Outcome            |
| --------------------- | ------------------ |
| `4242 4242 4242 4242` | Approved           |
| `4000 0000 0000 0002` | Declined           |
| `4000 0000 0000 9995` | Insufficient funds |
| `4000 0000 0000 0119` | Gateway error      |

The checkout page also has buttons that fill these numbers for you.

## Everyday commands

Run `make` (or `make help`) to list every target. Each one is a thin wrapper around `docker compose`.

| Command                                         | What it does                                                        |
| ----------------------------------------------- | ------------------------------------------------------------------- |
| `make setup`                                    | Build and start everything, then print the URL and demo credentials |
| `make up` / `make down`                         | Start or stop the stack; data is kept                               |
| `make restart`                                  | Restart the app, both queue workers and the scheduler               |
| `make logs`                                     | Follow the logs of every container                                  |
| `make shell`                                    | Open a shell inside the app container                               |
| `make fresh`                                    | Delete all data and start again with fresh demo data                |
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
- [Mailpit](https://mailpit.axllent.org/) catches outgoing email at http://localhost:8025, including order confirmation emails.
- Composer and npm dependencies are installed inside the containers on the first start.

Long-running workers keep the code they started with, so run `make restart` after changing jobs or other queued code.

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

GitHub Actions runs these checks on every push to every branch.

## Troubleshooting

- **Port 8080 (or 33060 or 63790) is already in use.** Choose another port when starting, for example `APP_PORT=8088 make setup`, or `APP_PORT=8088 docker compose up -d --build --wait`. The app URL follows the new port. Without Docker, change `SERVER_PORT` and `APP_URL` in `.env`.
- **The first start seems stuck.** Run `make logs`. The app container prints a banner with the URL and credentials once the database is ready. On a cold machine, the image build and the first MySQL initialization can take a few minutes.
- **I want a clean slate.** Run `make fresh`. It deletes the database, Redis and uploaded files, then loads the demo data again.
- **A CSV upload stays in "Queued".** The `queue-imports` container processes imports; check that it's running with `docker compose ps`. Without Docker, make sure `composer dev`, or `php artisan queue:work --queue=default,imports`, is running.

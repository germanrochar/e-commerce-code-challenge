# E-Commerce Code Challenge

An e-commerce application built with Laravel 13, Inertia, Vue 3, MySQL 8.4 and Redis. Guests browse and search the catalog, customers buy with a simulated payment gateway, and admins manage products and import them in bulk from CSV.

The sample CSV provided with the challenge was downloaded on **October 11, 2026**. It ships unchanged as [`database/data/sample-products.csv`](database/data/sample-products.csv) and is imported automatically the first time the app starts.

- [Quick start (Docker)](#quick-start-docker)
- [Demo accounts](#demo-accounts)
- [Everyday commands](#everyday-commands)
- [Development mode with hot reload](#development-mode-with-hot-reload)
- [Running the tests](#running-the-tests)
- [Troubleshooting](#troubleshooting)
- [Approach](#approach)
- [Decisions and alternatives considered](#decisions-and-alternatives-considered)
- [How the sample CSV's data problems are handled](#how-the-sample-csvs-data-problems-are-handled)
- [How AI was used](#how-ai-was-used)

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
- [Mailpit](https://mailpit.axllent.org/) catches outgoing email at http://localhost:8025.
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

GitHub Actions runs these checks on every push to every branch. It also builds the Docker stack from scratch and checks that the sample catalog is served.

## Troubleshooting

- **Port 8080 (or 33060 or 63790) is already in use.** Choose another port when starting, for example `APP_PORT=8088 make setup`, or `APP_PORT=8088 docker compose up -d --build --wait`. The app URL follows the new port. Without Docker, change `SERVER_PORT` and `APP_URL` in `.env`.
- **The first start seems stuck.** Run `make logs`. The app container prints a banner with the URL and credentials once the database is ready. On a cold machine, the image build and the first MySQL initialization can take a few minutes.
- **I want a clean slate.** Run `make fresh`. It deletes the database, Redis and uploaded files, then loads the demo data again.
- **A CSV upload stays in "Queued".** The `queue-imports` container processes imports; check that it's running with `docker compose ps`. Without Docker, make sure `composer dev`, or `php artisan queue:work --queue=default,imports`, is running.

## Approach

1. **Questions before code.** I started by listing the open questions in the brief and deciding each one before writing anything. Topics included: Livewire or Inertia, MySQL or SQLite, whether there are roles, what happens to duplicate SKUs, whether guests can shop, how payments are faked, and what "high traffic" means for the design. Several of those answers changed during planning; the biggest change, how duplicate SKUs are handled, is explained [below](#duplicate-skus-are-rejected-not-upserted).
2. **Read the data first.** The sample CSV is small but full of planted problems. Before writing the importer, I went through it row by row and wrote down the expected outcome: 85 products created, 7 invalid rows, 3 duplicates and 2 empty rows. Those numbers, and the message expected for each rejected row, became the importer's tests.
3. **A plan, then one feature branch at a time.** I wrote the plan up front, then built it in this order: CI, Docker, the domain model, admin CRUD, CSV import, then the catalog. Each feature lives on its own branch with [Conventional Commits](https://www.conventionalcommits.org/) and is merged into `main` with `--no-ff`, so the history shows one merge per feature.
4. **Test everything that matters, on the real database.** PHPUnit feature tests cover authorization, validation, the importer (including a 20,000-row file, a crash halfway through an import and a repeat import of the same file), search, filtering and sorting. They run on MySQL because the app depends on MySQL behaviour.
5. **Check it as a user would.** Every screen was clicked through in a browser on the development stack, and a 100,000-row import was run through the real queue worker while watching the progress page. CI builds the Docker stack from scratch on every push and checks that the seeded catalog is served, so "it works on my machine" is checked automatically.

## Decisions and alternatives considered

### Stack: Laravel with Inertia and Vue 3

Inertia keeps Laravel's routing, controllers, validation and authorization on the server, and renders the pages with Vue. That gives an SPA-like experience (instant filtering, live import progress, drawers and dialogs) without building and versioning a separate API.

- **Alternatives:** Livewire, which is great for forms but awkward for the very interactive catalog and import screens; a separate Vue or React SPA with a REST API, which means a second deployable, API auth and duplicated validation; plain Blade, which is too limited for the UI I wanted.
- **Starting point:** Laravel's official Vue starter kit (Fortify auth, Wayfinder typed routes, Tailwind 4, shadcn-style components), so the time went into the domain rather than login screens.

### MySQL, including for the tests

- **Why MySQL:** it's the database the brief suggests and the one most Laravel production apps run. The design relies on MySQL-specific behaviour: InnoDB full-text search, row locking and conditional `UPDATE … WHERE stock >= ?` statements for stock.
- **Tests run on MySQL, not in-memory SQLite.** SQLite would make the suite faster, but full-text search and locking behave differently there, so the tests would pass while hiding real bugs. The suite uses a separate `testing` database that Docker creates automatically.
- **Alternative:** PostgreSQL would work equally well. MySQL won on familiarity and on matching the brief.

### Money as integer cents

Prices are stored as `price_cents` (an unsigned integer). They go through a small `Money` value object that parses decimal strings with string arithmetic, so a float never touches a price.

- **Alternatives:** `DECIMAL(10,2)` columns work but invite float math in PHP; floats are simply wrong for money (`0.1 + 0.2`).
- **Validation:** prices with more than 2 decimals are rejected rather than rounded, so `12.999` is an error, not `13.00`.

### Categories in their own table

The CSV has a free-text `category` column. It's normalized into a `categories` table: whitespace is trimmed and the first letter is capitalized. A unique slug keeps `food & beverage` and `Food & Beverage` from becoming two categories.

- **Why:** cheap facet counts, clean URLs (`?category=home-office`), an index for filtering, and room for category pages later.
- **Alternatives:** a string column on `products`, which makes counts and renaming harder and lets near-duplicates creep in; a PHP enum, which is impossible because CSV imports can create new categories.

### Roles: a column and Laravel policies

Users have a `role` column (`admin` or `customer`). An `access-admin` gate protects the `/admin` area and `ProductPolicy` protects each action. The role can't be mass-assigned, so registration can never create an admin.

- **Alternative:** `spatie/laravel-permission`. That's worth it once there are many roles or editable permissions, but it's overkill for two fixed roles.

### Products are soft-deleted

Deleting a product hides it from the store and admin lists but keeps the row.

- **Why:** past orders keep a working link to what was bought, and an accidental delete can be undone in the database.
- **SKU reuse:** a deleted product's SKU stays reserved. Imports report it as "already exists" instead of quietly reusing it for a different product.
- **Alternative:** hard deletes with nullable order-item foreign keys. Order items already copy name, SKU and price, so this would also work, but it loses the audit trail.

### One set of validation rules for the form and the importer

`ProductRules` and `ProductInput` are shared by the admin form and the CSV importer, so a product that the form would reject can never enter through a CSV, and the reverse.

- **Normalization before validation:**
    - Whitespace is collapsed and trimmed.
    - Unicode is normalized.
    - SKUs are uppercased.
    - A leading currency symbol is stripped, so `$29.99` becomes `29.99`.
    - Empty strings become `null`.
- **HTML is rejected, not escaped.** A product named `<script>alert('xss')</script>` is almost certainly bad data, not a product. Vue escapes output anyway, so this is about data quality, not only XSS.
- **SQL-looking text is accepted.** `Robert'); DROP TABLE products;--` is stored as plain text. Every query uses parameter binding, so it's harmless, and rejecting apostrophes or semicolons would block legitimate names. A test checks it's stored verbatim.

### Duplicate SKUs are rejected, not upserted

This is the decision that changed during planning.

- **Original plan:** treat the SKU as the natural key and **upsert**. A repeated SKU, in the file or already in the database, would update the existing product, so the last row wins.
- **What I changed it to:** **duplicates are reported as errors and the row is skipped.**
    - **Within a file,** the first valid row with a SKU is imported. Later rows with that SKU are rejected with `Duplicate SKU 'RS-001': already used on row 2.`
    - **Against the database,** a SKU that already exists, including on a soft-deleted product, is rejected with `SKU 'RS-001' already exists.`
    - **An invalid first occurrence doesn't count:** if row 5 is invalid and row 9 has the same SKU and is valid, row 9 is imported.

Alternatives considered:

- **Upsert with last row winning:** rejected for the reasons above.
- **Upsert with first row winning:** still overwrites existing products.
- **Rejecting the whole file on the first problem:** one bad row in 100,000 would block the other 99,999, and the admin would have to fix the file one error at a time.
- **An "update existing products" switch on the upload form:** a reasonable future addition for admins who really do want bulk updates, kept out of scope here.

### CSV imports: streamed, chunked, queued and resumable

The brief's file has 97 rows, but I designed for files of 10,000 to 100,000+ rows.

- **Background job.** Uploads go to a dedicated `imports` queue with its own worker container, so a big file never ties up a web request or delays checkout emails on the default queue. The admin gets a progress page that updates every 2 seconds.
- **Streaming.** The file is read row by row with `league/csv`, so memory doesn't grow with file size. `league/csv` also handles byte-order marks, quoted commas, escaped quotes and Windows line endings, and Windows-1252 text is converted to UTF-8.
- **Chunks of 1,000 rows.** Each chunk does one query to check which SKUs already exist, one batch insert for the products and one for the errors. Categories are cached in memory and created in bulk. A test imports 20,000 generated rows using fewer than 200 queries. With per-row Eloquent saves it would be tens of thousands.
- **Resumable.** Each chunk's products, errors and progress counters are committed in one transaction. If the worker dies, the retried job continues from the last committed row, and duplicate detection still references the right earlier row numbers. A test kills an import after the first chunk and checks the resumed result matches a clean run.
- **Race-safe.** If someone creates the same SKU in the admin while an import runs, the batch insert hits the unique index. The importer falls back to row-by-row inserts for that chunk and reports the clash as a duplicate instead of failing.
- **Flexible headers.** Columns are matched by name, not position, ignoring case, spaces and a byte-order mark; extra columns are ignored. A file missing required columns fails straight away with a message naming the missing columns.
- **Measured:** 100,000 rows import in about 43 seconds on the development image, which runs without OPcache.

### Import errors in their own table

Each problem is a row in `product_import_errors`, with the row number, SKU, column, type, message and the original values.

- **Why:** a 100,000-row file can produce thousands of errors. A table can be paginated and filtered (invalid or duplicate) on the import page, and streamed into the rejected-rows CSV.

### Catalog search: MySQL full-text search

Search uses an InnoDB `FULLTEXT` index on name and description in boolean mode.

- **Matching:** every word must match, word beginnings match (`speak` finds "Speaker"), and results are ranked by relevance. An exact SKU also matches.
- **Stopwords:** common words like "the" or "with" are dropped before searching, because MySQL ignores them and would otherwise return nothing.
- **Category counts** come from one grouped query that respects the current search, so the counts always match what you'd get by clicking the category.
- **Unexpected URL parameters fall back to defaults.** Search and filters are a public GET page that bots and old links will hit, so a bad `sort` or a `q[]=` array shouldn't produce an error page. A test found that `?q[]=x` caused a 500 error; it now falls back like any other bad parameter.
- **Measured:** with 100,000 products, catalog pages respond in 150–500 ms on the unoptimized development image.

Alternatives considered:

- **`LIKE '%term%'`:** can't use an index and gets slow with many products.

### Docker: nginx and PHP-FPM, zero configuration

The image is built on [serversideup/php](https://serversideup.net/open-source/docker-php/) with nginx and PHP-FPM.

- **Production tuning:** PHP-FPM uses a fixed pool of 16 workers, and Laravel's caches are built at startup.
- **Separate containers** run the web app, the default queue, the import queue and the scheduler. Each can be scaled on its own, and the web container keeps no state on its own disk.
- **Zero configuration:** a bootstrap script generates and shares the app key, waits for MySQL, runs migrations and seeds. Interviewers only need `make setup`, or one `docker compose` command without `make`.

### CI on every push

GitHub Actions runs on every push to every branch, merges included, but not when a pull request is opened, so each commit is tested exactly once. A new push cancels any run still in progress for the same branch.

It runs three jobs:

1. **Tests:** the PHPUnit suite on a MySQL service.
2. **Lint:** Pint, Larastan at level 7, frontend lint and format, and the TypeScript check.
3. **Docker smoke test:** builds the stack from scratch, waits for health checks, and checks that the sample products are served.

### Placeholder product images

The CSV has no images, so every product shows a placeholder SVG. Its background is tinted by category and it carries the category's icon, which keeps the grid readable and makes categories easy to tell apart. Image upload (S3 with responsive sizes) is a natural next step.

### Designed, not built yet

These parts are designed and the database tables exist, but they aren't implemented yet:

- **Cart:** a session cart for guests, merged into the user's saved cart when they register or log in, with quantities capped at available stock.
- **Checkout:**
    - Reserve stock with a single conditional `UPDATE … SET stock = stock - ? WHERE stock >= ?` per item, sorted by product ID to avoid deadlocks.
    - Charge the card outside the database transaction, then confirm the order or release the stock.
    - An idempotency key and a Redis lock prevent double submits.
    - A scheduled job releases reservations abandoned for 15 minutes.
- **Fake payment gateway:** behind a `PaymentGateway` interface.
    - The card form validates the card number (Luhn check), expiry and CVC.
    - Stripe-style test cards: `4242…` is approved, `4000…0002` is declined, `4000…9995` fails for insufficient funds, and `4000…0119` simulates a gateway error.
    - Only the card brand and last 4 digits are stored.
- **Order history and confirmation emails** sent through the queue.
- **High-traffic safeguards:**
    - Cached catalog queries, invalidated by a version number that changes when products change.
    - Rate limits for search, cart and checkout.
    - An optional MySQL read replica.
    - k6 load tests, including 200 shoppers racing for 50 units of stock to show there's no overselling.

## How AI was used

I built this project with an AI coding agent in Cursor, working as a pair programmer that I directed and reviewed.

- **I made the decisions.**
    - The agent's first job was to list the brief's open questions; I answered them: stack, database, roles, guest shopping, duplicate handling and payment behaviour.
    - I then reviewed the plan and changed it several times. I asked for PHPUnit instead of Pest and for guests to have a cart. I added category filters, sorting, pagination and placeholder images, and asked for a high-traffic design.
    - I dropped Octane and FrankenPHP and required imports to handle 10k+ rows.
    - I switched duplicate SKUs from upserts to reported errors.
    - I asked for a one-command setup for interviewers, and for CI on every push but not on pull requests.
- **The agent did most of the typing and checking.**
    - It wrote code and tests and ran the test suite, Pint, Larastan and the frontend checks after each change.
    - It clicked through screens in a browser and ran the large imports and timing measurements quoted above.
    - Each feature was committed on its own branch.
- **Verification caught real problems along the way.** Examples:
    - CI had been failing silently on a formatting check after the Docker work.
    - A demo admin seeded before the role column existed couldn't open the admin panel.
    - `?q[]=x` in the catalog URL caused a 500 error.
    - `composer dev` didn't process the import queue.
    - The sample CSV's line endings were being changed by git.

    Each problem was fixed, and a test was added where it made sense.

- **Comments were removed** from the code, as the brief requires when AI is used.

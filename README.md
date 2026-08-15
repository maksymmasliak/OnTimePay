# OnTimePay

A multi-tenant B2B invoicing platform. Companies manage their clients, issue invoices, collect payments through Stripe, and get automated reminders for overdue accounts.

## Features

- **Multi-tenant by design** — every record is scoped to a company via a global Eloquent scope; cross-tenant access is blocked at the query level, not just in application logic.
- **Role-based access control** — owner and manager roles with distinct permissions across invoices, users, and financial data.
- **Invoicing** — create, update, and send invoices with line items; PDF generation and email delivery run asynchronously via a queue.
- **Stripe payments** — hosted Checkout sessions and webhook-driven payment confirmation, with signature verification, idempotent event processing, and row-level locking to prevent double payment.
- **Ledger** — an append-only record of every payment, refund, and adjustment against an invoice.
- **Automated dunning** — a daily scheduled job escalates overdue invoices through reminder stages and into collections, with idempotent reminder delivery.
- **Self-service onboarding** — a company and its first owner are created through a public registration endpoint; owners can then invite additional users.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Database:** PostgreSQL 16
- **Cache & Queue:** Redis
- **Payments:** Stripe (Checkout, Webhooks)
- **Web Server:** Nginx, PHP-FPM
- **Containerization:** Docker Compose

## Architecture

**Request flow.** Every route (except registration, login, and the Stripe webhook) runs behind Sanctum token authentication. Authorization is layered: Laravel Policies decide *whether* an action is permitted, while a global `CompanyScope` — attached to every tenant-owned model — decides *what data is even visible* to the query, independent of the controller. A request for another company's invoice never reaches application logic; it fails at the query layer with a 404.

**Money-moving code paths run inside a database transaction with `lockForUpdate()`.** The Stripe webhook handler re-checks the invoice's current status inside the lock before recording a payment, closing the window for a double-charge from two concurrent checkout attempts.

**Background work** is split across three long-running containers sharing the same application image: the web process, a Redis-backed queue worker (invoice emails, reminder emails), and a scheduler (daily dunning escalation, ledger reconciliation).

**Idempotency** is enforced wherever an external system can retry a request: Stripe webhook events are deduplicated by event ID before processing, and dunning reminders are deduplicated per invoice/stage before a second reminder can be queued for the same milestone.

## API Overview

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Create a company and its first owner |
| POST | `/api/login` | Authenticate, receive a Sanctum token |
| POST | `/api/logout` | Revoke the current token |
| GET | `/api/users` | List users in your company *(owner only)* |
| POST | `/api/users` | Invite a user with a temporary password *(owner only)* |
| GET / PATCH | `/api/users/{id}` | View or update a user (self, or any user if owner) |
| DELETE | `/api/users/{id}` | Remove a user *(owner only, not self)* |
| GET / POST | `/api/clients` | List or create clients |
| GET / PATCH / DELETE | `/api/clients/{id}` | View, update, or remove a client |
| GET / POST | `/api/invoices` | List or create invoices |
| GET / PATCH / DELETE | `/api/invoices/{id}` | View, update, or remove an invoice *(mutations require draft status)* |
| POST | `/api/invoices/{id}/send` | Email the invoice as a PDF |
| GET | `/api/invoices/{id}/ledger` | View the invoice's payment balance *(owner only)* |
| POST | `/api/invoices/{id}/checkout` | Create a Stripe Checkout session |
| POST | `/api/webhooks/stripe` | Stripe webhook receiver (signature-verified) |

## Getting Started

**Prerequisites:** Docker and Docker Compose.

```bash
git clone https://github.com/maksymmasliak/OnTimePay.git
cd OnTimePay

cp .env.example .env
cp src/.env.example src/.env

docker compose up -d
docker compose exec app php artisan key:generate
```

That's it — dependencies and database migrations run automatically as part of `docker compose up`. Visit `http://localhost:8080`.

To accept real test payments, add your Stripe test-mode keys to `src/.env` (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) — get them from the [Stripe Dashboard](https://dashboard.stripe.com/test/apikeys) and, for local webhook testing, the [Stripe CLI](https://stripe.com/docs/stripe-cli).

## Available Commands

| Command | Description |
|---|---|
| `make up` | Start all containers |
| `make down` | Stop all containers |
| `make shell` | Open a shell inside the app container |
| `make migrate` | Run database migrations |
| `make fresh` | Drop all tables and re-run migrations |
| `make seed` | Seed the database |
| `make test` | Run the test suite |
| `make logs` | Follow logs from all containers |

## Testing

113 automated tests cover tenant isolation, RBAC across every role/action combination, Stripe checkout and webhook handling (including signature failure, replayed events, and concurrent double-payment attempts), dunning escalation, and job/mail delivery.

```bash
make test
```

## Known Limitations

A few gaps were identified during review and deliberately left as-is, rather than expanded into features outside the project's scope:

- **Failed payment events aren't handled.** The webhook only processes `checkout.session.completed`; a declined card produces no record on the OnTimePay side (the customer is notified by their bank directly).
- **No password reset flow.** A user can change their own password once authenticated (with current-password confirmation), but there's no "forgot password" path — a locked-out user has to be reset by an owner.
- **User deletion is permanent.** Unlike clients and invoices, users aren't soft-deleted.
- **Checkout creation and ledger visibility are intentionally asymmetric.** Any company member can generate a payment link for an invoice, but only an owner can view the aggregated ledger balance — initiating a collection attempt is treated as lower-sensitivity than seeing the company's financial position.
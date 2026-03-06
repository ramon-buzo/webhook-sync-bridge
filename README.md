# webhook-sync-bridge

A portfolio project demonstrating an **async-to-sync webhook bridge** using Redis BLPOP — a pattern used in production billing and subscription systems to present a synchronous user experience over an inherently asynchronous partner notification flow.

---

## The Problem

Third-party billing partners (telcos, payment gateways) commonly work like this:

1. You call their API to initiate a subscription → they respond with an ACK (acknowledgement)
2. Seconds later, they fire an XML webhook to notify you of the actual result

This creates a UX challenge: the user is waiting for a confirmation, but the real answer arrives asynchronously via a webhook. How do you make this feel instant and synchronous to the user?

**The solution:** block the user's HTTP request on a Redis `BLPOP` key. When the webhook arrives, it pushes the result to that key, waking up the blocked request. The user sees an instant result — the async complexity is invisible.

---

## Architecture

```
User (browser)
    │
    │  POST /subscribe.php
    ▼
Backend (PHP)
    │
    ├─► Partner API ──► ACK 0 (success) or ACK 1 (error)
    │
    ├─► Redis BLPOP (blocks, max 5s)
    │        │
    │        │   (1–3s later)
    │        │
    │   Partner fires XML webhook
    │        │
    │        ▼
    │   POST /webhook.php
    │        │
    │        └─► Validates HMAC signature + timestamp
    │            └─► Redis LPUSH (wakes up BLPOP)
    │
    └─► Returns result to user
```

---

## Design Patterns

### Value Objects (DDD)
`SubscriptionRequest` and `WebhookPayload` encapsulate data as immutable, type-safe objects. They replace primitive arrays as parameters, making the code more expressive and preventing accidental mutation.

### Interface Segregation + Dependency Inversion (SOLID)
`CacheClientInterface` and `PartnerClientInterface` define contracts that services depend on — not concrete implementations. This means `RedisClient` can be swapped for Memcached, or `PartnerClient` can be swapped for a real partner without touching the business logic.

### Adapter Pattern
`RedisClient` and `PartnerClient` adapt external APIs (PHP Redis extension, cURL HTTP) to the application's internal contracts. Infrastructure details are isolated behind these adapters.

### Service Layer Pattern
`SubscriptionService` and `WebhookProcessor` contain the business logic. They are unaware of HTTP, databases, or infrastructure — they only orchestrate domain operations.

### Dependency Injection + IoC Container
`Container.php` wires all dependencies together. Classes never instantiate their own dependencies — the container builds and injects them. This makes the entire dependency graph explicit and testable.

---

## Webhook Security

Partner webhooks are authenticated using **HMAC-SHA256 with timestamp**:

1. Partner generates: `HMAC-SHA256(timestamp + xml_body, client_secret)`
2. Partner embeds the signature and timestamp inside the XML payload
3. Backend extracts the signature, rebuilds the signed string, and compares
4. Timestamps older than 5 minutes are rejected — this prevents **replay attacks**

---

## Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.2-FPM |
| Web server | Nginx |
| Cache / bridge | Redis 7 |
| Partner simulation | PHP built-in server |
| Dependency management | Composer (PSR-4 autoload) |
| Containerization | Docker + Docker Compose |

---

## Project Structure

```
app/
├── public/
│   ├── index.html          # Landing page / demo UI
│   ├── subscribe.php       # Entry point — initiates subscription flow
│   ├── webhook.php         # Entry point — receives partner webhook
│   └── health.php          # Health check endpoint
├── src/
│   ├── Contracts/
│   │   ├── CacheClientInterface.php
│   │   └── PartnerClientInterface.php
│   ├── Infrastructure/
│   │   ├── RedisClient.php
│   │   └── PartnerClient.php
│   ├── Services/
│   │   ├── SubscriptionService.php
│   │   └── WebhookProcessor.php
│   ├── ValueObjects/
│   │   ├── SubscriptionRequest.php
│   │   └── WebhookPayload.php
│   └── Container.php
├── composer.json
├── Dockerfile
└── nginx.conf
partner-mock/
└── index.php               # Simulates partner API + async webhook firing
```

---

## Installation

### Prerequisites

- Docker
- Docker Compose

### 1. Clone the repository

```bash
git clone git@github.com:ramon-buzo/webhook-sync-bridge.git
cd webhook-sync-bridge
```

### 2. Create environment file

```bash
cp .env.example .env
```

Edit `.env` and set your values:

```env
WEBHOOK_URL=http://app/webhook.php
PARTNER_MOCK_URL=http://partner-mock:8082
PARTNER_CLIENT_SECRET=your_secret_here
```

### 3. Build and start containers

```bash
docker compose up -d --build
```

### 4. Install Composer dependencies on the host

Composer runs inside the container during the Docker build, generating the `vendor/` directory. To make it available on your host for IDE autocompletion and development, run:

```bash
mkdir -p app/vendor
docker compose exec app composer install
```

> **Note:** The `docker-compose.override.yml` mounts `./app/vendor` into the container for local development. On the VPS, a named Docker volume is used instead — the override file is not deployed.

### 5. Open the demo

```
http://localhost:8282
```

---

## Usage

- Fill in a phone number and select a plan
- Click **Subscribe now** and watch the flow stepper in real time
- Toggle **Simulate partner error** to test the failure path

### Health check

```
GET http://localhost:8282/health.php
```

```json
{
    "status": "ok",
    "version": "1.0.0",
    "timestamp": "2026-03-06T05:00:00+00:00",
    "checks": {
        "redis": "ok",
        "partner": "ok"
    }
}
```

---

## Deployment

The project uses GitHub Actions for CI/CD. Three deploy modes are available via commit message tags:

| Commit message tag | Action |
|--------------------|--------|
| _(none)_ | `git pull` only — code updates instantly via volumes |
| `[deploy:restart]` | `docker compose down && up` — restart containers |
| `[deploy:reset]` | `docker compose down -v && up` — full reset including volumes |

---

## Live Demo

[subscription-demo.ramonbuzo.tech](https://subscription-demo.ramonbuzo.tech)
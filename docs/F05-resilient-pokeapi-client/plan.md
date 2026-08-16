# Implementation Plan: Resilient PokeAPI Client

**Prerequisites:**
- F01's stack running (`docker compose up -d`), so Redis and the `redis` cache/queue connections are available
- No outbound network access is required — the client is built and verified entirely against a faked upstream
- `docs/F05-resilient-pokeapi-client/spec.md` — this plan implements that document; consult it for exact key names, TTLs, and payload shapes

---

### Stage 1: Configuration Contract

**1. PokeAPI Configuration File** - Create the dedicated config file holding every tunable the client reads: base URI, connect and total timeouts, retry attempt count and backoff schedule, rate-limit ceiling and window, cache TTLs for successful and not-found results, circuit breaker thresholds, and the log channel name. Follow this project's existing pattern of one config file per integration, as already established for the queue worker and the WebSocket server.

**2. Dedicated Log Channel** - Add a single-driver logging channel that writes upstream failures to their own file, separate from the application's default log, so an incident is diagnosable without filtering.

**3. Environment Contract** - Add the one environment key this feature introduces to the example environment file, with a default that boots without editing. Every other tunable stays a fixed configuration default rather than an environment override, since the acceptance criteria depend on those exact values.

---

### Stage 2: Outcome Types and Core Client

**4. Outcome Enum and Result Object** - Create the backed enum representing the three outcomes a caller can receive, and the immutable value object that pairs a outcome with its payload. These are what every client method returns, so no caller ever needs to catch an exception to know what happened.

**5. Client Skeleton with Timeout and Retry** - Create the service class and its three public entry points for the index listing, a type roster, and a Pokémon's full detail. Wire the connect/total timeout and the bounded retry with exponential backoff around every upstream call, limiting retries to connection failures and the retryable HTTP statuses the spec lists, with a 404 short-circuiting straight to the not-found outcome.

**6. Response Cache** - Add the Redis read-through cache around each upstream call, keyed and scoped exactly as the spec's data model describes, with the longer TTL for successful payloads and the short TTL for not-found results. Ensure an unavailable outcome is never written to cache under any key.

**7. Detail Payload Assembly** - Implement the two-call sequence behind the detail method: the base Pokémon resource first, then the species resource for flavor text, merged into one result. A failed base call short-circuits the whole method; a failed species call degrades gracefully, leaving the flavor text absent rather than failing the result.

---

### Stage 3: Resilience Layer

**8. Outbound Rate Limiter** - Add the wait-based rate limiting in front of every upstream attempt, checked against the shared application-wide budget the spec defines. When the wait would fit inside the request's remaining time budget the client sleeps and proceeds; when it would not, the client returns unavailable without ever reaching the network.

**9. Circuit Breaker** - Add the failure counter and open-flag pair backed by the response cache's store, incrementing on every terminal failure and resetting on any success. Once the threshold trips, subsequent calls during the cooldown window return unavailable immediately, spending no retry or rate-limit budget, until the flag expires.

**10. Structured Failure Logging** - Log every upstream failure with its endpoint, status, attempt number, and elapsed time on the dedicated channel, and log a circuit trip at a higher severity than an ordinary failure. Gate the cache-outage warning to at most once per minute using a store that stays available even while the response cache's own store is down, per the spec's rationale.

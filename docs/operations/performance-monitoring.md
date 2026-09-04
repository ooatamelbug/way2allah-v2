# Performance Monitoring (Enhancement Batch E-01)

Lightweight, file-based observability for slow pages and slow SQL. No
external service, no extra package, no database writes.

## Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `PERFORMANCE_MONITORING_ENABLED` | `true` | Master switch. `false` = no listener, no timing, no logs. |
| `PERFORMANCE_WARNING_MS` | `400` | Requests at/over this are logged at `notice`. |
| `PERFORMANCE_SLOW_MS` | `1000` | …at `warning`. |
| `PERFORMANCE_CRITICAL_MS` | `2500` | …at `error`. |
| `PERFORMANCE_SLOW_QUERY_MS` | `200` | Individual queries at/over this are logged. |
| `PERFORMANCE_LOG_RETENTION_DAYS` | `14` | Daily rotation retention for both channels. |
| `PERFORMANCE_MAX_SQL_LENGTH` | `500` | Max SQL template length written. |

Requests faster than `PERFORMANCE_WARNING_MS` produce **no log line at all**.

Route exclusions live in `config/performance.php` (`excluded_routes`, matched
with `Request::is()`). Empty by default — deliberately broad visibility.

## Where the logs are

```
storage/logs/performance-YYYY-MM-DD.log   # one line per slow request
storage/logs/slow-sql-YYYY-MM-DD.log      # one line per slow query
```

Both rotate daily and are kept for 14 days. They are **separate from**
`laravel.log` on purpose, so operational signal doesn't bury real errors.
Their log levels are fixed, so tightening `LOG_LEVEL` to `error` in
production does not silently switch monitoring off.

## Correlating a request with its queries

Every request gets a `request_id` (UUID), written into both logs:

```bash
# 1. find the slowest requests today
grep -o '"duration_ms":[0-9.]*' storage/logs/performance-$(date +%F).log | sort -t: -k2 -rn | head

# 2. pull the offending request's id
grep 'khotab-item' storage/logs/performance-$(date +%F).log

# 3. see exactly which queries made it slow
grep '<request-id>' storage/logs/slow-sql-$(date +%F).log
```

## Answering the usual questions

- **Which pages are slow?** → `route_uri` / `route_name` in the performance log.
- **How slow?** → `duration_ms`, plus the `severity` bucket.
- **Was it the database?** → compare `total_query_ms` against `duration_ms`.
- **Which query?** → `slowest_query_ms`, then grep the request id in the slow-SQL log.
- **Which connection?** → `connection` in the slow-SQL log.
- **Memory-heavy?** → `peak_memory_bytes`.

## Disabling temporarily

```
PERFORMANCE_MONITORING_ENABLED=false
```
then `php artisan config:cache` (or `config:clear`). No code change, no deploy.

## Recommended initial production settings

Start with the defaults above. They are calibrated to this application as
measured: framework floor ~45 ms, healthy pages 30–120 ms, worst pages
2.4–2.9 s. Expect the first days to surface the khotab item/author pages
almost exclusively.

**After the known database findings (F-01/F-02) are repaired**, tighten to
roughly `WARNING=250`, `SLOW=600`, `CRITICAL=1500`, and
`SLOW_QUERY_MS=100` — otherwise the thresholds stop catching regressions.

## What is never logged

Cookies, authorization headers, session IDs, passwords, CSRF tokens,
request bodies, uploaded files, arbitrary query-string parameters, and
**SQL bindings**. The slow-SQL log stores only the parameterised template
(`where \`email\` = ?`), never the values bound into it.

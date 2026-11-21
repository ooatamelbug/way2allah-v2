# Download monitoring (Phase 3B)

`php artisan downloads:monitor [--window=60] [--json]` is a read-only health report.

- **Exit codes:** 0 ok, 1 warn, 2 critical, 3 unknown.
- **Not scheduled:** nothing in `routes/console.php` or cron runs it. Wiring it into a monitoring agent is an owner
  decision.
- **Thresholds:** `config/download_monitor.php`.

## Checks

| Check | Source | Status rules (defaults) |
|---|---|---|
| `slots` | slot files (`stat`) + `/proc/locks` | warn ≥ 80 % occupied, or any holder ≥ 2 h; critical ≥ 95 %, or any holder ≥ 12 h; unknown if `/proc/locks` is unreadable |
| `capacity_rejections` | `download_rejections` by `created_at` (index `idx_dr_created`), count capped at 100 000 | warn ≥ 1 in the window; critical ≥ 100 |
| `transfers` (stream failures and client disconnects) | `download_metrics_5m` by bucket (primary key prefix) | needs at least 10 failures: warn when the failure ratio is ≥ 5 %, critical when it is ≥ 25 %; disconnect ratio ≥ 60 % → warn |
| `spool` | `SpoolStatus` (directory listings bounded by `max_files`), `disk_free_space` | warn ≥ 50 % of the byte or file bound, or free disk < 2× the reserve; critical ≥ 80 %, or free disk < the reserve |
| `telemetry_drops` | drop-ledger shards (read-only snapshot) | warn on any drop, or `accounting_exact=false`; critical ≥ 1000 drops |
| `circuit_breaker` | breaker marker's mtime | critical while open |
| `pending_batches` | `sealed/` listing | warn ≥ 5 batches, or oldest ≥ 10 min; critical ≥ 20, or oldest ≥ 1 h |
| `rollup_lag` | age of the oldest sealed batch; `MAX(applied_at)` (indexed) | warn ≥ 15 min; critical ≥ 1 h. Nothing pending = no lag |
| `period_quality` | `download_period_status` by primary key, last 7 days | warn if any day is `incomplete` |
| `ip_ua_growth` | existing `IpUaGrowthMonitor` (25 bounded counts) | its own warnings; `rows_per_hour_critical` → critical |

A telemetry check whose feature is off reports `disabled`, which counts as ok. A check that throws reports `unknown`
with the exception class; the other checks still run.

## Safety

- **Never takes a slot.** Testing a slot's lock would itself be a slot holder for that instant, and could refuse a real
  download. Occupancy comes from matching each slot file's device and inode against the kernel lock table
  (`/proc/locks`, read up to 8 MiB). The holder's PID is reported; nothing is ever signalled or terminated.
- **Zero needs evidence (Phase 3B.1):**
  - Held slot locks found in the table are evidence on their own (`visibility=slot_locks_visible`).
  - When none are found, the inspector locks its own probe file `.monitor-visibility-probe` in the slot directory and
    looks for that lock in the table. The probe is never a slot: the guard only opens `slot-NNN.lock`. Zero is reported
    only if the probe is visible (`probe_visible`), or if no slot file exists at all (`no_slot_files`).
  - Otherwise occupancy is `unknown`, with `visibility` = `probe_not_visible`, `probe_unavailable`,
    `lock_table_truncated` or `lock_table_unreadable`.
  - Verified locally with a separate process holding slots on ext4. `/proc/locks` lists
    `FLOCK ADVISORY WRITE <pid> 08:11:<inode>`; the inspector matched both slots and the holder's PID, and reported
    zero with `probe_visible` after the holder exited.
  - Not verified: NFS, other mount namespaces and containers. In those cases the probe makes the result `unknown`
    rather than a false zero.
- **Slot age:** `DownloadCapacityGuard` now `touch()`es a slot file when it takes the slot. This is the only change on
  the download path, it is best effort, and it never affects acquisition. A held slot's age is `now − mtime`.
  - Slots taken by code from before this change, such as the current production processes, show their file's last mtime.
    Their age is therefore an upper bound, not exact.
- **No writes:** no database writes, verified with a query listener on SQLite and with `CHECKSUM TABLE` before and
  after on MariaDB 10.11. No spool directory is created and no slot file is changed; the only file the monitor ever
  creates is its empty probe file.
- **MariaDB 10.11 (disposable container), with realistic volume:** every query was run through `EXPLAIN`:
  - rejections: `range` on `idx_dr_created`, covering, with `LIMIT 100000` inside the derived count;
  - `MAX(created_at)` and `MAX(applied_at)`: "Select tables optimized away";
  - 5-minute metrics: `range` on the primary key (bucket prefix);
  - periods: `range` on the primary key, plus `idx_dps_state`;
  - IP/UA growth: `ref` on the primary key per hour, covering;
  - the only `ALL` rows are over the capped derived results, never a base table.
- **Bounded cost:** measured locally with 400 held slots, the slot check took about 10 ms and the full report about
  12–17 ms (SQLite in memory). The database work is limited to indexed range reads over a window of at most 24 hours.

## Not covered

- Per-process memory or CPU, and Apache worker saturation. Those belong to the host's monitoring.
- Rejections and stream outcomes before telemetry is enabled: the `transfers` check needs events, spool and rollup
  all on. Before that, stream failures exist only as `DOWNLOAD_SOURCE_*` log lines.

## Integration proposal (Phase 3C — prepared, NOT scheduled, no alert destination configured)

### Exit codes and how an agent should read them

| Exit | Overall | Meaning | Agent action |
|---|---|---|---|
| 0 | ok | Every enabled check is within thresholds (`disabled` counts as ok) | resolve any open alert (recovery) |
| 1 | warn | At least one check is in warning | warning alert, after the warn debounce |
| 2 | critical | At least one check is critical | page, after the critical debounce |
| 3 | unknown | At least one check could not be evaluated, and none is warn or critical | "monitoring degraded" ticket; **never treated as ok** |
| other / timeout | — | The command itself failed (PHP fatal, timeout) | the same as 3 |

- **What a critical outranks:** `unknown` ranks below warn and critical. A critical problem is therefore never hidden
  by an unknown check, while an unknown check is never silently treated as ok.
- **Per-check detail:** the agent should run with `--json` and alert on each `checks.<name>.status`, not only on
  `overall`.

### Alert thresholds (defaults in `config/download_monitor.php`)

Unchanged from the table above.

- **Most urgent:** `slots` critical (≥ 95 % occupied, or a holder older than 12 h) and `circuit_breaker` open. Both are
  operationally urgent; the first is the 2026-11-10 incident pattern.
- **Owner review needed:** `capacity_rejections` warns from the first rejection. If brief bursts are normal at peak,
  raise `rejections_warn`.

### Routing (proposal; destinations to be named by the owner)

| Check | Warn | Critical | Unknown |
|---|---|---|---|
| `slots`, `capacity_rejections` | operations channel | operations on-call (page) | operations channel |
| `transfers` (stream failures) | operations channel | operations channel | — |
| `spool`, `telemetry_drops`, `circuit_breaker`, `pending_batches`, `rollup_lag` | telemetry owner (ticket) | telemetry owner (ticket); never a page | telemetry owner |
| `period_quality`, `ip_ua_growth` | telemetry owner (daily digest) | telemetry owner (ticket) | — |

Telemetry is observational, so its failures never page: downloads are unaffected by them, as Phase 3C verified.

### Deduplication, cooldown, recovery

- **Alert key:** `downloads:<check>:<status>`. One open alert per check; a change from warn to critical is an
  escalation of the same alert, not a new one.
- **Debounce:**
  - warn: 2 consecutive runs;
  - critical: 1 run for `slots` and `circuit_breaker`, 2 runs for everything else;
  - with the proposed 5-minute interval, a warn fires after about 10 minutes.
- **Cooldown:** a re-notification for the same open alert at most every 60 minutes (warn) or 30 minutes (critical),
  with the latest numbers.
- **Recovery:** after 2 consecutive runs at ok or disabled, send one recovery notice with the duration and the worst
  value seen. An `unknown` never counts toward recovery.
- **Flapping:** more than 4 open/close cycles in an hour → hold the alert open, and send one "flapping" notice.

### Unknown-state handling

- `unknown` means "cannot tell": an unreadable lock table, a failed query, or a missing table. The check's `error` or
  `visibility` field says why.
- **Treatment:** a ticket after 3 consecutive unknown runs. A degraded monitor is a monitoring problem, not a download
  problem.
- **Zero is never assumed:** slot occupancy is reported as unknown rather than 0 whenever there is no evidence (see
  "Zero needs evidence").

### Proposed schedule (NOT configured)

- **Command:** `downloads:monitor --json --window=15`, every 5 minutes, from the system scheduler.
- **Overlap and time limit:** `withoutOverlapping`, with a 60 s timeout.
- **Cost:** a full run measured at 12–40 ms locally, plus about 25 bounded IP/UA counts.
- **State storage:** the alert state (debounce counters, open alerts) belongs in the agent, not in Laravel. The
  command stays stateless and read-only.

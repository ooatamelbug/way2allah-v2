# Local workstation disk usage: investigation and cleanup proposal (read only)

**Date:** 2026-11-14.

**Scope:** the developer workstation's root filesystem. **Nothing was deleted, moved or modified.** No Docker image,
container, volume or database was changed. Every finding below came from read-only commands: `df`, `du`, `find`,
`stat`, `snap list`, `docker system df -v`, `docker ps`, `docker image inspect`, `journalctl --disk-usage`, and `cmp`
for one duplicate check.

> This report is about this machine, not the application. The owner may prefer **not to commit it**: it names local
> paths outside the project.

## 1. Situation

| Filesystem | Size | Used | Free | Use |
|---|---|---|---|---|
| `/` (`/dev/sdb1`): home, `/var`, `/usr`, Docker data (snap Docker: `/var/snap/docker/common/var-lib-docker`) | 150 G | 140 G | **1.6 G** | **99 %** |
| `/mnt/Projects` (`/dev/sdb3`, a separate partition) | 137 G | 26 G | **105 G** | 20 % |

**Readable by the user:** about 95 G of `/` (`du -x`). The remaining ≈ 45 G is in root-only areas, mostly Docker's
data directory. `docker system df` accounts for about 40 G of that:

| Docker | Total | Reclaimable (Docker's estimate) |
|---|---|---|
| Images (68) | 25.9 GB | 13.7 GB |
| Containers (18) | 0.45 GB | 0.45 GB |
| Volumes (63) | 14.3 GB | 9.4 GB |
| Build cache | 0 | 0 |

**Largest areas on `/` (GB):**

| Area | Size | Main contents |
|---|---|---|
| `/home/mohamed18` | 52 | `.cache` 11, `clean-copy` 6.9, `.npm` 6.3, `.pnpm-store` 3.4, `.config` 3.2, `go` 3.1, `Downloads` 2.7, `.vscode` 2.4, `.codex` 2.4 |
| Docker (root-only) | ≈ 40 | |
| `/usr` | 19 | system packages |
| `/var/lib/snapd` | 17 | of which `/var/lib/snapd/snaps` is 16: installed snap revisions, including superseded ones |
| `/var/lib/flatpak` | 1.7 | |
| `/var/log/journal` | 0.53 | |
| `/swapfile` | 1.0 | not active: `swapon` shows only partition `/dev/sdb5`, and `/etc/fstab` has no `/swapfile` entry |

**Largest single files:**

| File | Size |
|---|---|
| `/var/lib/snapd/snaps/kf6-core24_64.snap` (superseded) | 1.67 GB |
| `kf6-core24_69.snap` (current) | 1.66 GB |
| `/swapfile` | 1.0 GB |
| `~/Downloads/w2acp_db2025-2.sql` (**byte-identical** to `/mnt/Projects/Php/pure/7amlat/w2acp_db2025-2.sql`; `cmp`) | 845 MB |
| several snap revisions | 500–616 MB each |
| this Claude session's transcript `~/.claude/projects/…/dad4d921-….jsonl` (**in use**) | 523 MB |

## 2. Classification

- **(R)** reclaimable and regenerable.
- **(P)** belongs to another project or personal data; the owner decides.
- **(K)** keep: in use, required, or out of bounds.

| Resource | Size | Class | In use? | Notes |
|---|---|---|---|---|
| Superseded snap revisions (`snap list --all`, "disabled": 30 revisions, largest `kf6-core24_64` 1.67 G, `gnome-46-2404_168` 0.62 G, `gnome-42-2204_247` 0.53 G, `insomnia_380`, `zoom-client_275`, `qt515-core20_44`, `beekeeper-studio_543`, …) | **≈ 7.7 GiB** | R | no (the current revisions stay) | removal needs `sudo snap remove <name> --revision=<rev>`; snapd keeps 2–3 revisions by default (`snap set system refresh.retain=2` limits recurrence) |
| Unused Docker images (no container): `mssql/server:2019-latest` 1.47 G, `kicbase:v0.0.30` 1.14 G, `django-docker` 1.12 G, `news-app_node-app` 1.52 G, `python:3.9` 1.0 G, `golang:1.17-buster` 0.88 G, `dart:3.9.2` 0.80 G, `mongo` (latest, 7) 0.78 G + 0.87 G, `phpmyadmin` (in use, keep), `neo4j` 0.54 G, … | up to **13.7 GB** (Docker's figure; shared layers make per-image figures overlap) | P | no container | re-pullable from registries, **except locally built images** (`news-app_node-app`, `django-docker`, `order_order-app`, `micro-apps_*`, `backend_yallapay`, `fix-car-*`, `app_php`, `nest-app`, `sportshub-api`), which need their source to rebuild |
| Dangling `<none>` images: 4 × 1.88 GB from 2026-11-03 (CapRover build of another project, sharing 1.86 GB) | **≈ 1.9 GB** | P | no | another project's build output |
| Dangling `<none>` images: 4 × 245 MB, plus `w2a-lab-fcgi:local` (this project's FastCGI lab, 2026-11-11) | **≈ 0.25 GB** (all five share one base) | R | no | re-buildable from `tools/download-lab/` |
| `mcr.microsoft.com/playwright:v1.48.2-jammy` (B7a, this session) | **2.0 GB** | R | no | re-pullable; needed only to rerun B7a |
| Dangling images from about 8 months ago (`fc84058e99ec` 462 M, `0631b88619cf` 379 M, `46abb56b742d` / `15c8f598cdcb` 254 M, `feca599066a0` 133 M) | ≈ 1.2 GB (partly shared) | P | referenced by exited containers | removable only together with those containers |
| Exited containers from about 8 months ago (`priceless_lovelace` 403 MB layer; `recursing_wescoff`, `blissful_elgamal`, `amazing_shirley`, `upbeat_pare`, `cranky_shamir`, `crazy_kirch`, `nifty_chatterjee`, `boring_hugle` 19 MB, `vibrant_einstein`; `foulk-postgres` with a volume) | ≈ 0.43 GB | P | exited | other projects |
| Containers `maria-final`, `mysql57`, `mysql8-test`, `phpmyadmin`, `phpmyadmin8`, `php81`, `legacy-admincp-compare` | small | **K** | 4 running | owner-protected or project-related; **do not touch** |
| Docker volumes: 59 not attached to a container ≈ 9.4 GB (for example `minikube` 1.48 G, `app_mysql_data` 1.41 G, anonymous `e01e85cd…` 1.40 G, `2251f495…` / `0f8873b6…` 0.66 G each, many 50–320 MB anonymous) | 9.4 GB | **K** | — | **out of bounds** by instruction. May hold databases. Listed for information only |
| Volumes in use: `testdb_mysql8-data` 2.39 G (mysql8-test), `admin_mysql_data` 1.62 G (mysql57), `832f63f0…` 0.74 G (maria-final), `6c33afe6…` (foulk-postgres) | — | **K** | yes | do not touch |
| `~/.npm/_cacache` | **6.0 G** | R | no (cache) | `npm cache clean --force`; packages re-download on demand |
| `~/.pnpm-store` | 3.4 G | R (partly) | **hard-linked into projects' `node_modules`** | `pnpm store prune` removes only unreferenced packages; deleting the directory would break pnpm projects |
| `~/.cache/yarn` | 3.1 G | R | no | `yarn cache clean` |
| `~/go/pkg/mod` (module cache) | 2.6 G | R | no | `go clean -modcache` |
| `~/.cache/pip` | 1.9 G | R | no | `pip cache purge` |
| `~/.cache/mozilla`, `~/.cache/google-chrome` | 1.1 G + 1.0 G | R | **while the browsers run** | close the browsers first |
| `~/.cache/Cypress` | 0.67 G | R | no | re-downloaded on the next Cypress install |
| `~/.cache/ms-playwright` (`chromium-1243`, `chromium_headless_shell-1243`, `ffmpeg-1011`, pre-existing) | 0.66 G | R / P | unknown | another tool's Playwright install; the 7amlat Puppeteer test uses `/usr/bin/google-chrome`, not this |
| `~/.cache/vscode-cpptools`, `prisma`, `typescript`, `pnpm`, `go-build`, `ms-playwright-go`, `composer` | 0.49 + 0.22 + 0.22 + 0.62 + 0.13 + 0.13 + **0.15** G | R | no | tool caches. Composer: `composer clear-cache` |
| `~/.minikube` (incl. a 506 MB preloaded tarball) | 0.89 G | P | — | another project |
| `/var/log/journal` | 0.53 G | R | system | `sudo journalctl --vacuum-size=100M` |
| `/tmp` | 0.14 G | R | partly | `/tmp/phpstan` 89 MB (cache; regenerates); 79 `/tmp/w2a-*` test leftovers ≈ 1.3 MB; `/tmp/claude-1000` 14 MB (this session's scratch, in use) |
| `/swapfile` | **1.0 G** | R | **not active, not in fstab** | `sudo rm /swapfile`, after a final `swapon --show` check |
| `~/Downloads/w2acp_db2025-2.sql` | **0.85 G** | R | no | **identical** copy of the project dump on `/mnt/Projects`; the project copy stays |
| `~/Downloads` other installers (`go1.18.4` 449 M, `arduino-1.8.16` 589 M, `jdk-20` tar 184 M, a Vivaldi `.deb` 121 M, a phone video 134 M) | ≈ 1.5 G | P | no | personal |
| `~/clean-copy` (personal video files) | 6.9 G | P | no | personal data. Candidate to **move** to `/mnt/Projects` (105 G free), not delete |
| `~/.claude` (incl. this session's 523 MB transcript) | 0.73 G | **K** | **yes** | do not touch during the session |
| Project logs (`laravel-project/storage/logs`) and project files | — | n/a | — | on `/mnt/Projects`, so they do not affect `/` |

## 3. Ranked cleanup proposal (awaiting owner approval; nothing executed)

Ranked by **space ÷ risk**.

| Rank | Action | Exact target | Est. reclaim | Risk | In use | Needs |
|---|---|---|---|---|---|---|
| 1 | Remove superseded snap revisions | every `disabled` row of `snap list --all` (30 revisions) | **≈ 7.7 GiB** | low (current revisions untouched) | no | sudo |
| 2 | Clear the npm cache | `~/.npm/_cacache` | **≈ 6.0 G** | low (re-download) | no | — |
| 3 | Remove the duplicate dump | `~/Downloads/w2acp_db2025-2.sql` (identical to the `/mnt/Projects` copy) | 0.85 G | very low | no | — |
| 4 | Remove the inactive swap file | `/swapfile` | 1.0 G | low (not active, not in fstab) | no | sudo |
| 5 | Remove the B7a Playwright image | `mcr.microsoft.com/playwright:v1.48.2-jammy` | 2.0 G | low (re-pullable) | no | — |
| 6 | Clear yarn, pip and Go module caches | `~/.cache/yarn`, `~/.cache/pip`, `~/go/pkg/mod` | ≈ 7.6 G | low (re-download; slower next build) | no | — |
| 7 | Prune the pnpm store (not delete) | `pnpm store prune` | unknown (≤ 3.4 G) | low | referenced packages kept | — |
| 8 | Vacuum the journal | `/var/log/journal` → 100 MB | ≈ 0.43 G | low | system | sudo |
| 9 | Remove this project's lab images | `w2a-lab-fcgi:local` + `16bfe685868a`, `8e7996828af9`, `86c0608c469e`, `fa32426301aa` | ≈ 0.25 G | low (rebuildable) | no | — |
| 10 | Clear browser and dev-tool caches | `~/.cache/{mozilla,google-chrome,Cypress,vscode-cpptools,prisma,typescript,composer}`, `/tmp/phpstan` | ≈ 3.9 G | low (browsers closed) | browsers: maybe | — |
| 11 | Move personal media to the large partition | `~/clean-copy` → `/mnt/Projects/…` | 6.9 G on `/` | low if moved with verification | no | owner |
| 12 | Other projects' Docker items | dangling CapRover images (≈ 1.9 G); unused images (up to 13.7 G); exited 8-month-old containers (≈ 0.43 G) | up to ≈ 16 G | **medium** (locally built images need source to rebuild) | no | owner per project |
| — | Docker volumes | 59 unattached (≈ 9.4 G) | — | **high**: may hold databases | — | **excluded by instruction** |
| — | **Structural option** | relocate Docker's data root (snap Docker) or large caches to `/mnt/Projects` | ≈ 40 G (Docker) | medium (service downtime; needs careful migration) | yes | owner, sudo |

**Ranks 1–6** alone free about **25 G** with low risk. That is enough for the 2 GiB telemetry reserve that the B1
re-validation needs, and for normal development.

**Before any cleanup**, confirm: no running browser for rank 10; no pnpm project mid-install for rank 7; `swapon
--show` once more for rank 4.

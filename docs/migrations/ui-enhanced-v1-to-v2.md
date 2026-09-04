# UI Enhanced V1 to V2 Migration Ledger

This ledger maps each UI commit from the legacy `ui-enhanced-v1` branch to its
Laravel adaptation on `ui-enhanced-v2`. Source commits are replayed in
chronological order as behavioral specifications, not cherry-picked, so the
Laravel backend, routes, query shapes, security controls, and performance
optimizations remain authoritative.

## Baselines

- V1 repository: `/Users/dash/work/way2allah`
- V1 branch: `ui-enhanced-v1`
- V1 parent baseline: `dc6a7a8e1ac711c57d685564a8ce03383600bd59`
- V2 repository: `/Users/dash/work/way2allah-v2`
- V2 branch: `ui-enhanced-v2`
- V2 parent baseline: `d664512ecfc9a0191b65eb1c82ebd398829676a9`

## Rules

- Preserve the one-source-commit-to-one-target-commit mapping.
- Port visible behavior and required assets; do not copy legacy database,
  Docker, Apache, error-suppression, raw-SQL, or remote-fetch behavior.
- Prefer anonymous Blade components for repeated interface structures.
- Keep output escaped, links and forms compatible, and controls accessible by
  keyboard and touch.
- Preserve Laravel's grouped category data, batched gallery thumbnails,
  cached sidebar collections, and media security policy.
- Record intentional no-op adaptations explicitly.

## Commit map

| # | V1 commit | V2 commit | Classification | Result |
|---:|---|---|---|---|
| 1 | `6feaca9422905b6948b8b41c94d9c5d03cb097ad` | `6ea6820` | Direct asset adaptation | Added the v1 design-token and global CSS foundation as a tracked public asset. |
| 2 | `3afc6bc6e8dc8dc2eecb98c03b6873f32a2ae74a` | `81be956` | Intentional safety adaptation | Retained Laravel environment configuration, database connections, local-only thumbnail policy, and current deployment infrastructure. The legacy Docker stack, hardcoded credentials/URLs, Apache media redirect, and remote TimThumb fallback are intentionally excluded. |
| 3 | `0babaea367d59e6ae047f7a1bc23a5c9a6901b74` | `b7b3e2e` | Blade and asset adaptation | Added the premium stylesheet/JavaScript, the five used Thmanyah Sans weights, accessible global chrome/search, lightweight hero and media rails, reusable homepage media links, premium poll markup, and removed queries for the two homepage cards retired by v1. Unreferenced Serif font families and formatting-only CSS churn are excluded. |
| 4 | `e2060207f8692e3f84e27a381bb138f19a765aa7` | pending | Blade component adaptation | Added a reusable recursive category tree over `$categoriesByParent`, accessible expand/collapse/search controls, and the searchable alphabetical preacher card directory. No new queries are introduced. |
| 5 | `9c1102e4de87f8edcfc5de2da3cee86cd3cc970e` | pending | Pending | |
| 6 | `40d613a35bc40054713618c3777e46e6ae569580` | pending | Pending | |
| 7 | `7ce48a2898532dddf401568ac6bb6ca3f24f28f0` | pending | Pending | |
| 8 | `0e7838fc099f716ba62fee5435c55c07bc8fa288` | pending | Pending | |
| 9 | `b2d15525ad00107b66587c085d7bfc8bc6faf839` | pending | Pending | |
| 10 | `2820ada64bd454acb55ec940e5069774f1f8c9be` | pending | Pending | |
| 11 | `bd88261abab747ca24be9db42c26f115f4f56a59` | pending | Pending | |
| 12 | `b4c8fe14ed7f5b5e084427417c078c8019296f0d` | pending | Pending | |
| 13 | `2dd88b4d3b7325335af6c52cf22b696fcb8fd00e` | pending | Pending | |
| 14 | `5f877dd395d3bea72faf7e4bdd0711b05cfc3f7f` | pending | Pending | |
| 15 | `128cb5a0a4307505c704778a5ea67edddb691c14` | pending | Pending | |
| 16 | `fbb10d8a9176be533ac38fa6a4c25e535722eb75` | pending | Pending | |
| 17 | `a7b8d01fb3b3b663049f02be41b043bda057dfaa` | pending | Pending | |
| 18 | `75b7557b9560857a91b9b7df6e1e205ea564d7c0` | pending | Pending | |
| 19 | `316b08d9b2c7454cfa9be280659b57fbb0e348bd` | pending | Pending | |
| 20 | `60b060eb6b6d2d7b1cc59d6c45eb325fe7bfe38d` | pending | Pending | |

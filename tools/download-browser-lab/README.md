# B7a browser-download lab (disposable, local only)

This lab validates the **proposed** client-side «حفظ المادة» save (plan §F04.4) in real browsers. It is **not**
application code:

- the prototype lives only in `page.html`;
- **B7b is not implemented**;
- nothing here is deployed or wired into Laravel.

Results are in `docs/reports/admincp-b7a-download-validation.md`. Raw JSON is in `results-2026-11-14/`.

| File | Role |
|---|---|
| `server.mjs` | fixture servers: page origin `:8000` (with a 302 `/route`, like `var-download`), media with CORS `:8001`, media without CORS `:8002`; real small MP3/MP4 plus deterministic 50/400 MiB streams; `rate=` throttle and `cut=` interrupt |
| `page.html` | today's button plus the prototype save module and a play button |
| `run.mjs` | Playwright runner: scenario matrix, outcome classification, integrity hashing, memory sampling |
| `run.sh` | in-container entry point (generates media with ffmpeg, starts the servers, runs) |
| `archive-files.json` | two public archive.org files (with their published md5) used as real-world sources |
| `archive_cors_probe.py` | the archive.org CORS sampler (input: a TSV of `module<TAB>link`) |

## Run

The official Playwright image bundles WebKit's system dependencies, so nothing is installed on the host.

```sh
docker pull mcr.microsoft.com/playwright:v1.48.2-jammy
docker run -d --rm --name w2a-b7a-lab --memory=3500m --shm-size=1g \
  --tmpfs /work:size=2400m,exec --tmpfs /tmp:size=1500m,exec \
  mcr.microsoft.com/playwright:v1.48.2-jammy sleep infinity
docker exec w2a-b7a-lab mkdir -p /lab
docker cp tools/download-browser-lab/. w2a-b7a-lab:/lab/
# the playwright npm package (1.48.2) must be in /lab/node_modules, e.g. from: npm i playwright@1.48.2 (any scratch dir)
docker cp <scratch>/node_modules w2a-b7a-lab:/lab/
docker exec w2a-b7a-lab /lab/run.sh chromium,firefox,webkit
docker exec w2a-b7a-lab cat /work/results.json > results.json   # docker cp cannot read tmpfs
docker rm -f w2a-b7a-lab
```

**Options:**

- `ONLY_IDS=J5,J6` runs a subset.
- `FRESH=1` launches a new browser per scenario, to isolate memory.
- `RESULTS=/work/x.json` writes the results to another path.

Data stays in tmpfs (RAM), so nothing large touches disk. The 400 MiB scenarios need about 3.5 GB of container memory
in Firefox.

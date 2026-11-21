// B7a browser-download lab: Playwright runner (disposable, local only).
//
// Every scenario opens the lab page in a fresh browser context, clicks «حفظ المادة» (or «تشغيل»), and classifies
// what actually happened:
//
//   SUCCESS_FORCED_DOWNLOAD   a download event fired from the prototype (blob), with the right name and exact bytes
//   DOWNLOAD_CORRUPT          a download fired but the name, size or hash is wrong
//   OPENED_MEDIA_PLAYER       the browser navigated to the media URL and rendered it inline (today's behaviour)
//   NATIVE_DOWNLOAD           the browser itself chose to download after navigating (no prototype involved)
//   FALLBACK_EXTERNAL_URL     the prototype gave up and sent the browser to today's href (detail says what then
//                             happened: player / download / other). Never counted as a forced download.
//   FAILED                    no file and no navigation (detail: cancelled / interrupted / short / timeout ...)
import { chromium, firefox, webkit } from 'playwright';
import fs from 'node:fs';
import crypto from 'node:crypto';

const MiB = 1024 * 1024;
const PAGE = 'http://127.0.0.1:8000/page.html';
const CORS = 'http://127.0.0.1:8001/f/';
const NOCORS = 'http://127.0.0.1:8002/f/';
const ROUTE = 'http://127.0.0.1:8000/route?to=';
const manifest = JSON.parse(fs.readFileSync('/work/manifest.json', 'utf8'));
const archive = JSON.parse(fs.readFileSync('/lab/archive-files.json', 'utf8'));
const only = process.argv[2] ? process.argv[2].split(',') : ['chromium', 'firefox', 'webkit'];
const engines = { chromium, firefox, webkit };

function pageUrl(o) {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(o)) if (v !== undefined && v !== null) q.set(k, String(v));
    return PAGE + '?' + q.toString();
}

function local(name, origin = CORS, extra = '') {
    return { url: origin + name + extra, size: manifest[name].size, sha256: manifest[name].sha256, name };
}

const A_MP3 = archive.mp3;
const A_MP4 = archive.mp4;

const scenarios = [
    { id: 'B1', title: 'today: redirect → CORS small.mp3', save: 0, src: local('small.mp3'), expect: 'OPENED_MEDIA_PLAYER' },
    { id: 'B2', title: 'today: redirect → CORS small.mp4', save: 0, src: local('small.mp4'), expect: 'OPENED_MEDIA_PLAYER' },
    { id: 'J1', title: 'prototype: CORS small.mp3', src: local('small.mp3'), expect: 'SUCCESS_FORCED_DOWNLOAD' },
    { id: 'J2', title: 'prototype: CORS small.mp4', src: local('small.mp4'), expect: 'SUCCESS_FORCED_DOWNLOAD' },
    { id: 'J3', title: 'prototype: CORS 50 MiB .mp3', src: local('big50.mp3'), expect: 'SUCCESS_FORCED_DOWNLOAD' },
    { id: 'J4', title: 'prototype: CORS 50 MiB .mp4', src: local('big50.mp4'), expect: 'SUCCESS_FORCED_DOWNLOAD' },
    { id: 'J5', title: 'prototype: CORS 400 MiB .mp3, no cap', src: local('big400.mp3'), expect: 'SUCCESS_FORCED_DOWNLOAD', timeout: 180000 },
    { id: 'J6', title: 'prototype: CORS 400 MiB .mp4, no cap', src: local('big400.mp4'), expect: 'SUCCESS_FORCED_DOWNLOAD', timeout: 180000 },
    { id: 'J7', title: 'prototype: CORS 400 MiB .mp4, cap 300 MiB', src: local('big400.mp4'), cap: 300 * MiB, expect: 'FALLBACK_EXTERNAL_URL' },
    { id: 'N1', title: 'prototype: no-CORS small.mp3', src: local('small.mp3', NOCORS), expect: 'FALLBACK_EXTERNAL_URL' },
    { id: 'N2', title: 'prototype: no-CORS small.mp4', src: local('small.mp4', NOCORS), expect: 'FALLBACK_EXTERNAL_URL' },
    { id: 'C1', title: 'prototype: cancel a 400 MiB .mp4 (20 MiB/s) after 2 s', src: local('big400.mp4', CORS, '?rate=' + 20 * MiB), cancelAfter: 2000, expect: 'FAILED' },
    { id: 'I1', title: 'prototype: source cuts a 50 MiB .mp4 at 20 MiB', src: local('big50.mp4', CORS, '?cut=' + 20 * MiB), expect: 'FAILED' },
    { id: 'A1', title: 'today: redirect → archive.org .mp3', save: 0, src: A_MP3, expect: 'OPENED_MEDIA_PLAYER', timeout: 90000 },
    { id: 'A2', title: 'prototype: archive.org .mp3', src: A_MP3, expect: 'SUCCESS_FORCED_DOWNLOAD', timeout: 120000 },
    { id: 'A3', title: 'today: redirect → archive.org .mp4', save: 0, src: A_MP4, expect: 'OPENED_MEDIA_PLAYER', timeout: 90000 },
    // A4 keeps the proposal's goal as its expectation: a FALLBACK here is the finding (archive.org sends no CORS
    // header for video/mp4 from its storage nodes, 2026-11-14), not a harness fault.
    { id: 'A4', title: 'prototype: archive.org .mp4', src: A_MP4, expect: 'SUCCESS_FORCED_DOWNLOAD', timeout: 120000 },
];

// ---- memory: PSS of the browser's processes (everything launched from /ms-playwright), plus the container cgroup
function browserPssKiB() {
    let total = 0;
    for (const pid of fs.readdirSync('/proc')) {
        if (!/^\d+$/.test(pid)) continue;
        try {
            const cmd = fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8');
            if (!cmd.includes('/ms-playwright/')) continue;
            const m = /^Pss:\s+(\d+) kB/m.exec(fs.readFileSync(`/proc/${pid}/smaps_rollup`, 'utf8'));
            if (m) total += Number(m[1]);
        } catch { /* process exited */ }
    }
    return total;
}
function cgroupBytes() {
    try { return Number(fs.readFileSync('/sys/fs/cgroup/memory.current', 'utf8')); } catch { return null; }
}
function sampler() {
    const s = { basePss: browserPssKiB(), baseCg: cgroupBytes(), peakPss: 0, peakCg: 0 };
    const tick = () => {
        s.peakPss = Math.max(s.peakPss, browserPssKiB());
        s.peakCg = Math.max(s.peakCg, cgroupBytes() || 0);
    };
    tick();
    s.timer = setInterval(tick, 150);
    s.stop = () => { clearInterval(s.timer); tick(); };
    return s;
}
const mib = (b) => (b == null ? null : Math.round((b / MiB) * 10) / 10);

async function hashFile(path, algo) {
    const h = crypto.createHash(algo);
    await new Promise((ok, ko) => fs.createReadStream(path).on('data', (c) => h.update(c)).on('end', ok).on('error', ko));
    return h.digest('hex');
}

async function runScenario(browser, sc) {
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();
    const timeout = sc.timeout || 60000;
    const isArchive = sc.src.url.startsWith('https://archive.org/');
    const fileName = sc.src.name;
    await page.goto(pageUrl({
        save: sc.save === 0 ? '0' : undefined,
        route: ROUTE + encodeURIComponent(sc.src.url),
        direct: sc.save === 0 ? undefined : sc.src.url,
        name: fileName,
        cap: sc.cap,
    }));

    const mem = sampler();
    const started = Date.now();
    const downloadP = page.waitForEvent('download', { timeout }).then((d) => ({ kind: 'download', d }), () => null);
    const navP = page.waitForURL((u) => !u.toString().startsWith(PAGE), { timeout, waitUntil: 'commit' }).then(() => ({ kind: 'navigated' }), () => null);
    const failP = page.waitForFunction(() => /^failed/.test(document.getElementById('status')?.textContent || ''), null, { timeout, polling: 200 })
        .then(() => ({ kind: 'failed' }), () => null);

    await page.click('#save');
    if (sc.cancelAfter) {
        await page.waitForTimeout(sc.cancelAfter);
        await page.click('#cancel');
    }

    const first = await Promise.race([
        downloadP.then((r) => r || new Promise(() => {})),
        navP.then((r) => r || new Promise(() => {})),
        failP.then((r) => r || new Promise(() => {})),
        new Promise((ok) => setTimeout(() => ok({ kind: 'timeout' }), timeout + 2000)),
    ]);

    const out = { browser: browser.browserType().name(), id: sc.id, title: sc.title, expect: sc.expect, bytes: sc.src.size ?? null };
    let status = '';
    const readStatus = async () => { try { status = await page.locator('#status').textContent({ timeout: 1000 }); } catch { /* navigated away */ } };

    if (first.kind === 'download') {
        await readStatus();
        const d = first.d;
        const failure = await d.failure();
        const path = failure ? null : await d.path();
        out.elapsed_s = (Date.now() - started) / 1000;
        mem.stop();
        if (!path) {
            out.category = 'FAILED';
            out.detail = 'download failure: ' + failure;
        } else {
            const size = fs.statSync(path).size;
            const okName = d.suggestedFilename() === fileName;
            let okHash;
            if (isArchive) okHash = (await hashFile(path, 'md5')) === sc.src.md5;
            else okHash = (await hashFile(path, 'sha256')) === sc.src.sha256;
            const okSize = sc.src.size == null || size === sc.src.size;
            const fromPrototype = /^saved/.test(status);
            out.saved_bytes = size;
            out.filename = d.suggestedFilename();
            out.integrity = okHash && okSize ? 'exact' : 'MISMATCH';
            out.category = !fromPrototype ? (status.startsWith('fallback') ? 'FALLBACK_EXTERNAL_URL' : 'NATIVE_DOWNLOAD')
                : (okHash && okSize && okName ? 'SUCCESS_FORCED_DOWNLOAD' : 'DOWNLOAD_CORRUPT');
            if (out.category === 'FALLBACK_EXTERNAL_URL') out.detail = status + ' → download';
            await d.delete();
        }
    } else if (first.kind === 'navigated') {
        out.elapsed_s = (Date.now() - started) / 1000;
        // the status was written just before the navigation; read it from the history entry we left
        await page.waitForTimeout(2500);
        mem.stop();
        let media = null;
        try {
            media = await Promise.race([
                page.evaluate(() => {
                    const el = document.querySelector('video, audio');
                    return el ? el.tagName.toLowerCase() : (document.contentType || 'unknown');
                }),
                new Promise((ok) => setTimeout(() => ok('evaluate-timeout'), 10000)),
            ]);
        } catch (e) { media = 'unreadable:' + e.message.slice(0, 60); }
        const late = await Promise.race([downloadP, new Promise((ok) => setTimeout(() => ok(null), 3000))]);
        out.navigated_to = page.url().slice(0, 90);
        const fellBack = sc.save !== 0;
        const what = media === 'video' || media === 'audio' ? 'player(' + media + ')' : 'other(' + media + ')';
        out.category = fellBack ? 'FALLBACK_EXTERNAL_URL' : (what.startsWith('player') ? 'OPENED_MEDIA_PLAYER' : 'OTHER_NAVIGATION');
        out.detail = (fellBack ? 'prototype fell back → ' : '') + what;
        if (late && late.d) { out.detail += ' + native download'; await late.d.delete().catch(() => {}); }
    } else if (first.kind === 'failed') {
        await readStatus();
        out.elapsed_s = (Date.now() - started) / 1000;
        // make sure no file appears afterwards
        const late = await Promise.race([downloadP, new Promise((ok) => setTimeout(() => ok(null), 4000))]);
        mem.stop();
        out.category = late && late.d ? 'DOWNLOAD_CORRUPT' : 'FAILED';
        out.detail = status + (late && late.d ? ' BUT a download fired' : ', no file saved');
        try { out.progress_bytes = Number(await page.locator('#progress').textContent({ timeout: 1000 })); } catch { /* */ }
    } else {
        await readStatus();
        mem.stop();
        out.category = 'FAILED';
        out.detail = 'timeout; status=' + status;
    }

    out.pss_base_mib = mib(mem.basePss * 1024);
    out.pss_peak_mib = mib(mem.peakPss * 1024);
    out.cgroup_peak_mib = mib(mem.peakCg);
    // memory a few seconds after the outcome, with the page still open (blob URL revoked after 30 s by design)
    await page.waitForTimeout(3000).catch(() => {});
    out.pss_after_mib = mib(browserPssKiB() * 1024);
    out.pass = out.category === sc.expect;
    await context.close();
    return out;
}

// ---- playback: the play button behaves the same with and without the save module, and survives a save
async function runPlayback(browser, src, kind, withSave) {
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();
    await page.goto(pageUrl({ save: withSave ? undefined : '0', direct: withSave ? src.url : undefined, play: src.url, kind, name: src.name, route: ROUTE + encodeURIComponent(src.url) }));
    const canPlay = await page.evaluate((k) => document.createElement(k).canPlayType(k === 'video' ? 'video/mp4; codecs="avc1.42E01E, mp4a.40.2"' : 'audio/mpeg'), kind);
    await page.click('#play');
    await page.waitForTimeout(5000);
    const probe = () => page.evaluate(() => {
        const el = document.querySelector('#player video, #player audio');
        return el ? { tag: el.tagName.toLowerCase(), src: el.currentSrc || el.src, t: el.currentTime, paused: el.paused, error: el.error ? el.error.code : null, playError: el.dataset.playError || null } : null;
    });
    const before = await probe();
    let saveOutcome = null;
    let after = null;
    if (withSave) {
        const dl = page.waitForEvent('download', { timeout: 120000 }).catch(() => null);
        await page.click('#save');
        const d = await dl;
        saveOutcome = d ? 'download:' + d.suggestedFilename() : 'no download';
        if (d) await d.delete().catch(() => {});
        await page.waitForTimeout(1500);
        after = await probe();
        after.page_unchanged = page.url().startsWith(PAGE);
    }
    await context.close();
    return { browser: browser.browserType().name(), kind, source: src.url.startsWith('https://archive.org') ? 'archive.org' : 'local', withSave, canPlayType: canPlay, before, saveOutcome, after };
}

// Optional: ONLY_IDS=J5,J6 runs just those scenarios, FRESH=1 launches a new browser for each one (isolates memory),
// RESULTS=<path> writes elsewhere. Defaults reproduce the full matrix.
const onlyIds = process.env.ONLY_IDS ? process.env.ONLY_IDS.split(',') : null;
const fresh = process.env.FRESH === '1';
const resultsPath = process.env.RESULTS || '/work/results.json';
const results = { scenarios: [], playback: [], env: {} };
for (const name of only) {
    let browser = await engines[name].launch({ downloadsPath: '/work/dl' });
    results.env[name] = browser.version();
    for (const sc of scenarios.filter((x) => !onlyIds || onlyIds.includes(x.id))) {
        if (fresh) { await browser.close(); browser = await engines[name].launch({ downloadsPath: '/work/dl' }); }
        let r;
        const deadline = (sc.timeout || 60000) + 60000;
        try {
            r = await Promise.race([
                runScenario(browser, sc),
                new Promise((_, ko) => setTimeout(() => ko(new Error('scenario deadline ' + deadline + ' ms')), deadline)),
            ]);
        } catch (e) { r = { browser: name, id: sc.id, title: sc.title, expect: sc.expect, category: 'HARNESS_ERROR', detail: String(e).slice(0, 300), pass: false }; }
        results.scenarios.push(r);
        console.log(JSON.stringify(r));
        fs.writeFileSync(resultsPath, JSON.stringify(results, null, 2));
    }
    if (onlyIds) { await browser.close(); continue; }
    for (const [src, kind] of [[A_MP3, 'audio'], [local('small.mp4'), 'video'], [local('small.mp3'), 'audio']]) {
        for (const withSave of [false, true]) {
            let r;
            try { r = await runPlayback(browser, src, kind, withSave); } catch (e) { r = { browser: name, kind, withSave, error: String(e).slice(0, 300) }; }
            results.playback.push(r);
            console.log(JSON.stringify(r));
        }
    }
    await browser.close();
    fs.writeFileSync(resultsPath, JSON.stringify(results, null, 2));
}
fs.writeFileSync(resultsPath, JSON.stringify(results, null, 2));
console.log('DONE');

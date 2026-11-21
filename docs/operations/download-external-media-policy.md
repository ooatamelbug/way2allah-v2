# External media (YouTube/SoundCloud) on Khotab mirrors: proposed redirect policy

**Status:** implemented locally in Phase 3B.1 (`ExternalMediaLink`, `KhotabItemController::downloadMirror`), exactly
as below.

Decisions taken in the implementation:

- A stored `http://` link is **upgraded**: the redirect target is always `https://`.
- Any other scheme is rejected.
- Primary Khotab downloads (`khotab-download-{id}.htm`) are not affected; the policy applies to mirrors only.
- Telemetry `redirect_issued` carries `st=redirect`.
- Every mirror that does not qualify keeps the proxied path; an HTML page there is still a 500 `source_rejected`.

## Background

- **Legacy:** `download_khotab()` proxied every mirror link. For a YouTube or SoundCloud page, the visitor received
  the page's HTML as a file attachment, and `hits` was incremented.
- **Item page:** `Mirror::extensionIconFilename()` already recognises these links by a literal `youtube`/`soundcloud`
  path segment, and shows them with their own icons, so visitors are offered them as "mirrors".
- **What a visitor would expect:** clicking such a mirror should open the media page, not download HTML or show an
  error.

## Proposed behaviour (for `khotab-mirror-{id}-{m}.htm` only)

1. **Classification happens before any slot or source contact.** The stored link is parsed with `parse_url()`. It is
   external media only when all of these hold:
   - **scheme:** `http` or `https`; `http` is upgraded to `https` in the redirect;
   - **host:** an exact, case-insensitive match against an allowlist: `youtube.com`, `www.youtube.com`, `m.youtube.com`,
     `youtu.be`, `soundcloud.com`, `www.soundcloud.com`, `m.soundcloud.com`, `on.soundcloud.com`. No suffix or
     substring matching, so `youtube.com.evil.tld` does not qualify;
   - **no userinfo** (`user@`), and **no explicit port** other than 443 (or 80 before the upgrade);
   - total length ≤ 2,048 bytes, and no control characters, spaces or CR/LF anywhere.
2. **Redirect safety:**
   - The `Location` is rebuilt from the parsed parts (`https://{host}{path}?{query}#{fragment}`), never echoed raw.
   - Use **302** with `Cache-Control: no-store`, not 301, so a corrected link takes effect at once and browsers never
     cache the hop.
   - The target comes only from the database (admin-controlled), never from request input, so this is not an open
     redirect.
   - Anything that fails classification keeps today's path. It is proxied and validated, so an HTML answer stays a 500.
     There is never a redirect to a non-allowlisted host.
3. **Slots:** none. A redirect transfers no bytes through PHP. This follows the guard's own invariant: a
   redirect-only endpoint must not take a slot.
4. **Counter:** GET increments the mirror's `hits` once, as legacy did for every mirror click (legacy counted, then
   proxied). That keeps the counter's meaning: a "visit to this mirror".
5. **HEAD:** the same 302 with the same `Location`. No counter, no slot, no outbound request. This matches the
   Phase 3B Khotab HEAD rules.
6. **Telemetry:**
   - one `redirect_issued` event with `sh` = the host and `k` = `youtube` or `soundcloud`;
   - **never** `download_accepted`, `transfer_*` or a `completed` outcome;
   - a HEAD is recorded as `head_answered`, as elsewhere.
7. **Compatibility:**
   - The public URLs stay the same (`khotab-mirror-{id}-{m}.htm`). Item-page links and icons are unchanged.
   - Download managers that expected a file: today they already receive HTML (production) or a 500 (local fix). A 302
     to a web page is no worse.
   - Crawlers will follow a 302 to YouTube or SoundCloud, which is harmless. `robots` rules for the mirror route are
     unchanged.
   - Anasheed's `var-mirror` already redirects every mirror without host checks. Aligning it with this allowlist is a
     separate decision.

## Tests to write when approved

- Allowlist hosts: redirect.
- Look-alike hosts, userinfo, ports, CR/LF and over-long links: no redirect.
- `http` is upgraded to `https`.
- GET counts once; HEAD does not count.
- No slot is taken (full pool still answers 302).
- `redirect_issued` is emitted, with no transfer events.
- A non-media HTML source still returns 500.

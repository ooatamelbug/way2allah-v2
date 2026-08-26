@props(['lat' => null, 'lng' => null])

{{--
    AdminCP Locations Map — Owner Decision Resolution (2026-08-22).

    Legacy contract (re-read fresh from `locations/add.php`/`edit.php`/
    `locations.js`): clicking the map sets lat/lng (real, functional on
    `edit.php` — its UPDATE query is live, unlike `add.php`'s commented-out
    INSERT). Reverse-geocoding address/country from the click is
    convenience only — both fields are already plain, manually-editable
    text inputs in the same form (placeholder text itself says "click the
    map to fill this," implying manual entry was always an accepted
    fallback) — so no geocoding provider is wired up here, per the owner
    decision (`LOCATIONS_MAP = IMPLEMENT_WITH_OPEN_SOURCE_PROVIDER`,
    geocoding NOT pre-authorized).

    Deliberately NOT reproduced: `LEGACY_GOOGLE_MAPS_API_KEY = DO_NOT_REUSE`
    (a live credential found hardcoded in legacy source is not reused
    here, or anywhere); `LEGACY_DEPRECATED_GOOGLE_MAPS_INTEGRATION =
    DO_NOT_REPRODUCE` (the deprecated keyless Maps JS API loading pattern).

    Deliberately CORRECTED, not reproduced as-is: legacy's own
    `locations.js` always initializes the map at a hardcoded default
    point (31.046109, 31.359602) even on the EDIT page — it never
    positions the existing marker from the location's own stored lat/lng,
    a confirmed legacy bug (`initialize()`'s own hardcoded call, ignoring
    whatever `$location->lat`/`lng` the surrounding PHP page had
    available). This component centers on and marks the EXISTING
    coordinate when one is passed — real behavioral parity with the
    evident intent of an edit map, not a silently-introduced feature.

    Assets: Leaflet 1.9.4, self-hosted (not a CDN) at
    `public/vendor/leaflet/` — deliberately NOT under `public/assets/`,
    which this repo's own `public/assets` is a symlink to
    `legacy-project/assets` (found only while placing these files —
    writing there would have put new files inside the protected,
    off-limits legacy repo). `public/vendor/` is a genuine
    Laravel-project-only path. Tiles: OpenStreetMap's standard tile
    server (`{s}.tile.openstreetmap.org`) — free, keyless, no billing
    account, but requires outbound internet access at view-time (the
    tile images themselves are fetched by the visitor's browser directly
    from OSM, not proxied through this application) and the OSM
    attribution link is a required term of using their tiles, kept
    in the map's own attribution control, not removed.
--}}
<link href="{{ asset('vendor/leaflet/leaflet.css') }}" rel="stylesheet" type="text/css">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" type="text/javascript"></script>

<div id="map_canvas" style="width: 100%; height: 500px;"></div>

<script>
(function () {
    var defaultLat = 31.046109;
    var defaultLng = 31.359602;
    var existingLat = {{ is_numeric($lat) ? (float) $lat : 'null' }};
    var existingLng = {{ is_numeric($lng) ? (float) $lng : 'null' }};
    var hasExisting = existingLat !== null && existingLng !== null && (existingLat !== 0 || existingLng !== 0);

    var map = L.map('map_canvas').setView(
        hasExisting ? [existingLat, existingLng] : [defaultLat, defaultLng],
        hasExisting ? 15 : 6
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
    }).addTo(map);

    var marker = hasExisting ? L.marker([existingLat, existingLng]).addTo(map) : null;

    map.on('click', function (event) {
        if (marker) { map.removeLayer(marker); }
        marker = L.marker(event.latlng).addTo(map);
        document.getElementById('loc_lat').value = event.latlng.lat;
        document.getElementById('loc_long').value = event.latlng.lng;
    });
})();
</script>

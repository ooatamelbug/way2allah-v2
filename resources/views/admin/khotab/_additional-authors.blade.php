{{--
    Multi-author creation (owner decisions 2026-11-25) — the optional «دعاة مشاركون» block of the three «إضافة مادة»
    forms (author, group and series pages), placed above the existing «أضف» button.

    Inputs: $primaryAuthorId, $media ('video' | 'audio'), $selected (after a failed submission only: at most the limit,
    [{id, name}]). No author list is rendered: names come from the server-side search
    (admin.khotab.author-search, 20 per request), only after two characters and a 350 ms pause.

    Each ticked sheikh is posted as `additional_authors[]`; nothing is posted when none is ticked, so a single-author
    submission is exactly as before. The server re-validates every id (exists, visible, not the primary, at most the
    limit, permission and section) — this block is a convenience, not a control.

    The same script also protects «أضف» on every submission (owner decision Q-B): once the form really submits, the
    button is disabled and reads «جارٍ الحفظ…»; a page restored from history gets it back. Server-side duplicate
    prevention remains the authority.

    archive.org images (acceptance testing, 2026-11-26) — two paths used to drop the images (and archive.org's size)
    silently, from the primary and therefore from every copy:
    1. submitting while «التالي»'s archive request was still pending (its hidden inputs had not joined the form yet):
       the page's script now announces the request (`w2a:archive-start` / `w2a:archive-end` on the form) and «أضف»
       stays disabled («جارٍ جلب بيانات المادة من الأرشيف…», owner-approved) until it has settled, successfully or not;
    2. resubmitting after a refused submission (the re-rendered form had lost those hidden inputs and hides «التالي»):
       the previous submission's `archive_gif`, `archive_frame` and `original_size` are rendered again below, and
       dropped as soon as the link is edited, so they can never be stored against another link.
--}}
@php
    $aaMax = \App\Domain\Admin\Actions\Khotab\KhotabAdditionalAuthorCopies::max();
    $aaMin = max(1, (int) config('admin_khotab.additional_authors.search_min_length', 2));
    $aaSelected = collect($selected ?? [])->take($aaMax)->values();
    // Only after a refused submission (old input exists): what archive.org supplied to that submission.
    $aaArchive = array_filter([
        'archive_gif' => (string) old('archive_gif', ''),
        'archive_frame' => (string) old('archive_frame', ''),
        'original_size' => (string) old('original_size', ''),
    ], fn (string $value) => $value !== '');
@endphp
@foreach ($aaArchive as $aaField => $aaValue)
    <input type="hidden" name="{{ $aaField }}" value="{{ $aaValue }}" data-w2a-archive-kept @if ($aaField !== 'original_size') id="{{ $aaField }}" @endif>
@endforeach
@if (isset($aaArchive['archive_gif']) || isset($aaArchive['archive_frame']))
    <p class="w2a-aa__kept" data-w2a-archive-kept>صور archive.org التي جُلبت مع المحاولة السابقة محفوظة وستُضاف مع المادة.</p>
@endif
<fieldset class="w2a-aa" data-additional-authors
          data-search-url="{{ route('admin.khotab.author-search', ['media' => $media]) }}"
          data-exclude="{{ (int) $primaryAuthorId }}" data-max="{{ $aaMax }}" data-min="{{ $aaMin }}" dir="rtl">
    <legend>دعاة مشاركون (اختياري)</legend>
    <p class="w2a-aa__help">تُنشأ نسخة مستقلة من المادة لكل داعية تختاره، خارج أي مجموعة أو سلسلة. يمكن اختيار {{ $aaMax }} دعاة على الأكثر.</p>

    <label for="w2a-aa-search">ابحث باسم الداعية</label>
    <input type="search" id="w2a-aa-search" class="form-control w2a-aa__search" autocomplete="off" dir="rtl"
           aria-describedby="w2a-aa-hint w2a-aa-status" aria-controls="w2a-aa-results">
    <small id="w2a-aa-hint">اكتب حرفين على الأقل.</small>
    <div id="w2a-aa-status" class="w2a-aa__status" role="status" aria-live="polite"></div>

    <ul id="w2a-aa-results" class="w2a-aa__results" aria-label="نتائج البحث"></ul>
    <button type="button" class="btn default btn-sm w2a-aa__more" id="w2a-aa-more" hidden>عرض المزيد</button>

    <div class="w2a-aa__count">المختارون: <span data-aa-count>{{ $aaSelected->count() }}</span> من {{ $aaMax }}</div>
    <ul class="w2a-aa__chips" data-aa-selected aria-label="الدعاة المختارون">
        @foreach ($aaSelected as $author)
            <li class="w2a-aa__chip" data-id="{{ $author['id'] }}" data-name="{{ $author['name'] }}">
                <span>{{ $author['name'] }}</span>
                <button type="button" class="w2a-aa__remove" aria-label="إزالة {{ $author['name'] }}">×</button>
                <input type="hidden" name="additional_authors[]" value="{{ $author['id'] }}">
            </li>
        @endforeach
    </ul>
</fieldset>

<style>
    /* The theme's `.btn{display:inline-block}` otherwise overrides the `hidden` attribute («عرض المزيد»). */
    .w2a-aa [hidden]{display:none !important}
    .w2a-aa__kept{margin:8px 0;color:#596673;font-size:13px}
    .w2a-aa{margin:12px 0;padding:10px 12px;border:1px solid #e1e5ea;border-radius:6px}
    .w2a-aa legend{width:auto;margin:0;padding:0 6px;border:0;font-size:15px}
    .w2a-aa__help{margin:0 0 8px;color:#596673;font-size:13px}
    .w2a-aa__search{max-width:420px}
    .w2a-aa__status{min-height:18px;margin:4px 0;color:#596673;font-size:13px}
    .w2a-aa__results{max-height:260px;overflow:auto;margin:0 0 6px;padding:0;list-style:none}
    .w2a-aa__results li{padding:4px 2px;border-bottom:1px solid #f1f3f5}
    .w2a-aa__results label{display:flex;align-items:center;gap:8px;margin:0;font-weight:400;cursor:pointer}
    .w2a-aa__results input[disabled] + span{color:#9aa4ae}
    .w2a-aa__count{margin:6px 0;font-weight:700}
    .w2a-aa__chips{display:flex;flex-wrap:wrap;gap:6px;margin:0;padding:0;list-style:none}
    .w2a-aa__chip{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border:1px solid #cfe3d4;border-radius:999px;background:#f3faf5}
    .w2a-aa__remove{padding:0 4px;border:0;background:none;font-size:16px;line-height:1;cursor:pointer}
    .w2a-aa__remove:focus-visible,.w2a-aa__results input:focus-visible{outline:2px solid #1f9d55;outline-offset:2px}
</style>

<script>
    (function () {
        var box = document.querySelector('[data-additional-authors]');
        var form = box ? box.closest('form') : null;
        if (!form) { return; }

        // ── submit protection (every submission of this form, single- or multi-author) ──────────────────────────
        // The button is looked up at submit time: this script is included above «أضف», so it does not exist yet here.
        var locked = null, lockedLabel = '';
        function unlock() {
            if (locked) { locked.disabled = false; locked.removeAttribute('aria-busy'); locked.innerHTML = lockedLabel; locked = null; }
        }
        // ── archive.org request of «التالي»: no submission until it has settled ───────────────────────────────
        var archivePending = false, archiveButton = null, archiveLabel = '';
        form.addEventListener('w2a:archive-start', function () {
            archivePending = true;
            if (archiveButton) { return; }                    // already held for an earlier lookup
            archiveButton = form.querySelector('button[type=submit]');
            if (archiveButton) {
                archiveLabel = archiveButton.innerHTML;
                archiveButton.disabled = true;
                archiveButton.setAttribute('aria-busy', 'true');
                archiveButton.textContent = 'جارٍ جلب بيانات المادة من الأرشيف…';
            }
        });
        form.addEventListener('w2a:archive-end', function () {
            archivePending = false;
            if (archiveButton) {
                archiveButton.disabled = false;
                archiveButton.removeAttribute('aria-busy');
                archiveButton.innerHTML = archiveLabel;
                archiveButton = null;
            }
        });
        // Kept images belong to the link they were fetched for: editing the link drops them.
        var linkInput = form.querySelector('[name=link]');
        if (linkInput) {
            linkInput.addEventListener('input', function () {
                form.querySelectorAll('[data-w2a-archive-kept]').forEach(function (node) { node.remove(); });
            });
        }

        form.addEventListener('submit', function (event) {
            if (archivePending) { event.preventDefault(); return; }
            var button = event.submitter || form.querySelector('button[type=submit]');
            // After every handler has run: only a submission that really goes ahead locks the button.
            setTimeout(function () {
                if (event.defaultPrevented || !button || button.disabled) { return; }
                locked = button;
                lockedLabel = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'جارٍ الحفظ…';
            }, 0);
        });
        window.addEventListener('pageshow', function (event) { if (event.persisted) { unlock(); } });

        // ── additional sheikhs ─────────────────────────────────────────────────────────────────────────────────
        var url = box.getAttribute('data-search-url');
        var exclude = box.getAttribute('data-exclude');
        var max = parseInt(box.getAttribute('data-max'), 10);
        var min = parseInt(box.getAttribute('data-min'), 10);
        var input = box.querySelector('#w2a-aa-search');
        var status = box.querySelector('#w2a-aa-status');
        var results = box.querySelector('#w2a-aa-results');
        var more = box.querySelector('#w2a-aa-more');
        var chips = box.querySelector('[data-aa-selected]');
        var count = box.querySelector('[data-aa-count]');
        var hint = box.querySelector('#w2a-aa-hint');

        var selected = new Map();
        chips.querySelectorAll('li[data-id]').forEach(function (li) { selected.set(li.getAttribute('data-id'), li.getAttribute('data-name')); });

        var timer = null, controller = null, sequence = 0, query = '', page = 1;

        function say(text) { status.textContent = text; }

        function renderChips() {
            chips.innerHTML = '';
            selected.forEach(function (name, id) {
                var li = document.createElement('li');
                li.className = 'w2a-aa__chip';
                li.setAttribute('data-id', id);
                li.setAttribute('data-name', name);
                var label = document.createElement('span');
                label.textContent = name;
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'w2a-aa__remove';
                remove.setAttribute('aria-label', 'إزالة ' + name);
                remove.textContent = '×';
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'additional_authors[]';
                hidden.value = id;
                li.appendChild(label); li.appendChild(remove); li.appendChild(hidden);
                chips.appendChild(li);
            });
            count.textContent = String(selected.size);
            syncBoxes();
        }

        function syncBoxes() {
            var full = selected.size >= max;
            results.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                cb.checked = selected.has(cb.value);
                cb.disabled = full && !cb.checked;
            });
            if (full) { say('بلغت الحد الأقصى (' + max + ' دعاة).'); }
        }

        function append(items) {
            items.forEach(function (author) {
                var id = String(author.id);
                var li = document.createElement('li');
                var label = document.createElement('label');
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = id;
                cb.setAttribute('data-name', author.name);
                var name = document.createElement('span');
                name.textContent = author.name;
                label.appendChild(cb); label.appendChild(name); li.appendChild(label);
                results.appendChild(li);
            });
            syncBoxes();
        }

        function search(reset) {
            if (controller) { controller.abort(); }
            if (reset) { page = 1; results.innerHTML = ''; more.hidden = true; }
            if (query.length < min) { say(''); return; }            // the hint below the box says it

            var mine = ++sequence;
            controller = typeof AbortController === 'function' ? new AbortController() : null;
            say('جارٍ البحث…');
            fetch(url + '?q=' + encodeURIComponent(query) + '&exclude=' + encodeURIComponent(exclude) + '&page=' + page, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                if (!response.ok) { throw new Error(String(response.status)); }
                return response.json();
            }).then(function (data) {
                if (mine !== sequence) { return; }          // a newer search has started: ignore this answer
                append(data.results || []);
                more.hidden = !data.more;
                if (!results.children.length) { say('لا توجد نتائج.'); }
                else if (selected.size < max) { say(results.children.length + ' نتيجة' + (data.more ? '، وهناك المزيد.' : '.')); }
            }).catch(function (error) {
                if (mine !== sequence || (error && error.name === 'AbortError')) { return; }
                say('تعذّر البحث، حاول مرة أخرى.');
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var next = input.value.trim();
            hint.hidden = next.length >= min;                // only while too short to search
            timer = setTimeout(function () { query = next; search(true); }, 350);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {                     // never submit the material form from the search box
                event.preventDefault();
                clearTimeout(timer);
                query = input.value.trim();
                search(true);
            }
        });
        more.addEventListener('click', function () { page += 1; search(false); });

        results.addEventListener('change', function (event) {
            var cb = event.target;
            if (!cb || cb.type !== 'checkbox') { return; }
            if (cb.checked) {
                if (selected.size >= max) { cb.checked = false; return; }
                selected.set(cb.value, cb.getAttribute('data-name'));
            } else {
                selected.delete(cb.value);
            }
            renderChips();
        });
        chips.addEventListener('click', function (event) {
            var button = event.target.closest('.w2a-aa__remove');
            if (!button) { return; }
            selected.delete(button.closest('li').getAttribute('data-id'));
            renderChips();
            input.focus();
        });

        renderChips();
    })();
</script>

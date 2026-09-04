{{--
    Global Chrome step — ported verbatim from legacy header.php:246-525's
    `.header-navigation` block. Every menu link below is legacy's own
    hardcoded, DB-free href. Links to not-yet-migrated pages are kept
    as-is and will 404 until those routes exist — out of scope for this
    global-chrome-only step, not a bug introduced here. `categories.htm`/
    `fatawa-categories.htm` (closed in later waves) and
    `video-advanced-search.htm` (G-09-01, Phase 1 audit — routed to the
    existing `KhotabSearchController`) are no longer in that category;
    this comment is not otherwise updated per link.

    Not reproduced: the commented-out "Mega Menu" block (header.php:353-408
    — legacy's own dead code, still commented out in the source).

    **w2acd relative-nav repair (decision-log #57), `BUSINESS_REPAIR_LOW_RISK`,
    explicitly NOT legacy parity.** Every `.htm` href/`search.htm` form
    action below was, until this repair, written bare-relative (no
    leading `/`) — byte-faithful to legacy's own `header.php`. Legacy's
    own pretty URLs are all root-level, so a bare-relative href resolves
    identically to a root-relative one from any FLAT page — but this
    partial is also rendered on several genuinely nested-path pages
    (`/w2acd/cds.php`, `/w2acd/item.php`, and others), where the browser
    resolves the same bare href against the current nested directory
    instead (e.g. `categories.htm` → `/w2acd/categories.htm`, a 404).
    Every link below is now root-relative (a leading `/`) — a verified
    no-op on every flat page (root-relative and bare-relative resolve
    identically there) and the actual fix for every nested one. No link
    target, route, or URL shape changed — only how the browser resolves
    the existing target.
--}}
<ul id="w2a-primary-navigation">
    <li>
        <a class="dropdown-toggle" href="/">الرئيسية</a>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">المرئيات</a>
        <ul class="dropdown-menu">
            <li><a href="/categories.htm">حسب التصنيف الموضوعي</a></li>
            <li><a href="/khotab-video.htm">حسب أسماء الشيوخ</a></li>
            <li><a href="/channels.htm">حسب القنوات</a></li>
            <li><a href="/khotab-video-today.htm">حسب تاريخ الإضافة</a></li>
            <li><a href="/video-advanced-search.htm">بحث فى المرئيات</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">صوتيات</a>
        <ul class="dropdown-menu">
            <li><a href="/khotab-audio.htm">دروس ومحاضرات</a></li>
            <li><a href="/recite.htm">تلاوات قرآنية</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">الفتاوى</a>
        <ul class="dropdown-menu">
            <li><a href="/fatawa-categories.htm">حسب التصنيف الموضوعي</a></li>
            <li><a href="/fatawa-authors.htm">حسب أسماء الشيوخ</a></li>
            <li><a href="/fatawa-channels.htm">حسب القنوات</a></li>
            <li><a href="/fatawa-today.htm">حسب تاريخ الإضافة</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">منوعات</a>
        <ul class="dropdown-menu">
            <li><a href="/var-group-98.htm">أناشيد اسلامية</a></li>
            <li><a href="/var-group-16.htm">مقاطع مؤثرة</a></li>
            <li><a href="/var-group-57.htm">كرتون أطفال</a></li>
            <li><a href="/var-group-12.htm">أفلام وثائقية</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">البث المباشر</a>
        <ul class="dropdown-menu">
            <li><a href="/radio.htm">راديو الطريق إلى الله</a></li>
            <li><a target="_blank" href="https://www.facebook.com/Way2Allah.Fb/">البث المباشر</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">إصدارات الموقع</a>
        <ul class="dropdown-menu">
            <li><a href="/var-group-158.htm">مقاطع مرئية</a></li>
            <li><a href="/khotab-pdf.htm">المواد المفرغة</a></li>
            <li><a href="/gallery.htm">تصميمات دعوية</a></li>
            <li><a href="/cds-main.htm">إسطوانات دعوية</a></li>
            <li><a href="/category-487.htm">برامج حصرية لشبكة الطريق إلى الله</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">أقسام خاصة</a>
        <ul class="dropdown-menu">
            <li><a href="https://way2allah.com/eye/">حملة عينك أمانة</a></li>
            <li><a href="/khotab-series-638.htm">على فين ياشباب</a></li>
            <li><a href="/khotab-series-867.htm">أنا لازم اتغير</a></li>
            <li><a href="/khotab-group-6.htm">الطريق الى الله</a></li>
            <li><a href="/khotab-series-510.htm">ابدأ من جديد</a></li>
            <li><a href="/khotab-series-893.htm">لكِ .. يا أختاه</a></li>
            <li><a href="/khotab-series-568.htm">الشباب والزواج</a></li>
            <li><a href="/khotab-series-105.htm">الطريق الى القرآن</a></li>
            <li><a href="/khotab-video-95.htm">الأكاديمية الإسلامية</a></li>
            <li><a href="/category-397.htm">الأكاديمية الإسلامية 1433-2012</a></li>
        </ul>
    </li>

    <li>
        <a class="dropdown-toggle" data-target="#" href="/chat_room.htm">غرفة الهداية</a>
    </li>

    <li>
        <a class="dropdown-toggle" data-target="#" href="/social.htm">تابعنا</a>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">اتصل بنا</a>
        <ul class="dropdown-menu">
            <li><a href="/landing_page.htm">من نحن</a></li>
            <li><a href="/social.htm">تابعنا</a></li>
            <li><a href="https://m.me/Way2allahCom" target="_blank">راسلنا</a></li>
            <li><a href="/share.htm">إنشر الموقع</a></li>
        </ul>
    </li>

    {{--
        Search box — legacy header.php:410-524. Form posts to search.htm,
        already implemented (SearchController::search). Autocomplete arrays
        below read the same cached .txt files legacy itself reads (via the
        public/w2a_autocomplete symlink), no database query — gracefully
        empty ({}) if a file is missing or empty, matching the "no DB
        query" instruction rather than legacy's own fallback of generating
        the cache from a live query.

        Department dropdown intentionally simplified to just the default
        "إختر" option — legacy's w2a_search_depts_arr() is a DB-backed
        helper, out of scope here (no business logic / no DB queries in
        this step).
    --}}
    <li class="menu-search">
        <span class="sep"></span>
        <button type="button" class="w2a-search-trigger-btn" aria-label="البحث المتقدم" title="البحث المتقدم">
            <i class="fa fa-search search-btn" aria-hidden="true"></i>
        </button>
        <div class="search-box" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="w2a-search-title">
            <div class="w2a-search-modal-card">
                <div class="w2a-search-modal-header">
                    <div class="w2a-search-modal-title">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <div>
                            <h3 id="w2a-search-title">البحث المتقدم في المكتبة</h3>
                            <p>ابحث عن السلاسل والدروس والخطب والأقسام بسهولة</p>
                        </div>
                    </div>
                    <button type="button" class="w2a-search-close-btn" aria-label="إغلاق نافذة البحث">&times;</button>
                </div>
                <div class="w2a-search-modal-body">
                    <script type="text/javascript">
                var popup_authors_autocomplete_list = {
                    @php
                        $authorsFile = public_path('w2a_autocomplete/authors.txt');
                        $authorsContents = is_file($authorsFile) ? trim(file_get_contents($authorsFile)) : '';
                        $authorsList = $authorsContents !== '' ? explode(',|^', $authorsContents) : [];
                    @endphp
                    @foreach ($authorsList as $index => $author)
                        "{{ $index + 1 }}": "{{ $author }}"@if (!$loop->last),@endif
                    @endforeach
                }
                var popup_channels_autocomplete_list = {
                    @php
                        $channelsFile = public_path('w2a_autocomplete/channels.txt');
                        $channelsContents = is_file($channelsFile) ? trim(file_get_contents($channelsFile)) : '';
                        $channelsList = $channelsContents !== '' ? explode(',|^', $channelsContents) : [];
                    @endphp
                    @foreach ($channelsList as $index => $channel)
                        "{{ $index + 1 }}": "{{ $channel }}"@if (!$loop->last),@endif
                    @endforeach
                }
                    </script>
                    <form class="form-horizontal w2a_advanced_search" name="w2a_search_form" id="w2a_search_form" method="post" action="/search.htm">
                        @csrf
                        <div class="w2a-search-grid">
                            <div class="w2a-search-field">
                                <label for="w2a_kh_title"><i class="fa fa-font" aria-hidden="true"></i> اسم السلسلة أو المادة :</label>
                                <input type="text" class="form-control" name="kh_title" id="w2a_kh_title" placeholder="أدخل اسم السلسلة أو المادة...">
                        <span id="w2a_kh_title_msg" class="way_msg"></span>
                            </div>
                            <div class="w2a-search-field">
                                <label for="w2a_kh_dept"><i class="fa fa-th-large" aria-hidden="true"></i> القسم :</label>
                                <select class="form-control" id="w2a_kh_dept" name="kh_dept">
                                    <option value="0">إختر القسم</option>
                                </select>
                        <span id="w2a_kh_dept_msg" class="way_msg"></span>
                            </div>
                            <div class="w2a-search-field">
                                <label for="w2a_kh_author_name"><i class="fa fa-user" aria-hidden="true"></i> الشيخ / المحاضر :</label>
                                <input type="text" class="form-control" name="kh_author_name" id="w2a_kh_author_name" placeholder="ابحث باسم الشيخ...">
                            </div>
                            <div class="w2a-search-field">
                                <label for="w2a_kh_channel"><i class="fa fa-tv" aria-hidden="true"></i> القناة :</label>
                                <input type="text" class="form-control" name="kh_channel" id="w2a_kh_channel" placeholder="اختر القناة...">
                            </div>
                            <div class="w2a-search-field w2a-search-fullwidth">
                                <span class="w2a-search-label"><i class="fa fa-calendar" aria-hidden="true"></i> تاريخ الإضافة :</span>
                                <div class="w2a-date-range">
                                    <label class="sr-only" for="w2a_kh_from">من تاريخ</label>
                                    <input type="date" name="kh_from" class="form-control datepikerinput mini-input" id="w2a_kh_from">
                                    <label class="sr-only" for="w2a_kh_to">إلى تاريخ</label>
                                    <input type="date" name="kh_to" class="form-control datepikerinput mini-input" id="w2a_kh_to">
                                </div>
                            </div>
                        </div>
                        <div class="w2a-search-actions">
                            <button type="submit" name="kh_search" id="w2a_kh_search" class="w2a-search-submit-btn">
                                <i class="fa fa-search" aria-hidden="true"></i> بــحــث
                            </button>
                            <button type="button" class="w2a-search-cancel-btn w2a-search-close-btn">إلغاء</button>
                        <noscript>
                            عفوا .. لا يمكنك البحث قبل تفعيل الجافا سكريبت و الكوكيز فى المتصفح
                        </noscript>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </li>
</ul>

{{--
    Global Chrome step — ported verbatim from legacy header.php:246-525's
    `.header-navigation` block. Every menu link below is legacy's own
    hardcoded, DB-free href, kept exactly as written there (relative, no
    leading slash — legacy's pretty URLs are all root-level, same as
    Laravel's routes here, so relative hrefs resolve identically). Links
    to not-yet-migrated pages (e.g. categories.htm, fatawa-categories.htm,
    video-advanced-search.htm) are kept as-is and will 404 until those
    routes exist — out of scope for this global-chrome-only step, not a
    bug introduced here.

    Not reproduced: the commented-out "Mega Menu" block (header.php:353-408
    — legacy's own dead code, still commented out in the source).
--}}
<ul>
    <li>
        <a class="dropdown-toggle" href="/">الرئيسية</a>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">المرئيات</a>
        <ul class="dropdown-menu">
            <li><a href="categories.htm">حسب التصنيف الموضوعي</a></li>
            <li><a href="khotab-video.htm">حسب أسماء الشيوخ</a></li>
            <li><a href="channels.htm">حسب القنوات</a></li>
            <li><a href="khotab-video-today.htm">حسب تاريخ الإضافة</a></li>
            <li><a href="video-advanced-search.htm">بحث فى المرئيات</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">صوتيات</a>
        <ul class="dropdown-menu">
            <li><a href="khotab-audio.htm">دروس ومحاضرات</a></li>
            <li><a href="recite.htm">تلاوات قرآنية</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">الفتاوى</a>
        <ul class="dropdown-menu">
            <li><a href="fatawa-categories.htm">حسب التصنيف الموضوعي</a></li>
            <li><a href="fatawa-authors.htm">حسب أسماء الشيوخ</a></li>
            <li><a href="fatawa-channels.htm">حسب القنوات</a></li>
            <li><a href="fatawa-today.htm">حسب تاريخ الإضافة</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">منوعات</a>
        <ul class="dropdown-menu">
            <li><a href="var-group-98.htm">أناشيد اسلامية</a></li>
            <li><a href="var-group-16.htm">مقاطع مؤثرة</a></li>
            <li><a href="var-group-57.htm">كرتون أطفال</a></li>
            <li><a href="var-group-12.htm">أفلام وثائقية</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">البث المباشر</a>
        <ul class="dropdown-menu">
            <li><a href="radio.htm">راديو الطريق إلى الله</a></li>
            <li><a target="_blank" href="https://www.facebook.com/Way2Allah.Fb/">البث المباشر</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">إصدارات الموقع</a>
        <ul class="dropdown-menu">
            <li><a href="var-group-158.htm">مقاطع مرئية</a></li>
            <li><a href="khotab-pdf.htm">المواد المفرغة</a></li>
            <li><a href="gallery.htm">تصميمات دعوية</a></li>
            <li><a href="cds-main.htm">إسطوانات دعوية</a></li>
            <li><a href="category-487.htm">برامج حصرية لشبكة الطريق إلى الله</a></li>
        </ul>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">أقسام خاصة</a>
        <ul class="dropdown-menu">
            <li><a href="https://way2allah.com/eye/">حملة عينك أمانة</a></li>
            <li><a href="khotab-series-638.htm">على فين ياشباب</a></li>
            <li><a href="khotab-series-867.htm">أنا لازم اتغير</a></li>
            <li><a href="khotab-group-6.htm">الطريق الى الله</a></li>
            <li><a href="khotab-series-510.htm">ابدأ من جديد</a></li>
            <li><a href="khotab-series-893.htm">لكِ .. يا أختاه</a></li>
            <li><a href="khotab-series-568.htm">الشباب والزواج</a></li>
            <li><a href="khotab-series-105.htm">الطريق الى القرآن</a></li>
            <li><a href="khotab-video-95.htm">الأكاديمية الإسلامية</a></li>
            <li><a href="category-397.htm">الأكاديمية الإسلامية 1433-2012</a></li>
        </ul>
    </li>

    <li>
        <a class="dropdown-toggle" data-target="#" href="chat_room.htm">غرفة الهداية</a>
    </li>

    <li>
        <a class="dropdown-toggle" data-target="#" href="social.htm">تابعنا</a>
    </li>

    <li class="dropdown">
        <a class="dropdown-toggle" data-toggle="dropdown" data-target="#" href="#">اتصل بنا</a>
        <ul class="dropdown-menu">
            <li><a href="landing_page.htm">من نحن</a></li>
            <li><a href="social.htm">تابعنا</a></li>
            <li><a href="https://m.me/Way2allahCom" target="_blank">راسلنا</a></li>
            <li><a href="share.htm">إنشر الموقع</a></li>
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
        <i class="fa fa-search search-btn"></i>
        <div class="search-box">
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
            <form class="form-horizontal w2a_advanced_search" name="w2a_search_form" id="w2a_search_form" method="post" action="search.htm">
                @csrf
                <div class="form-group">
                    <label for="w2a_kh_title" class="col-sm-4 control-label">اسم السلسلة أو المادة :</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control" name="kh_title" id="w2a_kh_title">
                        <span id="w2a_kh_title_msg" class="way_msg"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="w2a_kh_dept" class="col-sm-4 control-label">القسم :</label>
                    <div class="col-sm-8">
                        <select class="form-control" id="w2a_kh_dept" name="kh_dept">
                            <option value="0">إختر</option>
                        </select>
                        <span id="w2a_kh_dept_msg" class="way_msg"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="w2a_kh_author_name" class="col-sm-4 control-label">الشيخ :</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control" name="kh_author_name" id="w2a_kh_author_name" />
                    </div>
                </div>
                <div class="form-group">
                    <label for="w2a_kh_channel" class="col-sm-4 control-label">القناة :</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control" name="kh_channel" id="w2a_kh_channel" />
                    </div>
                </div>
                <div class="form-group">
                    <label for="w2a_kh_from" class="col-xs-12 col-sm-4 control-label">تاريخ الإضافة :</label>
                    <div class="col-xs-6 col-sm-4">
                        <input type="text" name="kh_from" class="form-control datepikerinput mini-input" id="w2a_kh_from" placeholder="من"/>
                    </div>
                    <div class="col-xs-6 col-sm-4">
                        <input type="text" name="kh_to" class="form-control datepikerinput mini-input" id="w2a_kh_to" placeholder="إلى"/>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-offset-2 col-sm-8">
                        <input type="submit" name="kh_search" id="w2a_kh_search" value="بــحــث" style="width:80px; height:30px;"/>
                        <noscript>
                            عفوا .. لا يمكنك البحث قبل تفعيل الجافا سكريبت و الكوكيز فى المتصفح
                        </noscript>
                    </div>
                </div>
            </form>
        </div>
    </li>
</ul>

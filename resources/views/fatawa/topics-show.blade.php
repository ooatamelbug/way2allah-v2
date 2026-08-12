@extends('layouts.app')

@section('title', $categoryModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="تصنيفات فرعية">
                <ul>
                    @foreach ($subCategories as $sub)
                        <li><a href="/fatawa-topics-{{ $sub->id }}-1.htm">{{ $sub->title }}</a></li>
                    @endforeach
                </ul>
            </section>

            <section aria-label="موضوعات الفتاوى">
                <ul>
                    {{-- Route order: topic id first, category id second (.htaccess:301-302's t_id=$1&cat_id=$2). --}}
                    @foreach ($topics as $topic)
                        <li><a href="/fatawa-group-{{ $topic->id }}-{{ $categoryModel->id }}.htm">{{ $topic->topic_name }}</a></li>
                    @endforeach
                </ul>
                {{ $topics->links() }}
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/fatawa-download-{{ $item->id }}.htm">{{ $item->question_text }}</a></li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/fatawa-download-{{ $item->id }}.htm">{{ $item->question_text }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

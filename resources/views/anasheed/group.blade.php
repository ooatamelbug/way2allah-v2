@extends('layouts.app')

@section('title', $groupModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        @if($subGroups->isNotEmpty())
            <section aria-label="قائمة الأقسام الفرعية">
                <ul>
                    @foreach ($subGroups as $subGroup)
                        <li><a href="/var-group-{{ $subGroup->id }}.htm">{{ $subGroup->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-label="قائمة المواد">
            @forelse ($items as $item)
                <div>
                    <a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                </div>
            @empty
                <p>عفوا ، لا يوجد مواد مضافة في هذا القسم</p>
            @endforelse
            {{ $items->links() }}
        </section>

        <aside aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>

        @if(!empty($groupModel->description))
            <section aria-label="وصف القسم">{{ $groupModel->description }}</section>
        @endif
    </div>
@endsection

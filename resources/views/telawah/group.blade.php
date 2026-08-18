@extends('layouts.app')

@section('title', 'قسم التلاوات - ' . $groupModel->title)

@section('content')
    {{--
        G-13-07 (media/visual parity phase): `telawah/functions.php:164`'s
        `list_telawat_groups($id)` (`group.php:34`) — same hardcoded
        `images/telawah.gif` as authors.blade.php's own listing, now
        reachable via the real `public/images` symlink (G-13-02).
    --}}
    @if($subGroups->isNotEmpty())
        <section aria-label="قائمة الأقسام الفرعية">
            <ul>
                @foreach ($subGroups as $subGroup)
                    <li>
                        <a href="/recite-group-{{ $subGroup->id }}.htm">
                            <img src="/images/telawah.gif" alt="{{ $subGroup->title }}">
                            <span>{{ $subGroup->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-label="قائمة السور القرآنية">
        @forelse ($items as $item)
            <div>
                <a href="/recite-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                <a href="/recite-download-{{ $item->id }}.htm">حفظ</a>
            </div>
        @empty
            <p>عفوا ، لا يوجد تلاوات مضافة في هذا القسم</p>
        @endforelse
    </section>
@endsection

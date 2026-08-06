@extends('layouts.app')

@section('title', 'قسم التلاوات - ' . $groupModel->title)

@section('content')
    @if($subGroups->isNotEmpty())
        <section aria-label="قائمة الأقسام الفرعية">
            <ul>
                @foreach ($subGroups as $subGroup)
                    <li><a href="/recite-group-{{ $subGroup->id }}.htm">{{ $subGroup->title }}</a></li>
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

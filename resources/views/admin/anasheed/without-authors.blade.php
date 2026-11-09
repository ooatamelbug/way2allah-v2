@extends('layouts.app')

@section('title', 'قائمة بالمنوعات التي لا تندرج تحت شيخ بعينه')

@section('content')
    {{-- OLD anasheed_without_authors() (modules/Anasheed/index.php:303-345) + without_{head,body,foot}.htm: an admin-only
         public page, inside the site layout as in OLD. The route itself requires anasheed.editanasheed. --}}
    <x-page-chrome heading="قائمة بالمنوعات التي لا تندرج تحت شيخ بعينه" />

    <div class="margin-bottom-40" dir="rtl" data-anasheed-without-authors>
        @if ($items->isEmpty())
            <p>لا توجد مواد بدون شيخ في هذه الصفحة.</p>
        @else
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th scope="col" width="10">م</th>
                        <th scope="col">إسم المادة</th>
                        <th scope="col" width="20">تعديل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $items->firstItem() + $loop->index }}</td>
                            <td><strong><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></strong></td>
                            <td>
                                <x-admin.contextual-controls variant="inline"
                                    :controls="[\App\Domain\Admin\Contextual\Controls\AnasheedWithoutAuthorsControls::edit((int) $item->id, $listPage)]" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{ $items->onEachSide(1)->links('components.content.premium-pagination') }}
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'الوصلات البديلة: '.$item->title)

@section('content')
    <x-admin-portlet :title="'المادة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-file-video-o">
        @include('admin.mirrors._errors')
        <p>
            {{ (int) $item->vedio === 1 ? 'مادة مرئية' : 'مادة صوتية' }}
            &nbsp;·&nbsp; عدد الوصلات: <strong>{{ $mirrors->count() }}</strong>
            {{-- The legacy counter is compatibility state, shown read-only.
                 It is never repaired here (3E.1 I-2). --}}
            &nbsp;·&nbsp; <span class="text-muted">العداد القديم: {{ (int) $item->mirror }}</span>
        </p>

        {{-- OLD Admin Migration Phase 2 — approved integration point into this
             frozen Phase 1 view: a link only, to the existing Phase 2
             link-health page, which hosts the existing recheck / fix-size
             actions. Nothing is rechecked from here. Shown under the same rule
             as that page's route (khotab.repair, super-admin bypass), via the
             same authorizer, so the link never leads to a 403. --}}
        @if (app(\App\Domain\Admin\Support\AdminRecordAuthorizer::class)->allows(auth('admin')->user(), 'khotab.repair'))
            <p><a href="{{ route('admin.link-quality.khotab.item', $item) }}">صحة الروابط وإعادة الفحص ←</a></p>
        @endif

        @if ($clipboard && $canUseClipboard)
            <div class="alert alert-info">
                الوصلة #{{ $clipboard->id }} ({{ $clipboard->comment }}) جاهزة للنقل.
                @if ((int) $clipboard->khid !== (int) $item->id && $canMoveClipboard)
                    <form method="post" action="{{ route('admin.mirrors.khotab.move', $clipboard) }}" style="display:inline">
                        @csrf
                        <x-admin.contextual-return />
                        <input type="hidden" name="target_item_id" value="{{ $item->id }}">
                        <button type="submit" class="btn btn-sm green">نقل الوصلة هنا</button>
                    </form>
                @endif
                <form method="post" action="{{ route('admin.mirrors.khotab.clipboard.clear') }}" style="display:inline">
                    @csrf
                    <x-admin.contextual-return />
                    <button type="submit" class="btn btn-sm default">إلغاء</button>
                </form>
            </div>
        @endif

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>التعليق</th><th>الوصلة</th><th>الحجم</th><th>النوع</th><th>نسخة احتياطية</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($mirrors as $mirror)
                    <tr>
                        <td>{{ $mirror->id }}</td>
                        <td>{{ $mirror->comment }}</td>
                        <td dir="ltr" style="word-break:break-all">{{ $mirror->link }}</td>
                        <td>{{ number_format(((int) $mirror->linksize) / 1048576, 2) }} MB</td>
                        <td>{{ (int) $mirror->vedio === 1 ? 'مرئية' : 'صوتية' }}</td>
                        <td>{{ (int) $mirror->backupme === 1 ? 'نعم' : 'لا' }}</td>
                        <td style="white-space:nowrap">
                            @if ($canEdit)
                                <a href="{{ route('admin.mirrors.khotab.edit', $mirror) }}" class="btn btn-sm blue">تعديل</a>
                                <form method="post" action="{{ route('admin.mirrors.khotab.pick', $mirror) }}" style="display:inline">
                                    @csrf
                                    <x-admin.contextual-return />
                                    <button type="submit" class="btn btn-sm default">نقل</button>
                                </form>
                            @endif
                            @if ($canDelete)
                                <form method="post" action="{{ route('admin.mirrors.khotab.destroy', $mirror) }}" style="display:inline"
                                      onsubmit="return confirm('حذف الوصلة #{{ $mirror->id }}؟')">
                                    @csrf
                                    <x-admin.contextual-return />
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد وصلات بديلة لهذه المادة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>

    @if ($canAdd)
        <x-admin-portlet title="إضافة وصلة بديلة" color="green" icon="fa fa-plus">
            <form method="post" action="{{ route('admin.mirrors.khotab.store', $item) }}">
                @csrf
                <x-admin.contextual-return />
                <label>التعليق <input type="text" name="comment" value="{{ old('comment') }}" maxlength="255" class="form-control"></label>
                <label>وصلة التحميل <input type="text" name="link" value="{{ old('link') }}" dir="ltr" required class="form-control"></label>
                <label>الحجم (ميجا بايت) <input type="text" name="linksize_mb" value="{{ old('linksize_mb') }}" class="form-control"></label>
                <label><input type="radio" name="vedio" value="0" @checked((string) old('vedio', (int) $item->vedio) === '0')> مادة صوتية</label>
                <label><input type="radio" name="vedio" value="1" @checked((string) old('vedio', (int) $item->vedio) === '1')> مادة مرئية</label>
                <label><input type="checkbox" name="backupme" value="1" @checked(old('backupme'))> إنزال نسخة إحتياطية ضمن أرشيف الموقع</label>
                <button type="submit" class="btn green">أضف الآن</button>
            </form>
        </x-admin-portlet>
    @endif
@endsection

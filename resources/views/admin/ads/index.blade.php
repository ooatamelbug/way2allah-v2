@extends('layouts.admin')

@section('title', 'قائمة الإعلانات')

@section('content')
    {{-- F01 / B6a — OLD w2a_ads_main(): search form (name, dates, show, type, position) and the list
         (name, status, shown/hidden toggle, position, views, clicks, edit, delete), ORDER BY show DESC, id DESC.
         Administration only: nothing here renders an advertisement on the public site. --}}
    <x-admin-portlet title="قائمة الإعلانات" color="blue" icon="fa fa-bullhorn">
        @include('admin.mirrors._errors')
        <p>
            <a href="{{ route('admin.ads.create') }}" class="btn green"><i class="fa fa-plus"></i> اضافة إعلان جديد</a>
            <a href="{{ route('admin.ads.reports') }}" class="btn default"><i class="fa fa-bar-chart"></i> تقارير الإعلانات</a>
        </p>

        <form method="get" action="{{ route('admin.ads.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>اسم الإعلان : <input type="text" name="name" value="{{ $filters['name'] }}" class="form-control"></label>
            <label>من : <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></label>
            <label>إلى : <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
            <label>نوع العرض :
                <select name="show" class="form-control">
                    <option value="2" @selected($filters['show'] === 2)>الكل</option>
                    <option value="0" @selected($filters['show'] === 0)>غير معروض</option>
                    <option value="1" @selected($filters['show'] === 1)>معروض</option>
                </select>
            </label>
            <label>مكان الإعلان :
                <select name="position" class="form-control">
                    <option value="-1" @selected($filters['position'] === -1)>الكل</option>
                    @foreach (\App\Domain\Admin\Support\LegacyAds::POSITIONS as $id => $label)
                        <option value="{{ $id }}" @selected($filters['position'] === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>نوع الإعلان :
                <select name="type" class="form-control">
                    <option value="2" @selected($filters['type'] === 2)>الكل</option>
                    <option value="0" @selected($filters['type'] === 0)>جوجل أدسنس</option>
                    <option value="1" @selected($filters['type'] === 1)>إعلان مصور</option>
                </select>
            </label>
            <button type="submit" class="btn blue">بـــحــث</button>
            <a href="{{ route('admin.ads.index') }}" class="btn default">عرض الكل</a>
        </form>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>إسم الاعلان</th><th>الحالة</th><th>معروض/غير معروض</th><th>مكان عرضه في الصفحة</th>
                    <th>عدد مرات الظهور</th><th>عدد النقرات</th><th>تعديل</th><th>حذف</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ads as $ad)
                    @php($isCode = (int) $ad->type === \App\Domain\Admin\Support\LegacyAds::TYPE_CODE)
                    @php($canChange = ! $isCode || $isSuperAdmin)
                    <tr>
                        <td>{{ $ad->name }} <small class="text-muted">({{ \App\Domain\Admin\Support\LegacyAds::typeLabel($ad->type) }})</small></td>
                        <td>{{ \App\Domain\Admin\Support\LegacyAds::status($ad) }}</td>
                        <td>
                            @if ($canChange)
                                <form method="post" action="{{ route('admin.ads.toggle', $ad) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="show" value="{{ (int) $ad->show === 1 ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-xs {{ (int) $ad->show === 1 ? 'green' : 'default' }}"
                                            title="{{ (int) $ad->show === 1 ? 'إيقاف العرض' : 'تفعيل العرض' }}">{{ (int) $ad->show === 1 ? 'معروض' : 'غير معروض' }}</button>
                                </form>
                            @else
                                {{ (int) $ad->show === 1 ? 'معروض' : 'غير معروض' }}
                            @endif
                        </td>
                        <td>{{ \App\Domain\Admin\Support\LegacyAds::positionLabel($ad->position) }}</td>
                        <td>{{ (int) $ad->num_view }}</td>
                        <td>{{ (int) $ad->num_click }}</td>
                        <td>
                            @if ($canChange)
                                <a href="{{ route('admin.ads.edit', $ad) }}" class="btn btn-xs blue">تعديل</a>
                            @else
                                <span class="text-muted" title="إعلانات الشفرة يعدلها المدير العام فقط">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($canChange)
                                <form method="post" action="{{ route('admin.ads.destroy', $ad) }}" style="display:inline"
                                      onsubmit="return confirm('هل ترغب في مسح الاعلان رقم {{ (int) $ad->id }}؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs red"><i class="fa fa-trash-o"></i> حذف</button>
                                </form>
                            @else
                                <span class="text-muted" title="إعلانات الشفرة يحذفها المدير العام فقط">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">لا يوجد اعلانات مخزنة</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $ads->links() }}
    </x-admin-portlet>
@endsection

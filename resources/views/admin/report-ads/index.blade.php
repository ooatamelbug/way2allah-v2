@extends('layouts.admin')

@section('title', 'قائمة الإبلاغات عن إعلانات غير لائقة')

@section('content')
    {{-- F01 / B6b — OLD w2a_bad_ads_main(): م / بريد المستخدم / رابط الإعلان / تاريخ الإبلاغ / حذف, ORDER BY id DESC.
         Values are escaped; a reported URL is a link only when it is a strict http(s) URL (OLD printed it raw into
         href, so a stored javascript:/quoted value would have executed). Reports cannot be created anywhere today. --}}
    <x-admin-portlet title="قائمة الإبلاغات عن إعلانات غير لائقة" color="red" icon="fa fa-flag">
        @include('admin.mirrors._errors')
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>م</th><th>بريد المستخدم</th><th>رابط الإعلان</th><th>تاريخ الإبلاغ</th><th>حذف</th></tr>
            </thead>
            <tbody>
                @forelse ($reports as $index => $report)
                    @php($href = \App\Domain\Admin\Support\SafeExternalLink::href($report->ads_url))
                    <tr>
                        <td>{{ $reports->firstItem() + $index }}</td>
                        <td dir="ltr">{{ $report->user_email }}</td>
                        <td dir="ltr">
                            @if ($href !== null)
                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer nofollow">{{ $report->ads_url }}</a>
                            @else
                                {{ $report->ads_url }}
                            @endif
                        </td>
                        <td dir="ltr">{{ $report->db_creation_date }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.report_ads.destroy', (int) $report->id) }}" style="display:inline"
                                  onsubmit="return confirm('هل أنت متأكد من أنك تريد حذف هذا الإبلاغ بالفعل؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs red"><i class="fa fa-trash-o"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لم يتم إرسال أي إبلاغات حتى الأن</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $reports->links() }}
    </x-admin-portlet>
@endsection

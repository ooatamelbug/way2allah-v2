@extends('layouts.admin')

@section('title', 'التحكم في الإستفتاءات')

@section('content')
    {{-- OLD Admin Migration Phase 4 — PHP-Nuke polls (not the custom surveys).
         One list replaces OLD's two pickers (polledit_select / remove); same
         order, ORDER BY timeStamp. --}}
    <x-admin-portlet title="التحكم في الإستفتاءات" color="blue" icon="fa fa-bar-chart">
        @include('admin.mirrors._errors')
        <p><a href="{{ route('admin.polls.create') }}" class="btn green"><i class="fa fa-plus"></i> أنشئ استفتاءا جديدا</a></p>
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>عنوان الإستفتاء</th><th>اللغة</th><th>تصويتات</th><th>التعليقات</th><th>التاريخ</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($polls as $poll)
                    <tr>
                        <td>{{ $poll->pollID }}</td>
                        <td>{{ $poll->pollTitle }}</td>
                        <td>{{ $poll->planguage }}</td>
                        <td>{{ (int) $poll->voters }}</td>
                        <td>{{ (int) $poll->comments }}</td>
                        <td dir="ltr">{{ $poll->timeStamp ? date('Y-m-d', (int) $poll->timeStamp) : '' }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('admin.polls.edit', $poll) }}" class="btn btn-sm blue">تعديل الإستفتاء</a>
                            @if ($canModerateComments)
                                <a href="{{ route('admin.polls.comments', $poll) }}" class="btn btn-sm default">التعليقات</a>
                            @endif
                            <form method="post" action="{{ route('admin.polls.destroy', $poll) }}" style="display:inline"
                                  onsubmit="return confirm('تحذير: سيتم حذف الإستفتاء هذا من قاعدة البيانات الآن!')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد إستفتاءات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>
@endsection

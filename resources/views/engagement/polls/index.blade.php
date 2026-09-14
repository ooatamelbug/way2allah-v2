@extends('layouts.app')

@section('title', 'الإستفتاءات واستطلاعات الرأي')

@section('content')
    <x-page-chrome
        heading="الإستفتاءات واستطلاعات الرأي"
        :breadcrumb="[['title' => 'الإستفتاءات', 'url' => '']]"
    />

    <div class="row service-box margin-bottom-40">
        <div class="col-md-12">
            <x-content.premium-panel title="أرشيف استطلاعات الرأي" icon="fa-check-square-o" description="شارك برأيك في القضايا والموضوعات الدعوية المطروحة">
                <div class="w2a-table-responsive-wrapper">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="65%">عنوان الاستفتاء</th>
                                <th width="15%" class="text-center">إجمالي الأصوات</th>
                                <th width="20%" class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($polls as $poll)
                                <tr>
                                    <td>
                                        <a href="{{ route('engagement.polls.show', $poll) }}" style="font-weight: 700; color: #0284c7; font-size: 15px;">
                                            {{ $poll->pollTitle }}
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="w2a-poll-votes-badge">
                                            <i class="fa fa-users" aria-hidden="true"></i>
                                            {{ number_format((int)$poll->totalVotes()) }} صوت
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="w2a-poll-actions">
                                            <a href="{{ route('engagement.polls.show', $poll) }}" class="w2a-poll-btn w2a-poll-btn--vote">
                                                <i class="fa fa-pencil" aria-hidden="true" style="margin-left: 4px;"></i>
                                                <span>تصويت</span>
                                            </a>
                                            <a href="{{ route('engagement.polls.results', $poll) }}" class="w2a-poll-btn w2a-poll-btn--results">
                                                <i class="fa fa-bar-chart" aria-hidden="true" style="margin-left: 4px;"></i>
                                                <span>النتائج</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center" style="padding: 30px; color: #64748b;">
                                        لا توجد استفتاءات متاحة حالياً
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-content.premium-panel>
        </div>
    </div>
@endsection

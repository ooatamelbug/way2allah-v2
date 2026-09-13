@extends('layouts.app')

@section('title', 'استطلاع رأي | ' . $poll->pollTitle)

@section('content')
    <x-page-chrome
        heading="استطلاع رأي"
        :breadcrumb="[['title' => 'الإستفتاءات', 'url' => route('engagement.polls.index')], ['title' => $poll->pollTitle, 'url' => '']]"
    />

    <div class="row service-box margin-bottom-40">
        <div class="col-md-8 col-md-offset-2">
            <x-content.premium-panel title="نموذج التصويت" icon="fa-check-square-o">
                <form method="post" action="{{ route('engagement.polls.vote', $poll) }}" style="padding: 10px 0;">
                    @csrf
                    <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.6; margin: 0 0 24px;">
                        {{ $poll->pollTitle }}
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px;">
                        @foreach ($options as $option)
                            @if ($option->optionText)
                                <label style="display: flex; align-items: center; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; cursor: pointer; transition: all 0.2s; margin: 0;">
                                    <input type="radio" name="voteID" value="{{ $option->voteID }}" required style="width: 18px; height: 18px; margin: 0; cursor: pointer;">
                                    <span style="font-size: 15px; font-weight: 600; color: #1e293b;">{{ $option->optionText }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 28px; font-size: 15px; font-weight: 700; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa fa-check" aria-hidden="true"></i> <span>إرسال التصويت</span>
                        </button>

                        <div style="display: flex; align-items: center; gap: 14px; font-size: 13.5px;">
                            <a href="{{ route('engagement.polls.results', $poll) }}" style="color: #0284c7; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-bar-chart" aria-hidden="true"></i> <span>عرض النتائج</span>
                            </a>
                            <span style="color: #cbd5e1;">|</span>
                            <a href="{{ route('engagement.polls.index') }}" style="color: #64748b; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-list" aria-hidden="true"></i> <span>جميع الاستفتاءات</span>
                            </a>
                            <span style="color: #cbd5e1;">|</span>
                            <span style="color: #64748b; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-users" aria-hidden="true"></i> <span>{{ number_format((int)$poll->totalVotes()) }} صوت</span>
                            </span>
                        </div>
                    </div>
                </form>
            </x-content.premium-panel>
        </div>
    </div>
@endsection
